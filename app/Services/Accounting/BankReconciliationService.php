<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BankReconciliationService
{
    private const SCALE = 6;
    private const AUTOMATIC_DATE_TOLERANCE_DAYS = 3;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryAccountService $accountService,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): BankReconciliation
    {
        $statementId = $this->positiveInteger($data['bank_statement_import_id'] ?? null, 'bank_statement_import_id');

        return DB::transaction(function () use ($statementId, $data, $actor): BankReconciliation {
            $statement = BankStatementImport::query()
                ->whereKey($statementId)
                ->lockForUpdate()
                ->first();

            if (!$statement instanceof BankStatementImport || $statement->status !== 'imported') {
                throw ValidationException::withMessages([
                    'bank_statement_import_id' => ['Select an imported bank statement that is not currently reconciled.'],
                ]);
            }

            $this->authorizeBranch($actor, (int) $statement->branch_id, true);
            $this->accountService->lockBankAccount((int) $statement->bank_account_id);
            $activeKey = sprintf('bank-statement:%d:active-reconciliation', (int) $statement->getKey());
            $existing = BankReconciliation::query()
                ->where('active_key', $activeKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof BankReconciliation) {
                throw ValidationException::withMessages([
                    'bank_statement_import_id' => ['This statement already has an active reconciliation session.'],
                ]);
            }

            $reconciliation = BankReconciliation::query()->create([
                'branch_id' => $statement->branch_id,
                'bank_account_id' => $statement->bank_account_id,
                'bank_statement_import_id' => $statement->getKey(),
                'document_number_allocation_id' => null,
                'reconciliation_number' => null,
                'active_key' => $activeKey,
                'statement_start_date' => $statement->period_start,
                'statement_end_date' => $statement->period_end,
                'currency_code' => $statement->currency_code,
                'statement_opening_balance' => $statement->opening_balance,
                'statement_closing_balance' => $statement->closing_balance,
                'status' => 'draft',
                'notes' => $this->nullableText($data['notes'] ?? null, 4000),
                'created_by_user_id' => $actor->getKey(),
            ]);

            $this->refreshCalculations($reconciliation);

            return $this->load($reconciliation->refresh());
        }, attempts: 5);
    }

    public function delete(BankReconciliation $reconciliation, User $actor): void
    {
        DB::transaction(function () use ($reconciliation, $actor): void {
            $locked = $this->lock($reconciliation);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages([
                    'reconciliation' => ['Only a draft reconciliation can be deleted.'],
                ]);
            }

            $matches = BankReconciliationMatch::query()
                ->where('bank_reconciliation_id', $locked->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($matches as $match) {
                $this->reverseMatch($match, $actor);
            }

            $this->restoreIgnoredLines((int) $locked->bank_statement_import_id);
            $locked->active_key = null;
            $locked->save();
            $locked->delete();
        }, attempts: 5);
    }

    public function automaticMatch(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $statementLines = BankStatementLine::query()
                ->where('bank_statement_import_id', $locked->bank_statement_import_id)
                ->whereIn('status', ['unmatched', 'partially_matched'])
                ->orderBy('transaction_date')
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

            $candidateLines = $this->candidateJournalLines($locked);

            foreach ($statementLines as $statementLine) {
                $statementRemaining = $this->statementRemaining($statementLine);

                if (!$statementRemaining->isGreaterThan(BigDecimal::zero())) {
                    continue;
                }

                $statementPositive = BigDecimal::of((string) $statementLine->signed_amount)->isPositive();
                $statementDate = CarbonImmutable::parse($statementLine->transaction_date);
                $candidates = $candidateLines->filter(function (object $candidate) use (
                    $statementLine,
                    $statementRemaining,
                    $statementPositive,
                    $statementDate,
                ): bool {
                    $journalSigned = BigDecimal::of((string) $candidate->base_debit_amount)
                        ->minus(BigDecimal::of((string) $candidate->base_credit_amount));
                    $available = $this->journalAvailable((int) $candidate->id, $journalSigned->abs());

                    if (!$available->isEqualTo($statementRemaining) || $journalSigned->isPositive() !== $statementPositive) {
                        return false;
                    }

                    $postingDate = CarbonImmutable::parse((string) $candidate->posting_date);

                    return abs($statementDate->diffInDays($postingDate, false)) <= self::AUTOMATIC_DATE_TOLERANCE_DAYS;
                })->values();

                if ($candidates->count() > 1) {
                    $referenceCandidates = $candidates->filter(
                        fn (object $candidate): bool => $this->referencesOverlap(
                            (string) ($statementLine->bank_reference ?? $statementLine->description),
                            implode(' ', [
                                (string) ($candidate->reference ?? ''),
                                (string) ($candidate->source_document_number ?? ''),
                                (string) ($candidate->journal_number ?? ''),
                                (string) ($candidate->description ?? ''),
                            ]),
                        ),
                    )->values();

                    if ($referenceCandidates->count() === 1) {
                        $candidates = $referenceCandidates;
                    }
                }

                if ($candidates->count() !== 1) {
                    continue;
                }

                $candidate = $candidates->first();

                if ($candidate === null) {
                    continue;
                }

                $this->createMatch(
                    reconciliation: $locked,
                    statementLine: $statementLine,
                    journalLineId: (int) $candidate->id,
                    amount: $statementRemaining,
                    matchType: 'automatic',
                    actor: $actor,
                );
            }

            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function manualMatch(
        BankReconciliation $reconciliation,
        int $statementLineId,
        int $journalEntryLineId,
        string $amount,
        User $actor,
    ): BankReconciliation {
        $matchedAmount = $this->positiveDecimal($amount, 'matched_amount');

        return DB::transaction(function () use (
            $reconciliation,
            $statementLineId,
            $journalEntryLineId,
            $matchedAmount,
            $actor,
        ): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $statementLine = BankStatementLine::query()
                ->whereKey($statementLineId)
                ->where('bank_statement_import_id', $locked->bank_statement_import_id)
                ->lockForUpdate()
                ->first();

            if (!$statementLine instanceof BankStatementLine || $statementLine->status === 'ignored') {
                throw ValidationException::withMessages(['bank_statement_line_id' => ['Select a matchable statement line.']]);
            }

            $journalLine = $this->postedJournalLine($locked, $journalEntryLineId, true);
            $statementRemaining = $this->statementRemaining($statementLine);
            $journalSigned = BigDecimal::of((string) $journalLine->base_debit_amount)
                ->minus(BigDecimal::of((string) $journalLine->base_credit_amount));
            $journalAvailable = $this->journalAvailable((int) $journalLine->id, $journalSigned->abs());

            if ($journalSigned->isPositive() !== BigDecimal::of((string) $statementLine->signed_amount)->isPositive()) {
                throw ValidationException::withMessages(['journal_entry_line_id' => ['The journal movement direction does not match the statement line.']]);
            }

            if ($matchedAmount->isGreaterThan($statementRemaining) || $matchedAmount->isGreaterThan($journalAvailable)) {
                throw ValidationException::withMessages([
                    'matched_amount' => ['The match amount exceeds the remaining statement or journal amount.'],
                ]);
            }

            $this->createMatch($locked, $statementLine, (int) $journalLine->id, $matchedAmount, 'manual', $actor);
            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function unmatch(
        BankReconciliation $reconciliation,
        BankReconciliationMatch $match,
        User $actor,
    ): BankReconciliation {
        return DB::transaction(function () use ($reconciliation, $match, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $lockedMatch = BankReconciliationMatch::query()
                ->whereKey($match->getKey())
                ->where('bank_reconciliation_id', $locked->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$lockedMatch instanceof BankReconciliationMatch) {
                throw ValidationException::withMessages(['match' => ['The selected active match is unavailable.']]);
            }

            $this->reverseMatch($lockedMatch, $actor);
            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function ignoreLine(
        BankReconciliation $reconciliation,
        BankStatementLine $statementLine,
        string $reason,
        User $actor,
    ): BankReconciliation {
        $reason = $this->requiredText($reason, 500, 'ignore_reason');

        return DB::transaction(function () use ($reconciliation, $statementLine, $reason, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $line = BankStatementLine::query()
                ->whereKey($statementLine->getKey())
                ->where('bank_statement_import_id', $locked->bank_statement_import_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (BigDecimal::of((string) $line->matched_amount)->isGreaterThan(BigDecimal::zero())) {
                throw ValidationException::withMessages(['statement_line' => ['A partially or fully matched statement line cannot be ignored.']]);
            }

            $line->status = 'ignored';
            $line->ignore_reason = $reason;
            $line->ignored_by_user_id = $actor->getKey();
            $line->ignored_at = now();
            $line->save();
            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function unignoreLine(
        BankReconciliation $reconciliation,
        BankStatementLine $statementLine,
        User $actor,
    ): BankReconciliation {
        return DB::transaction(function () use ($reconciliation, $statementLine, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $line = BankStatementLine::query()
                ->whereKey($statementLine->getKey())
                ->where('bank_statement_import_id', $locked->bank_statement_import_id)
                ->lockForUpdate()
                ->firstOrFail();
            $line->status = 'unmatched';
            $line->ignore_reason = null;
            $line->ignored_by_user_id = null;
            $line->ignored_at = null;
            $line->save();
            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function complete(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $this->refreshCalculations($locked);
            $unresolved = BankStatementLine::query()
                ->where('bank_statement_import_id', $locked->bank_statement_import_id)
                ->whereIn('status', ['unmatched', 'partially_matched'])
                ->lockForUpdate()
                ->count();

            if ($unresolved > 0) {
                throw ValidationException::withMessages([
                    'reconciliation' => ['Every statement line must be fully matched or explicitly ignored before completion.'],
                ]);
            }

            if (!BigDecimal::of((string) $locked->difference_amount)->isZero()) {
                throw ValidationException::withMessages([
                    'difference_amount' => [
                        sprintf('The reconciliation difference must be zero. Current difference: %s.', $locked->difference_amount),
                    ],
                ]);
            }

            $this->allocateNumber($locked);
            $locked->status = 'completed';
            $locked->completed_by_user_id = $actor->getKey();
            $locked->completed_at = now();
            $locked->save();
            $statement = BankStatementImport::query()
                ->whereKey($locked->bank_statement_import_id)
                ->lockForUpdate()
                ->firstOrFail();
            $statement->status = 'reconciled';
            $statement->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function reverse(BankReconciliation $reconciliation, string $reason, User $actor): BankReconciliation
    {
        $reason = $this->requiredText($reason, 500, 'reversal_reason');

        return DB::transaction(function () use ($reconciliation, $reason, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!$locked->isCompleted()) {
                throw ValidationException::withMessages(['status' => ['Only a completed reconciliation can be reversed.']]);
            }

            $matches = BankReconciliationMatch::query()
                ->where('bank_reconciliation_id', $locked->getKey())
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($matches as $match) {
                $this->reverseMatch($match, $actor);
            }

            $this->restoreIgnoredLines((int) $locked->bank_statement_import_id);

            $locked->status = 'reversed';
            $locked->active_key = null;
            $locked->reversed_by_user_id = $actor->getKey();
            $locked->reversed_at = now();
            $locked->reversal_reason = $reason;
            $locked->save();
            $statement = BankStatementImport::query()
                ->whereKey($locked->bank_statement_import_id)
                ->lockForUpdate()
                ->firstOrFail();
            $statement->status = 'imported';
            $statement->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function refresh(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): BankReconciliation {
            $locked = $this->lock($reconciliation);
            $this->authorizeDraft($locked, $actor);
            $this->refreshCalculations($locked);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    /** @return Collection<int, object> */
    public function availableJournalLines(BankReconciliation $reconciliation, ?BankStatementLine $statementLine = null): Collection
    {
        $lines = $this->manualCandidateJournalLines($reconciliation);

        return $lines->filter(function (object $line) use ($statementLine): bool {
            $signed = BigDecimal::of((string) $line->base_debit_amount)
                ->minus(BigDecimal::of((string) $line->base_credit_amount));
            $available = $this->journalAvailable((int) $line->id, $signed->abs());

            if (!$available->isGreaterThan(BigDecimal::zero())) {
                return false;
            }

            if ($statementLine !== null && $signed->isPositive() !== BigDecimal::of((string) $statementLine->signed_amount)->isPositive()) {
                return false;
            }

            $line->available_amount = $this->decimal($available);
            $line->signed_amount = $this->decimal($signed);

            return true;
        })->values();
    }

    private function createMatch(
        BankReconciliation $reconciliation,
        BankStatementLine $statementLine,
        int $journalLineId,
        BigDecimal $amount,
        string $matchType,
        User $actor,
    ): BankReconciliationMatch {
        $activeKey = hash('sha256', implode(':', [
            $reconciliation->getKey(),
            $statementLine->getKey(),
            $journalLineId,
            $matchType,
            $amount->__toString(),
            microtime(true),
        ]));
        $match = BankReconciliationMatch::query()->create([
            'bank_reconciliation_id' => $reconciliation->getKey(),
            'bank_statement_line_id' => $statementLine->getKey(),
            'journal_entry_line_id' => $journalLineId,
            'match_type' => $matchType,
            'matched_amount' => $this->decimal($amount),
            'active_key' => $activeKey,
            'status' => 'active',
            'matched_by_user_id' => $actor->getKey(),
            'matched_at' => now(),
        ]);
        $this->refreshStatementLine($statementLine);

        return $match;
    }

    private function reverseMatch(BankReconciliationMatch $match, User $actor): void
    {
        $statementLine = BankStatementLine::query()
            ->whereKey($match->bank_statement_line_id)
            ->lockForUpdate()
            ->firstOrFail();
        $match->status = 'reversed';
        $match->active_key = null;
        $match->reversed_by_user_id = $actor->getKey();
        $match->reversed_at = now();
        $match->save();
        $this->refreshStatementLine($statementLine);
    }

    private function restoreIgnoredLines(int $statementImportId): void
    {
        $ignoredLines = BankStatementLine::query()
            ->where('bank_statement_import_id', $statementImportId)
            ->where('status', 'ignored')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($ignoredLines as $line) {
            $line->status = 'unmatched';
            $line->ignore_reason = null;
            $line->ignored_by_user_id = null;
            $line->ignored_at = null;
            $line->save();
        }
    }

    private function refreshStatementLine(BankStatementLine $line): void
    {
        $matched = BigDecimal::of((string) BankReconciliationMatch::query()
            ->where('bank_statement_line_id', $line->getKey())
            ->where('status', 'active')
            ->sum('matched_amount'));
        $absolute = BigDecimal::of((string) $line->signed_amount)->abs();
        $line->matched_amount = $this->decimal($matched);

        if ($matched->isZero()) {
            $line->status = 'unmatched';
        } elseif ($matched->isLessThan($absolute)) {
            $line->status = 'partially_matched';
        } else {
            $line->status = 'matched';
        }

        $line->save();
    }

    private function refreshCalculations(BankReconciliation $reconciliation): void
    {
        $book = BigDecimal::of($this->accountService->baseBalance(
            accountId: (int) $reconciliation->bank_account_id,
            throughDate: $reconciliation->statement_end_date->toDateString(),
            branchId: (int) $reconciliation->branch_id,
        ));
        $outstandingDeposits = BigDecimal::zero();
        $outstandingPayments = BigDecimal::zero();
        $journalLines = $this->accountService
            ->postedBankLineQuery((int) $reconciliation->bank_account_id, (int) $reconciliation->branch_id)
            ->whereDate('journal_entries.posting_date', '<=', $reconciliation->statement_end_date)
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entry_lines.id')
            ->lockForUpdate()
            ->get();

        foreach ($journalLines as $line) {
            $signed = BigDecimal::of((string) $line->base_debit_amount)
                ->minus(BigDecimal::of((string) $line->base_credit_amount));
            $available = $this->journalHistoricalUnmatched(
                (int) $line->id,
                $signed->abs(),
                $reconciliation->statement_end_date->toDateString(),
            );

            if ($signed->isPositive()) {
                $outstandingDeposits = $outstandingDeposits->plus($available);
            } elseif ($signed->isNegative()) {
                $outstandingPayments = $outstandingPayments->plus($available);
            }
        }

        $statementClosing = BigDecimal::of((string) $reconciliation->statement_closing_balance);
        $adjusted = $statementClosing->plus($outstandingDeposits)->minus($outstandingPayments);
        $difference = $book->minus($adjusted);
        $reconciliation->book_closing_balance = $this->decimal($book);
        $reconciliation->outstanding_deposits = $this->decimal($outstandingDeposits);
        $reconciliation->outstanding_payments = $this->decimal($outstandingPayments);
        $reconciliation->adjusted_bank_balance = $this->decimal($adjusted);
        $reconciliation->difference_amount = $this->decimal($difference);
        $reconciliation->save();
    }

    /** @return Collection<int, object> */
    private function manualCandidateJournalLines(BankReconciliation $reconciliation): Collection
    {
        return $this->accountService
            ->postedBankLineQuery(
                (int) $reconciliation->bank_account_id,
                (int) $reconciliation->branch_id,
            )
            ->addSelect([
                'journal_entries.posting_date',
                'journal_entries.journal_number',
                'journal_entries.source_document_number',
                'journal_entries.currency_code',
            ])
            ->where('journal_entries.currency_code', $reconciliation->currency_code)
            ->whereDate(
                'journal_entries.posting_date',
                '<=',
                $reconciliation->statement_end_date
                    ->addDays(self::AUTOMATIC_DATE_TOLERANCE_DAYS)
                    ->toDateString(),
            )
            ->orderByDesc('journal_entries.posting_date')
            ->orderByDesc('journal_entry_lines.id')
            ->limit(5000)
            ->get();
    }

    /** @return Collection<int, object> */
    private function candidateJournalLines(BankReconciliation $reconciliation): Collection
    {
        return $this->accountService
            ->postedBankLineQuery((int) $reconciliation->bank_account_id, (int) $reconciliation->branch_id)
            ->addSelect([
                'journal_entries.posting_date',
                'journal_entries.journal_number',
                'journal_entries.source_document_number',
                'journal_entries.currency_code',
            ])
            ->where('journal_entries.currency_code', $reconciliation->currency_code)
            ->whereDate(
                'journal_entries.posting_date',
                '>=',
                $reconciliation->statement_start_date->subDays(self::AUTOMATIC_DATE_TOLERANCE_DAYS)->toDateString(),
            )
            ->whereDate(
                'journal_entries.posting_date',
                '<=',
                $reconciliation->statement_end_date->addDays(self::AUTOMATIC_DATE_TOLERANCE_DAYS)->toDateString(),
            )
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entry_lines.id')
            ->get();
    }

    private function postedJournalLine(BankReconciliation $reconciliation, int $lineId, bool $lock): object
    {
        $query = $this->accountService
            ->postedBankLineQuery((int) $reconciliation->bank_account_id, (int) $reconciliation->branch_id)
            ->addSelect([
                'journal_entries.posting_date',
                'journal_entries.journal_number',
                'journal_entries.source_document_number',
                'journal_entries.currency_code',
            ])
            ->where('journal_entry_lines.id', $lineId)
            ->where('journal_entries.currency_code', $reconciliation->currency_code);

        if ($lock) {
            $query->lockForUpdate();
        }

        $line = $query->first();

        if ($line === null) {
            throw ValidationException::withMessages(['journal_entry_line_id' => ['Select a posted bank-account journal line.']]);
        }

        return $line;
    }

    private function statementRemaining(BankStatementLine $line): BigDecimal
    {
        return BigDecimal::of((string) $line->signed_amount)
            ->abs()
            ->minus(BigDecimal::of((string) $line->matched_amount))
            ->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function journalAvailable(int $journalLineId, BigDecimal $absoluteAmount): BigDecimal
    {
        $matched = BigDecimal::of((string) BankReconciliationMatch::query()
            ->where('journal_entry_line_id', $journalLineId)
            ->where('status', 'active')
            ->sum('matched_amount'));

        return $absoluteAmount->minus($matched)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function journalHistoricalUnmatched(int $journalLineId, BigDecimal $absoluteAmount, string $throughDate): BigDecimal
    {
        $matched = BigDecimal::of((string) BankReconciliationMatch::query()
            ->join('bank_reconciliations', 'bank_reconciliations.id', '=', 'bank_reconciliation_matches.bank_reconciliation_id')
            ->where('bank_reconciliation_matches.journal_entry_line_id', $journalLineId)
            ->where('bank_reconciliation_matches.status', 'active')
            ->whereIn('bank_reconciliations.status', ['draft', 'completed'])
            ->whereDate('bank_reconciliations.statement_end_date', '<=', $throughDate)
            ->sum('bank_reconciliation_matches.matched_amount'));
        $remaining = $absoluteAmount->minus($matched);

        return $remaining->isNegative() ? BigDecimal::zero()->toScale(self::SCALE) : $remaining->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function referencesOverlap(string $left, string $right): bool
    {
        $tokens = static function (string $value): array {
            $value = strtoupper(preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '');

            return array_values(array_filter(
                explode(' ', $value),
                static fn (string $token): bool => mb_strlen($token) >= 4,
            ));
        };
        $leftTokens = $tokens($left);
        $rightTokens = $tokens($right);

        return array_intersect($leftTokens, $rightTokens) !== [];
    }

    private function allocateNumber(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->reconciliation_number !== null) {
            return;
        }

        $allocation = $this->documentNumberService->allocate(
            documentType: 'bank_reconciliation',
            branchId: (int) $reconciliation->branch_id,
            idempotencyKey: sprintf('bank-reconciliation:%d:%d', (int) $reconciliation->tenant_id, (int) $reconciliation->getKey()),
            allocatableType: BankReconciliation::class,
            allocatableId: (int) $reconciliation->getKey(),
            allocatedAt: $reconciliation->statement_end_date,
        );
        $reconciliation->document_number_allocation_id = $allocation->getKey();
        $reconciliation->reconciliation_number = $allocation->number;
    }

    private function authorizeDraft(BankReconciliation $reconciliation, User $actor): void
    {
        $this->authorizeBranch($actor, (int) $reconciliation->branch_id, true);

        if (!$reconciliation->isDraft()) {
            throw ValidationException::withMessages(['status' => ['Only a draft reconciliation can be modified.']]);
        }
    }

    private function authorizeBranch(User $actor, int $branchId, bool $active): void
    {
        $branch = Branch::query()->whereKey($branchId)->first();

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => ['The selected branch is unavailable.']]);
        }

        $this->branchAccessService->authorizeBranch($actor, $branch, $active);
    }

    private function lock(BankReconciliation $reconciliation): BankReconciliation
    {
        return BankReconciliation::query()->whereKey($reconciliation->getKey())->lockForUpdate()->firstOrFail();
    }

    private function load(BankReconciliation $reconciliation): BankReconciliation
    {
        return $reconciliation->load([
            'branch:id,name,code,status',
            'bankAccount:id,code,name,control_type,status',
            'statementImport:id,statement_reference,source_filename,period_start,period_end,opening_balance,closing_balance,status',
            'statementImport.lines.matches.journalEntryLine.journalEntry',
            'matches.statementLine',
            'matches.journalEntryLine.journalEntry',
            'matches.matchedBy:id,name',
            'createdBy:id,name',
            'completedBy:id,name',
            'reversedBy:id,name',
        ]);
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw ValidationException::withMessages([$field => ['Select a valid value.']]);
        }

        return $integer;
    }

    private function positiveDecimal(string $value, string $field): BigDecimal
    {
        try {
            $decimal = BigDecimal::of(trim($value))->toScale(self::SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['Enter a positive number with no more than six decimal places.']]);
        }

        if (!$decimal->isGreaterThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([$field => ['The value must be greater than zero.']]);
        }

        return $decimal;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages(['notes' => ["Text may not exceed {$max} characters."]]);
        }

        return $text;
    }

    private function requiredText(string $value, int $max, string $field): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => ["The field is required and may not exceed {$max} characters."]]);
        }

        return $value;
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}

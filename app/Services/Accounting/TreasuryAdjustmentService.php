<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\TreasuryAdjustmentAccountingGateway;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\TreasuryAdjustment;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\TreasuryAdjustmentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class TreasuryAdjustmentService
{
    private const MONEY_SCALE = 6;
    private const RATE_SCALE = 8;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryAccountService $accountService,
        private readonly TreasuryAdjustmentTypeRegistry $typeRegistry,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly TreasuryAdjustmentAccountingGateway $accountingGateway,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): TreasuryAdjustment
    {
        $normalized = $this->normalize($data, $actor);

        return DB::transaction(function () use ($normalized, $actor): TreasuryAdjustment {
            $accounts = $this->lockedAccounts($normalized);
            $statementLine = $this->lockStatementLine($normalized, $accounts['bank']);
            $adjustment = TreasuryAdjustment::query()->create([
                ...$normalized,
                'bank_statement_line_id' => $statementLine?->getKey(),
                'active_statement_key' => $this->statementActiveKey($statementLine),
                'bank_account_code' => $accounts['bank']->code,
                'bank_account_name' => $accounts['bank']->name,
                'offset_account_code' => $accounts['offset']->code,
                'offset_account_name' => $accounts['offset']->name,
                'base_amount' => $this->baseAmount($normalized['amount'], $normalized['exchange_rate']),
                'status' => 'draft',
                'revision' => 1,
                'created_by_user_id' => $actor->getKey(),
            ]);

            return $this->load($adjustment);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(TreasuryAdjustment $adjustment, array $data, User $actor): TreasuryAdjustment
    {
        $normalized = $this->normalize($data, $actor);

        return DB::transaction(function () use ($adjustment, $normalized, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->ensureEditable($locked);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);
            $accounts = $this->lockedAccounts($normalized);
            $statementLine = $this->lockStatementLine($normalized, $accounts['bank'], (int) $locked->getKey());
            $locked->fill([
                ...$normalized,
                'bank_statement_line_id' => $statementLine?->getKey(),
                'active_statement_key' => $this->statementActiveKey($statementLine),
                'bank_account_code' => $accounts['bank']->code,
                'bank_account_name' => $accounts['bank']->name,
                'offset_account_code' => $accounts['offset']->code,
                'offset_account_name' => $accounts['offset']->name,
                'base_amount' => $this->baseAmount($normalized['amount'], $normalized['exchange_rate']),
                'revision' => (int) $locked->revision + 1,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function delete(TreasuryAdjustment $adjustment, User $actor): void
    {
        DB::transaction(function () use ($adjustment, $actor): void {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!$locked->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'adjustment' => ['Only an unnumbered draft Treasury Adjustment can be deleted.'],
                ]);
            }

            $locked->active_statement_key = null;
            $locked->save();
            $locked->delete();
        }, attempts: 5);
    }

    public function submit(TreasuryAdjustment $adjustment, User $actor): TreasuryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, true);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['status' => ['Only a draft adjustment can be submitted.']]);
            }

            $this->revalidate($locked);
            $this->allocateNumber($locked);
            $locked->status = 'submitted';
            $locked->submitted_by_user_id = $actor->getKey();
            $locked->submitted_at = now();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function returnToDraft(TreasuryAdjustment $adjustment, User $actor): TreasuryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!$locked->isSubmitted()) {
                throw ValidationException::withMessages(['status' => ['Only a submitted adjustment can return to draft.']]);
            }

            $locked->status = 'draft';
            $locked->submitted_by_user_id = null;
            $locked->submitted_at = null;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function approve(TreasuryAdjustment $adjustment, User $actor): TreasuryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, true);

            if (!$locked->isSubmitted()) {
                throw ValidationException::withMessages(['status' => ['Only a submitted adjustment can be approved.']]);
            }

            $this->revalidate($locked);
            $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function post(TreasuryAdjustment $adjustment, User $actor): TreasuryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, true);

            if (!$locked->isApproved()) {
                throw ValidationException::withMessages(['status' => ['Only an approved adjustment can be posted.']]);
            }

            $this->revalidate($locked);
            $period = $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $reference = $this->accountingGateway->post($locked, $period, $actor);
            $locked->status = 'posted';
            $locked->posted_by_user_id = $actor->getKey();
            $locked->posted_at = now();
            $locked->accounting_posting_reference = $reference;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function cancel(TreasuryAdjustment $adjustment, string $reason, User $actor): TreasuryAdjustment
    {
        $reason = $this->reason($reason, 'cancellation_reason');

        return DB::transaction(function () use ($adjustment, $reason, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!in_array($locked->status, ['draft', 'submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => ['This adjustment cannot be cancelled.']]);
            }

            $locked->status = 'cancelled';
            $locked->active_statement_key = null;
            $locked->cancelled_by_user_id = $actor->getKey();
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function reverse(
        TreasuryAdjustment $adjustment,
        string $postingDate,
        string $reason,
        User $actor,
    ): TreasuryAdjustment {
        $reason = $this->reason($reason, 'reversal_reason');
        $date = $this->date($postingDate, 'reversal_posting_date');

        return DB::transaction(function () use ($adjustment, $date, $reason, $actor): TreasuryAdjustment {
            $locked = $this->lock($adjustment);
            $this->authorizeBranch($actor, (int) $locked->branch_id, false);

            if (!$locked->isPosted()) {
                throw ValidationException::withMessages(['status' => ['Only a posted adjustment can be reversed.']]);
            }

            $this->ensureNotReconciled($locked);
            $period = $this->accountingPeriodService->lockOpenPeriod(CarbonImmutable::parse($date));
            $reference = $this->accountingGateway->reverse($locked, $period, CarbonImmutable::parse($date), $reason, $actor);
            $locked->status = 'reversed';
            $locked->active_statement_key = null;
            $locked->reversal_posting_date = $date;
            $locked->reversed_by_user_id = $actor->getKey();
            $locked->reversed_at = now();
            $locked->reversal_reason = $reason;
            $locked->accounting_reversal_reference = $reference;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, User $actor): array
    {
        $branchId = $this->positiveInteger($data['branch_id'] ?? null, 'branch_id');
        $this->authorizeBranch($actor, $branchId, true);
        $type = trim((string) ($data['adjustment_type'] ?? ''));

        if (!$this->typeRegistry->exists($type)) {
            throw ValidationException::withMessages(['adjustment_type' => ['Select a valid adjustment type.']]);
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));
        $tenantCurrency = strtoupper((string) $this->tenantContext->tenant()->currency_code);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency_code' => ['Enter a valid three-letter currency code.']]);
        }

        $rate = $this->positiveDecimal($data['exchange_rate'] ?? null, self::RATE_SCALE, 'exchange_rate');

        if ($currency === $tenantCurrency && !$rate->isEqualTo(BigDecimal::one()->toScale(self::RATE_SCALE))) {
            throw ValidationException::withMessages(['exchange_rate' => ['The exchange rate must be 1.00000000 for the base currency.']]);
        }

        return [
            'branch_id' => $branchId,
            'bank_account_id' => $this->positiveInteger($data['bank_account_id'] ?? null, 'bank_account_id'),
            'offset_account_id' => $this->positiveInteger($data['offset_account_id'] ?? null, 'offset_account_id'),
            'bank_statement_line_id' => $this->nullablePositiveInteger($data['bank_statement_line_id'] ?? null, 'bank_statement_line_id'),
            'adjustment_type' => $type,
            'adjustment_date' => $this->date($data['adjustment_date'] ?? null, 'adjustment_date'),
            'posting_date' => $this->date($data['posting_date'] ?? null, 'posting_date'),
            'currency_code' => $currency,
            'exchange_rate' => $rate->__toString(),
            'amount' => $this->positiveDecimal($data['amount'] ?? null, self::MONEY_SCALE, 'amount')->__toString(),
            'reference' => $this->nullableText($data['reference'] ?? null, 160),
            'description' => $this->requiredText($data['description'] ?? null, 500, 'description'),
        ];
    }

    /** @param array<string, mixed> $normalized
     * @return array{bank: \App\Models\Account, offset: \App\Models\Account}
     */
    private function lockedAccounts(array $normalized): array
    {
        $bank = $this->accountService->lockBankAccount((int) $normalized['bank_account_id']);
        $offset = $this->accountService->lockOffsetAccount((int) $normalized['offset_account_id']);
        $type = (string) $normalized['adjustment_type'];

        if ($type === 'bank_charge' && $offset->system_key !== 'bank_charges') {
            throw ValidationException::withMessages([
                'offset_account_id' => [
                    'Bank charges must use the protected Bank Charges system account.',
                ],
            ]);
        }

        if ($type === 'bank_interest' && $offset->system_key !== 'bank_interest_income') {
            throw ValidationException::withMessages([
                'offset_account_id' => [
                    'Bank interest must use the protected Bank Interest Income system account.',
                ],
            ]);
        }

        if (
            in_array($type, ['other_debit', 'other_credit'], true)
            && !$offset->allowsManualPosting()
        ) {
            throw ValidationException::withMessages([
                'offset_account_id' => [
                    'Other bank adjustments require an account that allows manual posting.',
                ],
            ]);
        }

        return ['bank' => $bank, 'offset' => $offset];
    }

    /** @param array<string, mixed> $normalized */
    private function lockStatementLine(array $normalized, \App\Models\Account $bank, ?int $excludingAdjustmentId = null): ?BankStatementLine
    {
        $lineId = $normalized['bank_statement_line_id'];

        if ($lineId === null) {
            return null;
        }

        $line = BankStatementLine::query()
            ->with('statementImport')
            ->whereKey($lineId)
            ->lockForUpdate()
            ->first();

        if (
            !$line instanceof BankStatementLine
            || (int) $line->bank_account_id !== (int) $bank->getKey()
            || $line->status !== 'unmatched'
            || $line->statementImport === null
            || $line->statementImport->status !== 'imported'
            || (int) $line->statementImport->branch_id !== (int) $normalized['branch_id']
            || strtoupper((string) $line->statementImport->currency_code)
                !== strtoupper((string) $normalized['currency_code'])
        ) {
            throw ValidationException::withMessages([
                'bank_statement_line_id' => [
                    'Select an unmatched statement line for the same branch, bank account, and currency.',
                ],
            ]);
        }

        $alreadyUsed = TreasuryAdjustment::query()
            ->where('bank_statement_line_id', $line->getKey())
            ->when(
                $excludingAdjustmentId !== null,
                static fn ($query) => $query->where('id', '!=', $excludingAdjustmentId),
            )
            ->whereNotIn('status', ['cancelled', 'reversed'])
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages(['bank_statement_line_id' => ['This statement line already has a Treasury Adjustment.']]);
        }

        $amount = BigDecimal::of((string) $normalized['amount']);
        $lineAmount = BigDecimal::of((string) $line->signed_amount)->abs();

        if (!$amount->isEqualTo($lineAmount)) {
            throw ValidationException::withMessages(['amount' => ['The adjustment amount must equal the selected statement-line amount.']]);
        }

        $bankDirection = $this->typeRegistry->bankDirection((string) $normalized['adjustment_type']);
        $lineIsIncrease = BigDecimal::of((string) $line->signed_amount)->isPositive();

        if (($bankDirection === 'debit') !== $lineIsIncrease) {
            throw ValidationException::withMessages(['adjustment_type' => ['The adjustment direction does not match the selected statement line.']]);
        }

        return $line;
    }

    private function statementActiveKey(?BankStatementLine $statementLine): ?string
    {
        return $statementLine instanceof BankStatementLine
            ? sprintf(
                'bank-statement-line:%d:treasury-adjustment',
                (int) $statementLine->getKey(),
            )
            : null;
    }

    private function revalidate(TreasuryAdjustment $adjustment): void
    {
        $normalized = [
            'bank_account_id' => $adjustment->bank_account_id,
            'offset_account_id' => $adjustment->offset_account_id,
            'adjustment_type' => $adjustment->adjustment_type,
            'bank_statement_line_id' => $adjustment->bank_statement_line_id,
            'amount' => $adjustment->amount,
        ];
        $accounts = $this->lockedAccounts($normalized);
        $this->lockStatementLine($normalized, $accounts['bank'], (int) $adjustment->getKey());
    }

    private function allocateNumber(TreasuryAdjustment $adjustment): void
    {
        if ($adjustment->adjustment_number !== null) {
            return;
        }

        $allocation = $this->documentNumberService->allocate(
            documentType: 'treasury_adjustment',
            branchId: (int) $adjustment->branch_id,
            idempotencyKey: sprintf('treasury-adjustment:%d:%d', (int) $adjustment->tenant_id, (int) $adjustment->getKey()),
            allocatableType: TreasuryAdjustment::class,
            allocatableId: (int) $adjustment->getKey(),
            allocatedAt: $adjustment->adjustment_date,
        );
        $adjustment->document_number_allocation_id = $allocation->getKey();
        $adjustment->adjustment_number = $allocation->number;
    }

    private function ensureNotReconciled(TreasuryAdjustment $adjustment): void
    {
        $journalIds = JournalEntry::query()
            ->where('source_type', $adjustment->getMorphClass())
            ->where('source_id', $adjustment->getKey())
            ->pluck('id');

        $matched = !$journalIds->isEmpty() && BankReconciliationMatch::query()
            ->where('status', 'active')
            ->whereHas('journalEntryLine', static fn ($query) => $query->whereIn('journal_entry_id', $journalIds))
            ->exists();

        if ($matched) {
            throw ValidationException::withMessages([
                'adjustment' => ['This adjustment has already been matched in bank reconciliation and cannot be reversed.'],
            ]);
        }
    }

    private function lock(TreasuryAdjustment $adjustment): TreasuryAdjustment
    {
        return TreasuryAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
    }

    private function load(TreasuryAdjustment $adjustment): TreasuryAdjustment
    {
        return $adjustment->load([
            'branch:id,name,code,status',
            'bankAccount:id,code,name,control_type,status',
            'offsetAccount:id,code,name,account_type,account_subtype,status',
            'bankStatementLine:id,transaction_date,bank_reference,description,signed_amount,status',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);
    }

    private function ensureEditable(TreasuryAdjustment $adjustment): void
    {
        if (!$adjustment->canBeEdited()) {
            throw ValidationException::withMessages(['status' => ['Only a draft adjustment can be edited.']]);
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

    private function baseAmount(string $amount, string $rate): string
    {
        return BigDecimal::of($amount)
            ->multipliedBy(BigDecimal::of($rate))
            ->toScale(self::MONEY_SCALE, RoundingMode::HalfUp)
            ->__toString();
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw ValidationException::withMessages([$field => ['Select a valid value.']]);
        }

        return $integer;
    }

    private function nullablePositiveInteger(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveInteger($value, $field);
    }

    private function positiveDecimal(mixed $value, int $scale, string $field): BigDecimal
    {
        try {
            $decimal = BigDecimal::of(trim((string) $value))->toScale($scale, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ["Enter a positive number with no more than {$scale} decimal places."]]);
        }

        if (!$decimal->isGreaterThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([$field => ['The value must be greater than zero.']]);
        }

        return $decimal;
    }

    private function date(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw ValidationException::withMessages([$field => ['Use the YYYY-MM-DD date format.']]);
        }

        $value = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $this->tenantContext->tenant()->timezone);

        if (!$date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => ['Use the YYYY-MM-DD date format.']]);
        }

        return $value;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages(['adjustment' => ["Text may not exceed {$max} characters."]]);
        }

        return $text;
    }

    private function requiredText(mixed $value, int $max, string $field): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '' || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => ["The field is required and may not exceed {$max} characters."]]);
        }

        return $text;
    }

    private function reason(string $reason, string $field): string
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([$field => ['A reason is required and may not exceed 500 characters.']]);
        }

        return $reason;
    }
}

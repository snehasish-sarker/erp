<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\TreasuryAdjustment;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\BankReconciliationStatusRegistry;
use App\Support\Accounting\TreasuryAdjustmentTypeRegistry;
use App\Support\Accounting\TreasuryStatusRegistry;
use App\Support\Accounting\TreasuryTransferTypeRegistry;
use Illuminate\Support\Collection;

final class TreasuryPresentationService
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryAccountService $accountService,
        private readonly TreasuryStatusRegistry $statusRegistry,
        private readonly TreasuryTransferTypeRegistry $transferTypeRegistry,
        private readonly TreasuryAdjustmentTypeRegistry $adjustmentTypeRegistry,
        private readonly BankReconciliationStatusRegistry $reconciliationStatusRegistry,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function branches(User $actor, bool $activeOnly = true): array
    {
        return $this->branchAccessService
            ->accessibleBranches($actor, $activeOnly)
            ->map(static fn (Branch $branch): array => [
                'id' => (int) $branch->getKey(),
                'name' => $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function treasuryAccounts(?string $controlType = null): array
    {
        return $this->accountService->accounts($controlType)
            ->map(static fn (Account $account): array => [
                'id' => (int) $account->getKey(),
                'code' => $account->code,
                'name' => $account->name,
                'control_type' => $account->control_type,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function offsetAccounts(): array
    {
        return $this->accountService->offsetAccounts()
            ->map(static fn (Account $account): array => [
                'id' => (int) $account->getKey(),
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'account_subtype' => $account->account_subtype,
                'system_key' => $account->system_key,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function transferSummary(TreasuryTransfer $transfer, User $actor): array
    {
        return [
            'id' => (int) $transfer->getKey(),
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date?->format('Y-m-d'),
            'posting_date' => $transfer->posting_date?->format('Y-m-d'),
            'currency_code' => $transfer->currency_code,
            'amount' => (string) $transfer->amount,
            'base_amount' => (string) $transfer->base_amount,
            'transfer_type' => $transfer->transfer_type,
            'transfer_type_label' => $this->transferTypeRegistry->label($transfer->transfer_type),
            'reference' => $transfer->reference,
            'status' => $transfer->status,
            'status_label' => $this->statusRegistry->label($transfer->status),
            'source_branch' => $this->branchData($transfer->sourceBranch),
            'destination_branch' => $this->branchData($transfer->destinationBranch),
            'source_account' => $this->accountData($transfer->sourceAccount),
            'destination_account' => $this->accountData($transfer->destinationAccount),
            'created_at' => $transfer->created_at?->toIso8601String(),
            'can' => [
                'view' => $actor->can('view', $transfer),
                'update' => $actor->can('update', $transfer),
                'delete' => $actor->can('delete', $transfer),
                'submit' => $actor->can('submit', $transfer),
                'return_to_draft' => $actor->can('returnToDraft', $transfer),
                'approve' => $actor->can('approve', $transfer),
                'post' => $actor->can('post', $transfer),
                'cancel' => $actor->can('cancel', $transfer),
                'reverse' => $actor->can('reverse', $transfer),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function transferDetail(TreasuryTransfer $transfer, User $actor): array
    {
        return [
            ...$this->transferSummary($transfer, $actor),
            'source_branch_id' => (int) $transfer->source_branch_id,
            'destination_branch_id' => (int) $transfer->destination_branch_id,
            'source_account_id' => (int) $transfer->source_account_id,
            'destination_account_id' => (int) $transfer->destination_account_id,
            'exchange_rate' => (string) $transfer->exchange_rate,
            'notes' => $transfer->notes,
            'revision' => (int) $transfer->revision,
            'accounting_posting_reference' => $transfer->accounting_posting_reference,
            'accounting_reversal_reference' => $transfer->accounting_reversal_reference,
            'reversal_posting_date' => $transfer->reversal_posting_date?->format('Y-m-d'),
            'reversal_reason' => $transfer->reversal_reason,
            'cancellation_reason' => $transfer->cancellation_reason,
            'created_by' => $this->userData($transfer->createdBy),
            'submitted_by' => $this->userData($transfer->submittedBy),
            'approved_by' => $this->userData($transfer->approvedBy),
            'posted_by' => $this->userData($transfer->postedBy),
            'reversed_by' => $this->userData($transfer->reversedBy),
            'cancelled_by' => $this->userData($transfer->cancelledBy),
            'submitted_at' => $transfer->submitted_at?->toIso8601String(),
            'approved_at' => $transfer->approved_at?->toIso8601String(),
            'posted_at' => $transfer->posted_at?->toIso8601String(),
            'reversed_at' => $transfer->reversed_at?->toIso8601String(),
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function adjustmentSummary(TreasuryAdjustment $adjustment, User $actor): array
    {
        return [
            'id' => (int) $adjustment->getKey(),
            'adjustment_number' => $adjustment->adjustment_number,
            'adjustment_type' => $adjustment->adjustment_type,
            'adjustment_type_label' => $this->adjustmentTypeRegistry->label($adjustment->adjustment_type),
            'adjustment_date' => $adjustment->adjustment_date?->format('Y-m-d'),
            'posting_date' => $adjustment->posting_date?->format('Y-m-d'),
            'currency_code' => $adjustment->currency_code,
            'amount' => (string) $adjustment->amount,
            'base_amount' => (string) $adjustment->base_amount,
            'reference' => $adjustment->reference,
            'description' => $adjustment->description,
            'status' => $adjustment->status,
            'status_label' => $this->statusRegistry->label($adjustment->status),
            'branch' => $this->branchData($adjustment->branch),
            'bank_account' => $this->accountData($adjustment->bankAccount),
            'offset_account' => $this->accountData($adjustment->offsetAccount),
            'created_at' => $adjustment->created_at?->toIso8601String(),
            'can' => [
                'view' => $actor->can('view', $adjustment),
                'update' => $actor->can('update', $adjustment),
                'delete' => $actor->can('delete', $adjustment),
                'submit' => $actor->can('submit', $adjustment),
                'return_to_draft' => $actor->can('returnToDraft', $adjustment),
                'approve' => $actor->can('approve', $adjustment),
                'post' => $actor->can('post', $adjustment),
                'cancel' => $actor->can('cancel', $adjustment),
                'reverse' => $actor->can('reverse', $adjustment),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function adjustmentDetail(TreasuryAdjustment $adjustment, User $actor): array
    {
        return [
            ...$this->adjustmentSummary($adjustment, $actor),
            'branch_id' => (int) $adjustment->branch_id,
            'bank_account_id' => (int) $adjustment->bank_account_id,
            'offset_account_id' => (int) $adjustment->offset_account_id,
            'bank_statement_line_id' => $adjustment->bank_statement_line_id === null ? null : (int) $adjustment->bank_statement_line_id,
            'exchange_rate' => (string) $adjustment->exchange_rate,
            'revision' => (int) $adjustment->revision,
            'accounting_posting_reference' => $adjustment->accounting_posting_reference,
            'accounting_reversal_reference' => $adjustment->accounting_reversal_reference,
            'reversal_posting_date' => $adjustment->reversal_posting_date?->format('Y-m-d'),
            'reversal_reason' => $adjustment->reversal_reason,
            'cancellation_reason' => $adjustment->cancellation_reason,
            'statement_line' => $adjustment->bankStatementLine instanceof BankStatementLine
                ? $this->statementLineData($adjustment->bankStatementLine)
                : null,
            'created_by' => $this->userData($adjustment->createdBy),
            'submitted_by' => $this->userData($adjustment->submittedBy),
            'approved_by' => $this->userData($adjustment->approvedBy),
            'posted_by' => $this->userData($adjustment->postedBy),
            'reversed_by' => $this->userData($adjustment->reversedBy),
            'cancelled_by' => $this->userData($adjustment->cancelledBy),
            'submitted_at' => $adjustment->submitted_at?->toIso8601String(),
            'approved_at' => $adjustment->approved_at?->toIso8601String(),
            'posted_at' => $adjustment->posted_at?->toIso8601String(),
            'reversed_at' => $adjustment->reversed_at?->toIso8601String(),
            'cancelled_at' => $adjustment->cancelled_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function statementSummary(BankStatementImport $statement, User $actor): array
    {
        return [
            'id' => (int) $statement->getKey(),
            'statement_reference' => $statement->statement_reference,
            'source_filename' => $statement->source_filename,
            'period_start' => $statement->period_start?->format('Y-m-d'),
            'period_end' => $statement->period_end?->format('Y-m-d'),
            'currency_code' => $statement->currency_code,
            'opening_balance' => (string) $statement->opening_balance,
            'closing_balance' => (string) $statement->closing_balance,
            'line_count' => (int) $statement->line_count,
            'status' => $statement->status,
            'branch' => $this->branchData($statement->branch),
            'bank_account' => $this->accountData($statement->bankAccount),
            'imported_by' => $this->userData($statement->importedBy),
            'imported_at' => $statement->imported_at?->toIso8601String(),
            'can' => [
                'view' => $actor->can('view', $statement),
                'delete' => $actor->can('delete', $statement),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function statementDetail(BankStatementImport $statement, User $actor): array
    {
        return [
            ...$this->statementSummary($statement, $actor),
            'lines' => $statement->lines
                ->map(fn (BankStatementLine $line): array => $this->statementLineData($line))
                ->values()
                ->all(),
            'reconciliations' => $statement->reconciliations
                ->map(fn (BankReconciliation $reconciliation): array => $this->reconciliationSummary($reconciliation, $actor))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function reconciliationSummary(BankReconciliation $reconciliation, User $actor): array
    {
        return [
            'id' => (int) $reconciliation->getKey(),
            'reconciliation_number' => $reconciliation->reconciliation_number,
            'statement_start_date' => $reconciliation->statement_start_date?->format('Y-m-d'),
            'statement_end_date' => $reconciliation->statement_end_date?->format('Y-m-d'),
            'currency_code' => $reconciliation->currency_code,
            'statement_closing_balance' => (string) $reconciliation->statement_closing_balance,
            'book_closing_balance' => (string) $reconciliation->book_closing_balance,
            'outstanding_deposits' => (string) $reconciliation->outstanding_deposits,
            'outstanding_payments' => (string) $reconciliation->outstanding_payments,
            'adjusted_bank_balance' => (string) $reconciliation->adjusted_bank_balance,
            'difference_amount' => (string) $reconciliation->difference_amount,
            'status' => $reconciliation->status,
            'status_label' => $this->reconciliationStatusRegistry->label($reconciliation->status),
            'branch' => $this->branchData($reconciliation->branch),
            'bank_account' => $this->accountData($reconciliation->bankAccount),
            'statement' => $reconciliation->statementImport instanceof BankStatementImport
                ? [
                    'id' => (int) $reconciliation->statementImport->getKey(),
                    'reference' => $reconciliation->statementImport->statement_reference,
                    'filename' => $reconciliation->statementImport->source_filename,
                ]
                : null,
            'can' => [
                'view' => $actor->can('view', $reconciliation),
                'match' => $actor->can('match', $reconciliation),
                'complete' => $actor->can('complete', $reconciliation),
                'reverse' => $actor->can('reverse', $reconciliation),
            ],
        ];
    }

    /** @param Collection<int, object> $availableJournalLines
     * @return array<string, mixed>
     */
    public function reconciliationDetail(
        BankReconciliation $reconciliation,
        User $actor,
        Collection $availableJournalLines,
    ): array {
        return [
            ...$this->reconciliationSummary($reconciliation, $actor),
            'statement_opening_balance' => (string) $reconciliation->statement_opening_balance,
            'notes' => $reconciliation->notes,
            'created_by' => $this->userData($reconciliation->createdBy),
            'completed_by' => $this->userData($reconciliation->completedBy),
            'reversed_by' => $this->userData($reconciliation->reversedBy),
            'completed_at' => $reconciliation->completed_at?->toIso8601String(),
            'reversed_at' => $reconciliation->reversed_at?->toIso8601String(),
            'reversal_reason' => $reconciliation->reversal_reason,
            'statement_lines' => $reconciliation->statementImport instanceof BankStatementImport
                ? $reconciliation->statementImport->lines
                    ->map(fn (BankStatementLine $line): array => $this->statementLineData($line))
                    ->values()
                    ->all()
                : [],
            'matches' => $reconciliation->matches
                ->map(fn (BankReconciliationMatch $match): array => $this->matchData($match))
                ->values()
                ->all(),
            'available_journal_lines' => $availableJournalLines
                ->map(static fn (object $line): array => [
                    'id' => (int) $line->id,
                    'posting_date' => (string) $line->posting_date,
                    'journal_number' => $line->journal_number,
                    'source_document_number' => $line->source_document_number,
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'signed_amount' => (string) $line->signed_amount,
                    'available_amount' => (string) $line->available_amount,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function availableStatements(User $actor): array
    {
        $query = BankStatementImport::query()
            ->with(['branch:id,name,code', 'bankAccount:id,code,name'])
            ->where('status', 'imported')
            ->whereDoesntHave('reconciliations', static fn ($query) => $query->whereIn('status', ['draft', 'completed']))
            ->orderByDesc('period_end');
        $this->branchAccessService->scopeQuery($query, $actor, 'bank_statement_imports.branch_id');

        return $query->get()->map(static fn (BankStatementImport $statement): array => [
            'id' => (int) $statement->getKey(),
            'label' => sprintf(
                '%s — %s to %s — %s',
                $statement->bankAccount?->code ?? 'Bank',
                $statement->period_start?->format('Y-m-d'),
                $statement->period_end?->format('Y-m-d'),
                $statement->source_filename,
            ),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function statementLineData(BankStatementLine $line): array
    {
        return [
            'id' => (int) $line->getKey(),
            'line_number' => (int) $line->line_number,
            'transaction_date' => $line->transaction_date?->format('Y-m-d'),
            'value_date' => $line->value_date?->format('Y-m-d'),
            'bank_reference' => $line->bank_reference,
            'description' => $line->description,
            'debit_amount' => (string) $line->debit_amount,
            'credit_amount' => (string) $line->credit_amount,
            'signed_amount' => (string) $line->signed_amount,
            'running_balance' => $line->running_balance === null ? null : (string) $line->running_balance,
            'matched_amount' => (string) $line->matched_amount,
            'status' => $line->status,
            'ignore_reason' => $line->ignore_reason,
        ];
    }

    /** @return array<string, mixed> */
    private function matchData(BankReconciliationMatch $match): array
    {
        return [
            'id' => (int) $match->getKey(),
            'statement_line_id' => (int) $match->bank_statement_line_id,
            'journal_entry_line_id' => (int) $match->journal_entry_line_id,
            'match_type' => $match->match_type,
            'matched_amount' => (string) $match->matched_amount,
            'status' => $match->status,
            'matched_at' => $match->matched_at?->toIso8601String(),
            'matched_by' => $this->userData($match->matchedBy),
            'journal' => $match->journalEntryLine?->journalEntry === null
                ? null
                : [
                    'journal_number' => $match->journalEntryLine->journalEntry->journal_number,
                    'posting_date' => $match->journalEntryLine->journalEntry->posting_date?->format('Y-m-d'),
                    'source_document_number' => $match->journalEntryLine->journalEntry->source_document_number,
                    'reference' => $match->journalEntryLine->reference,
                    'description' => $match->journalEntryLine->description,
                ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function branchData(?Branch $branch): ?array
    {
        return $branch instanceof Branch
            ? ['id' => (int) $branch->getKey(), 'name' => $branch->name, 'code' => $branch->code]
            : null;
    }

    /** @return array<string, mixed>|null */
    private function accountData(?Account $account): ?array
    {
        return $account instanceof Account
            ? [
                'id' => (int) $account->getKey(),
                'code' => $account->code,
                'name' => $account->name,
                'control_type' => $account->control_type,
            ]
            : null;
    }

    /** @return array{id: int, name: string}|null */
    private function userData(?User $user): ?array
    {
        return $user instanceof User
            ? ['id' => (int) $user->getKey(), 'name' => $user->name]
            : null;
    }
}

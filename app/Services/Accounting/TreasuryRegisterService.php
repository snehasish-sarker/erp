<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TreasuryRegisterService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TreasuryAccountService $accountService,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function accountCards(User $actor): array
    {
        $branchIds = $this->branchAccessService
            ->accessibleBranches($actor, false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->accountService->accounts()
            ->map(function (Account $account) use ($branchIds): array {
                $balance = $this->balanceForBranches((int) $account->getKey(), $branchIds);
                $unmatched = $account->control_type === 'bank'
                    ? BankStatementLine::query()
                        ->where('bank_account_id', $account->getKey())
                        ->whereIn('status', ['unmatched', 'partially_matched'])
                        ->count()
                    : 0;

                return [
                    'id' => (int) $account->getKey(),
                    'code' => $account->code,
                    'name' => $account->name,
                    'control_type' => $account->control_type,
                    'base_balance' => $balance,
                    'unmatched_statement_lines' => $unmatched,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, opening_balance: string, closing_balance: string}
     */
    public function register(array $filters, User $actor): array
    {
        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $branchIds = $this->branchAccessService
            ->accessibleBranches($actor, false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $query = JournalEntryLine::query()
            ->select([
                'journal_entry_lines.id',
                'journal_entry_lines.account_id',
                'journal_entry_lines.branch_id',
                'journal_entry_lines.reference',
                'journal_entry_lines.description',
                'journal_entry_lines.debit_amount',
                'journal_entry_lines.credit_amount',
                'journal_entry_lines.base_debit_amount',
                'journal_entry_lines.base_credit_amount',
                'journal_entries.journal_number',
                'journal_entries.journal_type',
                'journal_entries.source_type',
                'journal_entries.source_id',
                'journal_entries.source_document_number',
                'journal_entries.posting_date',
                'journal_entries.currency_code',
                'journal_entries.exchange_rate',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.control_type',
                'branches.code as branch_code',
                'branches.name as branch_name',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('branches', 'branches.id', '=', 'journal_entry_lines.branch_id')
            ->where('journal_entries.status', 'posted')
            ->whereIn('accounts.control_type', ['cash', 'bank'])
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->when($accountId !== null, static fn (Builder $q): Builder => $q->where('journal_entry_lines.account_id', $accountId))
            ->when($branchId !== null, static fn (Builder $q): Builder => $q->where('journal_entry_lines.branch_id', $branchId))
            ->when($from !== '', static fn (Builder $q): Builder => $q->whereDate('journal_entries.posting_date', '>=', $from))
            ->when($to !== '', static fn (Builder $q): Builder => $q->whereDate('journal_entries.posting_date', '<=', $to))
            ->when($search !== '', static function (Builder $q) use ($search): void {
                $q->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('journal_entries.journal_number', 'like', "%{$search}%")
                        ->orWhere('journal_entries.source_document_number', 'like', "%{$search}%")
                        ->orWhere('journal_entry_lines.reference', 'like', "%{$search}%")
                        ->orWhere('journal_entry_lines.description', 'like', "%{$search}%");
                });
            })
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_number')
            ->limit(2000)
            ->get();

        $opening = $this->openingBalance($accountId, $branchId, $from, $branchIds);
        $running = BigDecimal::of($opening);
        $rows = [];

        foreach ($query as $line) {
            $movement = BigDecimal::of((string) $line->base_debit_amount)
                ->minus(BigDecimal::of((string) $line->base_credit_amount));
            $running = $running->plus($movement);
            $rows[] = [
                'id' => (int) $line->id,
                'posting_date' => (string) $line->posting_date,
                'journal_number' => $line->journal_number,
                'journal_type' => $line->journal_type,
                'source_document_number' => $line->source_document_number,
                'reference' => $line->reference,
                'description' => $line->description,
                'account_id' => (int) $line->account_id,
                'account_code' => $line->account_code,
                'account_name' => $line->account_name,
                'control_type' => $line->control_type,
                'branch_id' => (int) $line->branch_id,
                'branch_code' => $line->branch_code,
                'branch_name' => $line->branch_name,
                'currency_code' => $line->currency_code,
                'debit_amount' => (string) $line->debit_amount,
                'credit_amount' => (string) $line->credit_amount,
                'base_debit_amount' => (string) $line->base_debit_amount,
                'base_credit_amount' => (string) $line->base_credit_amount,
                'base_movement' => $this->decimal($movement),
                'base_running_balance' => $this->decimal($running),
            ];
        }

        return [
            'rows' => $rows,
            'opening_balance' => $this->decimal(BigDecimal::of($opening)),
            'closing_balance' => $this->decimal($running),
        ];
    }

    /** @param list<int> $branchIds */
    private function balanceForBranches(int $accountId, array $branchIds): string
    {
        if ($branchIds === []) {
            return '0.000000';
        }

        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $accountId)
            ->whereIn('journal_entry_lines.branch_id', $branchIds);
        $debit = (string) (clone $query)->sum('journal_entry_lines.base_debit_amount');
        $credit = (string) (clone $query)->sum('journal_entry_lines.base_credit_amount');

        return $this->decimal(BigDecimal::of($debit)->minus(BigDecimal::of($credit)));
    }

    /** @param list<int> $accessibleBranchIds */
    private function openingBalance(?int $accountId, ?int $branchId, string $from, array $accessibleBranchIds): string
    {
        if ($from === '' || $accessibleBranchIds === []) {
            return '0.000000';
        }

        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereIn('accounts.control_type', ['cash', 'bank'])
            ->whereIn('journal_entry_lines.branch_id', $accessibleBranchIds)
            ->whereDate('journal_entries.posting_date', '<', $from)
            ->when($accountId !== null, static fn (Builder $q): Builder => $q->where('journal_entry_lines.account_id', $accountId))
            ->when($branchId !== null, static fn (Builder $q): Builder => $q->where('journal_entry_lines.branch_id', $branchId));
        $debit = (string) (clone $query)->sum('journal_entry_lines.base_debit_amount');
        $credit = (string) (clone $query)->sum('journal_entry_lines.base_credit_amount');

        return $this->decimal(BigDecimal::of($debit)->minus(BigDecimal::of($credit)));
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}

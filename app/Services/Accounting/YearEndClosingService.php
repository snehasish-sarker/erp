<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\PeriodCloseRun;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use LogicException;

final class YearEndClosingService
{
    private const SCALE = 6;

    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @return list<int> */
    public function post(PeriodCloseRun $run, AccountingPeriod $period, User $actor): array
    {
        $this->requireTransaction();
        $period->loadMissing('fiscalYear.periods');

        if (!$this->isFinalPeriod($period)) {
            return [];
        }

        $retainedEarnings = Account::query()
            ->where('system_key', 'retained_earnings')
            ->where('status', 'active')
            ->where('is_group', false)
            ->lockForUpdate()
            ->first();

        if (!$retainedEarnings instanceof Account) {
            throw new LogicException('The retained earnings system account is not configured.');
        }

        $balances = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $period->tenant_id)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [
                $period->fiscalYear->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->whereIn('accounts.account_type', ['revenue', 'expense'])
            ->where('accounts.is_group', false)
            ->whereNotIn('journal_entries.journal_type', ['year_end_closing', 'year_end_closing_reversal'])
            ->groupBy([
                'journal_entry_lines.branch_id',
                'accounts.id',
                'accounts.code',
                'accounts.name',
            ])
            ->orderBy('journal_entry_lines.branch_id')
            ->orderBy('accounts.code')
            ->select([
                'journal_entry_lines.branch_id',
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
            ])
            ->selectRaw('SUM(journal_entry_lines.base_debit_amount) as debit')
            ->selectRaw('SUM(journal_entry_lines.base_credit_amount) as credit')
            ->get()
            ->groupBy('branch_id');

        $journalIds = [];
        foreach ($balances as $branchId => $rows) {
            $lines = [];
            $totalDebit = BigDecimal::zero();
            $totalCredit = BigDecimal::zero();

            foreach ($rows as $row) {
                $netDebit = BigDecimal::of((string) $row->debit)
                    ->minus((string) $row->credit);
                if ($netDebit->isZero()) {
                    continue;
                }

                $debit = $netDebit->isNegative() ? $netDebit->abs() : BigDecimal::zero();
                $credit = $netDebit->isPositive() ? $netDebit : BigDecimal::zero();
                $totalDebit = $totalDebit->plus($debit);
                $totalCredit = $totalCredit->plus($credit);
                $lines[] = $this->line(
                    accountId: (int) $row->account_id,
                    branchId: (int) $branchId,
                    reference: "FY-CLOSE-{$period->fiscalYear->code}",
                    description: "Close {$row->code} {$row->name} to retained earnings",
                    debit: $debit,
                    credit: $credit,
                );
            }

            if ($lines === []) {
                continue;
            }

            $difference = $totalDebit->minus($totalCredit);
            $retainedDebit = $difference->isNegative() ? $difference->abs() : BigDecimal::zero();
            $retainedCredit = $difference->isPositive() ? $difference : BigDecimal::zero();
            $lines[] = $this->line(
                accountId: (int) $retainedEarnings->getKey(),
                branchId: (int) $branchId,
                reference: "FY-CLOSE-{$period->fiscalYear->code}",
                description: "Transfer {$period->fiscalYear->code} earnings to retained earnings",
                debit: $retainedDebit,
                credit: $retainedCredit,
            );

            $journal = $this->journalEntryService->postSystemJournal(
                journalType: 'year_end_closing',
                source: $run,
                branchId: (int) $branchId,
                accountingPeriod: $period,
                documentDate: $period->end_date,
                postingDate: $period->end_date,
                currencyCode: $this->tenantContext->tenant()->currency_code,
                exchangeRate: '1.00000000',
                description: "Year-end closing for {$period->fiscalYear->code}",
                lines: $lines,
                postingKey: sprintf(
                    'period_close:%d:run:%d:branch:%d:year_end',
                    (int) $period->getKey(),
                    (int) $run->getKey(),
                    (int) $branchId,
                ),
                actor: $actor,
                sourceDocumentNumber: sprintf('CLOSE-%s-R%d', $period->code, $run->run_number),
                requireActiveBranch: false,
            );
            $journalIds[] = (int) $journal->getKey();
        }

        return $journalIds;
    }

    public function reverse(PeriodCloseRun $run, AccountingPeriod $period, string $reason, User $actor): void
    {
        $this->requireTransaction();
        foreach ((array) $run->closing_journal_ids as $journalId) {
            $journal = JournalEntry::query()->whereKey((int) $journalId)->lockForUpdate()->first();
            if (!$journal instanceof JournalEntry || $journal->isReversed()) {
                continue;
            }

            $this->journalEntryService->reverseSystemJournal(
                journalEntry: $journal,
                source: $run,
                accountingPeriod: $period,
                reversalPostingDate: $period->end_date,
                reason: $reason,
                postingKey: sprintf('period_close:%d:journal:%d:reopen', (int) $run->getKey(), (int) $journal->getKey()),
                actor: $actor,
            );
        }
    }

    private function isFinalPeriod(AccountingPeriod $period): bool
    {
        return !$period->fiscalYear->periods
            ->contains(static fn (AccountingPeriod $candidate): bool => $candidate->period_number > $period->period_number);
    }

    /** @return array<string, mixed> */
    private function line(
        int $accountId,
        int $branchId,
        string $reference,
        string $description,
        BigDecimal $debit,
        BigDecimal $credit,
    ): array {
        return [
            'account_id' => $accountId,
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => mb_substr($description, 0, 500),
            'due_date' => null,
            'debit_amount' => $this->decimal($debit),
            'credit_amount' => $this->decimal($credit),
            'base_debit_amount' => $this->decimal($debit),
            'base_credit_amount' => $this->decimal($credit),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Year-end closing must run inside a database transaction.');
        }
    }
}

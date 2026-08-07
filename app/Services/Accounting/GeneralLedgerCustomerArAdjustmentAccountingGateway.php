<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerArAdjustmentAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerArAdjustment;
use App\Models\CustomerLedgerEntry;
use App\Models\JournalEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerCustomerArAdjustmentAccountingGateway implements CustomerArAdjustmentAccountingGateway
{
    public function __construct(private readonly CustomerArAdjustmentJournalBuilder $journalBuilder, private readonly JournalEntryService $journalEntryService, private readonly CustomerArAdjustmentAccountsReceivableService $accountsReceivableService,)
    {
    }

    public function post(CustomerArAdjustment $adjustment, AccountingPeriod $period, User $actor): string
    {
        $this->requireTransaction();
        $payload = $this->journalBuilder->buildPosting($adjustment);
        $journal = $this->journalEntryService->postSystemJournal(journalType: 'customer_ar_adjustment', source: $adjustment, branchId: (int) $adjustment->branch_id, accountingPeriod: $period, documentDate: $adjustment->adjustment_date, postingDate: $adjustment->posting_date, currencyCode: (string) $adjustment->currency_code, exchangeRate: (string) $adjustment->exchange_rate, description: $payload['description'], lines: $payload['lines'], postingKey: $payload['posting_key'], actor: $actor, sourceDocumentNumber: $adjustment->adjustment_number,);
        $number = $this->journalNumber($journal);
        $ledger = $this->accountsReceivableService->post($adjustment, $period, $number, $actor);
        $this->ensureLedgerReference($ledger, $number);
        return $number;
    }

    public function reverse(CustomerArAdjustment $adjustment, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor): string
    {
        $this->requireTransaction();
        $original = JournalEntry::query()->where('posting_key', $this->journalBuilder->postingKey($adjustment))->lockForUpdate()->first();
        if (!$original instanceof JournalEntry) {
            throw new LogicException('The original Customer AR Adjustment journal is unavailable.');
        }
        $journal = $this->journalEntryService->reverseSystemJournal(journalEntry: $original, source: $adjustment, accountingPeriod: $period, reversalPostingDate: $reversalPostingDate, reason: $reason, postingKey: $this->journalBuilder->reversalPostingKey($adjustment), actor: $actor,);
        $number = $this->journalNumber($journal);
        $ledger = $this->accountsReceivableService->reverse($adjustment, $period, $reversalPostingDate, $number, $reason, $actor);
        $this->ensureLedgerReference($ledger, $number);
        return $number;
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);
        if ($number === '') {
            throw new LogicException('The Customer AR Adjustment journal does not have a journal number.');
        }
        return $number;
    }

    private function ensureLedgerReference(CustomerLedgerEntry $ledger, string $number): void
    {
        if ((string) $ledger->journal_reference !== $number) {
            throw new LogicException('The Customer AR Adjustment customer ledger does not match its General Ledger journal.');
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer AR Adjustment accounting must run inside a transaction.');
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerRefundAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerRefund;
use App\Models\JournalEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerCustomerRefundAccountingGateway implements CustomerRefundAccountingGateway
{
    public function __construct(private readonly CustomerRefundJournalBuilder $journalBuilder, private readonly JournalEntryService $journalEntryService, private readonly CustomerRefundAccountsReceivableService $accountsReceivableService,)
    {
    }

    public function post(CustomerRefund $refund, AccountingPeriod $period, User $actor): string
    {
        $this->requireTransaction();
        $payload = $this->journalBuilder->buildPosting($refund);
        $journal = $this->journalEntryService->postSystemJournal(journalType: 'customer_refund', source: $refund, branchId: (int) $refund->branch_id, accountingPeriod: $period, documentDate: $refund->refund_date, postingDate: $refund->posting_date, currencyCode: (string) $refund->currency_code, exchangeRate: (string) $refund->exchange_rate, description: $payload['description'], lines: $payload['lines'], postingKey: $payload['posting_key'], actor: $actor, sourceDocumentNumber: $refund->refund_number,);
        $number = $this->journalNumber($journal);
        $ledger = $this->accountsReceivableService->post($refund, $period, $number, $actor);
        $this->ensureLedgerReference($ledger, $number);
        return $number;
    }

    public function reverse(CustomerRefund $refund, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor): string
    {
        $this->requireTransaction();
        $original = JournalEntry::query()->where('posting_key', $this->journalBuilder->postingKey($refund))->lockForUpdate()->first();
        if (!$original instanceof JournalEntry) {
            throw new LogicException('The original Customer Refund journal is unavailable.');
        }
        $journal = $this->journalEntryService->reverseSystemJournal(journalEntry: $original, source: $refund, accountingPeriod: $period, reversalPostingDate: $reversalPostingDate, reason: $reason, postingKey: $this->journalBuilder->reversalPostingKey($refund), actor: $actor,);
        $number = $this->journalNumber($journal);
        $ledger = $this->accountsReceivableService->reverse($refund, $period, $reversalPostingDate, $number, $reason, $actor);
        $this->ensureLedgerReference($ledger, $number);
        return $number;
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);
        if ($number === '') {
            throw new LogicException('The Customer Refund journal does not have a journal number.');
        }
        return $number;
    }

    private function ensureLedgerReference(CustomerLedgerEntry $ledger, string $number): void
    {
        if ((string) $ledger->journal_reference !== $number) {
            throw new LogicException('The Customer Refund customer ledger does not match its General Ledger journal.');
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer Refund accounting must run inside a transaction.');
        }
    }
}

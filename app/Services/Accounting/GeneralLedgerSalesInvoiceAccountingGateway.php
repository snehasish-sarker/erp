<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SalesInvoiceAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerLedgerEntry;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerSalesInvoiceAccountingGateway implements SalesInvoiceAccountingGateway
{
    public function __construct(
        private readonly SalesInvoiceJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly AccountsReceivablePostingService $accountsReceivablePostingService,
    ) {
    }

    public function post(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $payload = $this->journalBuilder
            ->buildPosting($salesInvoice);

        $journal = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'sales_invoice',
                source: $salesInvoice,
                branchId: (int) $salesInvoice->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $salesInvoice->invoice_date,
                postingDate: $salesInvoice->posting_date,
                currencyCode: (string) $salesInvoice->currency_code,
                exchangeRate: (string) $salesInvoice->exchange_rate,
                description: $payload['description'],
                lines: $payload['lines'],
                postingKey: $payload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $salesInvoice->invoice_number,
            );

        $journalNumber = $this->journalNumber($journal);

        $ledger = $this->accountsReceivablePostingService
            ->postSalesInvoice(
                salesInvoice: $salesInvoice,
                accountingPeriod: $accountingPeriod,
                journalReference: $journalNumber,
                actor: $actor,
            );

        $this->ensureLedgerMatchesJournal(
            ledgerEntry: $ledger,
            journalNumber: $journalNumber,
        );

        return $journalNumber;
    }

    public function reverse(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $originalJournal = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->postingKey(
                    $salesInvoice,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$originalJournal instanceof JournalEntry) {
            throw new LogicException(
                'The original Sales Invoice journal is unavailable.',
            );
        }

        $reversal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $originalJournal,
                source: $salesInvoice,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey($salesInvoice),
                actor: $actor,
            );

        $journalNumber = $this->journalNumber($reversal);

        $ledger = $this->accountsReceivablePostingService
            ->reverseSalesInvoice(
                salesInvoice: $salesInvoice,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                journalReference: $journalNumber,
                reason: $reason,
                actor: $actor,
            );

        $this->ensureLedgerMatchesJournal(
            ledgerEntry: $ledger,
            journalNumber: $journalNumber,
        );

        return $journalNumber;
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim(
            (string) $journal->journal_number,
        );

        if ($number === '') {
            throw new LogicException(
                'The posted Sales Invoice journal does not have a journal number.',
            );
        }

        return $number;
    }

    private function ensureLedgerMatchesJournal(
        CustomerLedgerEntry $ledgerEntry,
        string $journalNumber,
    ): void {
        if (
            trim((string) $ledgerEntry->journal_reference)
            !== $journalNumber
        ) {
            throw new LogicException(
                'The Accounts Receivable customer ledger does not match the General Ledger journal.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Sales Invoice accounting must run inside the source transaction.',
            );
        }
    }
}
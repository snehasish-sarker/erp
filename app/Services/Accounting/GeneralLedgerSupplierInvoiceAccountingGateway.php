<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\SupplierInvoice;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Posts Supplier Invoices to both the General Ledger and the Accounts Payable
 * supplier subledger inside the source document's database transaction.
 */
final class GeneralLedgerSupplierInvoiceAccountingGateway implements SupplierInvoiceAccountingGateway
{
    public function __construct(
        private readonly SupplierInvoiceJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly AccountsPayablePostingService $accountsPayablePostingService,
    ) {
    }

    public function post(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $journalPayload = $this->journalBuilder
            ->buildPosting($supplierInvoice);

        $journalEntry = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'supplier_invoice',
                source: $supplierInvoice,
                branchId: (int) $supplierInvoice->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $supplierInvoice->invoice_date,
                postingDate: $supplierInvoice->posting_date,
                currencyCode: (string) $supplierInvoice->currency_code,
                exchangeRate: (string) $supplierInvoice->exchange_rate,
                description: $journalPayload['description'],
                lines: $journalPayload['lines'],
                postingKey: $journalPayload['posting_key'],
                actor: $actor,
                sourceDocumentNumber:
                    $supplierInvoice->document_number,
            );

        $journalNumber = $this->journalNumber(
            $journalEntry,
        );

        $supplierLedgerEntry =
            $this->accountsPayablePostingService
                ->postSupplierInvoice(
                    supplierInvoice: $supplierInvoice,
                    accountingPeriod: $accountingPeriod,
                    journalReference: $journalNumber,
                    actor: $actor,
                );

        $this->ensureSupplierLedgerMatchesJournal(
            supplierLedgerEntry: $supplierLedgerEntry,
            journalNumber: $journalNumber,
        );

        return $journalNumber;
    }

    public function reverse(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $originalJournal = $this->lockOriginalJournal(
            $supplierInvoice,
        );

        $reversalJournal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $originalJournal,
                source: $supplierInvoice,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey(
                        $supplierInvoice,
                    ),
                actor: $actor,
            );

        $reversalJournalNumber = $this->journalNumber(
            $reversalJournal,
        );

        $supplierLedgerReversal =
            $this->accountsPayablePostingService
                ->reverseSupplierInvoice(
                    supplierInvoice: $supplierInvoice,
                    accountingPeriod: $accountingPeriod,
                    reversalPostingDate:
                        $reversalPostingDate,
                    journalReference:
                        $reversalJournalNumber,
                    reason: $reason,
                    actor: $actor,
                );

        $this->ensureSupplierLedgerMatchesJournal(
            supplierLedgerEntry: $supplierLedgerReversal,
            journalNumber: $reversalJournalNumber,
        );

        return $reversalJournalNumber;
    }

    private function lockOriginalJournal(
        SupplierInvoice $supplierInvoice,
    ): JournalEntry {
        $journalEntry = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->postingKey(
                    $supplierInvoice,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$journalEntry instanceof JournalEntry) {
            throw new LogicException(
                'The original Supplier Invoice General Ledger journal is unavailable.',
            );
        }

        if (
            $journalEntry->journal_type !== 'supplier_invoice'
            || $journalEntry->source_type
                !== $supplierInvoice->getMorphClass()
            || (int) $journalEntry->source_id
                !== (int) $supplierInvoice->getKey()
        ) {
            throw new LogicException(
                'The original Supplier Invoice journal does not match the source document.',
            );
        }

        return $journalEntry;
    }

    private function journalNumber(
        JournalEntry $journalEntry,
    ): string {
        $journalNumber = trim(
            (string) $journalEntry->journal_number,
        );

        if ($journalNumber === '') {
            throw new LogicException(
                'The posted General Ledger journal does not have a journal number.',
            );
        }

        return $journalNumber;
    }

    private function ensureSupplierLedgerMatchesJournal(
        SupplierLedgerEntry $supplierLedgerEntry,
        string $journalNumber,
    ): void {
        if (
            trim(
                (string) $supplierLedgerEntry
                    ->journal_reference,
            ) !== $journalNumber
        ) {
            throw new LogicException(
                'The Accounts Payable supplier ledger reference does not match the General Ledger journal number.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Invoice accounting must run inside the source document transaction.',
            );
        }
    }
}
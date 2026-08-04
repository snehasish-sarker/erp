<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\SupplierDebitNote;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Posts Supplier Debit Notes to both the General Ledger and the Accounts
 * Payable supplier subledger inside the source document transaction.
 */
final class GeneralLedgerSupplierDebitNoteAccountingGateway implements SupplierDebitNoteAccountingGateway
{
    public function __construct(
        private readonly SupplierDebitNoteJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly AccountsPayablePostingService $accountsPayablePostingService,
    ) {
    }

    public function post(
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $journalPayload = $this->journalBuilder
            ->buildPosting($supplierDebitNote);

        $journalEntry = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'supplier_debit_note',
                source: $supplierDebitNote,
                branchId: (int) $supplierDebitNote->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $supplierDebitNote->debit_note_date,
                postingDate: $supplierDebitNote->posting_date,
                currencyCode: (string) $supplierDebitNote
                    ->currency_code,
                exchangeRate: (string) $supplierDebitNote
                    ->exchange_rate,
                description: $journalPayload['description'],
                lines: $journalPayload['lines'],
                postingKey: $journalPayload['posting_key'],
                actor: $actor,
                sourceDocumentNumber:
                    $supplierDebitNote->debit_note_number,
            );

        $journalNumber = $this->journalNumber(
            $journalEntry,
        );

        $supplierLedgerEntry =
            $this->accountsPayablePostingService
                ->postSupplierDebitNote(
                    supplierDebitNote: $supplierDebitNote,
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
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $originalJournal = $this->lockOriginalJournal(
            $supplierDebitNote,
        );

        $reversalJournal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $originalJournal,
                source: $supplierDebitNote,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey(
                        $supplierDebitNote,
                    ),
                actor: $actor,
            );

        $reversalJournalNumber = $this->journalNumber(
            $reversalJournal,
        );

        $supplierLedgerReversal =
            $this->accountsPayablePostingService
                ->reverseSupplierDebitNote(
                    supplierDebitNote: $supplierDebitNote,
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
        SupplierDebitNote $supplierDebitNote,
    ): JournalEntry {
        $journalEntry = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->postingKey(
                    $supplierDebitNote,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$journalEntry instanceof JournalEntry) {
            throw new LogicException(
                'The original Supplier Debit Note General Ledger journal is unavailable.',
            );
        }

        if (
            $journalEntry->journal_type
                !== 'supplier_debit_note'
            || $journalEntry->source_type
                !== $supplierDebitNote->getMorphClass()
            || (int) $journalEntry->source_id
                !== (int) $supplierDebitNote->getKey()
        ) {
            throw new LogicException(
                'The original Supplier Debit Note journal does not match the source document.',
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
                'Supplier Debit Note accounting must run inside the source document transaction.',
            );
        }
    }
}
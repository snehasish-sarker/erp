<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerCreditNoteAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerCreditNote;
use App\Models\JournalEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerCustomerCreditNoteAccountingGateway implements CustomerCreditNoteAccountingGateway
{
    public function __construct(
        private readonly CustomerCreditNoteJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @return array{
     *     accounting_reference: string,
     *     inventory_reference: string|null
     * }
     */
    public function post(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): array {
        $this->ensureInsideTransaction();

        $financialPayload = $this->journalBuilder
            ->buildFinancialPosting($creditNote);

        $financialJournal = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'customer_credit_note',
                source: $creditNote,
                branchId: (int) $creditNote->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $creditNote->credit_note_date,
                postingDate: $creditNote->posting_date,
                currencyCode: (string) $creditNote->currency_code,
                exchangeRate: (string) $creditNote->exchange_rate,
                description: $financialPayload['description'],
                lines: $financialPayload['lines'],
                postingKey: $financialPayload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $creditNote->credit_note_number,
            );

        $inventoryReference = null;
        $inventoryPayload = $this->journalBuilder
            ->buildInventoryPosting($creditNote);

        if ($inventoryPayload !== null) {
            $inventoryJournal = $this->journalEntryService
                ->postSystemJournal(
                    journalType: 'sales_return',
                    source: $creditNote,
                    branchId: (int) $creditNote->branch_id,
                    accountingPeriod: $accountingPeriod,
                    documentDate: $creditNote->credit_note_date,
                    postingDate: $creditNote->posting_date,
                    currencyCode: $inventoryPayload['currency_code'],
                    exchangeRate: '1.00000000',
                    description: $inventoryPayload['description'],
                    lines: $inventoryPayload['lines'],
                    postingKey: $inventoryPayload['posting_key'],
                    actor: $actor,
                    sourceDocumentNumber: $creditNote->credit_note_number,
                );

            $inventoryReference = $this->journalNumber($inventoryJournal);
        }

        return [
            'accounting_reference' => $this->journalNumber($financialJournal),
            'inventory_reference' => $inventoryReference,
        ];
    }

    /**
     * @return array{
     *     accounting_reference: string,
     *     inventory_reference: string|null
     * }
     */
    public function reverse(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): array {
        $this->ensureInsideTransaction();

        $financialOriginal = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->financialPostingKey($creditNote),
            )
            ->lockForUpdate()
            ->first();

        if (!$financialOriginal instanceof JournalEntry) {
            throw new LogicException(
                'The original Customer Credit Note financial journal is unavailable.',
            );
        }

        $financialReversal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $financialOriginal,
                source: $creditNote,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->financialReversalPostingKey($creditNote),
                actor: $actor,
            );

        $inventoryReference = null;

        $inventoryOriginal = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->inventoryPostingKey($creditNote),
            )
            ->lockForUpdate()
            ->first();

        if ($inventoryOriginal instanceof JournalEntry) {
            $inventoryReversal = $this->journalEntryService
                ->reverseSystemJournal(
                    journalEntry: $inventoryOriginal,
                    source: $creditNote,
                    accountingPeriod: $accountingPeriod,
                    reversalPostingDate: $reversalPostingDate,
                    reason: $reason,
                    postingKey: $this->journalBuilder
                        ->inventoryReversalPostingKey($creditNote),
                    actor: $actor,
                );

            $inventoryReference = $this->journalNumber($inventoryReversal);
        }

        return [
            'accounting_reference' => $this->journalNumber($financialReversal),
            'inventory_reference' => $inventoryReference,
        ];
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);

        if ($number === '') {
            throw new LogicException(
                'The Customer Credit Note journal does not have a journal number.',
            );
        }

        return $number;
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Credit Note accounting must run inside the source transaction.',
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\GoodsReceiptAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerGoodsReceiptAccountingGateway implements GoodsReceiptAccountingGateway
{
    public function __construct(
        private readonly GoodsReceiptJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function post(
        GoodsReceipt $goodsReceipt,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): ?string {
        $this->ensureInsideTransaction();

        $journalPayload = $this->journalBuilder
            ->buildPosting($goodsReceipt);

        if ($journalPayload === null) {
            return null;
        }

        $journalEntry = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'inventory',
                source: $goodsReceipt,
                branchId: (int) $goodsReceipt->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $goodsReceipt->receipt_date,
                postingDate: $goodsReceipt->receipt_date,
                currencyCode: $journalPayload['currency_code'],
                exchangeRate: $journalPayload['exchange_rate'],
                description: $journalPayload['description'],
                lines: $journalPayload['lines'],
                postingKey: $journalPayload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $goodsReceipt->receipt_number,
            );

        return $this->journalNumber($journalEntry);
    }

    public function reverse(
        GoodsReceipt $goodsReceipt,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): ?string {
        $this->ensureInsideTransaction();

        $originalJournal = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->postingKey(
                    $goodsReceipt,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$originalJournal instanceof JournalEntry) {
            if (
                $goodsReceipt->accounting_reference === null
                || trim(
                    (string) $goodsReceipt->accounting_reference,
                ) === ''
            ) {
                return null;
            }

            throw new LogicException(
                'The original Goods Receipt General Ledger journal is unavailable.',
            );
        }

        if (
            $originalJournal->journal_type !== 'inventory'
            || $originalJournal->source_type
                !== $goodsReceipt->getMorphClass()
            || (int) $originalJournal->source_id
                !== (int) $goodsReceipt->getKey()
        ) {
            throw new LogicException(
                'The original Goods Receipt journal does not match the source document.',
            );
        }

        $reversalJournal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $originalJournal,
                source: $goodsReceipt,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey(
                        $goodsReceipt,
                    ),
                actor: $actor,
            );

        return $this->journalNumber(
            $reversalJournal,
        );
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

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Goods Receipt accounting must run inside the source document transaction.',
            );
        }
    }
}
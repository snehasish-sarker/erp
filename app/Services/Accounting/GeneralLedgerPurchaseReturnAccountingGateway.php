<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\PurchaseReturnAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\PurchaseReturn;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerPurchaseReturnAccountingGateway implements PurchaseReturnAccountingGateway
{
    public function __construct(
        private readonly PurchaseReturnJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function post(
        PurchaseReturn $purchaseReturn,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): ?string {
        $this->ensureInsideTransaction();

        $journalPayload = $this->journalBuilder
            ->buildPosting($purchaseReturn);

        if ($journalPayload === null) {
            return null;
        }

        $journalEntry = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'inventory',
                source: $purchaseReturn,
                branchId: (int) $purchaseReturn->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $purchaseReturn->return_date,
                postingDate: $purchaseReturn->posting_date,
                currencyCode: $journalPayload['currency_code'],
                exchangeRate: $journalPayload['exchange_rate'],
                description: $journalPayload['description'],
                lines: $journalPayload['lines'],
                postingKey: $journalPayload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $purchaseReturn->return_number,
            );

        return $this->journalNumber($journalEntry);
    }

    public function reverse(
        PurchaseReturn $purchaseReturn,
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
                    $purchaseReturn,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$originalJournal instanceof JournalEntry) {
            if (
                $purchaseReturn->accounting_reference === null
                || trim(
                    (string) $purchaseReturn->accounting_reference,
                ) === ''
            ) {
                return null;
            }

            throw new LogicException(
                'The original Purchase Return General Ledger journal is unavailable.',
            );
        }

        if (
            $originalJournal->journal_type !== 'inventory'
            || $originalJournal->source_type
                !== $purchaseReturn->getMorphClass()
            || (int) $originalJournal->source_id
                !== (int) $purchaseReturn->getKey()
        ) {
            throw new LogicException(
                'The original Purchase Return journal does not match the source document.',
            );
        }

        $reversalJournal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $originalJournal,
                source: $purchaseReturn,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey(
                        $purchaseReturn,
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
                'Purchase Return accounting must run inside the source document transaction.',
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\TreasuryTransferAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerTreasuryTransferAccountingGateway implements TreasuryTransferAccountingGateway
{
    public function __construct(
        private readonly TreasuryTransferJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function post(
        TreasuryTransfer $transfer,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->requireTransaction();
        $references = [];

        foreach ($this->journalBuilder->build($transfer) as $payload) {
            $journal = $this->journalEntryService->postSystemJournal(
                journalType: 'treasury_transfer',
                source: $transfer,
                branchId: $payload['branch_id'],
                accountingPeriod: $accountingPeriod,
                documentDate: $transfer->transfer_date,
                postingDate: $transfer->posting_date,
                currencyCode: (string) $transfer->currency_code,
                exchangeRate: (string) $transfer->exchange_rate,
                description: $payload['description'],
                lines: $payload['lines'],
                postingKey: $payload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $transfer->transfer_number,
            );

            $references[] = $this->journalNumber($journal);
        }

        return implode(' / ', $references);
    }

    public function reverse(
        TreasuryTransfer $transfer,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->requireTransaction();
        $postingKeys = (int) $transfer->source_branch_id === (int) $transfer->destination_branch_id
            ? [$this->journalBuilder->sameBranchPostingKey($transfer)]
            : [
                $this->journalBuilder->sourcePostingKey($transfer),
                $this->journalBuilder->destinationPostingKey($transfer),
            ];

        $references = [];

        foreach ($postingKeys as $postingKey) {
            $original = JournalEntry::query()
                ->where('posting_key', $postingKey)
                ->lockForUpdate()
                ->first();

            if (!$original instanceof JournalEntry) {
                throw new LogicException('The original Treasury Transfer journal is unavailable.');
            }

            $reversal = $this->journalEntryService->reverseSystemJournal(
                journalEntry: $original,
                source: $transfer,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder->reversalKey($postingKey),
                actor: $actor,
            );

            $references[] = $this->journalNumber($reversal);
        }

        return implode(' / ', $references);
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);

        if ($number === '') {
            throw new LogicException('The Treasury Transfer journal does not have a number.');
        }

        return $number;
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Treasury Transfer accounting must run inside a transaction.');
        }
    }
}

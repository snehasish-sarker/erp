<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\TreasuryAdjustmentAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\TreasuryAdjustment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerTreasuryAdjustmentAccountingGateway implements TreasuryAdjustmentAccountingGateway
{
    public function __construct(
        private readonly TreasuryAdjustmentJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function post(
        TreasuryAdjustment $adjustment,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->requireTransaction();
        $payload = $this->journalBuilder->build($adjustment);
        $journal = $this->journalEntryService->postSystemJournal(
            journalType: 'treasury_adjustment',
            source: $adjustment,
            branchId: (int) $adjustment->branch_id,
            accountingPeriod: $accountingPeriod,
            documentDate: $adjustment->adjustment_date,
            postingDate: $adjustment->posting_date,
            currencyCode: (string) $adjustment->currency_code,
            exchangeRate: (string) $adjustment->exchange_rate,
            description: $payload['description'],
            lines: $payload['lines'],
            postingKey: $payload['posting_key'],
            actor: $actor,
            sourceDocumentNumber: $adjustment->adjustment_number,
        );

        return $this->journalNumber($journal);
    }

    public function reverse(
        TreasuryAdjustment $adjustment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->requireTransaction();
        $original = JournalEntry::query()
            ->where('posting_key', $this->journalBuilder->postingKey($adjustment))
            ->lockForUpdate()
            ->first();

        if (!$original instanceof JournalEntry) {
            throw new LogicException('The original Treasury Adjustment journal is unavailable.');
        }

        $reversal = $this->journalEntryService->reverseSystemJournal(
            journalEntry: $original,
            source: $adjustment,
            accountingPeriod: $accountingPeriod,
            reversalPostingDate: $reversalPostingDate,
            reason: $reason,
            postingKey: $this->journalBuilder->reversalPostingKey($adjustment),
            actor: $actor,
        );

        return $this->journalNumber($reversal);
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim((string) $journal->journal_number);

        if ($number === '') {
            throw new LogicException('The Treasury Adjustment journal does not have a number.');
        }

        return $number;
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Treasury Adjustment accounting must run inside a transaction.');
        }
    }
}

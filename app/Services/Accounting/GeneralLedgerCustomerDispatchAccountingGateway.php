<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerDispatchAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\CustomerDispatch;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GeneralLedgerCustomerDispatchAccountingGateway implements CustomerDispatchAccountingGateway
{
    public function __construct(
        private readonly CustomerDispatchJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function post(
        CustomerDispatch $customerDispatch,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $payload = $this->journalBuilder
            ->buildPosting($customerDispatch);

        $tenant = $this->tenantContext->tenant();

        $journal = $this->journalEntryService
            ->postSystemJournal(
                journalType: 'customer_dispatch',
                source: $customerDispatch,
                branchId: (int) $customerDispatch->branch_id,
                accountingPeriod: $accountingPeriod,
                documentDate: $customerDispatch->dispatch_date,
                postingDate: $customerDispatch->dispatch_date,
                currencyCode: (string) $tenant->currency_code,
                exchangeRate: '1.00000000',
                description: $payload['description'],
                lines: $payload['lines'],
                postingKey: $payload['posting_key'],
                actor: $actor,
                sourceDocumentNumber: $customerDispatch->dispatch_number,
            );

        return $this->journalNumber($journal);
    }

    public function reverse(
        CustomerDispatch $customerDispatch,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $original = JournalEntry::query()
            ->where(
                'posting_key',
                $this->journalBuilder->postingKey(
                    $customerDispatch,
                ),
            )
            ->lockForUpdate()
            ->first();

        if (!$original instanceof JournalEntry) {
            throw new LogicException(
                'The original Customer Dispatch accounting journal is unavailable.',
            );
        }

        $reversal = $this->journalEntryService
            ->reverseSystemJournal(
                journalEntry: $original,
                source: $customerDispatch,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                postingKey: $this->journalBuilder
                    ->reversalPostingKey($customerDispatch),
                actor: $actor,
            );

        return $this->journalNumber($reversal);
    }

    private function journalNumber(JournalEntry $journal): string
    {
        $number = trim(
            (string) $journal->journal_number,
        );

        if ($number === '') {
            throw new LogicException(
                'The Customer Dispatch accounting journal does not have a journal number.',
            );
        }

        return $number;
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Dispatch accounting must run inside the source transaction.',
            );
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Accounting\CustomerDispatchAccountingGateway;
use App\Models\CustomerDispatch;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\CustomerDispatchJournalBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CustomerDispatchObserver
{
    public function __construct(
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly CustomerDispatchAccountingGateway $accountingGateway,
        private readonly CustomerDispatchJournalBuilder $journalBuilder,
    ) {
    }

    public function updating(
        CustomerDispatch $customerDispatch,
    ): void {
        if (!$customerDispatch->isDirty('status')) {
            return;
        }

        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Dispatch status transitions must run inside a database transaction.',
            );
        }

        $oldStatus = (string) $customerDispatch
            ->getOriginal('status');

        $newStatus = (string) $customerDispatch->status;

        if ($oldStatus === 'draft' && $newStatus === 'posted') {
            if (!$this->journalBuilder->hasStockCost($customerDispatch)) {
                return;
            }

            $actor = $this->actor(
                (int) $customerDispatch->posted_by_user_id,
            );

            $period = $this->accountingPeriodService
                ->lockOpenPeriod(
                    $customerDispatch->dispatch_date,
                );

            $customerDispatch->accounting_posting_reference =
                $this->accountingGateway->post(
                    customerDispatch: $customerDispatch,
                    accountingPeriod: $period,
                    actor: $actor,
                );

            return;
        }

        if ($oldStatus === 'posted' && $newStatus === 'reversed') {
            if (
                $customerDispatch->accounting_posting_reference === null
                || $customerDispatch->accounting_posting_reference === ''
            ) {
                return;
            }

            $actor = $this->actor(
                (int) $customerDispatch->reversed_by_user_id,
            );

            $date = $customerDispatch->reversed_at !== null
                ? CarbonImmutable::instance(
                    $customerDispatch->reversed_at,
                )
                : CarbonImmutable::now();

            $period = $this->accountingPeriodService
                ->lockOpenPeriod($date);

            $customerDispatch->accounting_reversal_reference =
                $this->accountingGateway->reverse(
                    customerDispatch: $customerDispatch,
                    accountingPeriod: $period,
                    reversalPostingDate: $date,
                    reason: (string) $customerDispatch->reversal_reason,
                    actor: $actor,
                );
        }
    }

    private function actor(int $userId): User
    {
        $actor = User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->first();

        if (!$actor instanceof User) {
            throw new LogicException(
                'The Customer Dispatch accounting actor is unavailable.',
            );
        }

        return $actor;
    }
}
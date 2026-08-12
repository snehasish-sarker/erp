<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsReceivableRegistry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerOpenItemAllocationService
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsReceivableRegistry $registry,
    ) {
    }

    public function apply(
        CustomerOpenItem $receivableOpenItem,
        CustomerOpenItem $creditOpenItem,
        AccountingPeriod $accountingPeriod,
        string $allocationType,
        string $postingKey,
        DateTimeInterface $allocationDate,
        DateTimeInterface $postingDate,
        string $amount,
        ?Model $source,
        User $actor,
    ): CustomerOpenItemAllocation {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $allocationType = trim($allocationType);

        if (!$this->registry->isAllocationType($allocationType)) {
            throw ValidationException::withMessages([
                'allocation_type' => [
                    'The customer allocation type is invalid.',
                ],
            ]);
        }

        $postingKey = $this->requiredString(
            value: $postingKey,
            field: 'posting_key',
            maximumLength: 190,
        );

        $allocationAmount = $this->positiveMoney(
            value: $amount,
            field: 'amount',
        );

        $allocationDateString = $this->dateString(
            value: $allocationDate,
            timezone: $tenant->timezone,
        );

        $postingDateString = $this->dateString(
            value: $postingDate,
            timezone: $tenant->timezone,
        );

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $postingDateString,
            tenantId: $tenantId,
        );

        $existingAllocation =
            CustomerOpenItemAllocation::query()
                ->where('posting_key', $postingKey)
                ->lockForUpdate()
                ->first();

        if (
            $existingAllocation
            instanceof CustomerOpenItemAllocation
        ) {
            return $this->validateExistingAllocation(
                allocation: $existingAllocation,
                receivableOpenItemId:
                    (int) $receivableOpenItem->getKey(),
                creditOpenItemId:
                    (int) $creditOpenItem->getKey(),
                allocationType: $allocationType,
                amount: $allocationAmount,
                source: $source,
            );
        }

        [
            $lockedReceivable,
            $lockedCredit,
        ] = $this->lockOpenItems(
            receivableOpenItemId:
                (int) $receivableOpenItem->getKey(),
            creditOpenItemId:
                (int) $creditOpenItem->getKey(),
        );

        $this->validateOpenItemPair(
            receivableOpenItem: $lockedReceivable,
            creditOpenItem: $lockedCredit,
            amount: $allocationAmount,
            tenantId: $tenantId,
        );

        $branch = Branch::query()
            ->whereKey($lockedReceivable->branch_id)
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: false,
        );

        $this->ensureSourceBelongsToTenant(
            source: $source,
            tenantId: $tenantId,
        );

        $receivableBaseAmount =
            $this->baseAmountForAllocation(
                openItem: $lockedReceivable,
                amount: $allocationAmount,
            );

        $creditBaseAmount =
            $this->baseAmountForAllocation(
                openItem: $lockedCredit,
                amount: $allocationAmount,
            );

        $exchangeDifference = $creditBaseAmount
            ->minus($receivableBaseAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

        $this->applyAmountToOpenItem(
            openItem: $lockedReceivable,
            amount: $allocationAmount,
            baseAmount: $receivableBaseAmount,
        );

        $this->applyAmountToOpenItem(
            openItem: $lockedCredit,
            amount: $allocationAmount,
            baseAmount: $creditBaseAmount,
        );

        $allocation =
            CustomerOpenItemAllocation::query()
                ->create([
                    'branch_id' =>
                        $lockedReceivable->branch_id,

                    'customer_id' =>
                        $lockedReceivable->customer_id,

                    'accounting_period_id' =>
                        $accountingPeriod->getKey(),

                    'receivable_open_item_id' =>
                        $lockedReceivable->getKey(),

                    'credit_open_item_id' =>
                        $lockedCredit->getKey(),

                    'allocation_type' =>
                        $allocationType,

                    'posting_key' =>
                        $postingKey,

                    'source_type' =>
                        $source?->getMorphClass(),

                    'source_id' =>
                        $source?->getKey(),

                    'allocation_date' =>
                        $allocationDateString,

                    'posting_date' =>
                        $postingDateString,

                    'currency_code' =>
                        $lockedReceivable->currency_code,

                    'amount' =>
                        $allocationAmount->__toString(),

                    'receivable_base_amount' =>
                        $receivableBaseAmount->__toString(),

                    'credit_base_amount' =>
                        $creditBaseAmount->__toString(),

                    'exchange_difference_amount' =>
                        $exchangeDifference->__toString(),

                    'status' => 'applied',

                    'created_by_user_id' =>
                        $actor->getKey(),
                ]);

        return $allocation->load([
            'accountingPeriod',
            'receivableOpenItem',
            'creditOpenItem',
            'createdBy',
        ]);
    }

    public function reverse(
        CustomerOpenItemAllocation $allocation,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): CustomerOpenItemAllocation {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $reason = $this->requiredString(
            value: $reason,
            field: 'reversal_reason',
            maximumLength: 500,
        );

        $reversalDate = $this->dateString(
            value: $reversalPostingDate,
            timezone: $tenant->timezone,
        );

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $reversalDate,
            tenantId: $tenantId,
        );

        $lockedAllocation =
            CustomerOpenItemAllocation::query()
                ->whereKey($allocation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

        if ($lockedAllocation->isReversed()) {
            return $lockedAllocation->load([
                'accountingPeriod',
                'reversalAccountingPeriod',
                'receivableOpenItem',
                'creditOpenItem',
                'createdBy',
                'reversedBy',
            ]);
        }

        if (!$lockedAllocation->isApplied()) {
            throw new LogicException(
                'Only an applied customer open-item allocation can be reversed.',
            );
        }

        [
            $lockedReceivable,
            $lockedCredit,
        ] = $this->lockOpenItems(
            receivableOpenItemId:
                (int) $lockedAllocation
                    ->receivable_open_item_id,
            creditOpenItemId:
                (int) $lockedAllocation
                    ->credit_open_item_id,
        );

        $this->ensureAllocationContextMatches(
            allocation: $lockedAllocation,
            receivableOpenItem: $lockedReceivable,
            creditOpenItem: $lockedCredit,
            tenantId: $tenantId,
        );

        $branch = Branch::query()
            ->whereKey($lockedReceivable->branch_id)
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: false,
        );

        $this->restoreAmountToOpenItem(
            openItem: $lockedReceivable,
            amount: $this->money(
                $lockedAllocation->amount,
            ),
            baseAmount: $this->money(
                $lockedAllocation
                    ->receivable_base_amount,
            ),
        );

        $this->restoreAmountToOpenItem(
            openItem: $lockedCredit,
            amount: $this->money(
                $lockedAllocation->amount,
            ),
            baseAmount: $this->money(
                $lockedAllocation
                    ->credit_base_amount,
            ),
        );

        $lockedAllocation->status = 'reversed';
        $lockedAllocation->reversed_by_user_id =
            $actor->getKey();
        $lockedAllocation->reversal_accounting_period_id =
            $accountingPeriod->getKey();
        $lockedAllocation->reversal_posting_date =
            $reversalDate;
        $lockedAllocation->reversed_at =
            CarbonImmutable::now('UTC');
        $lockedAllocation->reversal_reason = $reason;
        $lockedAllocation->save();

        return $lockedAllocation->refresh()->load([
            'accountingPeriod',
            'reversalAccountingPeriod',
            'receivableOpenItem',
            'creditOpenItem',
            'createdBy',
            'reversedBy',
        ]);
    }

    /**
     * @return array{0: CustomerOpenItem, 1: CustomerOpenItem}
     */
    private function lockOpenItems(
        int $receivableOpenItemId,
        int $creditOpenItemId,
    ): array {
        if (
            $receivableOpenItemId < 1
            || $creditOpenItemId < 1
            || $receivableOpenItemId === $creditOpenItemId
        ) {
            throw new LogicException(
                'A customer allocation requires two different open items.',
            );
        }

        /** @var Collection<int, CustomerOpenItem> $items */
        $items = CustomerOpenItem::query()
            ->whereIn(
                'id',
                [
                    $receivableOpenItemId,
                    $creditOpenItemId,
                ],
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(
                static fn (
                    CustomerOpenItem $item,
                ): int => (int) $item->getKey(),
            );

        $receivable = $items->get(
            $receivableOpenItemId,
        );

        $credit = $items->get(
            $creditOpenItemId,
        );

        if (
            !$receivable instanceof CustomerOpenItem
            || !$credit instanceof CustomerOpenItem
        ) {
            throw new LogicException(
                'One or more customer open items are unavailable.',
            );
        }

        return [
            $receivable,
            $credit,
        ];
    }

    private function validateOpenItemPair(
        CustomerOpenItem $receivableOpenItem,
        CustomerOpenItem $creditOpenItem,
        BigDecimal $amount,
        int $tenantId,
    ): void {
        if (
            (int) $receivableOpenItem->tenant_id
                !== $tenantId
            || (int) $creditOpenItem->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The customer open items do not belong to the active tenant.',
            );
        }

        if (!$receivableOpenItem->isReceivable()) {
            throw ValidationException::withMessages([
                'receivable_open_item_id' => [
                    'The selected item must be an open customer receivable.',
                ],
            ]);
        }

        if (!$creditOpenItem->isCredit()) {
            throw ValidationException::withMessages([
                'credit_open_item_id' => [
                    'The selected credit item must be a customer credit or unallocated receipt.',
                ],
            ]);
        }

        if (
            $receivableOpenItem->isReversed()
            || $creditOpenItem->isReversed()
        ) {
            throw ValidationException::withMessages([
                'allocation' => [
                    'A reversed customer open item cannot be allocated.',
                ],
            ]);
        }

        if (
            (int) $receivableOpenItem->branch_id
                !== (int) $creditOpenItem->branch_id
            || (int) $receivableOpenItem->customer_id
                !== (int) $creditOpenItem->customer_id
            || strtoupper(
                (string) $receivableOpenItem->currency_code,
            ) !== strtoupper(
                (string) $creditOpenItem->currency_code,
            )
        ) {
            throw ValidationException::withMessages([
                'allocation' => [
                    'Customer open-item allocations must use the same branch, customer, and document currency.',
                ],
            ]);
        }

        if (
            $this->money(
                $receivableOpenItem->outstanding_amount,
            )->isLessThan($amount)
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'The allocation exceeds the receivable item outstanding amount.',
                ],
            ]);
        }

        if (
            $this->money(
                $creditOpenItem->outstanding_amount,
            )->isLessThan($amount)
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'The allocation exceeds the credit item outstanding amount.',
                ],
            ]);
        }
    }

    private function ensureAllocationContextMatches(
        CustomerOpenItemAllocation $allocation,
        CustomerOpenItem $receivableOpenItem,
        CustomerOpenItem $creditOpenItem,
        int $tenantId,
    ): void {
        if (
            (int) $allocation->tenant_id !== $tenantId
            || (int) $receivableOpenItem->tenant_id
                !== $tenantId
            || (int) $creditOpenItem->tenant_id
                !== $tenantId
            || (int) $allocation->branch_id
                !== (int) $receivableOpenItem->branch_id
            || (int) $allocation->branch_id
                !== (int) $creditOpenItem->branch_id
            || (int) $allocation->customer_id
                !== (int) $receivableOpenItem->customer_id
            || (int) $allocation->customer_id
                !== (int) $creditOpenItem->customer_id
            || (int) $allocation->receivable_open_item_id
                !== (int) $receivableOpenItem->getKey()
            || (int) $allocation->credit_open_item_id
                !== (int) $creditOpenItem->getKey()
        ) {
            throw new LogicException(
                'The customer open-item allocation context is inconsistent.',
            );
        }
    }

    private function applyAmountToOpenItem(
        CustomerOpenItem $openItem,
        BigDecimal $amount,
        BigDecimal $baseAmount,
    ): void {
        $outstanding = $this->money(
            $openItem->outstanding_amount,
        );

        $baseOutstanding = $this->money(
            $openItem->base_outstanding_amount,
        );

        if (
            $outstanding->isLessThan($amount)
            || $baseOutstanding->isLessThan(
                $baseAmount,
            )
        ) {
            throw new LogicException(
                'The customer open item does not retain enough outstanding value for the allocation.',
            );
        }

        $openItem->allocated_amount = $this->money(
            $openItem->allocated_amount,
        )
            ->plus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            )
            ->__toString();

        $openItem->outstanding_amount = $outstanding
            ->minus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            )
            ->__toString();

        $openItem->base_allocated_amount =
            $this->money(
                $openItem->base_allocated_amount,
            )
                ->plus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $openItem->base_outstanding_amount =
            $baseOutstanding
                ->minus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $this->synchronizeOpenItemStatus(
            $openItem,
        );

        $openItem->save();
    }

    private function restoreAmountToOpenItem(
        CustomerOpenItem $openItem,
        BigDecimal $amount,
        BigDecimal $baseAmount,
    ): void {
        $allocated = $this->money(
            $openItem->allocated_amount,
        );

        $baseAllocated = $this->money(
            $openItem->base_allocated_amount,
        );

        if (
            $allocated->isLessThan($amount)
            || $baseAllocated->isLessThan(
                $baseAmount,
            )
        ) {
            throw new LogicException(
                'The customer open item allocated value is lower than the allocation being reversed.',
            );
        }

        $newOutstanding = $this->money(
            $openItem->outstanding_amount,
        )->plus($amount);

        $newBaseOutstanding = $this->money(
            $openItem->base_outstanding_amount,
        )->plus($baseAmount);

        if (
            $newOutstanding->isGreaterThan(
                $this->money(
                    $openItem->original_amount,
                ),
            )
            || $newBaseOutstanding->isGreaterThan(
                $this->money(
                    $openItem->base_original_amount,
                ),
            )
        ) {
            throw new LogicException(
                'Reversing the allocation would exceed the customer open item original value.',
            );
        }

        $openItem->allocated_amount = $allocated
            ->minus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            )
            ->__toString();

        $openItem->outstanding_amount =
            $newOutstanding
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $openItem->base_allocated_amount =
            $baseAllocated
                ->minus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $openItem->base_outstanding_amount =
            $newBaseOutstanding
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $this->synchronizeOpenItemStatus(
            $openItem,
        );

        $openItem->save();
    }

    private function synchronizeOpenItemStatus(
        CustomerOpenItem $openItem,
    ): void {
        $outstanding = $this->money(
            $openItem->outstanding_amount,
        );

        $allocated = $this->money(
            $openItem->allocated_amount,
        );

        if ($outstanding->isZero()) {
            $openItem->status = 'settled';
            $openItem->closed_at =
                CarbonImmutable::now('UTC');

            return;
        }

        $openItem->status = $allocated->isZero()
            ? 'open'
            : 'partially_settled';

        $openItem->closed_at = null;
    }

    private function baseAmountForAllocation(
        CustomerOpenItem $openItem,
        BigDecimal $amount,
    ): BigDecimal {
        $outstanding = $this->money(
            $openItem->outstanding_amount,
        );

        $baseOutstanding = $this->money(
            $openItem->base_outstanding_amount,
        );

        if ($amount->compareTo($outstanding) === 0) {
            return $baseOutstanding;
        }

        $baseAmount = $amount
            ->multipliedBy(
                $this->positiveExchangeRate(
                    $openItem->exchange_rate,
                ),
            )
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

        return $baseAmount->isGreaterThan(
            $baseOutstanding,
        )
            ? $baseOutstanding
            : $baseAmount;
    }

    private function validateExistingAllocation(
        CustomerOpenItemAllocation $allocation,
        int $receivableOpenItemId,
        int $creditOpenItemId,
        string $allocationType,
        BigDecimal $amount,
        ?Model $source,
    ): CustomerOpenItemAllocation {
        if (
            !$allocation->isApplied()
            || (int) $allocation->receivable_open_item_id
                !== $receivableOpenItemId
            || (int) $allocation->credit_open_item_id
                !== $creditOpenItemId
            || $allocation->allocation_type
                !== $allocationType
            || $this->money(
                $allocation->amount,
            )->compareTo($amount) !== 0
            || $allocation->source_type
                !== $source?->getMorphClass()
            || (
                $allocation->source_id !== null
                ? (int) $allocation->source_id
                : null
            ) !== (
                $source?->getKey() !== null
                ? (int) $source->getKey()
                : null
            )
        ) {
            throw new LogicException(
                'The customer allocation posting key already belongs to a different allocation.',
            );
        }

        return $allocation->load([
            'accountingPeriod',
            'receivableOpenItem',
            'creditOpenItem',
            'createdBy',
        ]);
    }

    private function ensureAccountingPeriod(
        AccountingPeriod $accountingPeriod,
        string $postingDate,
        int $tenantId,
    ): void {
        if (
            (int) $accountingPeriod->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The accounting period does not belong to the active tenant.',
            );
        }

        if (!$accountingPeriod->isOpen()) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    "The accounting period {$accountingPeriod->code} is closed.",
                ],
            ]);
        }

        if (
            $postingDate
                < $accountingPeriod->start_date->toDateString()
            || $postingDate
                > $accountingPeriod->end_date->toDateString()
        ) {
            throw new LogicException(
                'The allocation posting date is outside the supplied accounting period.',
            );
        }
    }

    private function ensureSourceBelongsToTenant(
        ?Model $source,
        int $tenantId,
    ): void {
        if ($source === null) {
            return;
        }

        if (!$source->exists || $source->getKey() === null) {
            throw new LogicException(
                'The customer allocation source has not been persisted.',
            );
        }

        $sourceTenantId = $source->getAttribute(
            'tenant_id',
        );

        if (
            $sourceTenantId !== null
            && (int) $sourceTenantId !== $tenantId
        ) {
            throw new LogicException(
                'The customer allocation source belongs to a different tenant.',
            );
        }
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The actor does not belong to the active tenant.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }

        throw new LogicException(
            'Customer open-item allocation must run inside the accounting database transaction.',
        );
    }

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->decimal(
            value: $value,
            field: $field,
            scale: self::MONEY_SCALE,
        );

        if (
            !$amount->isPositive()
            || $amount->isGreaterThan(
                BigDecimal::of(
                    self::MAXIMUM_AMOUNT,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount must be greater than zero and within the supported maximum.',
                ],
            ]);
        }

        return $amount;
    }

    private function money(mixed $value): BigDecimal
    {
        return $this->decimal(
            value: $value,
            field: 'amount',
            scale: self::MONEY_SCALE,
        );
    }

    private function positiveExchangeRate(
        mixed $value,
    ): BigDecimal {
        $rate = $this->decimal(
            value: $value,
            field: 'exchange_rate',
            scale: self::EXCHANGE_RATE_SCALE,
        );

        if (!$rate->isPositive()) {
            throw new LogicException(
                'A customer open item must retain a positive exchange rate.',
            );
        }

        return $rate;
    }

    private function decimal(
        mixed $value,
        string $field,
        int $scale,
    ): BigDecimal {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw new LogicException(
                "The {$field} value is not numeric.",
            );
        }

        try {
            return BigDecimal::of((string) $value)
                ->toScale(
                    $scale,
                    RoundingMode::HalfUp,
                );
        } catch (ArithmeticException $exception) {
            throw new LogicException(
                "The {$field} value is invalid.",
                previous: $exception,
            );
        }
    }

    private function requiredString(
        string $value,
        string $field,
        int $maximumLength,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => [
                    'The value is required.',
                ],
            ]);
        }

        if (mb_strlen($value) > $maximumLength) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function dateString(
        DateTimeInterface $value,
        string $timezone,
    ): string {
        return CarbonImmutable::instance($value)
            ->setTimezone($timezone)
            ->toDateString();
    }
}
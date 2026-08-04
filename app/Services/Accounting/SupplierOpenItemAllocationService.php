<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\SupplierOpenItem;
use App\Models\SupplierOpenItemAllocation;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsPayableRegistry;
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

final class SupplierOpenItemAllocationService
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsPayableRegistry $registry,
    ) {
    }

    public function apply(
        SupplierOpenItem $payableOpenItem,
        SupplierOpenItem $creditOpenItem,
        AccountingPeriod $accountingPeriod,
        string $allocationType,
        string $postingKey,
        DateTimeInterface $allocationDate,
        DateTimeInterface $postingDate,
        string $amount,
        ?Model $source,
        User $actor,
    ): SupplierOpenItemAllocation {
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
                    'The supplier allocation type is invalid.',
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
            SupplierOpenItemAllocation::query()
                ->where('posting_key', $postingKey)
                ->lockForUpdate()
                ->first();

        if (
            $existingAllocation
            instanceof SupplierOpenItemAllocation
        ) {
            return $this->validateExistingAllocation(
                allocation: $existingAllocation,
                payableOpenItemId:
                    (int) $payableOpenItem->getKey(),
                creditOpenItemId:
                    (int) $creditOpenItem->getKey(),
                allocationType: $allocationType,
                amount: $allocationAmount,
                source: $source,
            );
        }

        [
            $lockedPayable,
            $lockedCredit,
        ] = $this->lockOpenItems(
            payableOpenItemId:
                (int) $payableOpenItem->getKey(),
            creditOpenItemId:
                (int) $creditOpenItem->getKey(),
        );

        $this->validateOpenItemPair(
            payableOpenItem: $lockedPayable,
            creditOpenItem: $lockedCredit,
            amount: $allocationAmount,
            tenantId: $tenantId,
        );

        $branch = Branch::query()
            ->whereKey($lockedPayable->branch_id)
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

        $payableBaseAmount =
            $this->baseAmountForAllocation(
                openItem: $lockedPayable,
                amount: $allocationAmount,
            );

        $creditBaseAmount =
            $this->baseAmountForAllocation(
                openItem: $lockedCredit,
                amount: $allocationAmount,
            );

        $exchangeDifference = $payableBaseAmount
            ->minus($creditBaseAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $this->applyAmountToOpenItem(
            openItem: $lockedPayable,
            amount: $allocationAmount,
            baseAmount: $payableBaseAmount,
        );

        $this->applyAmountToOpenItem(
            openItem: $lockedCredit,
            amount: $allocationAmount,
            baseAmount: $creditBaseAmount,
        );

        $allocation =
            SupplierOpenItemAllocation::query()
                ->create([
                    'branch_id' =>
                        $lockedPayable->branch_id,

                    'supplier_id' =>
                        $lockedPayable->supplier_id,

                    'accounting_period_id' =>
                        $accountingPeriod->getKey(),

                    'payable_open_item_id' =>
                        $lockedPayable->getKey(),

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
                        $lockedPayable->currency_code,

                    'amount' =>
                        $allocationAmount->__toString(),

                    'payable_base_amount' =>
                        $payableBaseAmount->__toString(),

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
            'payableOpenItem',
            'creditOpenItem',
            'createdBy',
        ]);
    }

    public function reverse(
        SupplierOpenItemAllocation $allocation,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): SupplierOpenItemAllocation {
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
            SupplierOpenItemAllocation::query()
                ->whereKey($allocation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

        if ($lockedAllocation->isReversed()) {
            return $lockedAllocation->load([
                'accountingPeriod',
                'reversalAccountingPeriod',
                'payableOpenItem',
                'creditOpenItem',
                'createdBy',
                'reversedBy',
            ]);
        }

        if (!$lockedAllocation->isApplied()) {
            throw new LogicException(
                'Only an applied supplier open-item allocation can be reversed.',
            );
        }

        [
            $lockedPayable,
            $lockedCredit,
        ] = $this->lockOpenItems(
            payableOpenItemId:
                (int) $lockedAllocation
                    ->payable_open_item_id,
            creditOpenItemId:
                (int) $lockedAllocation
                    ->credit_open_item_id,
        );

        $this->ensureAllocationContextMatches(
            allocation: $lockedAllocation,
            payableOpenItem: $lockedPayable,
            creditOpenItem: $lockedCredit,
            tenantId: $tenantId,
        );

        $branch = Branch::query()
            ->whereKey($lockedPayable->branch_id)
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: false,
        );

        $this->restoreAmountToOpenItem(
            openItem: $lockedPayable,
            amount: $this->money(
                $lockedAllocation->amount,
            ),
            baseAmount: $this->money(
                $lockedAllocation
                    ->payable_base_amount,
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
            'payableOpenItem',
            'creditOpenItem',
            'createdBy',
            'reversedBy',
        ]);
    }

    /**
     * @return array{0: SupplierOpenItem, 1: SupplierOpenItem}
     */
    private function lockOpenItems(
        int $payableOpenItemId,
        int $creditOpenItemId,
    ): array {
        if (
            $payableOpenItemId < 1
            || $creditOpenItemId < 1
            || $payableOpenItemId === $creditOpenItemId
        ) {
            throw new LogicException(
                'A supplier allocation requires two different open items.',
            );
        }

        /** @var Collection<int, SupplierOpenItem> $items */
        $items = SupplierOpenItem::query()
            ->whereIn(
                'id',
                [
                    $payableOpenItemId,
                    $creditOpenItemId,
                ],
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(
                static fn (
                    SupplierOpenItem $item,
                ): int => (int) $item->getKey(),
            );

        $payable = $items->get(
            $payableOpenItemId,
        );

        $credit = $items->get(
            $creditOpenItemId,
        );

        if (
            !$payable instanceof SupplierOpenItem
            || !$credit instanceof SupplierOpenItem
        ) {
            throw new LogicException(
                'One or more supplier open items are unavailable.',
            );
        }

        return [
            $payable,
            $credit,
        ];
    }

    private function validateOpenItemPair(
        SupplierOpenItem $payableOpenItem,
        SupplierOpenItem $creditOpenItem,
        BigDecimal $amount,
        int $tenantId,
    ): void {
        if (
            (int) $payableOpenItem->tenant_id
                !== $tenantId
            || (int) $creditOpenItem->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The supplier open items do not belong to the active tenant.',
            );
        }

        if (!$payableOpenItem->isInvoice()) {
            throw ValidationException::withMessages([
                'payable_open_item_id' => [
                    'The selected payable item must be a Supplier Invoice.',
                ],
            ]);
        }

        if (!$creditOpenItem->isCredit()) {
            throw ValidationException::withMessages([
                'credit_open_item_id' => [
                    'The selected credit item must be a supplier credit or unallocated payment.',
                ],
            ]);
        }

        if (
            $payableOpenItem->isReversed()
            || $creditOpenItem->isReversed()
        ) {
            throw ValidationException::withMessages([
                'allocation' => [
                    'A reversed supplier open item cannot be allocated.',
                ],
            ]);
        }

        if (
            (int) $payableOpenItem->branch_id
                !== (int) $creditOpenItem->branch_id
            || (int) $payableOpenItem->supplier_id
                !== (int) $creditOpenItem->supplier_id
            || strtoupper(
                (string) $payableOpenItem->currency_code,
            ) !== strtoupper(
                (string) $creditOpenItem->currency_code,
            )
        ) {
            throw ValidationException::withMessages([
                'allocation' => [
                    'Supplier open-item allocations must use the same branch, supplier, and document currency.',
                ],
            ]);
        }

        if (
            $this->money(
                $payableOpenItem->outstanding_amount,
            )->isLessThan($amount)
        ) {
            throw ValidationException::withMessages([
                'amount' => [
                    'The allocation exceeds the payable item outstanding amount.',
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
        SupplierOpenItemAllocation $allocation,
        SupplierOpenItem $payableOpenItem,
        SupplierOpenItem $creditOpenItem,
        int $tenantId,
    ): void {
        if (
            (int) $allocation->tenant_id !== $tenantId
            || (int) $payableOpenItem->tenant_id
                !== $tenantId
            || (int) $creditOpenItem->tenant_id
                !== $tenantId
            || (int) $allocation->branch_id
                !== (int) $payableOpenItem->branch_id
            || (int) $allocation->branch_id
                !== (int) $creditOpenItem->branch_id
            || (int) $allocation->supplier_id
                !== (int) $payableOpenItem->supplier_id
            || (int) $allocation->supplier_id
                !== (int) $creditOpenItem->supplier_id
            || (int) $allocation->payable_open_item_id
                !== (int) $payableOpenItem->getKey()
            || (int) $allocation->credit_open_item_id
                !== (int) $creditOpenItem->getKey()
        ) {
            throw new LogicException(
                'The supplier open-item allocation context is inconsistent.',
            );
        }
    }

    private function applyAmountToOpenItem(
        SupplierOpenItem $openItem,
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
                'The supplier open item does not retain enough outstanding value for the allocation.',
            );
        }

        $openItem->allocated_amount = $this->money(
            $openItem->allocated_amount,
        )
            ->plus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();

        $openItem->outstanding_amount = $outstanding
            ->minus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();

        $openItem->base_allocated_amount =
            $this->money(
                $openItem->base_allocated_amount,
            )
                ->plus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

        $openItem->base_outstanding_amount =
            $baseOutstanding
                ->minus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

        $this->synchronizeOpenItemStatus(
            $openItem,
        );

        $openItem->save();
    }

    private function restoreAmountToOpenItem(
        SupplierOpenItem $openItem,
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
                'The supplier open item allocated value is lower than the allocation being reversed.',
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
                'Reversing the allocation would exceed the supplier open item original value.',
            );
        }

        $openItem->allocated_amount = $allocated
            ->minus($amount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();

        $openItem->outstanding_amount =
            $newOutstanding
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

        $openItem->base_allocated_amount =
            $baseAllocated
                ->minus($baseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

        $openItem->base_outstanding_amount =
            $newBaseOutstanding
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

        $this->synchronizeOpenItemStatus(
            $openItem,
        );

        $openItem->save();
    }

    private function synchronizeOpenItemStatus(
        SupplierOpenItem $openItem,
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
        SupplierOpenItem $openItem,
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
                RoundingMode::HALF_UP,
            );

        return $baseAmount->isGreaterThan(
            $baseOutstanding,
        )
            ? $baseOutstanding
            : $baseAmount;
    }

    private function validateExistingAllocation(
        SupplierOpenItemAllocation $allocation,
        int $payableOpenItemId,
        int $creditOpenItemId,
        string $allocationType,
        BigDecimal $amount,
        ?Model $source,
    ): SupplierOpenItemAllocation {
        if (
            !$allocation->isApplied()
            || (int) $allocation->payable_open_item_id
                !== $payableOpenItemId
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
                'The supplier allocation posting key already belongs to a different allocation.',
            );
        }

        return $allocation->load([
            'accountingPeriod',
            'payableOpenItem',
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
                'The supplier allocation source has not been persisted.',
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
                'The supplier allocation source belongs to a different tenant.',
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
            'Supplier open-item allocation must run inside the accounting database transaction.',
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
                'A supplier open item must retain a positive exchange rate.',
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
                    RoundingMode::HALF_UP,
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
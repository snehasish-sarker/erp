<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductWarehouseSetting;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class InventoryAdjustmentService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    /**
     * @param array{
     *     warehouse_id: int,
     *     adjustment_date: string,
     *     reason: string,
     *     notes?: string|null,
     *     lines: list<array{
     *         product_id: int,
     *         adjustment_type: string,
     *         quantity: numeric-string|int|float
     *     }>
     * } $data
     */
    public function create(
        array $data,
        User $actor,
    ): InventoryAdjustment {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($data, $actor): InventoryAdjustment {
                $warehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $data['warehouse_id'],
                );

                $adjustment = InventoryAdjustment::query()->create([
                    'branch_id' => $warehouse->branch_id,
                    'warehouse_id' => $warehouse->getKey(),
                    'adjustment_date' => $data['adjustment_date'],
                    'status' => 'draft',
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                    'created_by_user_id' => $actor->getKey(),
                ]);

                foreach ($data['lines'] as $index => $inputLine) {
                    $product = Product::query()
                        ->with('baseUnit:id,name,code,symbol')
                        ->whereKey((int) $inputLine['product_id'])
                        ->where('product_type', 'stock')
                        ->where('status', 'active')
                        ->firstOrFail();

                    $this->ensureProductEnabledForWarehouse(
                        product: $product,
                        warehouse: $warehouse,
                    );

                    if ($product->baseUnit === null) {
                        throw new LogicException(
                            "Stock product {$product->name} has no base unit.",
                        );
                    }

                    $quantity = $this->decimal(
                        (string) $inputLine['quantity'],
                    );

                    $type = (string) $inputLine['adjustment_type'];

                    if ($type === 'decrease') {
                        $balance = InventoryBalance::query()
                            ->where('warehouse_id', $warehouse->getKey())
                            ->where('product_id', $product->getKey())
                            ->first();

                        if (!$balance instanceof InventoryBalance) {
                            throw ValidationException::withMessages([
                                "lines.{$index}.product_id" => [
                                    "No inventory exists for {$product->name} in {$warehouse->name}.",
                                ],
                            ]);
                        }

                        $available = BigDecimal::of(
                            $balance->availableQuantity(),
                        );

                        if ($available->isLessThan($quantity)) {
                            throw ValidationException::withMessages([
                                "lines.{$index}.quantity" => [
                                    "Only {$available->__toString()} is available for {$product->name} in {$warehouse->name} after reservations.",
                                ],
                            ]);
                        }
                    }

                    InventoryAdjustmentLine::query()->create([
                        'inventory_adjustment_id' => $adjustment->getKey(),
                        'product_id' => $product->getKey(),
                        'unit_id' => $product->baseUnit->getKey(),
                        'line_number' => $index + 1,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_name' => $product->baseUnit->name,
                        'unit_code' => $product->baseUnit->code,
                        'adjustment_type' => $type,
                        'quantity' => $quantity->__toString(),
                        'unit_cost' => '0.000000',
                        'adjustment_value' => '0.000000',
                        'quantity_before' => '0.000000',
                        'quantity_after' => '0.000000',
                    ]);
                }

                return $adjustment->load([
                    'branch:id,name,code',
                    'warehouse:id,branch_id,name,code',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function post(
        InventoryAdjustment $adjustment,
        User $actor,
    ): InventoryAdjustment {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($adjustment, $actor): InventoryAdjustment {
                $locked = InventoryAdjustment::query()
                    ->whereKey($adjustment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeAdjustment($locked, $actor);

                if ($locked->isPosted()) {
                    return $locked->refresh();
                }

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Inventory Adjustment can be posted.',
                        ],
                    ]);
                }

                $warehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $locked->warehouse_id,
                );

                $lines = $locked->lines()
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'At least one adjustment line is required.',
                        ],
                    ]);
                }

                $allocation = $this->documentNumberService->allocate(
                    documentType: 'stock_adjustment',
                    branchId: (int) $warehouse->branch_id,
                    idempotencyKey: sprintf(
                        'inventory-adjustment:%d:%d',
                        (int) $locked->tenant_id,
                        (int) $locked->getKey(),
                    ),
                    allocatableType: InventoryAdjustment::class,
                    allocatableId: (int) $locked->getKey(),
                    allocatedAt: $locked->adjustment_date,
                );

                $postedAt = CarbonImmutable::now(
                    $this->tenantContext->tenant()->timezone,
                );

                $totalQuantityIn = BigDecimal::zero();
                $totalQuantityOut = BigDecimal::zero();
                $totalValueIn = BigDecimal::zero();
                $totalValueOut = BigDecimal::zero();

                foreach ($lines as $line) {
                    $result = $this->postLine(
                        adjustment: $locked,
                        line: $line,
                        warehouse: $warehouse,
                        actor: $actor,
                        documentNumber: $allocation->number,
                        postedAt: $postedAt,
                    );

                    $totalQuantityIn = $totalQuantityIn->plus(
                        $result['quantity_in'],
                    );
                    $totalQuantityOut = $totalQuantityOut->plus(
                        $result['quantity_out'],
                    );
                    $totalValueIn = $totalValueIn->plus(
                        $result['value_in'],
                    );
                    $totalValueOut = $totalValueOut->plus(
                        $result['value_out'],
                    );
                }

                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->adjustment_number = $allocation->number;
                $locked->status = 'posted';
                $locked->total_quantity_in = $this->scaled($totalQuantityIn);
                $locked->total_quantity_out = $this->scaled($totalQuantityOut);
                $locked->total_value_in = $this->scaled($totalValueIn);
                $locked->total_value_out = $this->scaled($totalValueOut);
                $locked->posted_by_user_id = $actor->getKey();
                $locked->posted_at = $postedAt;
                $locked->save();

                return $locked->refresh()->load([
                    'branch:id,name,code',
                    'warehouse:id,branch_id,name,code',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function cancel(
        InventoryAdjustment $adjustment,
        User $actor,
        string $reason,
    ): InventoryAdjustment {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($adjustment, $actor, $reason): InventoryAdjustment {
                $locked = InventoryAdjustment::query()
                    ->whereKey($adjustment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeAdjustment($locked, $actor);

                if ($locked->isCancelled()) {
                    return $locked->refresh();
                }

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Inventory Adjustment can be cancelled.',
                        ],
                    ]);
                }

                $locked->status = 'cancelled';
                $locked->cancelled_by_user_id = $actor->getKey();
                $locked->cancelled_at = CarbonImmutable::now(
                    $this->tenantContext->tenant()->timezone,
                );
                $locked->cancellation_reason = trim($reason);
                $locked->save();

                return $locked->refresh();
            },
            attempts: 5,
        );
    }

    public function authorizeAdjustment(
        InventoryAdjustment $adjustment,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        if ((int) $adjustment->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Inventory Adjustment does not belong to the active tenant.',
            );
        }

        $warehouse = Warehouse::query()
            ->with('branch')
            ->whereKey($adjustment->warehouse_id)
            ->firstOrFail();

        if ($warehouse->branch === null) {
            throw new LogicException(
                'The Inventory Adjustment warehouse has no branch.',
            );
        }

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $warehouse->branch,
            requireActive: false,
        );
    }

    /**
     * @return array{
     *     quantity_in: BigDecimal,
     *     quantity_out: BigDecimal,
     *     value_in: BigDecimal,
     *     value_out: BigDecimal
     * }
     */
    private function postLine(
        InventoryAdjustment $adjustment,
        InventoryAdjustmentLine $line,
        Warehouse $warehouse,
        User $actor,
        string $documentNumber,
        CarbonImmutable $postedAt,
    ): array {
        $product = Product::query()
            ->with('baseUnit:id,name,code,symbol')
            ->whereKey($line->product_id)
            ->where('product_type', 'stock')
            ->where('status', 'active')
            ->firstOrFail();

        $this->ensureProductEnabledForWarehouse(
            product: $product,
            warehouse: $warehouse,
        );

        if ($product->baseUnit === null) {
            throw new LogicException(
                "Stock product {$product->name} has no base unit.",
            );
        }

        if ((int) $product->baseUnit->getKey() !== (int) $line->unit_id) {
            throw new LogicException(
                "The base unit for {$product->name} changed after the draft was created.",
            );
        }

        $balance = InventoryBalance::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->getKey(),
                'product_id' => $product->getKey(),
            ],
            [
                'branch_id' => $warehouse->branch_id,
                'unit_id' => $product->baseUnit->getKey(),
                'quantity_on_hand' => '0.000000',
                'quantity_reserved' => '0.000000',
                'inventory_value' => '0.000000',
                'average_unit_cost' => '0.000000',
                'version' => 0,
            ],
        );

        $balance = InventoryBalance::query()
            ->whereKey($balance->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $balance->unit_id !== (int) $line->unit_id) {
            throw new LogicException(
                "The Inventory Balance unit for {$product->name} does not match the adjustment unit.",
            );
        }

        $quantity = $this->decimal((string) $line->quantity);
        $quantityBefore = $this->decimal((string) $balance->quantity_on_hand);
        $reserved = $this->decimal((string) $balance->quantity_reserved);
        $valueBefore = $this->decimal((string) $balance->inventory_value);
        $averageCost = $this->decimal((string) $balance->average_unit_cost);

        $quantityIn = BigDecimal::zero();
        $quantityOut = BigDecimal::zero();
        $valueIn = BigDecimal::zero();
        $valueOut = BigDecimal::zero();

        if ($line->adjustment_type === 'decrease') {
            $available = $quantityBefore->minus($reserved);

            if ($available->isLessThan($quantity)) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Only {$available->__toString()} of {$product->name} is available in {$warehouse->name} after reservations.",
                    ],
                ]);
            }

            $unitCost = $averageCost;
            $adjustmentValue = $quantity->multipliedBy($unitCost);
            $quantityAfter = $quantityBefore->minus($quantity);
            $valueAfter = $valueBefore->minus($adjustmentValue);

            if ($quantityAfter->isZero()) {
                $valueAfter = BigDecimal::zero();
                $newAverageCost = BigDecimal::zero();
            } else {
                $newAverageCost = $valueAfter->dividedBy(
                    $quantityAfter,
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );
            }

            $movementType = 'adjustment_out';
            $quantityOut = $quantity;
            $valueOut = $adjustmentValue;
        } elseif ($line->adjustment_type === 'increase') {
            $fallbackCost = $this->decimal((string) ($product->cost_price ?? '0'));
            $unitCost = $quantityBefore->isGreaterThan(BigDecimal::zero())
                ? $averageCost
                : $fallbackCost;
            $adjustmentValue = $quantity->multipliedBy($unitCost);
            $quantityAfter = $quantityBefore->plus($quantity);
            $valueAfter = $valueBefore->plus($adjustmentValue);
            $newAverageCost = $valueAfter->dividedBy(
                $quantityAfter,
                self::SCALE,
                RoundingMode::HALF_UP,
            );

            $movementType = 'adjustment_in';
            $quantityIn = $quantity;
            $valueIn = $adjustmentValue;
        } else {
            throw new LogicException(
                "Unsupported inventory adjustment type [{$line->adjustment_type}].",
            );
        }

        $balance->quantity_on_hand = $this->scaled($quantityAfter);
        $balance->inventory_value = $this->scaled($valueAfter);
        $balance->average_unit_cost = $this->scaled($newAverageCost);
        $balance->version = (int) $balance->version + 1;
        $balance->save();

        $ledger = StockLedgerEntry::query()->create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->getKey(),
            'product_id' => $product->getKey(),
            'unit_id' => $line->unit_id,
            'movement_type' => $movementType,
            'posting_key' => sprintf(
                'inventory-adjustment:%d:%d:%d:%s',
                (int) $adjustment->tenant_id,
                (int) $adjustment->getKey(),
                (int) $line->getKey(),
                $movementType,
            ),
            'source_type' => InventoryAdjustment::class,
            'source_id' => $adjustment->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $documentNumber,
            'occurred_at' => $postedAt,
            'quantity_in' => $this->scaled($quantityIn),
            'quantity_out' => $this->scaled($quantityOut),
            'unit_cost' => $this->scaled($unitCost),
            'total_cost' => $this->scaled($adjustmentValue),
            'balance_quantity' => $this->scaled($quantityAfter),
            'balance_value' => $this->scaled($valueAfter),
            'created_by_user_id' => $actor->getKey(),
        ]);

        $line->unit_cost = $this->scaled($unitCost);
        $line->adjustment_value = $this->scaled($adjustmentValue);
        $line->quantity_before = $this->scaled($quantityBefore);
        $line->quantity_after = $this->scaled($quantityAfter);
        $line->stock_ledger_entry_id = $ledger->getKey();
        $line->save();

        return [
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'value_in' => $valueIn,
            'value_out' => $valueOut,
        ];
    }

    private function resolveWarehouse(
        User $user,
        int $warehouseId,
    ): Warehouse {
        $warehouse = Warehouse::query()
            ->with('branch')
            ->whereKey($warehouseId)
            ->where('status', 'active')
            ->firstOrFail();

        if ($warehouse->branch === null) {
            throw new LogicException(
                'The selected warehouse has no branch.',
            );
        }

        $this->branchAccessService->authorizeBranch(
            user: $user,
            branch: $warehouse->branch,
            requireActive: true,
        );

        return $warehouse;
    }

    private function ensureProductEnabledForWarehouse(
        Product $product,
        Warehouse $warehouse,
    ): void {
        $enabled = ProductWarehouseSetting::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->where('status', 'active')
            ->exists();

        if (!$enabled) {
            throw ValidationException::withMessages([
                'lines' => [
                    "{$product->name} is not enabled for warehouse {$warehouse->name}.",
                ],
            ]);
        }
    }

    private function activeTenantId(): int
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw new LogicException(
                'Tenant context has not been initialized.',
            );
        }

        return $tenantId;
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The authenticated user does not belong to the active tenant.',
            );
        }
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(
            self::SCALE,
            RoundingMode::HALF_UP,
        );
    }

    private function scaled(BigDecimal $value): string
    {
        return $value->toScale(
            self::SCALE,
            RoundingMode::HALF_UP,
        )->__toString();
    }
}

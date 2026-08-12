<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
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

final class InventoryStockCountService
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
     *     count_date: string,
     *     notes?: string|null,
     *     lines: list<array{
     *         product_id: int,
     *         counted_quantity: numeric-string|int|float
     *     }>
     * } $data
     */
    public function create(
        array $data,
        User $actor,
    ): InventoryStockCount {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($data, $actor): InventoryStockCount {
                $warehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $data['warehouse_id'],
                );

                $stockCount = InventoryStockCount::query()->create([
                    'branch_id' => $warehouse->branch_id,
                    'warehouse_id' => $warehouse->getKey(),
                    'count_date' => $data['count_date'],
                    'status' => 'draft',
                    'notes' => $data['notes'] ?? null,
                    'created_by_user_id' => $actor->getKey(),
                ]);

                $positiveVariance = BigDecimal::zero();
                $negativeVariance = BigDecimal::zero();
                $valueGain = BigDecimal::zero();
                $valueLoss = BigDecimal::zero();
                $varianceLineCount = 0;

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

                    $balance = InventoryBalance::query()
                        ->where('warehouse_id', $warehouse->getKey())
                        ->where('product_id', $product->getKey())
                        ->first();

                    $systemQuantity = $this->decimal(
                        (string) ($balance?->quantity_on_hand ?? '0'),
                    );

                    $reservedQuantity = $this->decimal(
                        (string) ($balance?->quantity_reserved ?? '0'),
                    );

                    $countedQuantity = $this->decimal(
                        (string) $inputLine['counted_quantity'],
                    );

                    $varianceQuantity = $countedQuantity
                        ->minus($systemQuantity)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);

                    $unitCost = $this->resolveUnitCost(
                        balance: $balance,
                        product: $product,
                    );

                    $varianceValue = $varianceQuantity
                        ->multipliedBy($unitCost)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);

                    if (!$varianceQuantity->isZero()) {
                        ++$varianceLineCount;
                    }

                    if ($varianceQuantity->isPositive()) {
                        $positiveVariance = $positiveVariance
                            ->plus($varianceQuantity);
                        $valueGain = $valueGain->plus($varianceValue);
                    } elseif ($varianceQuantity->isNegative()) {
                        $negativeVariance = $negativeVariance
                            ->plus($varianceQuantity->abs());
                        $valueLoss = $valueLoss
                            ->plus($varianceValue->abs());
                    }

                    $latestLedgerEntryId = StockLedgerEntry::query()
                        ->where('warehouse_id', $warehouse->getKey())
                        ->where('product_id', $product->getKey())
                        ->max('id');

                    InventoryStockCountLine::query()->create([
                        'inventory_stock_count_id' => $stockCount->getKey(),
                        'product_id' => $product->getKey(),
                        'unit_id' => $product->baseUnit->getKey(),
                        'line_number' => $index + 1,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_name' => $product->baseUnit->name,
                        'unit_code' => $product->baseUnit->code,
                        'system_quantity' => $this->scaled($systemQuantity),
                        'reserved_quantity' => $this->scaled($reservedQuantity),
                        'counted_quantity' => $this->scaled($countedQuantity),
                        'variance_quantity' => $this->scaled($varianceQuantity),
                        'snapshot_ledger_entry_id' => $latestLedgerEntryId === null
                            ? null
                            : (int) $latestLedgerEntryId,
                        'unit_cost' => $this->scaled($unitCost),
                        'variance_value' => $this->scaled($varianceValue),
                    ]);
                }

                $stockCount->total_lines = count($data['lines']);
                $stockCount->variance_line_count = $varianceLineCount;
                $stockCount->total_positive_variance = $this->scaled(
                    $positiveVariance,
                );
                $stockCount->total_negative_variance = $this->scaled(
                    $negativeVariance,
                );
                $stockCount->total_value_gain = $this->scaled($valueGain);
                $stockCount->total_value_loss = $this->scaled($valueLoss);
                $stockCount->save();

                return $stockCount->load([
                    'branch:id,name,code',
                    'warehouse:id,branch_id,name,code',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function post(
        InventoryStockCount $stockCount,
        User $actor,
    ): InventoryStockCount {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($stockCount, $actor): InventoryStockCount {
                $locked = InventoryStockCount::query()
                    ->whereKey($stockCount->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeStockCount($locked, $actor);

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only draft stock counts can be posted.',
                        ],
                    ]);
                }

                $warehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $locked->warehouse_id,
                );

                $lines = InventoryStockCountLine::query()
                    ->where('inventory_stock_count_id', $locked->getKey())
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'The stock count has no lines to post.',
                        ],
                    ]);
                }

                $allocation = $this->documentNumberService->allocate(
                    documentType: 'stock_count',
                    branchId: (int) $locked->branch_id,
                    idempotencyKey: sprintf(
                        'inventory-stock-count:%d:%d',
                        (int) $locked->tenant_id,
                        (int) $locked->getKey(),
                    ),
                    allocatableType: InventoryStockCount::class,
                    allocatableId: (int) $locked->getKey(),
                    allocatedAt: $locked->count_date,
                );

                $postedAt = CarbonImmutable::now(
                    $this->tenantContext->tenant()->timezone,
                );

                $positiveVariance = BigDecimal::zero();
                $negativeVariance = BigDecimal::zero();
                $valueGain = BigDecimal::zero();
                $valueLoss = BigDecimal::zero();
                $varianceLineCount = 0;

                foreach ($lines as $line) {
                    $result = $this->postLine(
                        stockCount: $locked,
                        line: $line,
                        warehouse: $warehouse,
                        actor: $actor,
                        documentNumber: $allocation->number,
                        postedAt: $postedAt,
                    );

                    if (!$result['variance']->isZero()) {
                        ++$varianceLineCount;
                    }

                    if ($result['variance']->isPositive()) {
                        $positiveVariance = $positiveVariance
                            ->plus($result['variance']);
                        $valueGain = $valueGain
                            ->plus($result['value']);
                    } elseif ($result['variance']->isNegative()) {
                        $negativeVariance = $negativeVariance
                            ->plus($result['variance']->abs());
                        $valueLoss = $valueLoss
                            ->plus($result['value']->abs());
                    }
                }

                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->count_number = $allocation->number;
                $locked->status = 'posted';
                $locked->total_lines = $lines->count();
                $locked->variance_line_count = $varianceLineCount;
                $locked->total_positive_variance = $this->scaled(
                    $positiveVariance,
                );
                $locked->total_negative_variance = $this->scaled(
                    $negativeVariance,
                );
                $locked->total_value_gain = $this->scaled($valueGain);
                $locked->total_value_loss = $this->scaled($valueLoss);
                $locked->posted_by_user_id = $actor->getKey();
                $locked->posted_at = $postedAt;
                $locked->save();

                return $locked->refresh()->load([
                    'branch:id,name,code',
                    'warehouse:id,branch_id,name,code',
                    'createdBy:id,name,email',
                    'postedBy:id,name,email',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function cancel(
        InventoryStockCount $stockCount,
        User $actor,
        string $reason,
    ): InventoryStockCount {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use ($stockCount, $actor, $reason): InventoryStockCount {
                $locked = InventoryStockCount::query()
                    ->whereKey($stockCount->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeStockCount($locked, $actor);

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only draft stock counts can be cancelled.',
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

    public function authorizeStockCount(
        InventoryStockCount $stockCount,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        if ((int) $stockCount->tenant_id !== $tenantId) {
            throw new LogicException(
                'The stock count does not belong to the active tenant.',
            );
        }

        $warehouse = Warehouse::query()
            ->with('branch')
            ->whereKey($stockCount->warehouse_id)
            ->firstOrFail();

        if ($warehouse->branch === null) {
            throw new LogicException(
                'The stock count warehouse has no branch.',
            );
        }

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $warehouse->branch,
            requireActive: false,
        );
    }

    /**
     * @return array{variance: BigDecimal, value: BigDecimal}
     */
    private function postLine(
        InventoryStockCount $stockCount,
        InventoryStockCountLine $line,
        Warehouse $warehouse,
        User $actor,
        string $documentNumber,
        CarbonImmutable $postedAt,
    ): array {
        $product = Product::query()
            ->whereKey($line->product_id)
            ->where('product_type', 'stock')
            ->where('status', 'active')
            ->firstOrFail();

        $this->ensureProductEnabledForWarehouse(
            product: $product,
            warehouse: $warehouse,
        );

        $latestLedgerEntryId = StockLedgerEntry::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('product_id', $line->product_id)
            ->max('id');

        $latestLedgerEntryId = $latestLedgerEntryId === null
            ? null
            : (int) $latestLedgerEntryId;

        if ($latestLedgerEntryId !== $line->snapshot_ledger_entry_id) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Stock moved for {$line->product_name} after this count was captured. Create a fresh stock count before posting.",
                ],
            ]);
        }

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('product_id', $line->product_id)
            ->lockForUpdate()
            ->first();

        $currentQuantity = $this->decimal(
            (string) ($balance?->quantity_on_hand ?? '0'),
        );

        $currentReserved = $this->decimal(
            (string) ($balance?->quantity_reserved ?? '0'),
        );

        if (
            !$currentQuantity->isEqualTo(
                $this->decimal((string) $line->system_quantity),
            )
            || !$currentReserved->isEqualTo(
                $this->decimal((string) $line->reserved_quantity),
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Inventory or reservations changed for {$line->product_name} after this count was captured. Create a fresh stock count before posting.",
                ],
            ]);
        }

        $countedQuantity = $this->decimal(
            (string) $line->counted_quantity,
        );

        if ($countedQuantity->isLessThan($currentReserved)) {
            throw ValidationException::withMessages([
                'lines' => [
                    "The counted quantity for {$line->product_name} is below its reserved quantity. Resolve reservations before posting the stock count.",
                ],
            ]);
        }

        $variance = $countedQuantity
            ->minus($currentQuantity)
            ->toScale(self::SCALE, RoundingMode::HALF_UP);

        $unitCost = $this->resolveUnitCost(
            balance: $balance,
            product: $product,
        );

        $varianceValue = $variance
            ->multipliedBy($unitCost)
            ->toScale(self::SCALE, RoundingMode::HALF_UP);

        $line->variance_quantity = $this->scaled($variance);
        $line->unit_cost = $this->scaled($unitCost);
        $line->variance_value = $this->scaled($varianceValue);

        if ($variance->isZero()) {
            $line->stock_ledger_entry_id = null;
            $line->save();

            return [
                'variance' => $variance,
                'value' => $varianceValue,
            ];
        }

        if (!$balance instanceof InventoryBalance) {
            $balance = InventoryBalance::query()->create([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $line->unit_id,
                'quantity_on_hand' => '0.000000',
                'quantity_reserved' => '0.000000',
                'inventory_value' => '0.000000',
                'average_unit_cost' => '0.000000',
                'version' => 1,
            ]);
        }

        $newValue = $countedQuantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $countedQuantity
                ->multipliedBy($unitCost)
                ->toScale(self::SCALE, RoundingMode::HALF_UP);

        $newAverageCost = $countedQuantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $unitCost;

        $balance->quantity_on_hand = $this->scaled($countedQuantity);
        $balance->inventory_value = $this->scaled($newValue);
        $balance->average_unit_cost = $this->scaled($newAverageCost);
        $balance->version = (int) $balance->version + 1;
        $balance->save();

        $movementType = $variance->isPositive()
            ? 'adjustment_in'
            : 'adjustment_out';

        $quantityIn = $variance->isPositive()
            ? $variance
            : BigDecimal::zero()->toScale(self::SCALE);

        $quantityOut = $variance->isNegative()
            ? $variance->abs()
            : BigDecimal::zero()->toScale(self::SCALE);

        $ledger = StockLedgerEntry::query()->create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->getKey(),
            'product_id' => $product->getKey(),
            'unit_id' => $line->unit_id,
            'movement_type' => $movementType,
            'posting_key' => sprintf(
                'inventory-stock-count:%d:%d:%s',
                (int) $stockCount->getKey(),
                (int) $line->getKey(),
                $movementType,
            ),
            'source_type' => InventoryStockCount::class,
            'source_id' => $stockCount->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $documentNumber,
            'occurred_at' => $postedAt,
            'quantity_in' => $this->scaled($quantityIn),
            'quantity_out' => $this->scaled($quantityOut),
            'unit_cost' => $this->scaled($unitCost),
            'total_cost' => $this->scaled($varianceValue->abs()),
            'balance_quantity' => $this->scaled($countedQuantity),
            'balance_value' => $this->scaled($newValue),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => null,
        ]);

        $line->stock_ledger_entry_id = $ledger->getKey();
        $line->save();

        return [
            'variance' => $variance,
            'value' => $varianceValue,
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

    private function resolveUnitCost(
        ?InventoryBalance $balance,
        Product $product,
    ): BigDecimal {
        if ($balance instanceof InventoryBalance) {
            $quantity = $this->decimal(
                (string) $balance->quantity_on_hand,
            );

            $averageCost = $this->decimal(
                (string) $balance->average_unit_cost,
            );

            if (
                $quantity->isGreaterThan(BigDecimal::zero())
                && $averageCost->isGreaterThanOrEqualTo(
                    BigDecimal::zero(),
                )
            ) {
                return $averageCost;
            }
        }

        return $this->decimal(
            (string) ($product->cost_price ?? '0'),
        );
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

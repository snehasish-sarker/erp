<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferLine;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class InventoryTransferService
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
     *     source_warehouse_id: int,
     *     destination_warehouse_id: int,
     *     transfer_date: string,
     *     notes?: string|null,
     *     lines: list<array{product_id: int, quantity: numeric-string|int|float}>
     * } $data
     */
    public function create(
        array $data,
        User $actor,
    ): InventoryTransfer {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use (
                $data,
                $actor,
                $tenantId,
            ): InventoryTransfer {
                $sourceWarehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $data['source_warehouse_id'],
                );

                $destinationWarehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $data['destination_warehouse_id'],
                );

                if (
                    (int) $sourceWarehouse->getKey()
                    === (int) $destinationWarehouse->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'destination_warehouse_id' => [
                            'The destination warehouse must be different from the source warehouse.',
                        ],
                    ]);
                }

                $transfer = InventoryTransfer::query()->create([
                    'source_branch_id' => $sourceWarehouse->branch_id,
                    'destination_branch_id' => $destinationWarehouse->branch_id,
                    'source_warehouse_id' => $sourceWarehouse->getKey(),
                    'destination_warehouse_id' => $destinationWarehouse->getKey(),
                    'transfer_date' => $data['transfer_date'],
                    'status' => 'draft',
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
                        warehouse: $sourceWarehouse,
                        field: 'lines',
                    );

                    $this->ensureProductEnabledForWarehouse(
                        product: $product,
                        warehouse: $destinationWarehouse,
                        field: 'lines',
                    );

                    $quantity = BigDecimal::of(
                        (string) $inputLine['quantity'],
                    )->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );

                    $sourceBalance = InventoryBalance::query()
                        ->where('warehouse_id', $sourceWarehouse->getKey())
                        ->where('product_id', $product->getKey())
                        ->first();

                    if (!$sourceBalance instanceof InventoryBalance) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.product_id" => [
                                "No source inventory exists for {$product->name} in {$sourceWarehouse->name}.",
                            ],
                        ]);
                    }

                    $available = BigDecimal::of(
                        $sourceBalance->availableQuantity(),
                    );

                    if ($available->isLessThan($quantity)) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.quantity" => [
                                "Only {$available->__toString()} is available for {$product->name} in {$sourceWarehouse->name}.",
                            ],
                        ]);
                    }

                    if ($product->baseUnit === null) {
                        throw new LogicException(
                            "Stock product {$product->name} has no base unit.",
                        );
                    }

                    InventoryTransferLine::query()->create([
                        'inventory_transfer_id' => $transfer->getKey(),
                        'product_id' => $product->getKey(),
                        'unit_id' => $product->baseUnit->getKey(),
                        'line_number' => $index + 1,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_name' => $product->baseUnit->name,
                        'unit_code' => $product->baseUnit->code,
                        'quantity' => $quantity->__toString(),
                        'unit_cost' => '0.000000',
                        'transfer_value' => '0.000000',
                    ]);
                }

                return $transfer->load([
                    'sourceWarehouse:id,branch_id,name,code',
                    'destinationWarehouse:id,branch_id,name,code',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function post(
        InventoryTransfer $transfer,
        User $actor,
    ): InventoryTransfer {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use (
                $transfer,
                $actor,
            ): InventoryTransfer {
                $locked = InventoryTransfer::query()
                    ->whereKey($transfer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeTransfer($locked, $actor);

                if ($locked->isPosted()) {
                    return $locked->refresh();
                }

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Inventory Transfer can be posted.',
                        ],
                    ]);
                }

                $sourceWarehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $locked->source_warehouse_id,
                );

                $destinationWarehouse = $this->resolveWarehouse(
                    user: $actor,
                    warehouseId: (int) $locked->destination_warehouse_id,
                );

                $lines = $locked->lines()
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'At least one transfer line is required.',
                        ],
                    ]);
                }

                $allocation = $this->documentNumberService->allocate(
                    documentType: 'stock_transfer',
                    branchId: (int) $sourceWarehouse->branch_id,
                    idempotencyKey: sprintf(
                        'inventory-transfer:%d:%d',
                        (int) $locked->tenant_id,
                        (int) $locked->getKey(),
                    ),
                    allocatableType: InventoryTransfer::class,
                    allocatableId: (int) $locked->getKey(),
                    allocatedAt: $locked->transfer_date,
                );

                $postedAt = CarbonImmutable::now(
                    $this->tenantContext->tenant()->timezone,
                );

                foreach ($lines as $line) {
                    $this->postLine(
                        transfer: $locked,
                        line: $line,
                        sourceWarehouse: $sourceWarehouse,
                        destinationWarehouse: $destinationWarehouse,
                        actor: $actor,
                        documentNumber: $allocation->number,
                        postedAt: $postedAt,
                    );
                }

                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->transfer_number = $allocation->number;
                $locked->status = 'posted';
                $locked->posted_by_user_id = $actor->getKey();
                $locked->posted_at = $postedAt;
                $locked->save();

                return $locked->refresh()->load([
                    'sourceWarehouse:id,branch_id,name,code',
                    'destinationWarehouse:id,branch_id,name,code',
                    'lines',
                ]);
            },
            attempts: 5,
        );
    }

    public function cancel(
        InventoryTransfer $transfer,
        User $actor,
        string $reason,
    ): InventoryTransfer {
        $tenantId = $this->activeTenantId();
        $this->ensureActorBelongsToTenant($actor, $tenantId);

        return DB::transaction(
            function () use (
                $transfer,
                $actor,
                $reason,
            ): InventoryTransfer {
                $locked = InventoryTransfer::query()
                    ->whereKey($transfer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeTransfer($locked, $actor);

                if (!$locked->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Inventory Transfer can be cancelled.',
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

    private function postLine(
        InventoryTransfer $transfer,
        InventoryTransferLine $line,
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        User $actor,
        string $documentNumber,
        CarbonImmutable $postedAt,
    ): void {
        $product = Product::query()
            ->whereKey($line->product_id)
            ->where('product_type', 'stock')
            ->where('status', 'active')
            ->firstOrFail();

        $this->ensureProductEnabledForWarehouse(
            product: $product,
            warehouse: $sourceWarehouse,
            field: 'lines',
        );

        $this->ensureProductEnabledForWarehouse(
            product: $product,
            warehouse: $destinationWarehouse,
            field: 'lines',
        );

        [$sourceBalance, $destinationBalance] = $this->lockBalancePair(
            sourceWarehouse: $sourceWarehouse,
            destinationWarehouse: $destinationWarehouse,
            product: $product,
            unitId: (int) $line->unit_id,
        );

        $quantity = BigDecimal::of((string) $line->quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $sourceQuantity = BigDecimal::of(
            (string) $sourceBalance->quantity_on_hand,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $sourceReserved = BigDecimal::of(
            (string) $sourceBalance->quantity_reserved,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $sourceAvailable = $sourceQuantity->minus($sourceReserved);

        if ($sourceAvailable->isLessThan($quantity)) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Insufficient available stock for {$line->product_name}. Available: {$sourceAvailable->__toString()}.",
                ],
            ]);
        }

        $sourceValue = BigDecimal::of(
            (string) $sourceBalance->inventory_value,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $sourceAverageCost = BigDecimal::of(
            (string) $sourceBalance->average_unit_cost,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $transferValue = $sourceQuantity->isEqualTo($quantity)
            ? $sourceValue
            : $sourceAverageCost
                ->multipliedBy($quantity)
                ->toScale(self::SCALE, RoundingMode::HalfUp);

        if ($transferValue->isGreaterThan($sourceValue)) {
            $transferValue = $sourceValue;
        }

        $newSourceQuantity = $sourceQuantity
            ->minus($quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newSourceValue = $sourceValue
            ->minus($transferValue)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        if (
            $newSourceQuantity->isLessThan($sourceReserved)
            || $newSourceValue->isLessThan(BigDecimal::zero())
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "The transfer would create an invalid source balance for {$line->product_name}.",
                ],
            ]);
        }

        $newSourceAverageCost = $newSourceQuantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $newSourceValue->dividedBy(
                $newSourceQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $destinationQuantity = BigDecimal::of(
            (string) $destinationBalance->quantity_on_hand,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $destinationValue = BigDecimal::of(
            (string) $destinationBalance->inventory_value,
        )->toScale(self::SCALE, RoundingMode::HalfUp);

        $newDestinationQuantity = $destinationQuantity
            ->plus($quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newDestinationValue = $destinationValue
            ->plus($transferValue)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newDestinationAverageCost = $newDestinationQuantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $newDestinationValue->dividedBy(
                $newDestinationQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $sourceBalance->quantity_on_hand = $newSourceQuantity->__toString();
        $sourceBalance->inventory_value = $newSourceValue->__toString();
        $sourceBalance->average_unit_cost = $newSourceAverageCost->__toString();
        $sourceBalance->version = (int) $sourceBalance->version + 1;
        $sourceBalance->save();

        $destinationBalance->quantity_on_hand = $newDestinationQuantity->__toString();
        $destinationBalance->inventory_value = $newDestinationValue->__toString();
        $destinationBalance->average_unit_cost = $newDestinationAverageCost->__toString();
        $destinationBalance->version = (int) $destinationBalance->version + 1;
        $destinationBalance->save();

        $line->unit_cost = $sourceAverageCost->__toString();
        $line->transfer_value = $transferValue->__toString();
        $line->save();

        StockLedgerEntry::query()->create([
            'branch_id' => $sourceWarehouse->branch_id,
            'warehouse_id' => $sourceWarehouse->getKey(),
            'product_id' => $line->product_id,
            'unit_id' => $line->unit_id,
            'movement_type' => 'transfer_out',
            'posting_key' => sprintf(
                'inventory-transfer:%d:line:%d:out',
                (int) $transfer->getKey(),
                (int) $line->getKey(),
            ),
            'source_type' => InventoryTransfer::class,
            'source_id' => $transfer->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $documentNumber,
            'occurred_at' => $postedAt,
            'quantity_in' => '0.000000',
            'quantity_out' => $quantity->__toString(),
            'unit_cost' => $sourceAverageCost->__toString(),
            'total_cost' => $transferValue->__toString(),
            'balance_quantity' => $newSourceQuantity->__toString(),
            'balance_value' => $newSourceValue->__toString(),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => null,
        ]);

        StockLedgerEntry::query()->create([
            'branch_id' => $destinationWarehouse->branch_id,
            'warehouse_id' => $destinationWarehouse->getKey(),
            'product_id' => $line->product_id,
            'unit_id' => $line->unit_id,
            'movement_type' => 'transfer_in',
            'posting_key' => sprintf(
                'inventory-transfer:%d:line:%d:in',
                (int) $transfer->getKey(),
                (int) $line->getKey(),
            ),
            'source_type' => InventoryTransfer::class,
            'source_id' => $transfer->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $documentNumber,
            'occurred_at' => $postedAt,
            'quantity_in' => $quantity->__toString(),
            'quantity_out' => '0.000000',
            'unit_cost' => $sourceAverageCost->__toString(),
            'total_cost' => $transferValue->__toString(),
            'balance_quantity' => $newDestinationQuantity->__toString(),
            'balance_value' => $newDestinationValue->__toString(),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => null,
        ]);
    }

    /**
     * @return array{InventoryBalance, InventoryBalance}
     */
    private function lockBalancePair(
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        Product $product,
        int $unitId,
    ): array {
        $tenantId = $this->activeTenantId();

        DB::table('inventory_balances')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'branch_id' => $destinationWarehouse->branch_id,
            'warehouse_id' => $destinationWarehouse->getKey(),
            'product_id' => $product->getKey(),
            'unit_id' => $unitId,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'inventory_value' => 0,
            'average_unit_cost' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var Collection<int, InventoryBalance> $balances */
        $balances = InventoryBalance::query()
            ->where('product_id', $product->getKey())
            ->whereIn('warehouse_id', [
                $sourceWarehouse->getKey(),
                $destinationWarehouse->getKey(),
            ])
            ->orderBy('warehouse_id')
            ->lockForUpdate()
            ->get();

        $sourceBalance = $balances->firstWhere(
            'warehouse_id',
            $sourceWarehouse->getKey(),
        );

        $destinationBalance = $balances->firstWhere(
            'warehouse_id',
            $destinationWarehouse->getKey(),
        );

        if (!$sourceBalance instanceof InventoryBalance) {
            throw ValidationException::withMessages([
                'lines' => [
                    "No source inventory balance exists for {$product->name}.",
                ],
            ]);
        }

        if (!$destinationBalance instanceof InventoryBalance) {
            throw new LogicException(
                'The destination inventory balance could not be initialized.',
            );
        }

        foreach ([$sourceBalance, $destinationBalance] as $balance) {
            if (
                (int) $balance->unit_id !== $unitId
                || (int) $balance->product_id !== (int) $product->getKey()
            ) {
                throw new LogicException(
                    'The inventory balance unit does not match the transfer line.',
                );
            }
        }

        return [$sourceBalance, $destinationBalance];
    }

    private function resolveWarehouse(
        User $user,
        int $warehouseId,
    ): Warehouse {
        $warehouse = $this->branchAccessService
            ->scopeQuery(
                query: Warehouse::query(),
                user: $user,
            )
            ->whereKey($warehouseId)
            ->where('status', 'active')
            ->first();

        if (!$warehouse instanceof Warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The selected warehouse is not active or is not accessible.',
                ],
            ]);
        }

        return $warehouse;
    }

    private function ensureProductEnabledForWarehouse(
        Product $product,
        Warehouse $warehouse,
        string $field,
    ): void {
        $setting = ProductWarehouseSetting::query()
            ->where('product_id', $product->getKey())
            ->where('branch_id', $warehouse->branch_id)
            ->where('warehouse_id', $warehouse->getKey())
            ->where('status', 'active')
            ->first();

        if (!$setting instanceof ProductWarehouseSetting) {
            throw ValidationException::withMessages([
                $field => [
                    "Product {$product->name} is not enabled for warehouse {$warehouse->name}.",
                ],
            ]);
        }
    }

    public function authorizeTransfer(
        InventoryTransfer $transfer,
        User $actor,
    ): void {
        $source = Warehouse::query()
            ->whereKey($transfer->source_warehouse_id)
            ->firstOrFail();

        $destination = Warehouse::query()
            ->whereKey($transfer->destination_warehouse_id)
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $source->branch()->firstOrFail(),
            requireActive: false,
        );

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $destination->branch()->firstOrFail(),
            requireActive: false,
        );
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The user does not belong to the active tenant.',
            );
        }
    }
}

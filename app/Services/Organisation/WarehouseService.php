<?php

declare(strict_types=1);

namespace App\Services\Organisation;

use App\Models\Branch;
use App\Models\ProductWarehouseSetting;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WarehouseService
{
    /**
     * @param array{
     *     branch_id: int,
     *     name: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     is_default: bool,
     *     address: string|null
     * } $attributes
     */
    public function create(array $attributes): Warehouse
    {
        return DB::transaction(
            function () use ($attributes): Warehouse {
                $this->lockBranch(
                    $attributes['branch_id'],
                );

                if ($attributes['is_default']) {
                    $this->clearCurrentDefault(
                        branchId:
                            $attributes['branch_id'],
                    );
                }

                return Warehouse::query()->create(
                    $attributes,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     branch_id: int,
     *     name: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     is_default: bool,
     *     address: string|null
     * } $attributes
     */
    public function update(
        Warehouse $warehouse,
        array $attributes,
    ): Warehouse {
        return DB::transaction(
            function () use (
                $warehouse,
                $attributes,
            ): Warehouse {
                $lockedWarehouse =
                    Warehouse::query()
                        ->whereKey(
                            $warehouse->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->lockBranch(
                    $attributes['branch_id'],
                );

                if (
                    (int) $lockedWarehouse->branch_id
                    !== $attributes['branch_id']
                ) {
                    $this->lockBranch(
                        (int) $lockedWarehouse
                            ->branch_id,
                    );
                }

                $this->ensureWarehouseCanBeUpdated(
                    warehouse: $lockedWarehouse,
                    attributes: $attributes,
                );

                if ($attributes['is_default']) {
                    $this->clearCurrentDefault(
                        branchId:
                            $attributes['branch_id'],

                        exceptWarehouseId:
                            (int) $lockedWarehouse
                                ->getKey(),
                    );
                }

                $lockedWarehouse->fill(
                    $attributes,
                );

                $lockedWarehouse->save();

                return $lockedWarehouse->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Warehouse $warehouse,
    ): void {
        DB::transaction(
            function () use ($warehouse): void {
                $lockedWarehouse =
                    Warehouse::query()
                        ->whereKey(
                            $warehouse->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->lockBranch(
                    (int) $lockedWarehouse
                        ->branch_id,
                );

                $hasProductSettings =
                    ProductWarehouseSetting::query()
                        ->where(
                            'warehouse_id',
                            $lockedWarehouse->getKey(),
                        )
                        ->exists();

                if ($hasProductSettings) {
                    throw ValidationException::withMessages([
                        'warehouse' => [
                            'The warehouse cannot be deleted while product configurations are assigned to it.',
                        ],
                    ]);
                }

                $hasPurchaseOrders =
                    PurchaseOrder::query()
                        ->withTrashed()
                        ->where(
                            'warehouse_id',
                            $lockedWarehouse->getKey(),
                        )
                        ->exists();

                if ($hasPurchaseOrders) {
                    throw ValidationException::withMessages([
                        'warehouse' => [
                            'The warehouse cannot be deleted because it is referenced by one or more purchase orders.',
                        ],
                    ]);
                }

                $lockedWarehouse->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     branch_id: int,
     *     name: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     is_default: bool,
     *     address: string|null
     * } $attributes
     */
    private function ensureWarehouseCanBeUpdated(
        Warehouse $warehouse,
        array $attributes,
    ): void {
        $branchChanged =
            (int) $warehouse->branch_id
            !== $attributes['branch_id'];

        if ($branchChanged) {
            $hasProductSettings =
                ProductWarehouseSetting::query()
                    ->where(
                        'warehouse_id',
                        $warehouse->getKey(),
                    )
                    ->exists();

            if ($hasProductSettings) {
                throw ValidationException::withMessages([
                    'branch_id' => [
                        'A warehouse with product configurations cannot be moved to another branch.',
                    ],
                ]);
            }

            $hasPurchaseOrders =
                PurchaseOrder::query()
                    ->withTrashed()
                    ->where(
                        'warehouse_id',
                        $warehouse->getKey(),
                    )
                    ->exists();

            if ($hasPurchaseOrders) {
                throw ValidationException::withMessages([
                    'branch_id' => [
                        'A warehouse referenced by purchase orders cannot be moved to another branch.',
                    ],
                ]);
            }
        }

        if (
            $attributes['status']
            === 'active'
        ) {
            return;
        }

        $hasActiveProductSettings =
            ProductWarehouseSetting::query()
                ->where(
                    'warehouse_id',
                    $warehouse->getKey(),
                )
                ->where('status', 'active')
                ->exists();

        if (!$hasActiveProductSettings) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Deactivate all product warehouse configurations before making this warehouse inactive or archived.',
            ],
        ]);
    }

    private function lockBranch(
        int $branchId,
    ): void {
        Branch::query()
            ->whereKey($branchId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function clearCurrentDefault(
        int $branchId,
        ?int $exceptWarehouseId = null,
    ): void {
        Warehouse::query()
            ->where(
                'branch_id',
                $branchId,
            )
            ->when(
                $exceptWarehouseId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'id',
                    '!=',
                    $exceptWarehouseId,
                ),
            )
            ->where('is_default', true)
            ->update([
                'is_default' => false,
            ]);
    }
}
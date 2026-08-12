<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceiptLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class PurchaseReturnCalculator
{
    private const SCALE = 6;

    /**
     * @return array{
     *     accepted_quantity_snapshot: string,
     *     previously_returned_quantity_snapshot: string,
     *     previously_reserved_quantity_snapshot: string,
     *     returnable_quantity_snapshot: string,
     *     return_quantity: string,
     *     supplier_unit_cost: string,
     *     supplier_total_cost: string,
     *     inventory_unit_cost: string,
     *     inventory_total_cost: string,
     *     cost_variance_amount: string
     * }
     */
    public function calculateLine(
        GoodsReceiptLine $goodsReceiptLine,
        BigDecimal $returnQuantity,
    ): array {
        $acceptedQuantity =
            BigDecimal::of(
                (string) $goodsReceiptLine
                    ->accepted_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $returnedQuantity =
            BigDecimal::of(
                (string) $goodsReceiptLine
                    ->returned_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $reservedQuantity =
            BigDecimal::of(
                (string) $goodsReceiptLine
                    ->return_reserved_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $returnableQuantity =
            $acceptedQuantity
                ->minus($returnedQuantity)
                ->minus($reservedQuantity)
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $supplierUnitCost =
            BigDecimal::of(
                (string) $goodsReceiptLine
                    ->unit_cost,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $supplierTotalCost =
            $supplierUnitCost
                ->multipliedBy(
                    $returnQuantity,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        return [
            'accepted_quantity_snapshot' =>
                $acceptedQuantity
                    ->__toString(),

            'previously_returned_quantity_snapshot' =>
                $returnedQuantity
                    ->__toString(),

            'previously_reserved_quantity_snapshot' =>
                $reservedQuantity
                    ->__toString(),

            'returnable_quantity_snapshot' =>
                $returnableQuantity
                    ->__toString(),

            'return_quantity' =>
                $returnQuantity
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'supplier_unit_cost' =>
                $supplierUnitCost
                    ->__toString(),

            'supplier_total_cost' =>
                $supplierTotalCost
                    ->__toString(),

            'inventory_unit_cost' =>
                '0.000000',

            'inventory_total_cost' =>
                '0.000000',

            'cost_variance_amount' =>
                '0.000000',
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return array{
     *     total_return_quantity: string,
     *     total_supplier_value: string,
     *     total_inventory_value: string,
     *     total_cost_variance: string
     * }
     */
    public function calculateTotals(
        array $lines,
    ): array {
        $totalReturnQuantity =
            BigDecimal::zero();

        $totalSupplierValue =
            BigDecimal::zero();

        $totalInventoryValue =
            BigDecimal::zero();

        $totalCostVariance =
            BigDecimal::zero();

        foreach ($lines as $line) {
            $totalReturnQuantity =
                $totalReturnQuantity->plus(
                    BigDecimal::of(
                        (string) $line[
                            'return_quantity'
                        ],
                    ),
                );

            $totalSupplierValue =
                $totalSupplierValue->plus(
                    BigDecimal::of(
                        (string) $line[
                            'supplier_total_cost'
                        ],
                    ),
                );

            $totalInventoryValue =
                $totalInventoryValue->plus(
                    BigDecimal::of(
                        (string) $line[
                            'inventory_total_cost'
                        ],
                    ),
                );

            $totalCostVariance =
                $totalCostVariance->plus(
                    BigDecimal::of(
                        (string) $line[
                            'cost_variance_amount'
                        ],
                    ),
                );
        }

        return [
            'total_return_quantity' =>
                $totalReturnQuantity
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'total_supplier_value' =>
                $totalSupplierValue
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'total_inventory_value' =>
                $totalInventoryValue
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'total_cost_variance' =>
                $totalCostVariance
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),
        ];
    }
}
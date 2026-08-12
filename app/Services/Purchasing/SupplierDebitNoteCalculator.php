<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class SupplierDebitNoteCalculator
{
    private const SCALE = 6;

    /**
     * @return array{
     *     return_quantity: string,
     *     unit_price: string,
     *     gross_amount: string,
     *     discount_amount: string,
     *     subtotal: string,
     *     tax_rate: string,
     *     tax_amount: string,
     *     total_amount: string
     * }
     */
    public function calculateLine(
        BigDecimal $returnQuantity,
        BigDecimal $unitPrice,
        BigDecimal $discountPerUnit,
        BigDecimal $taxRate,
    ): array {
        $returnQuantity =
            $returnQuantity->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $unitPrice =
            $unitPrice->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $discountPerUnit =
            $discountPerUnit->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $taxRate =
            $taxRate->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        if (
            !$returnQuantity->isPositive()
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Supplier Debit Note return quantity must be greater than zero.',
                ],
            ]);
        }

        if (
            $unitPrice->isLessThan(
                BigDecimal::zero(),
            )
            || $discountPerUnit->isLessThan(
                BigDecimal::zero(),
            )
            || $taxRate->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Supplier Debit Note prices, discounts, and tax rates cannot be negative.',
                ],
            ]);
        }

        $grossAmount =
            $returnQuantity
                ->multipliedBy(
                    $unitPrice,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $discountAmount =
            $returnQuantity
                ->multipliedBy(
                    $discountPerUnit,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        if (
            $discountAmount
                ->isGreaterThan(
                    $grossAmount,
                )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Supplier Debit Note discount cannot exceed the gross line amount.',
                ],
            ]);
        }

        $subtotal =
            $grossAmount
                ->minus(
                    $discountAmount,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $taxAmount =
            $subtotal
                ->multipliedBy(
                    $taxRate,
                )
                ->dividedBy(
                    BigDecimal::of('100'),
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $totalAmount =
            $subtotal
                ->plus(
                    $taxAmount,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        return [
            'return_quantity' =>
                $returnQuantity
                    ->__toString(),

            'unit_price' =>
                $unitPrice
                    ->__toString(),

            'gross_amount' =>
                $grossAmount
                    ->__toString(),

            'discount_amount' =>
                $discountAmount
                    ->__toString(),

            'subtotal' =>
                $subtotal
                    ->__toString(),

            'tax_rate' =>
                $taxRate
                    ->__toString(),

            'tax_amount' =>
                $taxAmount
                    ->__toString(),

            'total_amount' =>
                $totalAmount
                    ->__toString(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return array{
     *     gross_amount: string,
     *     discount_amount: string,
     *     subtotal: string,
     *     tax_amount: string,
     *     total_amount: string
     * }
     */
    public function calculateTotals(
        array $lines,
    ): array {
        $grossAmount =
            BigDecimal::zero();

        $discountAmount =
            BigDecimal::zero();

        $subtotal =
            BigDecimal::zero();

        $taxAmount =
            BigDecimal::zero();

        $totalAmount =
            BigDecimal::zero();

        foreach ($lines as $line) {
            $grossAmount =
                $grossAmount->plus(
                    BigDecimal::of(
                        (string) $line[
                            'gross_amount'
                        ],
                    ),
                );

            $discountAmount =
                $discountAmount->plus(
                    BigDecimal::of(
                        (string) $line[
                            'discount_amount'
                        ],
                    ),
                );

            $subtotal =
                $subtotal->plus(
                    BigDecimal::of(
                        (string) $line[
                            'subtotal'
                        ],
                    ),
                );

            $taxAmount =
                $taxAmount->plus(
                    BigDecimal::of(
                        (string) $line[
                            'tax_amount'
                        ],
                    ),
                );

            $totalAmount =
                $totalAmount->plus(
                    BigDecimal::of(
                        (string) $line[
                            'total_amount'
                        ],
                    ),
                );
        }

        return [
            'gross_amount' =>
                $grossAmount
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'discount_amount' =>
                $discountAmount
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'subtotal' =>
                $subtotal
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'tax_amount' =>
                $taxAmount
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),

            'total_amount' =>
                $totalAmount
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString(),
        ];
    }
}
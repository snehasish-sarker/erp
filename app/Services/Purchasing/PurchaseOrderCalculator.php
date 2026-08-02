<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderCalculator
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 6;

    private const TAX_RATE_SCALE = 6;

    private const MAXIMUM_QUANTITY =
        '99999999999999.999999';

    private const MAXIMUM_MONEY =
        '99999999999999.999999';

    private const MAXIMUM_TAX_RATE =
        '100.000000';

    /**
     * @param array<string, mixed> $line
     *
     * @return array{
     *     ordered_quantity: string,
     *     unit_price: string,
     *     gross_amount: string,
     *     discount_amount: string,
     *     tax_rate: string,
     *     tax_amount: string,
     *     line_total: string
     * }
     */
    public function calculateLine(
        array $line,
        int $lineIndex,
    ): array {
        $fieldPrefix = "lines.{$lineIndex}";

        $quantity = $this->normalizeDecimal(
            value:
                $line['ordered_quantity']
                    ?? null,

            scale: self::QUANTITY_SCALE,

            field:
                "{$fieldPrefix}.ordered_quantity",

            label: 'ordered quantity',

            allowZero: false,

            maximum:
                self::MAXIMUM_QUANTITY,
        );

        $unitPrice = $this->normalizeDecimal(
            value:
                $line['unit_price']
                    ?? null,

            scale: self::MONEY_SCALE,

            field:
                "{$fieldPrefix}.unit_price",

            label: 'unit price',

            allowZero: true,

            maximum:
                self::MAXIMUM_MONEY,
        );

        $discountAmount =
            $this->normalizeDecimal(
                value:
                    $line['discount_amount']
                        ?? 0,

                scale: self::MONEY_SCALE,

                field:
                    "{$fieldPrefix}.discount_amount",

                label: 'discount amount',

                allowZero: true,

                maximum:
                    self::MAXIMUM_MONEY,
            );

        $taxRate = $this->normalizeDecimal(
            value:
                $line['tax_rate']
                    ?? 0,

            scale: self::TAX_RATE_SCALE,

            field:
                "{$fieldPrefix}.tax_rate",

            label: 'tax rate',

            allowZero: true,

            maximum:
                self::MAXIMUM_TAX_RATE,
        );

        $grossAmount = $quantity
            ->multipliedBy($unitPrice)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $this->ensureMoneyFitsColumn(
            value: $grossAmount,

            field:
                "{$fieldPrefix}.gross_amount",

            label: 'gross amount',
        );

        if (
            $discountAmount->isGreaterThan(
                $grossAmount,
            )
        ) {
            throw ValidationException::withMessages([
                "{$fieldPrefix}.discount_amount" => [
                    'The line discount cannot exceed the gross amount.',
                ],
            ]);
        }

        $taxableAmount = $grossAmount
            ->minus($discountAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $taxAmount = $taxableAmount
            ->multipliedBy($taxRate)
            ->dividedBy(
                BigDecimal::of('100'),
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $lineTotal = $taxableAmount
            ->plus($taxAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $this->ensureMoneyFitsColumn(
            value: $taxAmount,

            field:
                "{$fieldPrefix}.tax_amount",

            label: 'tax amount',
        );

        $this->ensureMoneyFitsColumn(
            value: $lineTotal,

            field:
                "{$fieldPrefix}.line_total",

            label: 'line total',
        );

        return [
            'ordered_quantity' =>
                $quantity->__toString(),

            'unit_price' =>
                $unitPrice->__toString(),

            'gross_amount' =>
                $grossAmount->__toString(),

            'discount_amount' =>
                $discountAmount->__toString(),

            'tax_rate' =>
                $taxRate->__toString(),

            'tax_amount' =>
                $taxAmount->__toString(),

            'line_total' =>
                $lineTotal->__toString(),
        ];
    }

    /**
     * @param list<array{
     *     gross_amount: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     line_total: string
     * }> $calculatedLines
     *
     * @return array{
     *     subtotal: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     shipping_amount: string,
     *     other_charges: string,
     *     total_amount: string
     * }
     */
    public function calculateOrder(
        array $calculatedLines,
        mixed $shippingAmount,
        mixed $otherCharges,
    ): array {
        if ($calculatedLines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A purchase order must contain at least one line.',
                ],
            ]);
        }

        $subtotal = BigDecimal::zero()
            ->toScale(self::MONEY_SCALE);

        $discountTotal = BigDecimal::zero()
            ->toScale(self::MONEY_SCALE);

        $taxTotal = BigDecimal::zero()
            ->toScale(self::MONEY_SCALE);

        foreach ($calculatedLines as $line) {
            $subtotal = $subtotal->plus(
                BigDecimal::of(
                    $line['gross_amount'],
                ),
            );

            $discountTotal =
                $discountTotal->plus(
                    BigDecimal::of(
                        $line['discount_amount'],
                    ),
                );

            $taxTotal = $taxTotal->plus(
                BigDecimal::of(
                    $line['tax_amount'],
                ),
            );
        }

        $subtotal = $subtotal->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $discountTotal =
            $discountTotal->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $taxTotal = $taxTotal->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $shipping = $this->normalizeDecimal(
            value: $shippingAmount,

            scale: self::MONEY_SCALE,

            field: 'shipping_amount',

            label: 'shipping amount',

            allowZero: true,

            maximum:
                self::MAXIMUM_MONEY,
        );

        $other = $this->normalizeDecimal(
            value: $otherCharges,

            scale: self::MONEY_SCALE,

            field: 'other_charges',

            label: 'other charges',

            allowZero: true,

            maximum:
                self::MAXIMUM_MONEY,
        );

        $totalAmount = $subtotal
            ->minus($discountTotal)
            ->plus($taxTotal)
            ->plus($shipping)
            ->plus($other)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $this->ensureMoneyFitsColumn(
            value: $subtotal,
            field: 'subtotal',
            label: 'subtotal',
        );

        $this->ensureMoneyFitsColumn(
            value: $discountTotal,
            field: 'discount_amount',
            label: 'discount amount',
        );

        $this->ensureMoneyFitsColumn(
            value: $taxTotal,
            field: 'tax_amount',
            label: 'tax amount',
        );

        $this->ensureMoneyFitsColumn(
            value: $shipping,
            field: 'shipping_amount',
            label: 'shipping amount',
        );

        $this->ensureMoneyFitsColumn(
            value: $other,
            field: 'other_charges',
            label: 'other charges',
        );

        $this->ensureMoneyFitsColumn(
            value: $totalAmount,
            field: 'total_amount',
            label: 'total amount',
        );

        return [
            'subtotal' =>
                $subtotal->__toString(),

            'discount_amount' =>
                $discountTotal->__toString(),

            'tax_amount' =>
                $taxTotal->__toString(),

            'shipping_amount' =>
                $shipping->__toString(),

            'other_charges' =>
                $other->__toString(),

            'total_amount' =>
                $totalAmount->__toString(),
        ];
    }

    private function normalizeDecimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
        bool $allowZero,
        string $maximum,
    ): BigDecimal {
        if (
            $value === null
            || $value === ''
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} is required.",
                ],
            ]);
        }

        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }

        if (
            is_float($value)
            && !is_finite($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }

        $normalized = trim((string) $value);

        if (
            preg_match(
                '/^\d+(?:\.\d+)?$/',
                $normalized,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a non-negative number.",
                ],
            ]);
        }

        $decimal = BigDecimal::of(
            $normalized,
        );

        if (
            !$allowZero
            && $decimal->isZero()
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be greater than zero.",
                ],
            ]);
        }

        try {
            $decimal = $decimal->toScale(
                $scale,
                RoundingMode::UNNECESSARY,
            );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not contain more than {$scale} decimal places.",
                ],
            ]);
        }

        if (
            $decimal->isGreaterThan(
                BigDecimal::of($maximum),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} exceeds the supported maximum value.",
                ],
            ]);
        }

        return $decimal;
    }

    private function ensureMoneyFitsColumn(
        BigDecimal $value,
        string $field,
        string $label,
    ): void {
        if (
            !$value->isGreaterThan(
                BigDecimal::of(
                    self::MAXIMUM_MONEY,
                ),
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                "The calculated {$label} exceeds the supported maximum value.",
            ],
        ]);
    }
}
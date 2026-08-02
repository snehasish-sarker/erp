<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseOrderLine;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class SupplierInvoiceCalculator
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 6;

    private const TAX_RATE_SCALE = 6;

    private const MAXIMUM_QUANTITY =
        '99999999999999.999999';

    private const MAXIMUM_MONEY =
        '99999999999999.999999';

    /**
     * @param array<string, mixed> $line
     *
     * @return array<string, string>
     */
    public function calculateLine(
        PurchaseOrderLine $purchaseOrderLine,
        array $line,
        int $lineIndex,
        BigDecimal $matchedQuantity,
        BigDecimal $availableQuantity,
        BigDecimal $previouslyInvoicedQuantity,
    ): array {
        $fieldPrefix = "lines.{$lineIndex}";

        $invoicedQuantity = $this->normalizeDecimal(
            value: $line['invoiced_quantity'] ?? null,
            scale: self::QUANTITY_SCALE,
            field: "{$fieldPrefix}.invoiced_quantity",
            label: 'invoiced quantity',
            allowZero: false,
            allowNegative: false,
            maximum: self::MAXIMUM_QUANTITY,
        );

        $invoiceUnitPrice = $this->normalizeDecimal(
            value: $line['invoice_unit_price'] ?? null,
            scale: self::MONEY_SCALE,
            field: "{$fieldPrefix}.invoice_unit_price",
            label: 'invoice unit price',
            allowZero: true,
            allowNegative: false,
            maximum: self::MAXIMUM_MONEY,
        );

        $discountAmount = $this->normalizeDecimal(
            value: $line['discount_amount'] ?? 0,
            scale: self::MONEY_SCALE,
            field: "{$fieldPrefix}.discount_amount",
            label: 'discount amount',
            allowZero: true,
            allowNegative: false,
            maximum: self::MAXIMUM_MONEY,
        );

        $invoiceTaxRate = $this->normalizeDecimal(
            value: $line['invoice_tax_rate'] ?? 0,
            scale: self::TAX_RATE_SCALE,
            field: "{$fieldPrefix}.invoice_tax_rate",
            label: 'invoice tax rate',
            allowZero: true,
            allowNegative: false,
            maximum: '100.000000',
        );

        $purchaseOrderQuantity = BigDecimal::of(
            (string) $purchaseOrderLine->ordered_quantity,
        );

        $purchaseOrderUnitPrice = BigDecimal::of(
            (string) $purchaseOrderLine->unit_price,
        )->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $grossAmount = $invoicedQuantity
            ->multipliedBy($invoiceUnitPrice)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        if ($discountAmount->isGreaterThan($grossAmount)) {
            throw ValidationException::withMessages([
                "{$fieldPrefix}.discount_amount" => [
                    'The line discount cannot exceed the invoice gross amount.',
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
            ->multipliedBy($invoiceTaxRate)
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

        $expectedGrossAmount = $invoicedQuantity
            ->multipliedBy($purchaseOrderUnitPrice)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $ratio = $invoicedQuantity->dividedBy(
            $purchaseOrderQuantity,
            12,
            RoundingMode::HALF_UP,
        );

        $expectedDiscountAmount = BigDecimal::of(
            (string) $purchaseOrderLine->discount_amount,
        )
            ->multipliedBy($ratio)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $expectedTaxAmount = BigDecimal::of(
            (string) $purchaseOrderLine->tax_amount,
        )
            ->multipliedBy($ratio)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $expectedLineTotal = $expectedGrossAmount
            ->minus($expectedDiscountAmount)
            ->plus($expectedTaxAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $receiptQuantityVariance = $invoicedQuantity
            ->minus($matchedQuantity)
            ->toScale(
                self::QUANTITY_SCALE,
                RoundingMode::HALF_UP,
            );

        $cumulativeInvoicedQuantity =
            $previouslyInvoicedQuantity->plus(
                $invoicedQuantity,
            );

        $purchaseOrderQuantityVariance =
            $cumulativeInvoicedQuantity->isGreaterThan(
                $purchaseOrderQuantity,
            )
                ? $cumulativeInvoicedQuantity->minus(
                    $purchaseOrderQuantity,
                )
                : BigDecimal::zero();

        $quantityVariance =
            $receiptQuantityVariance->isGreaterThan(
                $purchaseOrderQuantityVariance,
            )
                ? $receiptQuantityVariance
                : $purchaseOrderQuantityVariance;

        $quantityVariance = $quantityVariance->toScale(
            self::QUANTITY_SCALE,
            RoundingMode::HALF_UP,
        );

        $priceVarianceAmount = $invoiceUnitPrice
            ->minus($purchaseOrderUnitPrice)
            ->multipliedBy($invoicedQuantity)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $discountVarianceAmount = $discountAmount
            ->minus($expectedDiscountAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $taxVarianceAmount = $taxAmount
            ->minus($expectedTaxAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $totalVarianceAmount = $lineTotal
            ->minus($expectedLineTotal)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $matchStatus = $this->lineMatchStatus(
            invoicedQuantity: $invoicedQuantity,
            matchedQuantity: $matchedQuantity,
            availableQuantity: $availableQuantity,
            receiptQuantityVariance: $receiptQuantityVariance,
            purchaseOrderQuantityVariance:
                $purchaseOrderQuantityVariance,
            priceVarianceAmount: $priceVarianceAmount,
            discountVarianceAmount: $discountVarianceAmount,
            taxVarianceAmount: $taxVarianceAmount,
            totalVarianceAmount: $totalVarianceAmount,
        );

        foreach (
            [
                'gross_amount' => $grossAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'expected_line_total' => $expectedLineTotal,
            ] as $field => $value
        ) {
            $this->ensureMoneyFitsColumn(
                value: $value,
                field: "{$fieldPrefix}.{$field}",
            );
        }

        return [
            'invoiced_quantity' => $invoicedQuantity->__toString(),
            'matched_quantity' => $matchedQuantity
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString(),
            'purchase_order_unit_price' =>
                $purchaseOrderUnitPrice->__toString(),
            'invoice_unit_price' => $invoiceUnitPrice->__toString(),
            'gross_amount' => $grossAmount->__toString(),
            'expected_discount_amount' =>
                $expectedDiscountAmount->__toString(),
            'discount_amount' => $discountAmount->__toString(),
            'purchase_order_tax_rate' => BigDecimal::of(
                (string) $purchaseOrderLine->tax_rate,
            )
                ->toScale(
                    self::TAX_RATE_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString(),
            'invoice_tax_rate' => $invoiceTaxRate->__toString(),
            'expected_tax_amount' => $expectedTaxAmount->__toString(),
            'tax_amount' => $taxAmount->__toString(),
            'expected_line_total' => $expectedLineTotal->__toString(),
            'line_total' => $lineTotal->__toString(),
            'quantity_variance' => $quantityVariance->__toString(),
            'price_variance_amount' => $priceVarianceAmount->__toString(),
            'discount_variance_amount' =>
                $discountVarianceAmount->__toString(),
            'tax_variance_amount' => $taxVarianceAmount->__toString(),
            'total_variance_amount' => $totalVarianceAmount->__toString(),
            'match_status' => $matchStatus,
        ];
    }

    /**
     * @param list<array<string, mixed>> $calculatedLines
     *
     * @return array<string, string>
     */
    public function calculateInvoice(
        array $calculatedLines,
        mixed $otherCharges,
        mixed $roundingAdjustment,
    ): array {
        if ($calculatedLines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A supplier invoice must contain at least one line.',
                ],
            ]);
        }

        $totals = [
            'total_invoiced_quantity' => BigDecimal::zero(),
            'total_matched_quantity' => BigDecimal::zero(),
            'subtotal' => BigDecimal::zero(),
            'discount_amount' => BigDecimal::zero(),
            'tax_amount' => BigDecimal::zero(),
            'quantity_variance' => BigDecimal::zero(),
            'price_variance_amount' => BigDecimal::zero(),
            'discount_variance_amount' => BigDecimal::zero(),
            'tax_variance_amount' => BigDecimal::zero(),
            'total_variance_amount' => BigDecimal::zero(),
        ];

        $lineFieldMap = [
            'total_invoiced_quantity' => 'invoiced_quantity',
            'total_matched_quantity' => 'matched_quantity',
            'subtotal' => 'gross_amount',
            'discount_amount' => 'discount_amount',
            'tax_amount' => 'tax_amount',
            'quantity_variance' => 'quantity_variance',
            'price_variance_amount' => 'price_variance_amount',
            'discount_variance_amount' => 'discount_variance_amount',
            'tax_variance_amount' => 'tax_variance_amount',
            'total_variance_amount' => 'total_variance_amount',
        ];

        foreach ($calculatedLines as $line) {
            foreach ($lineFieldMap as $totalField => $lineField) {
                $totals[$totalField] = $totals[$totalField]->plus(
                    BigDecimal::of($line[$lineField]),
                );
            }
        }

        $other = $this->normalizeDecimal(
            value: $otherCharges,
            scale: self::MONEY_SCALE,
            field: 'other_charges',
            label: 'other charges',
            allowZero: true,
            allowNegative: false,
            maximum: self::MAXIMUM_MONEY,
        );

        $rounding = $this->normalizeDecimal(
            value: $roundingAdjustment,
            scale: self::MONEY_SCALE,
            field: 'rounding_adjustment',
            label: 'rounding adjustment',
            allowZero: true,
            allowNegative: true,
            maximum: self::MAXIMUM_MONEY,
        );

        $lineTotal = BigDecimal::zero();

        foreach ($calculatedLines as $line) {
            $lineTotal = $lineTotal->plus(
                BigDecimal::of($line['line_total']),
            );
        }

        $totalAmount = $lineTotal
            ->plus($other)
            ->plus($rounding)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        if ($totalAmount->isNegative()) {
            throw ValidationException::withMessages([
                'rounding_adjustment' => [
                    'The rounding adjustment cannot make the invoice total negative.',
                ],
            ]);
        }

        $totals['total_variance_amount'] =
            $totals['total_variance_amount']
                ->plus($other)
                ->plus($rounding);

        $result = [];

        foreach ($totals as $field => $value) {
            $scale = str_contains($field, 'quantity')
                ? self::QUANTITY_SCALE
                : self::MONEY_SCALE;

            $result[$field] = $value
                ->toScale(
                    $scale,
                    RoundingMode::HALF_UP,
                )
                ->__toString();
        }

        $result['other_charges'] = $other->__toString();
        $result['rounding_adjustment'] = $rounding->__toString();
        $result['total_amount'] = $totalAmount->__toString();

        foreach (
            [
                'subtotal',
                'discount_amount',
                'tax_amount',
                'other_charges',
                'rounding_adjustment',
                'total_amount',
                'price_variance_amount',
                'discount_variance_amount',
                'tax_variance_amount',
                'total_variance_amount',
            ] as $field
        ) {
            $this->ensureMoneyFitsColumn(
                value: BigDecimal::of($result[$field]),
                field: $field,
            );
        }

        return $result;
    }

    private function lineMatchStatus(
        BigDecimal $invoicedQuantity,
        BigDecimal $matchedQuantity,
        BigDecimal $availableQuantity,
        BigDecimal $receiptQuantityVariance,
        BigDecimal $purchaseOrderQuantityVariance,
        BigDecimal $priceVarianceAmount,
        BigDecimal $discountVarianceAmount,
        BigDecimal $taxVarianceAmount,
        BigDecimal $totalVarianceAmount,
    ): string {
        if (
            $invoicedQuantity->isGreaterThan($availableQuantity)
            || $matchedQuantity->isGreaterThan($invoicedQuantity)
        ) {
            return 'blocked';
        }

        if (!$receiptQuantityVariance->isZero()) {
            return 'unmatched';
        }

        foreach (
            [
                $purchaseOrderQuantityVariance,
                $priceVarianceAmount,
                $discountVarianceAmount,
                $taxVarianceAmount,
                $totalVarianceAmount,
            ] as $variance
        ) {
            if (!$variance->isZero()) {
                return 'variance';
            }
        }

        return 'matched';
    }

    private function normalizeDecimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
        bool $allowZero,
        bool $allowNegative,
        string $maximum,
    ): BigDecimal {
        if ($value === null || $value === '') {
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

        if (is_float($value) && !is_finite($value)) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }

        $normalized = trim((string) $value);
        $pattern = $allowNegative
            ? '/^-?\d+(?:\.\d+)?$/'
            : '/^\d+(?:\.\d+)?$/';

        if (preg_match($pattern, $normalized) !== 1) {
            throw ValidationException::withMessages([
                $field => [
                    $allowNegative
                        ? "The {$label} must be a valid number."
                        : "The {$label} must be a non-negative number.",
                ],
            ]);
        }

        $decimal = BigDecimal::of($normalized);

        if (!$allowZero && $decimal->isZero()) {
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
            $decimal->abs()->isGreaterThan(
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
    ): void {
        if (
            !$value->abs()->isGreaterThan(
                BigDecimal::of(self::MAXIMUM_MONEY),
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                'The calculated amount exceeds the supported maximum value.',
            ],
        ]);
    }
}
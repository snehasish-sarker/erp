<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\SupplierInvoiceMatch;
use App\Models\Unit;
use App\Support\Purchasing\SupplierInvoiceMatchStatusRegistry;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierInvoiceMatchingService
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 6;

    private const MAXIMUM_QUANTITY = '99999999999999.999999';

    private const MAXIMUM_MONEY = '99999999999999.999999';

    public function __construct(
        private readonly SupplierInvoiceCalculator $calculator,
        private readonly SupplierInvoiceMatchStatusRegistry $matchStatusRegistry,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $inputLines
     *
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     totals: array<string, string>,
     *     match_status: string
     * }
     */
    public function build(
        PurchaseOrder $purchaseOrder,
        array $inputLines,
        mixed $otherCharges,
        mixed $roundingAdjustment,
    ): array {
        if ($inputLines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A supplier invoice must contain at least one line.',
                ],
            ]);
        }

        $builtLines = [];
        $lineStatuses = [];
        $usedPurchaseOrderLineIds = [];

        foreach ($inputLines as $index => $inputLine) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each supplier invoice line must be an object.',
                    ],
                ]);
            }

            $purchaseOrderLineId = $this->requiredId(
                value: $inputLine['purchase_order_line_id'] ?? null,
                field: "lines.{$index}.purchase_order_line_id",
                message: 'The selected Purchase Order line is invalid.',
            );

            if (isset($usedPurchaseOrderLineIds[$purchaseOrderLineId])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => [
                        'A Purchase Order line may only appear once on a supplier invoice.',
                    ],
                ]);
            }

            $usedPurchaseOrderLineIds[$purchaseOrderLineId] = true;

            $purchaseOrderLine = PurchaseOrderLine::query()
                ->with('unit')
                ->whereKey($purchaseOrderLineId)
                ->where(
                    'purchase_order_id',
                    $purchaseOrder->getKey(),
                )
                ->lockForUpdate()
                ->first();

            if (!$purchaseOrderLine instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => [
                        'The selected Purchase Order line does not belong to this Purchase Order.',
                    ],
                ]);
            }

            $invoicedQuantity = $this->normalizeQuantity(
                value: $inputLine['invoiced_quantity'] ?? null,
                field: "lines.{$index}.invoiced_quantity",
                allowZero: false,
            );

            $this->ensureUnitPrecision(
                quantity: $invoicedQuantity,
                unit: $purchaseOrderLine->unit,
                field: "lines.{$index}.invoiced_quantity",
            );

            $receiptLines = $this->postedReceiptLines(
                purchaseOrderLine: $purchaseOrderLine,
            );

            $receivedQuantity = BigDecimal::zero();
            $previouslyInvoicedQuantity = BigDecimal::zero();

            foreach ($receiptLines as $receiptLine) {
                $receivedQuantity = $receivedQuantity->plus(
                    BigDecimal::of(
                        (string) $receiptLine->accepted_quantity,
                    ),
                );

                $previouslyInvoicedQuantity =
                    $previouslyInvoicedQuantity->plus(
                        BigDecimal::of(
                            (string) $receiptLine->invoiced_quantity,
                        ),
                    );
            }

            $availableQuantity = $receivedQuantity
                ->minus($previouslyInvoicedQuantity)
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HALF_UP,
                );

            if ($invoicedQuantity->isGreaterThan($availableQuantity)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.invoiced_quantity" => [
                        "The invoiced quantity for {$purchaseOrderLine->product_name} exceeds the currently accepted and uninvoiced Goods Receipt quantity.",
                    ],
                ]);
            }

            $matches = $this->buildMatches(
                purchaseOrder: $purchaseOrder,
                purchaseOrderLine: $purchaseOrderLine,
                receiptLines: $receiptLines,
                inputMatches: $inputLine['matches'] ?? [],
                inputLineIndex: $index,
                invoiceUnitPrice: $inputLine['invoice_unit_price'] ?? null,
            );

            $matchedQuantity = BigDecimal::zero();

            foreach ($matches as $match) {
                $matchedQuantity = $matchedQuantity->plus(
                    BigDecimal::of($match['matched_quantity']),
                );
            }

            if ($matchedQuantity->isGreaterThan($invoicedQuantity)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.matches" => [
                        'The allocated Goods Receipt quantity cannot exceed the invoiced quantity.',
                    ],
                ]);
            }

            $calculated = $this->calculator->calculateLine(
                purchaseOrderLine: $purchaseOrderLine,
                line: $inputLine,
                lineIndex: $index,
                matchedQuantity: $matchedQuantity,
                availableQuantity: $availableQuantity,
                previouslyInvoicedQuantity: $previouslyInvoicedQuantity,
            );

            $varianceReason = $this->nullableString(
                value: $inputLine['variance_reason'] ?? null,
                maximumLength: 500,
                field: "lines.{$index}.variance_reason",
            );

            $builtLines[] = [
                'purchase_order_line_id' =>
                    (int) $purchaseOrderLine->getKey(),
                'product_id' => (int) $purchaseOrderLine->product_id,
                'unit_id' => (int) $purchaseOrderLine->unit_id,
                'line_number' => $index + 1,
                'product_name' => $purchaseOrderLine->product_name,
                'product_sku' => $purchaseOrderLine->product_sku,
                'product_type' => $purchaseOrderLine->product_type,
                'unit_name' => $purchaseOrderLine->unit_name,
                'unit_code' => $purchaseOrderLine->unit_code,
                'description' => $purchaseOrderLine->description,
                'ordered_quantity_snapshot' =>
                    (string) $purchaseOrderLine->ordered_quantity,
                'received_quantity_snapshot' =>
                    $receivedQuantity
                        ->toScale(
                            self::QUANTITY_SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
                'previously_invoiced_quantity_snapshot' =>
                    $previouslyInvoicedQuantity
                        ->toScale(
                            self::QUANTITY_SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
                'available_to_invoice_quantity_snapshot' =>
                    $availableQuantity->__toString(),
                ...$calculated,
                'variance_reason' => $varianceReason,
                'matches' => $matches,
            ];

            $lineStatuses[] = $calculated['match_status'];
        }

        $totals = $this->calculator->calculateInvoice(
            calculatedLines: $builtLines,
            otherCharges: $otherCharges,
            roundingAdjustment: $roundingAdjustment,
        );

        return [
            'lines' => $builtLines,
            'totals' => $totals,
            'match_status' => $this->matchStatusRegistry
                ->summarize($lineStatuses),
        ];
    }

    public function reserve(
        SupplierInvoice $supplierInvoice,
    ): void {
        if ($supplierInvoice->hasMatchingReservation()) {
            return;
        }

        $lines = SupplierInvoiceLine::query()
            ->with('matches')
            ->where(
                'supplier_invoice_id',
                $supplierInvoice->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The supplier invoice must contain at least one line.',
                ],
            ]);
        }

        /*
         * Lock the Purchase Order lines in a stable order.
         *
         * This serializes invoice validation for the same Purchase Order
         * lines even when separate invoices reference different Goods
         * Receipt lines.
         */
        $purchaseOrderLineIds = $lines
            ->pluck('purchase_order_line_id')
            ->map(
                static fn (mixed $id): int => (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        $lockedPurchaseOrderLines = PurchaseOrderLine::query()
            ->whereIn('id', $purchaseOrderLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (
            $lockedPurchaseOrderLines->count()
            !== count($purchaseOrderLineIds)
        ) {
            throw new LogicException(
                'A supplier invoice references a missing Purchase Order line.',
            );
        }

        $goodsReceiptLineIds = $lines
            ->flatMap(
                static fn (
                    SupplierInvoiceLine $line,
                ) => $line->matches->pluck(
                    'goods_receipt_line_id',
                ),
            )
            ->map(
                static fn (mixed $id): int => (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        $lockedReceiptLines = GoodsReceiptLine::query()
            ->with('goodsReceipt')
            ->whereIn(
                'purchase_order_line_id',
                $purchaseOrderLineIds,
            )
            ->whereHas(
                'goodsReceipt',
                static fn ($query) => $query->where(
                    'status',
                    'posted',
                ),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($goodsReceiptLineIds as $goodsReceiptLineId) {
            if (
                !$lockedReceiptLines->has(
                    $goodsReceiptLineId,
                )
            ) {
                throw ValidationException::withMessages([
                    'matches' => [
                        'A matched Goods Receipt line is no longer eligible for invoicing.',
                    ],
                ]);
            }
        }

        foreach ($lines as $line) {
            if (
                !$this->matchStatusRegistry->allowsValidation(
                    $line->match_status,
                )
            ) {
                throw ValidationException::withMessages([
                    'match_status' => [
                        "Supplier invoice line {$line->line_number} is not fully matched.",
                    ],
                ]);
            }

            if (
                $line->hasVariance()
                && (
                    $line->variance_reason === null
                    || trim($line->variance_reason) === ''
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$line->line_number}.variance_reason" => [
                        'A variance reason is required before validating a line with a financial variance.',
                    ],
                ]);
            }

            $lineMatchedQuantity = BigDecimal::zero();

            foreach ($line->matches as $match) {
                $receiptLine = $lockedReceiptLines->get(
                    (int) $match->goods_receipt_line_id,
                );

                if (!$receiptLine instanceof GoodsReceiptLine) {
                    throw new LogicException(
                        'A supplier invoice match references a missing Goods Receipt line.',
                    );
                }

                if (
                    !$receiptLine->goodsReceipt
                    || $receiptLine->goodsReceipt->status !== 'posted'
                    || (int) $receiptLine->purchase_order_line_id
                        !== (int) $line->purchase_order_line_id
                ) {
                    throw ValidationException::withMessages([
                        'matches' => [
                            'A matched Goods Receipt line is no longer eligible for invoicing.',
                        ],
                    ]);
                }

                $matchedQuantity = BigDecimal::of(
                    (string) $match->matched_quantity,
                );

                $acceptedQuantity = BigDecimal::of(
                    (string) $receiptLine->accepted_quantity,
                );

                $previouslyInvoiced = BigDecimal::of(
                    (string) $receiptLine->invoiced_quantity,
                );

                $available = $acceptedQuantity
                    ->minus($previouslyInvoiced);

                if ($matchedQuantity->isGreaterThan($available)) {
                    throw ValidationException::withMessages([
                        'matches' => [
                            "Goods Receipt line {$receiptLine->line_number} no longer has enough uninvoiced accepted quantity.",
                        ],
                    ]);
                }

                $receiptLine->invoiced_quantity = $previouslyInvoiced
                    ->plus($matchedQuantity)
                    ->toScale(
                        self::QUANTITY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString();

                $receiptLine->save();

                $match->receipt_accepted_quantity_snapshot =
                    $acceptedQuantity->__toString();

                $match->previously_invoiced_quantity_snapshot =
                    $previouslyInvoiced->__toString();

                $match->available_quantity_snapshot =
                    $available->__toString();

                $match->save();

                $lineMatchedQuantity = $lineMatchedQuantity->plus(
                    $matchedQuantity,
                );
            }

            $invoicedQuantity = BigDecimal::of(
                (string) $line->invoiced_quantity,
            );

            if (!$lineMatchedQuantity->isEqualTo($invoicedQuantity)) {
                throw ValidationException::withMessages([
                    'matches' => [
                        "Supplier invoice line {$line->line_number} must be fully allocated to posted Goods Receipt lines.",
                    ],
                ]);
            }

            $receiptLinesForOrderLine = $lockedReceiptLines
                ->filter(
                    static fn (
                        GoodsReceiptLine $receiptLine,
                    ): bool => (
                        (int) $receiptLine->purchase_order_line_id
                        === (int) $line->purchase_order_line_id
                    ),
                );

            $receivedForOrderLine = BigDecimal::zero();
            $currentInvoicedForOrderLine = BigDecimal::zero();

            foreach ($receiptLinesForOrderLine as $receiptLine) {
                $receivedForOrderLine =
                    $receivedForOrderLine->plus(
                        BigDecimal::of(
                            (string) $receiptLine->accepted_quantity,
                        ),
                    );

                $currentInvoicedForOrderLine =
                    $currentInvoicedForOrderLine->plus(
                        BigDecimal::of(
                            (string) $receiptLine->invoiced_quantity,
                        ),
                    );
            }

            $previouslyInvoicedForOrderLine =
                $currentInvoicedForOrderLine->minus(
                    $lineMatchedQuantity,
                );

            $availableBeforeReservation = $receivedForOrderLine
                ->minus($previouslyInvoicedForOrderLine);

            $purchaseOrderLine = $lockedPurchaseOrderLines->get(
                (int) $line->purchase_order_line_id,
            );

            if (!$purchaseOrderLine instanceof PurchaseOrderLine) {
                throw new LogicException(
                    'A supplier invoice references a missing Purchase Order line.',
                );
            }

            $orderedQuantity = BigDecimal::of(
                (string) $purchaseOrderLine->ordered_quantity,
            );

            $cumulativeInvoicedQuantity =
                $previouslyInvoicedForOrderLine->plus(
                    $invoicedQuantity,
                );

            $purchaseOrderQuantityVariance =
                $cumulativeInvoicedQuantity->isGreaterThan(
                    $orderedQuantity,
                )
                    ? $cumulativeInvoicedQuantity->minus(
                        $orderedQuantity,
                    )
                    : BigDecimal::zero();

            $line->matched_quantity = $lineMatchedQuantity
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

            $line->quantity_variance =
                $purchaseOrderQuantityVariance
                    ->toScale(
                        self::QUANTITY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString();

            $line->match_status = $this->validatedLineMatchStatus(
                line: $line,
                quantityVariance: $purchaseOrderQuantityVariance,
            );

            if (
                $line->match_status === 'variance'
                && (
                    $line->variance_reason === null
                    || trim($line->variance_reason) === ''
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$line->line_number}.variance_reason" => [
                        'A variance reason is required before validating a line with a quantity or financial variance.',
                    ],
                ]);
            }

            $line->received_quantity_snapshot = $receivedForOrderLine
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

            $line->previously_invoiced_quantity_snapshot =
                $previouslyInvoicedForOrderLine
                    ->toScale(
                        self::QUANTITY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString();

            $line->available_to_invoice_quantity_snapshot =
                $availableBeforeReservation
                    ->toScale(
                        self::QUANTITY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString();

            $line->save();
        }

        $refreshedLines = SupplierInvoiceLine::query()
            ->where(
                'supplier_invoice_id',
                $supplierInvoice->getKey(),
            )
            ->orderBy('line_number')
            ->get();

        $quantityVariance = BigDecimal::zero();
        $lineStatuses = [];

        foreach ($refreshedLines as $line) {
            $quantityVariance = $quantityVariance->plus(
                BigDecimal::of(
                    (string) $line->quantity_variance,
                ),
            );

            $lineStatuses[] = $line->match_status;
        }

        $supplierInvoice->quantity_variance = $quantityVariance
            ->toScale(
                self::QUANTITY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();

        $supplierInvoice->match_status =
            $this->matchStatusRegistry->summarize(
                $lineStatuses,
            );
    }

    public function release(
        SupplierInvoice $supplierInvoice,
    ): void {
        if (!$supplierInvoice->hasMatchingReservation()) {
            return;
        }

        $matches = SupplierInvoiceMatch::query()
            ->where(
                'supplier_invoice_id',
                $supplierInvoice->getKey(),
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $purchaseOrderLineIds = $matches
            ->pluck('purchase_order_line_id')
            ->map(
                static fn (mixed $id): int => (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        PurchaseOrderLine::query()
            ->whereIn('id', $purchaseOrderLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $goodsReceiptLineIds = $matches
            ->pluck('goods_receipt_line_id')
            ->map(
                static fn (mixed $id): int => (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        $lockedReceiptLines = GoodsReceiptLine::query()
            ->whereIn('id', $goodsReceiptLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($matches as $match) {
            $receiptLine = $lockedReceiptLines->get(
                (int) $match->goods_receipt_line_id,
            );

            if (!$receiptLine instanceof GoodsReceiptLine) {
                throw new LogicException(
                    'A supplier invoice match references a missing Goods Receipt line.',
                );
            }

            $currentInvoiced = BigDecimal::of(
                (string) $receiptLine->invoiced_quantity,
            );

            $matchedQuantity = BigDecimal::of(
                (string) $match->matched_quantity,
            );

            if ($currentInvoiced->isLessThan($matchedQuantity)) {
                throw new LogicException(
                    'The Goods Receipt invoiced quantity is lower than the supplier invoice quantity being released.',
                );
            }

            $receiptLine->invoiced_quantity = $currentInvoiced
                ->minus($matchedQuantity)
                ->toScale(
                    self::QUANTITY_SCALE,
                    RoundingMode::HALF_UP,
                )
                ->__toString();

            $receiptLine->save();
        }
    }

    private function validatedLineMatchStatus(
        SupplierInvoiceLine $line,
        BigDecimal $quantityVariance,
    ): string {
        if (!$quantityVariance->isZero()) {
            return 'variance';
        }

        foreach (
            [
                $line->price_variance_amount,
                $line->discount_variance_amount,
                $line->tax_variance_amount,
                $line->total_variance_amount,
            ] as $variance
        ) {
            if (
                !BigDecimal::of(
                    (string) $variance,
                )->isZero()
            ) {
                return 'variance';
            }
        }

        return 'matched';
    }

    /**
     * @param Collection<int, GoodsReceiptLine> $receiptLines
     *
     * @return list<array<string, int|string>>
     */
    private function buildMatches(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLine $purchaseOrderLine,
        Collection $receiptLines,
        mixed $inputMatches,
        int $inputLineIndex,
        mixed $invoiceUnitPrice,
    ): array {
        if ($inputMatches === null || $inputMatches === '') {
            $inputMatches = [];
        }

        if (!is_array($inputMatches)) {
            throw ValidationException::withMessages([
                "lines.{$inputLineIndex}.matches" => [
                    'The Goods Receipt matches must be an array.',
                ],
            ]);
        }

        $receiptLinesById = $receiptLines->keyBy('id');
        $matches = [];
        $usedReceiptLineIds = [];

        $invoicePrice = $this->normalizeMoney(
            value: $invoiceUnitPrice,
            field: "lines.{$inputLineIndex}.invoice_unit_price",
        );

        $purchaseOrderPrice = BigDecimal::of(
            (string) $purchaseOrderLine->unit_price,
        );

        foreach (array_values($inputMatches) as $matchIndex => $inputMatch) {
            if (!is_array($inputMatch)) {
                throw ValidationException::withMessages([
                    "lines.{$inputLineIndex}.matches.{$matchIndex}" => [
                        'Each Goods Receipt match must be an object.',
                    ],
                ]);
            }

            $goodsReceiptLineId = $this->requiredId(
                value: $inputMatch['goods_receipt_line_id'] ?? null,
                field: "lines.{$inputLineIndex}.matches.{$matchIndex}.goods_receipt_line_id",
                message: 'The selected Goods Receipt line is invalid.',
            );

            if (isset($usedReceiptLineIds[$goodsReceiptLineId])) {
                throw ValidationException::withMessages([
                    "lines.{$inputLineIndex}.matches.{$matchIndex}.goods_receipt_line_id" => [
                        'A Goods Receipt line may only be matched once per supplier invoice line.',
                    ],
                ]);
            }

            $usedReceiptLineIds[$goodsReceiptLineId] = true;

            $receiptLine = $receiptLinesById->get(
                $goodsReceiptLineId,
            );

            if (!$receiptLine instanceof GoodsReceiptLine) {
                throw ValidationException::withMessages([
                    "lines.{$inputLineIndex}.matches.{$matchIndex}.goods_receipt_line_id" => [
                        'The selected Goods Receipt line is not a posted receipt for this Purchase Order line.',
                    ],
                ]);
            }

            $matchedQuantity = $this->normalizeQuantity(
                value: $inputMatch['matched_quantity'] ?? null,
                field: "lines.{$inputLineIndex}.matches.{$matchIndex}.matched_quantity",
                allowZero: false,
            );

            $this->ensureUnitPrecision(
                quantity: $matchedQuantity,
                unit: $purchaseOrderLine->unit,
                field: "lines.{$inputLineIndex}.matches.{$matchIndex}.matched_quantity",
            );

            $acceptedQuantity = BigDecimal::of(
                (string) $receiptLine->accepted_quantity,
            );

            $previouslyInvoicedQuantity = BigDecimal::of(
                (string) $receiptLine->invoiced_quantity,
            );

            $availableQuantity = $acceptedQuantity
                ->minus($previouslyInvoicedQuantity);

            if ($matchedQuantity->isGreaterThan($availableQuantity)) {
                throw ValidationException::withMessages([
                    "lines.{$inputLineIndex}.matches.{$matchIndex}.matched_quantity" => [
                        'The matched quantity exceeds the uninvoiced accepted quantity on the selected Goods Receipt line.',
                    ],
                ]);
            }

            $priceVariancePerUnit = $invoicePrice
                ->minus($purchaseOrderPrice)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );

            $matches[] = [
                'purchase_order_id' => (int) $purchaseOrder->getKey(),
                'purchase_order_line_id' =>
                    (int) $purchaseOrderLine->getKey(),
                'goods_receipt_id' =>
                    (int) $receiptLine->goods_receipt_id,
                'goods_receipt_line_id' =>
                    (int) $receiptLine->getKey(),
                'matched_quantity' =>
                    $matchedQuantity->__toString(),
                'receipt_accepted_quantity_snapshot' =>
                    $acceptedQuantity->__toString(),
                'previously_invoiced_quantity_snapshot' =>
                    $previouslyInvoicedQuantity->__toString(),
                'available_quantity_snapshot' =>
                    $availableQuantity->__toString(),
                'purchase_order_unit_price_snapshot' =>
                    $purchaseOrderPrice
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
                'invoice_unit_price_snapshot' =>
                    $invoicePrice->__toString(),
                'price_variance_per_unit' =>
                    $priceVariancePerUnit->__toString(),
                'price_variance_amount' =>
                    $priceVariancePerUnit
                        ->multipliedBy($matchedQuantity)
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
                'matched_amount' =>
                    $invoicePrice
                        ->multipliedBy($matchedQuantity)
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
            ];
        }

        return $matches;
    }

    /**
     * @return Collection<int, GoodsReceiptLine>
     */
    private function postedReceiptLines(
        PurchaseOrderLine $purchaseOrderLine,
    ): Collection {
        return GoodsReceiptLine::query()
            ->with('goodsReceipt')
            ->where(
                'purchase_order_line_id',
                $purchaseOrderLine->getKey(),
            )
            ->whereHas(
                'goodsReceipt',
                static fn ($query) => $query->where(
                    'status',
                    'posted',
                ),
            )
            ->orderBy('goods_receipt_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function ensureUnitPrecision(
        BigDecimal $quantity,
        ?Unit $unit,
        string $field,
    ): void {
        if (!$unit instanceof Unit) {
            throw ValidationException::withMessages([
                $field => [
                    'The Purchase Order line unit is unavailable.',
                ],
            ]);
        }

        $allowedScale = $unit->allowsDecimal()
            ? (int) $unit->decimal_places
            : 0;

        try {
            $quantity->toScale(
                $allowedScale,
                RoundingMode::UNNECESSARY,
            );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The quantity may not contain more than {$allowedScale} decimal places for unit {$unit->code}.",
                ],
            ]);
        }
    }

    private function normalizeQuantity(
        mixed $value,
        string $field,
        bool $allowZero,
    ): BigDecimal {
        return $this->normalizeUnsignedDecimal(
            value: $value,
            scale: self::QUANTITY_SCALE,
            field: $field,
            label: 'quantity',
            allowZero: $allowZero,
            maximum: self::MAXIMUM_QUANTITY,
        );
    }

    private function normalizeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        return $this->normalizeUnsignedDecimal(
            value: $value,
            scale: self::MONEY_SCALE,
            field: $field,
            label: 'amount',
            allowZero: true,
            maximum: self::MAXIMUM_MONEY,
        );
    }

    private function normalizeUnsignedDecimal(
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
            || !is_scalar($value)
            || preg_match(
                '/^\d+(?:\.\d+)?$/',
                trim((string) $value),
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid non-negative number.",
                ],
            ]);
        }

        $decimal = BigDecimal::of(
            trim((string) $value),
        );

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

    private function requiredId(
        mixed $value,
        string $field,
        string $message,
    ): int {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^[1-9]\d*$/',
                trim($value),
            ) === 1
        ) {
            return (int) trim($value);
        }

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }

    private function nullableString(
        mixed $value,
        int $maximumLength,
        string $field,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be text.',
                ],
            ]);
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maximumLength) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $normalized;
    }
}
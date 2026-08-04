<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceMatch;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierInvoiceJournalBuilder
{
    private const MONEY_SCALE = 6;

    private const RATE_SCALE = 8;

    private const MAXIMUM_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AccountService $accountService,
    ) {
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>,
     *     expected_grni_amount: string,
     *     purchase_price_variance_amount: string,
     *     tax_amount: string,
     *     rounding_adjustment: string,
     *     total_amount: string
     * }
     */
    public function buildPosting(
        SupplierInvoice $supplierInvoice,
    ): array {
        $this->ensureInsideTransaction();
        $this->ensureInvoiceContext($supplierInvoice);

        if (!$supplierInvoice->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'Only an approved Supplier Invoice can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$supplierInvoice->hasDocumentNumber()) {
            throw new LogicException(
                'The approved Supplier Invoice does not retain its document number.',
            );
        }

        $this->ensureExchangeRateMatchesReceiptAccrual(
            $supplierInvoice,
        );

        $expectedGrniAmount = $this->expectedGrniAmount(
            $supplierInvoice,
        );

        $subtotal = $this->nonNegativeMoney(
            value: $supplierInvoice->subtotal,
            field: 'subtotal',
        );

        $discountAmount = $this->nonNegativeMoney(
            value: $supplierInvoice->discount_amount,
            field: 'discount_amount',
        );

        if ($discountAmount->isGreaterThan($subtotal)) {
            throw new LogicException(
                'The Supplier Invoice discount exceeds its subtotal.',
            );
        }

        $actualLineNetAmount = $subtotal
            ->minus($discountAmount)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $otherCharges = $this->nonNegativeMoney(
            value: $supplierInvoice->other_charges,
            field: 'other_charges',
        );

        $taxAmount = $this->nonNegativeMoney(
            value: $supplierInvoice->tax_amount,
            field: 'tax_amount',
        );

        $roundingAdjustment = $this->money(
            value: $supplierInvoice->rounding_adjustment,
            field: 'rounding_adjustment',
        );

        $totalAmount = $this->positiveMoney(
            value: $supplierInvoice->total_amount,
            field: 'total_amount',
        );

        /*
         * Goods Receipts carry provisional inventory cost at Purchase Order
         * net value. The invoice clears that GRNI amount. Any difference
         * between the supplier's net commercial value and the provisional
         * receipt value is posted to Purchase Price Variance. Other charges
         * remain in PPV until a landed-cost allocation module is introduced.
         */
        $purchasePriceVariance = $actualLineNetAmount
            ->minus($expectedGrniAmount)
            ->plus($otherCharges)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $reconstructedTotal = $expectedGrniAmount
            ->plus($purchasePriceVariance)
            ->plus($taxAmount)
            ->plus($roundingAdjustment)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        if (!$reconstructedTotal->isEqualTo($totalAmount)) {
            throw new LogicException(sprintf(
                'The Supplier Invoice accounting breakdown does not reconcile. Reconstructed total is %s while the invoice total is %s.',
                (string) $reconstructedTotal,
                (string) $totalAmount,
            ));
        }

        $accounts = $this->lockRequiredAccounts();
        $reference = (string) $supplierInvoice->document_number;
        $branchId = (int) $supplierInvoice->branch_id;
        $supplierId = (int) $supplierInvoice->supplier_id;
        $lines = [];

        $this->appendDebitLine(
            lines: $lines,
            account: $accounts['goods_received_not_invoiced'],
            branchId: $branchId,
            amount: $expectedGrniAmount,
            reference: $reference,
            description: sprintf(
                'Clear GRNI for Supplier Invoice %s',
                $reference,
            ),
        );

        $this->appendDebitLine(
            lines: $lines,
            account: $accounts['input_tax_receivable'],
            branchId: $branchId,
            amount: $taxAmount,
            reference: $reference,
            description: sprintf(
                'Input tax for Supplier Invoice %s',
                $reference,
            ),
        );

        $this->appendSignedLine(
            lines: $lines,
            account: $accounts['purchase_price_variance'],
            branchId: $branchId,
            signedDebitAmount: $purchasePriceVariance,
            reference: $reference,
            description: sprintf(
                'Purchase price variance and unallocated charges for Supplier Invoice %s',
                $reference,
            ),
        );

        $this->appendSignedLine(
            lines: $lines,
            account: $accounts['rounding_difference'],
            branchId: $branchId,
            signedDebitAmount: $roundingAdjustment,
            reference: $reference,
            description: sprintf(
                'Rounding difference for Supplier Invoice %s',
                $reference,
            ),
        );

        $lines[] = [
            'account_id' => $accounts[
                'accounts_payable_control'
            ]->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description(
                sprintf(
                    'Accounts Payable for Supplier Invoice %s',
                    $reference,
                ),
            ),
            'due_date' => $supplierInvoice->due_date?->toDateString(),
            'debit_amount' => '0.000000',
            'credit_amount' => (string) $totalAmount,
        ];

        if (count($lines) < 2) {
            throw new LogicException(
                'The Supplier Invoice journal does not contain enough accounting lines.',
            );
        }

        return [
            'posting_key' => $this->postingKey(
                $supplierInvoice,
            ),
            'description' => $this->description(
                sprintf(
                    'Supplier Invoice %s — %s',
                    $reference,
                    (string) $supplierInvoice->supplier_name,
                ),
            ),
            'lines' => $lines,
            'expected_grni_amount' => (string) $expectedGrniAmount,
            'purchase_price_variance_amount' =>
                (string) $purchasePriceVariance,
            'tax_amount' => (string) $taxAmount,
            'rounding_adjustment' =>
                (string) $roundingAdjustment,
            'total_amount' => (string) $totalAmount,
        ];
    }

    public function postingKey(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'supplier_invoice:%d:journal:post',
            $supplierInvoice->getKey(),
        );
    }

    public function reversalPostingKey(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'supplier_invoice:%d:journal:reverse',
            $supplierInvoice->getKey(),
        );
    }

    private function ensureExchangeRateMatchesReceiptAccrual(
        SupplierInvoice $supplierInvoice,
    ): void {
        $purchaseOrder = PurchaseOrder::query()
            ->whereKey(
                $supplierInvoice->purchase_order_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$purchaseOrder instanceof PurchaseOrder) {
            throw new LogicException(
                'The Supplier Invoice Purchase Order is unavailable.',
            );
        }

        if (
            (int) $purchaseOrder->tenant_id
                !== (int) $supplierInvoice->tenant_id
            || (int) $purchaseOrder->branch_id
                !== (int) $supplierInvoice->branch_id
            || (int) $purchaseOrder->supplier_id
                !== (int) $supplierInvoice->supplier_id
            || (string) $purchaseOrder->currency_code
                !== (string) $supplierInvoice->currency_code
        ) {
            throw new LogicException(
                'The Supplier Invoice and Purchase Order accounting context does not match.',
            );
        }

        try {
            $receiptAccrualRate = BigDecimal::of(
                (string) $purchaseOrder->exchange_rate,
            )->toScale(
                self::RATE_SCALE,
                RoundingMode::UNNECESSARY,
            );

            $invoiceRate = BigDecimal::of(
                (string) $supplierInvoice->exchange_rate,
            )->toScale(
                self::RATE_SCALE,
                RoundingMode::UNNECESSARY,
            );
        } catch (NumberFormatException|\ArithmeticException) {
            throw new LogicException(
                'The Supplier Invoice or Purchase Order exchange rate is invalid.',
            );
        }

        if (!$invoiceRate->isEqualTo($receiptAccrualRate)) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'Supplier Invoice posting is blocked because its exchange rate differs from the Goods Receipt accrual rate. Foreign-currency GRNI revaluation is not implemented yet.',
                ],
            ]);
        }
    }

    private function expectedGrniAmount(
        SupplierInvoice $supplierInvoice,
    ): BigDecimal {
        /** @var Collection<int, SupplierInvoiceMatch> $matches */
        $matches = SupplierInvoiceMatch::query()
            ->where(
                'supplier_invoice_id',
                $supplierInvoice->getKey(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'matching' => [
                    'The Supplier Invoice has no Goods Receipt matching records.',
                ],
            ]);
        }

        $goodsReceiptLineIds = $matches
            ->pluck('goods_receipt_line_id')
            ->map(
                static fn (mixed $id): int => (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        /** @var Collection<int, GoodsReceiptLine> $receiptLines */
        $receiptLines = GoodsReceiptLine::query()
            ->whereIn('id', $goodsReceiptLineIds)
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

        if ($receiptLines->count() !== count($goodsReceiptLineIds)) {
            throw ValidationException::withMessages([
                'matching' => [
                    'One or more matched Goods Receipt lines are unavailable or are no longer posted.',
                ],
            ]);
        }

        $expectedGrniAmount = $this->zeroMoney();
        $matchedQuantityTotal = $this->zeroMoney();
        $goodsReceiptIds = [];

        foreach ($matches as $match) {
            $receiptLine = $receiptLines->get(
                (int) $match->goods_receipt_line_id,
            );

            if (!$receiptLine instanceof GoodsReceiptLine) {
                throw new LogicException(
                    'A Supplier Invoice match references a missing Goods Receipt line.',
                );
            }

            if (
                (int) $match->goods_receipt_id
                    !== (int) $receiptLine->goods_receipt_id
            ) {
                throw new LogicException(
                    'A Supplier Invoice match does not agree with its Goods Receipt line.',
                );
            }

            $matchedQuantity = $this->positiveMoney(
                value: $match->matched_quantity,
                field: 'matched_quantity',
            );

            $unitCost = $this->nonNegativeMoney(
                value: $receiptLine->unit_cost,
                field: 'goods_receipt_unit_cost',
            );

            $expectedGrniAmount = $expectedGrniAmount
                ->plus(
                    $matchedQuantity
                        ->multipliedBy($unitCost)
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HALF_UP,
                        ),
                )
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );

            $matchedQuantityTotal = $matchedQuantityTotal
                ->plus($matchedQuantity)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );

            $goodsReceiptIds[] = (int) $receiptLine
                ->goods_receipt_id;
        }

        $invoiceMatchedQuantity = $this->nonNegativeMoney(
            value: $supplierInvoice->total_matched_quantity,
            field: 'total_matched_quantity',
        );

        $invoiceInvoicedQuantity = $this->nonNegativeMoney(
            value: $supplierInvoice->total_invoiced_quantity,
            field: 'total_invoiced_quantity',
        );

        if (
            !$matchedQuantityTotal->isEqualTo(
                $invoiceMatchedQuantity,
            )
            || !$invoiceMatchedQuantity->isEqualTo(
                $invoiceInvoicedQuantity,
            )
        ) {
            throw ValidationException::withMessages([
                'matching' => [
                    'The Supplier Invoice matching quantities do not reconcile with the invoice totals.',
                ],
            ]);
        }

        $this->ensureReceiptAccountingCoverage(
            array_values(
                array_unique($goodsReceiptIds),
            ),
        );

        return $expectedGrniAmount;
    }

    /**
     * @param list<int> $goodsReceiptIds
     */
    private function ensureReceiptAccountingCoverage(
        array $goodsReceiptIds,
    ): void {
        sort($goodsReceiptIds);

        $receiptMorphClass = (new GoodsReceipt())
            ->getMorphClass();

        $journalCounts = JournalEntry::query()
            ->where(
                'source_type',
                $receiptMorphClass,
            )
            ->whereIn(
                'source_id',
                $goodsReceiptIds,
            )
            ->where('journal_type', 'inventory')
            ->where('status', 'posted')
            ->orderBy('source_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id',
                'source_id',
            ])
            ->groupBy(
                static fn (JournalEntry $journal): int =>
                    (int) $journal->source_id,
            );

        $invalidReceiptIds = [];

        foreach ($goodsReceiptIds as $goodsReceiptId) {
            if ($journalCounts->get($goodsReceiptId)?->count() !== 1) {
                $invalidReceiptIds[] = $goodsReceiptId;
            }
        }

        if ($invalidReceiptIds === []) {
            return;
        }

        $receiptNumbers = GoodsReceipt::query()
            ->whereIn('id', $invalidReceiptIds)
            ->orderBy('id')
            ->pluck('receipt_number')
            ->filter(
                static fn (mixed $number): bool =>
                    is_string($number) && trim($number) !== '',
            )
            ->values()
            ->all();

        $labels = $receiptNumbers !== []
            ? implode(', ', $receiptNumbers)
            : implode(
                ', ',
                array_map(
                    static fn (int $id): string => "#{$id}",
                    $invalidReceiptIds,
                ),
            );

        throw ValidationException::withMessages([
            'accounting' => [
                "Supplier Invoice posting is blocked because the matched Goods Receipts do not each have exactly one posted inventory/GRNI journal: {$labels}.",
            ],
        ]);
    }

    /**
     * @return array{
     *     accounts_payable_control: Account,
     *     goods_received_not_invoiced: Account,
     *     input_tax_receivable: Account,
     *     purchase_price_variance: Account,
     *     rounding_difference: Account
     * }
     */
    private function lockRequiredAccounts(): array
    {
        /*
         * Resolve accounts in a stable order so concurrent accounting
         * postings acquire database locks consistently.
         */
        return [
            'accounts_payable_control' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'accounts_payable_control',
                    lockForUpdate: true,
                ),
            'goods_received_not_invoiced' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'goods_received_not_invoiced',
                    lockForUpdate: true,
                ),
            'input_tax_receivable' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'input_tax_receivable',
                    lockForUpdate: true,
                ),
            'purchase_price_variance' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'purchase_price_variance',
                    lockForUpdate: true,
                ),
            'rounding_difference' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'rounding_difference',
                    lockForUpdate: true,
                ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function appendDebitLine(
        array &$lines,
        Account $account,
        int $branchId,
        BigDecimal $amount,
        string $reference,
        string $description,
    ): void {
        if ($amount->isZero()) {
            return;
        }

        if ($amount->isNegative()) {
            throw new LogicException(
                'A fixed debit journal line cannot contain a negative amount.',
            );
        }

        $lines[] = [
            'account_id' => $account->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description($description),
            'due_date' => null,
            'debit_amount' => (string) $amount,
            'credit_amount' => '0.000000',
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function appendSignedLine(
        array &$lines,
        Account $account,
        int $branchId,
        BigDecimal $signedDebitAmount,
        string $reference,
        string $description,
    ): void {
        if ($signedDebitAmount->isZero()) {
            return;
        }

        $isDebit = $signedDebitAmount->isPositive();
        $amount = $signedDebitAmount->abs()->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $lines[] = [
            'account_id' => $account->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description($description),
            'due_date' => null,
            'debit_amount' => $isDebit
                ? (string) $amount
                : '0.000000',
            'credit_amount' => $isDebit
                ? '0.000000'
                : (string) $amount,
        ];
    }

    private function ensureInvoiceContext(
        SupplierInvoice $supplierInvoice,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if ((int) $supplierInvoice->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Supplier Invoice does not belong to the active tenant.',
            );
        }

        if (
            (int) $supplierInvoice->branch_id < 1
            || (int) $supplierInvoice->supplier_id < 1
        ) {
            throw new LogicException(
                'The Supplier Invoice does not retain a valid branch and supplier.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Invoice journal construction must run inside the source accounting transaction.',
            );
        }
    }

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->money(
            value: $value,
            field: $field,
        );

        if (!$amount->isPositive()) {
            throw new LogicException(
                "The Supplier Invoice {$field} must be greater than zero.",
            );
        }

        return $amount;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->money(
            value: $value,
            field: $field,
        );

        if ($amount->isNegative()) {
            throw new LogicException(
                "The Supplier Invoice {$field} cannot be negative.",
            );
        }

        return $amount;
    }

    private function money(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            return BigDecimal::of(
                (string) $value,
            )->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );
        } catch (NumberFormatException) {
            throw new LogicException(
                "The Supplier Invoice {$field} is not a valid decimal value.",
            );
        }
    }

    private function zeroMoney(): BigDecimal
    {
        return BigDecimal::zero()->toScale(
            self::MONEY_SCALE,
        );
    }

    private function description(string $description): string
    {
        return mb_substr(
            trim($description),
            0,
            self::MAXIMUM_DESCRIPTION_LENGTH,
        );
    }
}
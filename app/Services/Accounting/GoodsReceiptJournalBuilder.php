<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class GoodsReceiptJournalBuilder
{
    private const MONEY_SCALE = 6;

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
     *     currency_code: string,
     *     exchange_rate: string,
     *     total_receipt_value: string,
     *     inventory_value: string,
     *     non_stock_value: string,
     *     lines: list<array<string, mixed>>
     * }|null
     */
    public function buildPosting(
        GoodsReceipt $goodsReceipt,
    ): ?array {
        $this->ensureInsideTransaction();
        $this->ensureReceiptContext($goodsReceipt);

        if (!$goodsReceipt->isDraft()) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'Only a draft Goods Receipt can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$goodsReceipt->hasReceiptNumber()) {
            throw new LogicException(
                'The Goods Receipt does not retain its document number.',
            );
        }

        $purchaseOrder = $this->lockPurchaseOrder(
            $goodsReceipt,
        );

        /** @var Collection<int, GoodsReceiptLine> $lines */
        $lines = GoodsReceiptLine::query()
            ->where(
                'goods_receipt_id',
                $goodsReceipt->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            throw new LogicException(
                'The Goods Receipt has no lines available for accounting.',
            );
        }

        $inventoryValue = $this->zeroMoney();
        $nonStockValue = $this->zeroMoney();
        $acceptedQuantity = $this->zeroMoney();

        foreach ($lines as $line) {
            $lineAcceptedQuantity = $this->nonNegativeMoney(
                value: $line->accepted_quantity,
                field: "line_{$line->line_number}_accepted_quantity",
            );

            $lineUnitCost = $this->nonNegativeMoney(
                value: $line->unit_cost,
                field: "line_{$line->line_number}_unit_cost",
            );

            $lineTotalCost = $this->nonNegativeMoney(
                value: $line->total_cost,
                field: "line_{$line->line_number}_total_cost",
            );

            $calculatedLineTotal = $lineAcceptedQuantity
                ->multipliedBy($lineUnitCost)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );

            if (!$calculatedLineTotal->isEqualTo($lineTotalCost)) {
                throw new LogicException(
                    "Goods Receipt line {$line->line_number} does not reconcile accepted quantity, unit cost, and total cost.",
                );
            }

            $acceptedQuantity = $acceptedQuantity
                ->plus($lineAcceptedQuantity)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );

            if ($lineAcceptedQuantity->isZero()) {
                continue;
            }

            if ($line->isStockItem()) {
                $inventoryValue = $inventoryValue
                    ->plus($lineTotalCost)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    );

                continue;
            }

            if (
                !in_array(
                    $line->product_type,
                    [
                        'non_stock',
                        'service',
                    ],
                    true,
                )
            ) {
                throw new LogicException(
                    "Goods Receipt line {$line->line_number} uses unsupported product type [{$line->product_type}].",
                );
            }

            $nonStockValue = $nonStockValue
                ->plus($lineTotalCost)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HALF_UP,
                );
        }

        $receiptInventoryValue = $this->nonNegativeMoney(
            value: $goodsReceipt->total_inventory_value,
            field: 'total_inventory_value',
        );

        if (!$inventoryValue->isEqualTo($receiptInventoryValue)) {
            throw new LogicException(sprintf(
                'The Goods Receipt inventory value does not reconcile. Calculated value is %s while the stored value is %s.',
                (string) $inventoryValue,
                (string) $receiptInventoryValue,
            ));
        }

        $totalReceiptValue = $inventoryValue
            ->plus($nonStockValue)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        if ($acceptedQuantity->isZero()) {
            if (!$totalReceiptValue->isZero()) {
                throw new LogicException(
                    'A Goods Receipt without accepted quantity cannot have an accounting value.',
                );
            }

            return null;
        }

        if ($totalReceiptValue->isZero()) {
            /*
             * Zero-value accepted goods are valid inventory movements but do
             * not create a General Ledger amount. A later Supplier Invoice
             * cannot clear GRNI for these quantities until a non-zero value
             * is introduced through an explicit valuation workflow.
             */
            return null;
        }

        $accounts = $this->lockRequiredAccounts();
        $reference = (string) $goodsReceipt->receipt_number;
        $branchId = (int) $goodsReceipt->branch_id;
        $journalLines = [];

        $this->appendDebitLine(
            lines: $journalLines,
            account: $accounts['inventory_asset'],
            branchId: $branchId,
            amount: $inventoryValue,
            reference: $reference,
            description: sprintf(
                'Inventory received under Goods Receipt %s',
                $reference,
            ),
        );

        $this->appendDebitLine(
            lines: $journalLines,
            account: $accounts['non_stock_purchase_expense'],
            branchId: $branchId,
            amount: $nonStockValue,
            reference: $reference,
            description: sprintf(
                'Non-stock purchases and services received under Goods Receipt %s',
                $reference,
            ),
        );

        $journalLines[] = [
            'account_id' => $accounts[
                'goods_received_not_invoiced'
            ]->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description(
                sprintf(
                    'Goods Received Not Invoiced for Goods Receipt %s',
                    $reference,
                ),
            ),
            'due_date' => null,
            'debit_amount' => '0.000000',
            'credit_amount' => (string) $totalReceiptValue,
        ];

        if (count($journalLines) < 2) {
            throw new LogicException(
                'The Goods Receipt journal does not contain enough accounting lines.',
            );
        }

        return [
            'posting_key' => $this->postingKey(
                $goodsReceipt,
            ),
            'description' => $this->description(
                sprintf(
                    'Goods Receipt %s — %s',
                    $reference,
                    (string) $goodsReceipt->supplier_name,
                ),
            ),
            'currency_code' => (string) $purchaseOrder
                ->currency_code,
            'exchange_rate' => (string) $purchaseOrder
                ->exchange_rate,
            'total_receipt_value' => (string) $totalReceiptValue,
            'inventory_value' => (string) $inventoryValue,
            'non_stock_value' => (string) $nonStockValue,
            'lines' => $journalLines,
        ];
    }

    public function postingKey(
        GoodsReceipt $goodsReceipt,
    ): string {
        return sprintf(
            'goods_receipt:%d:journal:post',
            $goodsReceipt->getKey(),
        );
    }

    public function reversalPostingKey(
        GoodsReceipt $goodsReceipt,
    ): string {
        return sprintf(
            'goods_receipt:%d:journal:reverse',
            $goodsReceipt->getKey(),
        );
    }

    private function lockPurchaseOrder(
        GoodsReceipt $goodsReceipt,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::query()
            ->whereKey(
                $goodsReceipt->purchase_order_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$purchaseOrder instanceof PurchaseOrder) {
            throw new LogicException(
                'The Goods Receipt Purchase Order is unavailable.',
            );
        }

        if (
            (int) $purchaseOrder->tenant_id
                !== (int) $goodsReceipt->tenant_id
            || (int) $purchaseOrder->branch_id
                !== (int) $goodsReceipt->branch_id
            || (int) $purchaseOrder->supplier_id
                !== (int) $goodsReceipt->supplier_id
        ) {
            throw new LogicException(
                'The Goods Receipt and Purchase Order accounting context does not match.',
            );
        }

        $currencyCode = mb_strtoupper(
            trim((string) $purchaseOrder->currency_code),
        );

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new LogicException(
                'The Purchase Order does not retain a valid ISO currency code.',
            );
        }

        $exchangeRate = $this->positiveRate(
            value: $purchaseOrder->exchange_rate,
            field: 'purchase_order_exchange_rate',
        );

        $tenantCurrency = mb_strtoupper(
            trim(
                (string) $this->tenantContext
                    ->tenant()
                    ->currency_code,
            ),
        );

        if (
            $currencyCode === $tenantCurrency
            && !$exchangeRate->isEqualTo(
                BigDecimal::one()->toScale(8),
            )
        ) {
            throw new LogicException(
                'A base-currency Purchase Order must use an exchange rate of exactly 1.00000000.',
            );
        }

        return $purchaseOrder;
    }

    /**
     * @return array{
     *     goods_received_not_invoiced: Account,
     *     inventory_asset: Account,
     *     non_stock_purchase_expense: Account
     * }
     */
    private function lockRequiredAccounts(): array
    {
        return [
            'goods_received_not_invoiced' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'goods_received_not_invoiced',
                    lockForUpdate: true,
                ),
            'inventory_asset' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'inventory_asset',
                    lockForUpdate: true,
                ),
            'non_stock_purchase_expense' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'non_stock_purchase_expense',
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
                'A Goods Receipt debit line cannot contain a negative amount.',
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

    private function ensureReceiptContext(
        GoodsReceipt $goodsReceipt,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if ((int) $goodsReceipt->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Goods Receipt does not belong to the active tenant.',
            );
        }

        if (
            (int) $goodsReceipt->branch_id < 1
            || (int) $goodsReceipt->supplier_id < 1
            || (int) $goodsReceipt->purchase_order_id < 1
        ) {
            throw new LogicException(
                'The Goods Receipt does not retain a valid branch, supplier, and Purchase Order.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Goods Receipt journal construction must run inside the source transaction.',
            );
        }
    }

    private function positiveRate(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $rate = BigDecimal::of(
                (string) $value,
            )->toScale(
                8,
                RoundingMode::UNNECESSARY,
            );
        } catch (NumberFormatException|\ArithmeticException) {
            throw new LogicException(
                "The {$field} is not a valid eight-decimal exchange rate.",
            );
        }

        if (!$rate->isPositive()) {
            throw new LogicException(
                "The {$field} must be greater than zero.",
            );
        }

        return $rate;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $amount = BigDecimal::of(
                (string) $value,
            )->toScale(
                self::MONEY_SCALE,
                RoundingMode::UNNECESSARY,
            );
        } catch (NumberFormatException|\ArithmeticException) {
            throw new LogicException(
                "The Goods Receipt {$field} is not a valid six-decimal amount.",
            );
        }

        if ($amount->isNegative()) {
            throw new LogicException(
                "The Goods Receipt {$field} cannot be negative.",
            );
        }

        return $amount;
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
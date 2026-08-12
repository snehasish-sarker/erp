<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\StockLedgerEntry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PurchaseReturnJournalBuilder
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

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
     *     total_supplier_value: string,
     *     total_inventory_value: string,
     *     total_non_stock_value: string,
     *     total_cost_variance: string,
     *     source_goods_receipt_journal_id: int,
     *     lines: list<array<string, mixed>>
     * }|null
     */
    public function buildPosting(
        PurchaseReturn $purchaseReturn,
    ): ?array {
        $this->ensureInsideTransaction();
        $this->ensureReturnContext($purchaseReturn);

        if (!$purchaseReturn->isApproved()) {
            throw ValidationException::withMessages([
                'purchase_return' => [
                    'Only an approved Purchase Return can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$purchaseReturn->hasReturnNumber()) {
            throw new LogicException(
                'The approved Purchase Return does not retain its document number.',
            );
        }

        $goodsReceipt = $this->lockGoodsReceipt(
            $purchaseReturn,
        );

        $purchaseOrder = $this->lockPurchaseOrder(
            purchaseReturn: $purchaseReturn,
            goodsReceipt: $goodsReceipt,
        );

        $currencyCode = $this->currencyCode(
            $purchaseOrder->currency_code,
            'Purchase Order',
        );

        $exchangeRate = $this->exchangeRate(
            $purchaseOrder->exchange_rate,
            'Purchase Order',
        );

        $this->ensureBaseCurrencyRate(
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );

        $totals = $this->validatedTotals(
            $purchaseReturn,
        );

        $hasFinancialValue = !$totals['total_supplier_value']
            ->isZero()
            || !$totals['total_inventory_value']->isZero()
            || !$totals['total_non_stock_value']->isZero()
            || !$totals['total_cost_variance']->isZero();

        if (!$hasFinancialValue) {
            return null;
        }

        $sourceJournal = $this->lockGoodsReceiptJournal(
            goodsReceipt: $goodsReceipt,
            purchaseReturn: $purchaseReturn,
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );

        $accounts = $this->lockRequiredAccounts();
        $reference = (string) $purchaseReturn->return_number;
        $branchId = (int) $purchaseReturn->branch_id;
        $journalLines = [];

        $this->appendDebitLine(
            lines: $journalLines,
            account: $accounts['purchase_return_recovery'],
            branchId: $branchId,
            amount: $totals['total_supplier_value'],
            reference: $reference,
            description: sprintf(
                'Purchase return clearing for Purchase Return %s',
                $reference,
            ),
        );

        $this->appendCreditLine(
            lines: $journalLines,
            account: $accounts['inventory_asset'],
            branchId: $branchId,
            amount: $totals['total_inventory_value'],
            reference: $reference,
            description: sprintf(
                'Inventory removed under Purchase Return %s',
                $reference,
            ),
        );

        $this->appendCreditLine(
            lines: $journalLines,
            account: $accounts['non_stock_purchase_expense'],
            branchId: $branchId,
            amount: $totals['total_non_stock_value'],
            reference: $reference,
            description: sprintf(
                'Reverse non-stock purchase expense for Purchase Return %s',
                $reference,
            ),
        );

        $this->appendSignedCreditLine(
            lines: $journalLines,
            account: $accounts['purchase_price_variance'],
            branchId: $branchId,
            signedCreditAmount: $totals['total_cost_variance'],
            reference: $reference,
            description: sprintf(
                'Purchase return valuation variance for Purchase Return %s',
                $reference,
            ),
        );

        if (count($journalLines) < 2) {
            throw new LogicException(
                'The Purchase Return journal does not contain enough accounting lines.',
            );
        }

        return [
            'posting_key' => $this->postingKey(
                $purchaseReturn,
            ),
            'description' => $this->description(
                sprintf(
                    'Purchase Return %s — %s',
                    $reference,
                    (string) $purchaseReturn->supplier_name,
                ),
            ),
            'currency_code' => $currencyCode,
            'exchange_rate' => (string) $exchangeRate,
            'total_supplier_value' =>
                (string) $totals['total_supplier_value'],
            'total_inventory_value' =>
                (string) $totals['total_inventory_value'],
            'total_non_stock_value' =>
                (string) $totals['total_non_stock_value'],
            'total_cost_variance' =>
                (string) $totals['total_cost_variance'],
            'source_goods_receipt_journal_id' =>
                (int) $sourceJournal->getKey(),
            'lines' => $journalLines,
        ];
    }

    public function postingKey(
        PurchaseReturn $purchaseReturn,
    ): string {
        return sprintf(
            'purchase_return:%d:journal:post',
            $purchaseReturn->getKey(),
        );
    }

    public function reversalPostingKey(
        PurchaseReturn $purchaseReturn,
    ): string {
        return sprintf(
            'purchase_return:%d:journal:reverse',
            $purchaseReturn->getKey(),
        );
    }

    private function lockGoodsReceipt(
        PurchaseReturn $purchaseReturn,
    ): GoodsReceipt {
        $goodsReceipt = GoodsReceipt::query()
            ->whereKey($purchaseReturn->goods_receipt_id)
            ->lockForUpdate()
            ->first();

        if (!$goodsReceipt instanceof GoodsReceipt) {
            throw new LogicException(
                'The Purchase Return source Goods Receipt is unavailable.',
            );
        }

        if (!$goodsReceipt->isPosted()) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => [
                    'The source Goods Receipt must remain posted before the Purchase Return can be financially posted.',
                ],
            ]);
        }

        if (
            (int) $goodsReceipt->tenant_id
                !== (int) $purchaseReturn->tenant_id
            || (int) $goodsReceipt->branch_id
                !== (int) $purchaseReturn->branch_id
            || (int) $goodsReceipt->supplier_id
                !== (int) $purchaseReturn->supplier_id
            || (int) $goodsReceipt->purchase_order_id
                !== (int) $purchaseReturn->purchase_order_id
        ) {
            throw new LogicException(
                'The Purchase Return and Goods Receipt accounting context does not match.',
            );
        }

        return $goodsReceipt;
    }

    private function lockPurchaseOrder(
        PurchaseReturn $purchaseReturn,
        GoodsReceipt $goodsReceipt,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::query()
            ->whereKey($purchaseReturn->purchase_order_id)
            ->lockForUpdate()
            ->first();

        if (!$purchaseOrder instanceof PurchaseOrder) {
            throw new LogicException(
                'The Purchase Return source Purchase Order is unavailable.',
            );
        }

        if (
            (int) $purchaseOrder->tenant_id
                !== (int) $purchaseReturn->tenant_id
            || (int) $purchaseOrder->branch_id
                !== (int) $purchaseReturn->branch_id
            || (int) $purchaseOrder->supplier_id
                !== (int) $purchaseReturn->supplier_id
            || (int) $goodsReceipt->purchase_order_id
                !== (int) $purchaseOrder->getKey()
        ) {
            throw new LogicException(
                'The Purchase Return and Purchase Order accounting context does not match.',
            );
        }

        return $purchaseOrder;
    }

    /**
     * @return array{
     *     total_supplier_value: BigDecimal,
     *     total_inventory_value: BigDecimal,
     *     total_non_stock_value: BigDecimal,
     *     total_cost_variance: BigDecimal
     * }
     */
    private function validatedTotals(
        PurchaseReturn $purchaseReturn,
    ): array {
        /** @var Collection<int, PurchaseReturnLine> $lines */
        $lines = PurchaseReturnLine::query()
            ->where(
                'purchase_return_id',
                $purchaseReturn->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            throw new LogicException(
                'The approved Purchase Return has no lines.',
            );
        }

        $totalSupplierValue = $this->zeroMoney();
        $totalInventoryValue = $this->zeroMoney();
        $totalNonStockValue = $this->zeroMoney();
        $totalCostVariance = $this->zeroMoney();

        foreach ($lines as $line) {
            $supplierValue = $this->nonNegativeMoney(
                $line->supplier_total_cost,
                "line_{$line->line_number}_supplier_total_cost",
            );

            $inventoryValue = $this->nonNegativeMoney(
                $line->inventory_total_cost,
                "line_{$line->line_number}_inventory_total_cost",
            );

            $costVariance = $this->money(
                $line->cost_variance_amount,
                "line_{$line->line_number}_cost_variance_amount",
            );

            if ($line->isStockItem()) {
                $this->ensureStockLineLedgerMatches(
                    purchaseReturn: $purchaseReturn,
                    line: $line,
                    inventoryValue: $inventoryValue,
                );

                $expectedVariance = $supplierValue
                    ->minus($inventoryValue)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

                if (!$costVariance->isEqualTo($expectedVariance)) {
                    throw new LogicException(
                        "Purchase Return line {$line->line_number} does not reconcile supplier value, inventory value, and cost variance.",
                    );
                }

                $totalInventoryValue = $totalInventoryValue
                    ->plus($inventoryValue)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

                $totalCostVariance = $totalCostVariance
                    ->plus($costVariance)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );
            } elseif ($line->product_type === 'non_stock') {
                if (
                    !$inventoryValue->isZero()
                    || !$costVariance->isZero()
                ) {
                    throw new LogicException(
                        "Non-stock Purchase Return line {$line->line_number} cannot carry inventory value or inventory cost variance.",
                    );
                }

                $this->ensureNoStockLedgerEntry(
                    purchaseReturn: $purchaseReturn,
                    line: $line,
                );

                $totalNonStockValue = $totalNonStockValue
                    ->plus($supplierValue)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );
            } else {
                throw new LogicException(
                    "Purchase Return line {$line->line_number} uses unsupported product type [{$line->product_type}].",
                );
            }

            $totalSupplierValue = $totalSupplierValue
                ->plus($supplierValue)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );
        }

        $reconstructedSupplierValue = $totalInventoryValue
            ->plus($totalNonStockValue)
            ->plus($totalCostVariance)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

        if (!$reconstructedSupplierValue->isEqualTo($totalSupplierValue)) {
            throw new LogicException(
                'The Purchase Return accounting totals do not reconcile.',
            );
        }

        $this->assertEqual(
            actual: $totalSupplierValue,
            expected: $this->nonNegativeMoney(
                $purchaseReturn->total_supplier_value,
                'total_supplier_value',
            ),
            message: 'The Purchase Return supplier value does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $totalInventoryValue,
            expected: $this->nonNegativeMoney(
                $purchaseReturn->total_inventory_value,
                'total_inventory_value',
            ),
            message: 'The Purchase Return inventory value does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $totalCostVariance,
            expected: $this->money(
                $purchaseReturn->total_cost_variance,
                'total_cost_variance',
            ),
            message: 'The Purchase Return cost variance does not reconcile with its lines.',
        );

        return [
            'total_supplier_value' => $totalSupplierValue,
            'total_inventory_value' => $totalInventoryValue,
            'total_non_stock_value' => $totalNonStockValue,
            'total_cost_variance' => $totalCostVariance,
        ];
    }

    private function ensureStockLineLedgerMatches(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        BigDecimal $inventoryValue,
    ): void {
        $postingKey = sprintf(
            'purchase-return:%d:line:%d:post',
            $purchaseReturn->getKey(),
            $line->getKey(),
        );

        $entry = StockLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if (!$entry instanceof StockLedgerEntry) {
            throw new LogicException(
                "The stock ledger entry for Purchase Return line {$line->line_number} is unavailable.",
            );
        }

        $quantity = $this->positiveMoney(
            $line->return_quantity,
            "line_{$line->line_number}_return_quantity",
        );

        $ledgerQuantityOut = $this->nonNegativeMoney(
            $entry->quantity_out,
            "line_{$line->line_number}_ledger_quantity_out",
        );

        $ledgerQuantityIn = $this->nonNegativeMoney(
            $entry->quantity_in,
            "line_{$line->line_number}_ledger_quantity_in",
        );

        $ledgerValue = $this->nonNegativeMoney(
            $entry->total_cost,
            "line_{$line->line_number}_ledger_total_cost",
        );

        if (
            $entry->movement_type !== 'purchase_return'
            || $entry->source_type !== PurchaseReturn::class
            || (int) $entry->source_id
                !== (int) $purchaseReturn->getKey()
            || (int) $entry->source_line_id
                !== (int) $line->getKey()
            || (int) $entry->branch_id
                !== (int) $purchaseReturn->branch_id
            || (int) $entry->warehouse_id
                !== (int) $purchaseReturn->warehouse_id
            || (int) $entry->product_id
                !== (int) $line->product_id
            || (int) $entry->unit_id
                !== (int) $line->unit_id
            || $entry->reversal_of_id !== null
            || !$ledgerQuantityIn->isZero()
            || !$ledgerQuantityOut->isEqualTo($quantity)
            || !$ledgerValue->isEqualTo($inventoryValue)
        ) {
            throw new LogicException(
                "The stock ledger entry for Purchase Return line {$line->line_number} does not match the return line.",
            );
        }
    }

    private function ensureNoStockLedgerEntry(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
    ): void {
        $postingKey = sprintf(
            'purchase-return:%d:line:%d:post',
            $purchaseReturn->getKey(),
            $line->getKey(),
        );

        $entry = StockLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if ($entry instanceof StockLedgerEntry) {
            throw new LogicException(
                "Non-stock Purchase Return line {$line->line_number} unexpectedly created a stock ledger entry.",
            );
        }
    }

    private function lockGoodsReceiptJournal(
        GoodsReceipt $goodsReceipt,
        PurchaseReturn $purchaseReturn,
        string $currencyCode,
        BigDecimal $exchangeRate,
    ): JournalEntry {
        $journals = JournalEntry::query()
            ->where(
                'source_type',
                $goodsReceipt->getMorphClass(),
            )
            ->where(
                'source_id',
                $goodsReceipt->getKey(),
            )
            ->where('journal_type', 'inventory')
            ->where('status', 'posted')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($journals->count() !== 1) {
            throw ValidationException::withMessages([
                'accounting' => [
                    'Purchase Return posting is blocked because the source Goods Receipt does not have exactly one posted inventory and GRNI journal.',
                ],
            ]);
        }

        $journal = $journals->first();

        if (!$journal instanceof JournalEntry) {
            throw new LogicException(
                'The source Goods Receipt journal could not be resolved.',
            );
        }

        if (
            (int) $journal->branch_id
                !== (int) $purchaseReturn->branch_id
            || $this->currencyCode(
                $journal->currency_code,
                'Goods Receipt journal',
            ) !== $currencyCode
            || !$this->exchangeRate(
                $journal->exchange_rate,
                'Goods Receipt journal',
            )->isEqualTo($exchangeRate)
        ) {
            throw new LogicException(
                'The source Goods Receipt journal does not match the Purchase Return accounting context.',
            );
        }

        return $journal;
    }

    /**
     * @return array{
     *     purchase_return_recovery: Account,
     *     inventory_asset: Account,
     *     non_stock_purchase_expense: Account,
     *     purchase_price_variance: Account
     * }
     */
    private function lockRequiredAccounts(): array
    {
        return [
            'purchase_return_recovery' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'purchase_return_recovery',
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
            'purchase_price_variance' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'purchase_price_variance',
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
        $amount = $amount->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );

        if ($amount->isZero()) {
            return;
        }

        if ($amount->isNegative()) {
            throw new LogicException(
                'A Purchase Return debit line cannot contain a negative amount.',
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
    private function appendCreditLine(
        array &$lines,
        Account $account,
        int $branchId,
        BigDecimal $amount,
        string $reference,
        string $description,
    ): void {
        $amount = $amount->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );

        if ($amount->isZero()) {
            return;
        }

        if ($amount->isNegative()) {
            throw new LogicException(
                'A Purchase Return credit line cannot contain a negative amount.',
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
            'debit_amount' => '0.000000',
            'credit_amount' => (string) $amount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function appendSignedCreditLine(
        array &$lines,
        Account $account,
        int $branchId,
        BigDecimal $signedCreditAmount,
        string $reference,
        string $description,
    ): void {
        if ($signedCreditAmount->isZero()) {
            return;
        }

        $isCredit = $signedCreditAmount->isPositive();

        $amount = $signedCreditAmount->abs()->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );

        $lines[] = [
            'account_id' => $account->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description($description),
            'due_date' => null,
            'debit_amount' => $isCredit
                ? '0.000000'
                : (string) $amount,
            'credit_amount' => $isCredit
                ? (string) $amount
                : '0.000000',
        ];
    }

    private function ensureReturnContext(
        PurchaseReturn $purchaseReturn,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if ((int) $purchaseReturn->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Purchase Return does not belong to the active tenant.',
            );
        }

        if (
            (int) $purchaseReturn->branch_id < 1
            || (int) $purchaseReturn->supplier_id < 1
            || (int) $purchaseReturn->purchase_order_id < 1
            || (int) $purchaseReturn->goods_receipt_id < 1
        ) {
            throw new LogicException(
                'The Purchase Return does not retain a valid accounting source context.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Purchase Return journal construction must run inside the source transaction.',
            );
        }
    }

    private function currencyCode(
        mixed $value,
        string $source,
    ): string {
        $currencyCode = mb_strtoupper(
            trim((string) $value),
        );

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new LogicException(
                "The {$source} does not retain a valid ISO currency code.",
            );
        }

        return $currencyCode;
    }

    private function exchangeRate(
        mixed $value,
        string $source,
    ): BigDecimal {
        try {
            $exchangeRate = BigDecimal::of(
                (string) $value,
            )->toScale(
                self::EXCHANGE_RATE_SCALE,
                RoundingMode::Unnecessary,
            );
        } catch (NumberFormatException|ArithmeticException) {
            throw new LogicException(
                "The {$source} does not retain a valid eight-decimal exchange rate.",
            );
        }

        if (!$exchangeRate->isPositive()) {
            throw new LogicException(
                "The {$source} exchange rate must be greater than zero.",
            );
        }

        return $exchangeRate;
    }

    private function ensureBaseCurrencyRate(
        string $currencyCode,
        BigDecimal $exchangeRate,
    ): void {
        $tenantCurrency = $this->currencyCode(
            $this->tenantContext->tenant()->currency_code,
            'Tenant',
        );

        if (
            $currencyCode === $tenantCurrency
            && !$exchangeRate->isEqualTo(
                BigDecimal::one()->toScale(
                    self::EXCHANGE_RATE_SCALE,
                ),
            )
        ) {
            throw new LogicException(
                'A base-currency Purchase Return must use an exchange rate of exactly 1.00000000.',
            );
        }
    }

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->money($value, $field);

        if (!$amount->isPositive()) {
            throw new LogicException(
                "The Purchase Return {$field} must be greater than zero.",
            );
        }

        return $amount;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->money($value, $field);

        if ($amount->isNegative()) {
            throw new LogicException(
                "The Purchase Return {$field} cannot be negative.",
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
                RoundingMode::Unnecessary,
            );
        } catch (NumberFormatException|ArithmeticException) {
            throw new LogicException(
                "The Purchase Return {$field} is not a valid six-decimal amount.",
            );
        }
    }

    private function zeroMoney(): BigDecimal
    {
        return BigDecimal::zero()->toScale(
            self::MONEY_SCALE,
        );
    }

    private function assertEqual(
        BigDecimal $actual,
        BigDecimal $expected,
        string $message,
    ): void {
        if (!$actual->isEqualTo($expected)) {
            throw new LogicException($message);
        }
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
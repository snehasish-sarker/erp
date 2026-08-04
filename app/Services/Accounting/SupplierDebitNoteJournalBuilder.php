<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SupplierDebitNote;
use App\Models\SupplierDebitNoteLine;
use App\Models\SupplierInvoice;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierDebitNoteJournalBuilder
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
     *     lines: list<array<string, mixed>>,
     *     subtotal: string,
     *     tax_amount: string,
     *     total_amount: string,
     *     source_purchase_return_journal_id: int
     * }
     */
    public function buildPosting(
        SupplierDebitNote $supplierDebitNote,
    ): array {
        $this->ensureInsideTransaction();
        $this->ensureDebitNoteContext($supplierDebitNote);

        if (!$supplierDebitNote->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'Only an approved Supplier Debit Note can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$supplierDebitNote->hasDebitNoteNumber()) {
            throw new LogicException(
                'The approved Supplier Debit Note does not retain its document number.',
            );
        }

        $purchaseReturn = $this->lockPurchaseReturn(
            $supplierDebitNote,
        );

        $this->ensureCurrencyContext(
            supplierDebitNote: $supplierDebitNote,
            purchaseReturn: $purchaseReturn,
        );

        $sourceJournal = $this->lockPurchaseReturnJournal(
            purchaseReturn: $purchaseReturn,
            supplierDebitNote: $supplierDebitNote,
        );

        $totals = $this->validatedTotals(
            supplierDebitNote: $supplierDebitNote,
            purchaseReturn: $purchaseReturn,
        );

        $accounts = $this->lockRequiredAccounts();
        $reference = (string) $supplierDebitNote
            ->debit_note_number;
        $branchId = (int) $supplierDebitNote->branch_id;
        $supplierId = (int) $supplierDebitNote->supplier_id;

        $lines = [
            [
                'account_id' => $accounts[
                    'accounts_payable_control'
                ]->getKey(),
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'customer_id' => null,
                'reference' => $reference,
                'description' => $this->description(
                    sprintf(
                        'Accounts Payable reduction for Supplier Debit Note %s',
                        $reference,
                    ),
                ),
                'due_date' => null,
                'debit_amount' => $totals[
                    'total_amount'
                ],
                'credit_amount' => '0.000000',
            ],
        ];

        $this->appendCreditLine(
            lines: $lines,
            account: $accounts['purchase_return_recovery'],
            branchId: $branchId,
            amount: BigDecimal::of($totals['subtotal']),
            reference: $reference,
            description: sprintf(
                'Supplier commercial recovery for Purchase Return %s',
                (string) $supplierDebitNote
                    ->purchase_return_number,
            ),
        );

        $this->appendCreditLine(
            lines: $lines,
            account: $accounts['input_tax_receivable'],
            branchId: $branchId,
            amount: BigDecimal::of($totals['tax_amount']),
            reference: $reference,
            description: sprintf(
                'Input tax reversal for Supplier Debit Note %s',
                $reference,
            ),
        );

        if (count($lines) < 2) {
            throw new LogicException(
                'The Supplier Debit Note journal does not contain enough accounting lines.',
            );
        }

        return [
            'posting_key' => $this->postingKey(
                $supplierDebitNote,
            ),
            'description' => $this->description(
                sprintf(
                    'Supplier Debit Note %s — %s',
                    $reference,
                    (string) $supplierDebitNote->supplier_name,
                ),
            ),
            'lines' => $lines,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
            'source_purchase_return_journal_id' =>
                (int) $sourceJournal->getKey(),
        ];
    }

    public function postingKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier_debit_note:%d:journal:post',
            $supplierDebitNote->getKey(),
        );
    }

    public function reversalPostingKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier_debit_note:%d:journal:reverse',
            $supplierDebitNote->getKey(),
        );
    }

    private function lockPurchaseReturn(
        SupplierDebitNote $supplierDebitNote,
    ): PurchaseReturn {
        $purchaseReturn = PurchaseReturn::query()
            ->whereKey(
                $supplierDebitNote->purchase_return_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$purchaseReturn instanceof PurchaseReturn) {
            throw new LogicException(
                'The Supplier Debit Note Purchase Return is unavailable.',
            );
        }

        if (!$purchaseReturn->isPosted()) {
            throw ValidationException::withMessages([
                'purchase_return_id' => [
                    'The source Purchase Return must remain posted before the Supplier Debit Note can be financially posted.',
                ],
            ]);
        }

        if (
            (int) $purchaseReturn->tenant_id
                !== (int) $supplierDebitNote->tenant_id
            || (int) $purchaseReturn->branch_id
                !== (int) $supplierDebitNote->branch_id
            || (int) $purchaseReturn->supplier_id
                !== (int) $supplierDebitNote->supplier_id
            || (int) $purchaseReturn->purchase_order_id
                !== (int) $supplierDebitNote->purchase_order_id
            || (int) $purchaseReturn->goods_receipt_id
                !== (int) $supplierDebitNote->goods_receipt_id
            || (int) $purchaseReturn->revision
                !== (int) $supplierDebitNote
                    ->source_purchase_return_revision
        ) {
            throw new LogicException(
                'The Supplier Debit Note and Purchase Return accounting context does not match.',
            );
        }

        return $purchaseReturn;
    }

    private function ensureCurrencyContext(
        SupplierDebitNote $supplierDebitNote,
        PurchaseReturn $purchaseReturn,
    ): void {
        $purchaseOrder = PurchaseOrder::query()
            ->whereKey($purchaseReturn->purchase_order_id)
            ->lockForUpdate()
            ->first();

        if (!$purchaseOrder instanceof PurchaseOrder) {
            throw new LogicException(
                'The source Purchase Order is unavailable.',
            );
        }

        $debitNoteCurrency = $this->currencyCode(
            $supplierDebitNote->currency_code,
            'Supplier Debit Note',
        );

        $purchaseOrderCurrency = $this->currencyCode(
            $purchaseOrder->currency_code,
            'Purchase Order',
        );

        $debitNoteRate = $this->exchangeRate(
            $supplierDebitNote->exchange_rate,
            'Supplier Debit Note',
        );

        $purchaseOrderRate = $this->exchangeRate(
            $purchaseOrder->exchange_rate,
            'Purchase Order',
        );

        if (
            $debitNoteCurrency !== $purchaseOrderCurrency
            || !$debitNoteRate->isEqualTo($purchaseOrderRate)
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'Supplier Debit Note posting is blocked because its currency or exchange rate differs from the source Purchase Return accounting rate.',
                ],
            ]);
        }

        if ($supplierDebitNote->supplier_invoice_id === null) {
            return;
        }

        $supplierInvoice = SupplierInvoice::query()
            ->whereKey(
                $supplierDebitNote->supplier_invoice_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$supplierInvoice instanceof SupplierInvoice) {
            throw new LogicException(
                'The linked Supplier Invoice is unavailable.',
            );
        }

        if (
            !$supplierInvoice->isPosted()
            || (int) $supplierInvoice->tenant_id
                !== (int) $supplierDebitNote->tenant_id
            || (int) $supplierInvoice->branch_id
                !== (int) $supplierDebitNote->branch_id
            || (int) $supplierInvoice->supplier_id
                !== (int) $supplierDebitNote->supplier_id
            || (int) $supplierInvoice->purchase_order_id
                !== (int) $supplierDebitNote->purchase_order_id
            || $this->currencyCode(
                $supplierInvoice->currency_code,
                'Supplier Invoice',
            ) !== $debitNoteCurrency
            || !$this->exchangeRate(
                $supplierInvoice->exchange_rate,
                'Supplier Invoice',
            )->isEqualTo($debitNoteRate)
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The linked Supplier Invoice is not posted in the same accounting currency and exchange rate as the Supplier Debit Note.',
                ],
            ]);
        }
    }

    private function lockPurchaseReturnJournal(
        PurchaseReturn $purchaseReturn,
        SupplierDebitNote $supplierDebitNote,
    ): JournalEntry {
        $journals = JournalEntry::query()
            ->where(
                'source_type',
                $purchaseReturn->getMorphClass(),
            )
            ->where(
                'source_id',
                $purchaseReturn->getKey(),
            )
            ->where('journal_type', 'inventory')
            ->where('status', 'posted')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($journals->count() !== 1) {
            throw ValidationException::withMessages([
                'accounting' => [
                    'Supplier Debit Note posting is blocked because the source Purchase Return does not have exactly one posted inventory and return-clearing journal.',
                ],
            ]);
        }

        $journal = $journals->first();

        if (!$journal instanceof JournalEntry) {
            throw new LogicException(
                'The source Purchase Return journal could not be resolved.',
            );
        }

        if (
            (int) $journal->branch_id
                !== (int) $supplierDebitNote->branch_id
            || $this->currencyCode(
                $journal->currency_code,
                'Purchase Return journal',
            ) !== $this->currencyCode(
                $supplierDebitNote->currency_code,
                'Supplier Debit Note',
            )
            || !$this->exchangeRate(
                $journal->exchange_rate,
                'Purchase Return journal',
            )->isEqualTo(
                $this->exchangeRate(
                    $supplierDebitNote->exchange_rate,
                    'Supplier Debit Note',
                ),
            )
        ) {
            throw new LogicException(
                'The source Purchase Return journal does not match the Supplier Debit Note accounting context.',
            );
        }

        return $journal;
    }

    /**
     * @return array{
     *     subtotal: string,
     *     tax_amount: string,
     *     total_amount: string
     * }
     */
    private function validatedTotals(
        SupplierDebitNote $supplierDebitNote,
        PurchaseReturn $purchaseReturn,
    ): array {
        /** @var Collection<int, SupplierDebitNoteLine> $lines */
        $lines = SupplierDebitNoteLine::query()
            ->where(
                'supplier_debit_note_id',
                $supplierDebitNote->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            throw new LogicException(
                'The approved Supplier Debit Note has no lines.',
            );
        }

        $gross = $this->zeroMoney();
        $discount = $this->zeroMoney();
        $subtotal = $this->zeroMoney();
        $tax = $this->zeroMoney();
        $total = $this->zeroMoney();
        $sourceSupplierValue = $this->zeroMoney();
        $sourceInventoryValue = $this->zeroMoney();
        $sourceCostVariance = $this->zeroMoney();

        foreach ($lines as $line) {
            $lineGross = $this->nonNegativeMoney(
                $line->gross_amount,
                "line_{$line->line_number}_gross_amount",
            );

            $lineDiscount = $this->nonNegativeMoney(
                $line->discount_amount,
                "line_{$line->line_number}_discount_amount",
            );

            $lineSubtotal = $this->nonNegativeMoney(
                $line->subtotal,
                "line_{$line->line_number}_subtotal",
            );

            $lineTax = $this->nonNegativeMoney(
                $line->tax_amount,
                "line_{$line->line_number}_tax_amount",
            );

            $lineTotal = $this->nonNegativeMoney(
                $line->total_amount,
                "line_{$line->line_number}_total_amount",
            );

            if (
                !$lineGross->minus($lineDiscount)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->isEqualTo($lineSubtotal)
                || !$lineSubtotal->plus($lineTax)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->isEqualTo($lineTotal)
            ) {
                throw new LogicException(
                    "Supplier Debit Note line {$line->line_number} does not reconcile.",
                );
            }

            $gross = $gross->plus($lineGross);
            $discount = $discount->plus($lineDiscount);
            $subtotal = $subtotal->plus($lineSubtotal);
            $tax = $tax->plus($lineTax);
            $total = $total->plus($lineTotal);

            $sourceSupplierValue = $sourceSupplierValue->plus(
                $this->nonNegativeMoney(
                    $line->purchase_return_supplier_total_cost,
                    "line_{$line->line_number}_source_supplier_value",
                ),
            );

            $sourceInventoryValue = $sourceInventoryValue->plus(
                $this->nonNegativeMoney(
                    $line->purchase_return_inventory_total_cost,
                    "line_{$line->line_number}_source_inventory_value",
                ),
            );

            $sourceCostVariance = $sourceCostVariance->plus(
                $this->money(
                    $line->purchase_return_cost_variance,
                    "line_{$line->line_number}_source_cost_variance",
                ),
            );
        }

        $gross = $gross->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $discount = $discount->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $subtotal = $subtotal->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $tax = $tax->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $total = $total->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $sourceSupplierValue = $sourceSupplierValue->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $sourceInventoryValue = $sourceInventoryValue->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $sourceCostVariance = $sourceCostVariance->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        );

        $this->assertEqual(
            actual: $gross,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->gross_amount,
                'gross_amount',
            ),
            message: 'The Supplier Debit Note gross amount does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $discount,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->discount_amount,
                'discount_amount',
            ),
            message: 'The Supplier Debit Note discount amount does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $subtotal,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->subtotal,
                'subtotal',
            ),
            message: 'The Supplier Debit Note subtotal does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $tax,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->tax_amount,
                'tax_amount',
            ),
            message: 'The Supplier Debit Note tax amount does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $total,
            expected: $this->positiveMoney(
                $supplierDebitNote->total_amount,
                'total_amount',
            ),
            message: 'The Supplier Debit Note total does not reconcile with its lines.',
        );

        $this->assertEqual(
            actual: $sourceSupplierValue,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->purchase_return_supplier_value,
                'purchase_return_supplier_value',
            ),
            message: 'The Supplier Debit Note source supplier value does not reconcile with its line snapshots.',
        );

        $this->assertEqual(
            actual: $sourceInventoryValue,
            expected: $this->nonNegativeMoney(
                $supplierDebitNote->purchase_return_inventory_value,
                'purchase_return_inventory_value',
            ),
            message: 'The Supplier Debit Note source inventory value does not reconcile with its line snapshots.',
        );

        $this->assertEqual(
            actual: $sourceCostVariance,
            expected: $this->money(
                $supplierDebitNote->purchase_return_cost_variance,
                'purchase_return_cost_variance',
            ),
            message: 'The Supplier Debit Note source cost variance does not reconcile with its line snapshots.',
        );

        $this->assertEqual(
            actual: $sourceSupplierValue,
            expected: $this->nonNegativeMoney(
                $purchaseReturn->total_supplier_value,
                'purchase_return_total_supplier_value',
            ),
            message: 'The source Purchase Return supplier value no longer matches the Supplier Debit Note snapshot.',
        );

        $this->assertEqual(
            actual: $sourceInventoryValue,
            expected: $this->nonNegativeMoney(
                $purchaseReturn->total_inventory_value,
                'purchase_return_total_inventory_value',
            ),
            message: 'The source Purchase Return inventory value no longer matches the Supplier Debit Note snapshot.',
        );

        $this->assertEqual(
            actual: $sourceCostVariance,
            expected: $this->money(
                $purchaseReturn->total_cost_variance,
                'purchase_return_total_cost_variance',
            ),
            message: 'The source Purchase Return cost variance no longer matches the Supplier Debit Note snapshot.',
        );

        $allocated = $this->nonNegativeMoney(
            $supplierDebitNote->allocated_amount,
            'allocated_amount',
        );

        $unallocated = $this->nonNegativeMoney(
            $supplierDebitNote->unallocated_amount,
            'unallocated_amount',
        );

        if (!$allocated->plus($unallocated)->isEqualTo($total)) {
            throw new LogicException(
                'The Supplier Debit Note allocated and unallocated amounts do not reconcile with its total.',
            );
        }

        return [
            'subtotal' => (string) $subtotal,
            'tax_amount' => (string) $tax,
            'total_amount' => (string) $total,
        ];
    }

    /**
     * @return array{
     *     accounts_payable_control: Account,
     *     purchase_return_recovery: Account,
     *     input_tax_receivable: Account
     * }
     */
    private function lockRequiredAccounts(): array
    {
        return [
            'accounts_payable_control' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'accounts_payable_control',
                    lockForUpdate: true,
                ),
            'purchase_return_recovery' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'purchase_return_recovery',
                    lockForUpdate: true,
                ),
            'input_tax_receivable' =>
                $this->accountService->findSystemAccount(
                    systemKey: 'input_tax_receivable',
                    lockForUpdate: true,
                ),
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
            RoundingMode::HALF_UP,
        );

        if ($amount->isZero()) {
            return;
        }

        if ($amount->isNegative()) {
            throw new LogicException(
                'A Supplier Debit Note credit line cannot contain a negative amount.',
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

    private function ensureDebitNoteContext(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if ((int) $supplierDebitNote->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Supplier Debit Note does not belong to the active tenant.',
            );
        }

        if (
            (int) $supplierDebitNote->branch_id < 1
            || (int) $supplierDebitNote->supplier_id < 1
            || (int) $supplierDebitNote->purchase_return_id < 1
            || (int) $supplierDebitNote->purchase_order_id < 1
            || (int) $supplierDebitNote->goods_receipt_id < 1
        ) {
            throw new LogicException(
                'The Supplier Debit Note does not retain a valid accounting source context.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Debit Note journal construction must run inside the source accounting transaction.',
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
                RoundingMode::UNNECESSARY,
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

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->money($value, $field);

        if (!$amount->isPositive()) {
            throw new LogicException(
                "The Supplier Debit Note {$field} must be greater than zero.",
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
                "The Supplier Debit Note {$field} cannot be negative.",
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
                RoundingMode::UNNECESSARY,
            );
        } catch (NumberFormatException|ArithmeticException) {
            throw new LogicException(
                "The Supplier Debit Note {$field} is not a valid six-decimal amount.",
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
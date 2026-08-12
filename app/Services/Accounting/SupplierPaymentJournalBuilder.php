<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\SupplierInvoice;
use App\Models\SupplierOpenItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Support\Accounting\SupplierPaymentMethodRegistry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierPaymentJournalBuilder
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AccountService $accountService,
        private readonly SupplierPaymentMethodRegistry $methodRegistry,
    ) {
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>,
     *     total_amount: string,
     *     allocated_amount: string,
     *     unallocated_amount: string,
     *     base_total_amount: string,
     *     base_allocated_amount: string,
     *     base_unallocated_amount: string,
     *     payable_base_amount: string,
     *     exchange_difference_amount: string,
     *     allocations: list<array{
     *         supplier_payment_allocation_id: int,
     *         supplier_open_item_id: int,
     *         supplier_invoice_id: int,
     *         line_number: int,
     *         amount: string,
     *         payable_base_amount: string,
     *         credit_base_amount: string,
     *         exchange_difference_amount: string
     *     }>
     * }
     */
    public function buildPosting(
        SupplierPayment $supplierPayment,
    ): array {
        $this->ensureInsideTransaction();

        $payment = SupplierPayment::query()
            ->whereKey(
                $supplierPayment->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensurePaymentContext(
            $payment,
        );

        if (!$payment->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_payment' => [
                    'Only an approved Supplier Payment can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$payment->hasPaymentNumber()) {
            throw new LogicException(
                'The approved Supplier Payment does not retain its payment number.',
            );
        }

        $currencyCode = $this->currencyCode(
            $payment->currency_code,
        );

        $paymentExchangeRate =
            $this->positiveExchangeRate(
                $payment->exchange_rate,
            );

        $totalAmount = $this->positiveMoney(
            $payment->total_amount,
            'total_amount',
        );

        $storedAllocatedAmount =
            $this->nonNegativeMoney(
                $payment->allocated_amount,
                'allocated_amount',
            );

        $storedUnallocatedAmount =
            $this->nonNegativeMoney(
                $payment->unallocated_amount,
                'unallocated_amount',
            );

        if (
            !$storedAllocatedAmount
                ->plus($storedUnallocatedAmount)
                ->isEqualTo($totalAmount)
        ) {
            throw new LogicException(
                'The Supplier Payment allocation totals do not equal its total amount.',
            );
        }

        $paymentAccount =
            $this->lockPaymentAccount(
                payment: $payment,
                requireActive: true,
            );

        /**
         * @var Collection<int, SupplierPaymentAllocation>
         *     $paymentAllocations
         */
        $paymentAllocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $openItems =
            $this->lockPayableOpenItems(
                $paymentAllocations,
            );

        $invoices =
            $this->lockSupplierInvoices(
                $paymentAllocations,
            );

        $baseTotalAmount = $this->baseAmount(
            amount: $totalAmount,
            exchangeRate:
                $paymentExchangeRate,
        );

        $remainingPaymentAmount =
            $totalAmount;

        $remainingPaymentBaseAmount =
            $baseTotalAmount;

        $calculatedAllocatedAmount =
            $this->zeroMoney();

        $payableBaseAmount =
            $this->zeroMoney();

        $creditBaseAmount =
            $this->zeroMoney();

        $allocationSnapshots = [];

        foreach (
            $paymentAllocations
            as $allocation
        ) {
            $this->ensureDraftAllocation(
                $allocation,
            );

            $openItem = $openItems->get(
                (int) $allocation
                    ->supplier_open_item_id,
            );

            $invoice = $invoices->get(
                (int) $allocation
                    ->supplier_invoice_id,
            );

            if (
                !$openItem
                    instanceof SupplierOpenItem
            ) {
                throw new LogicException(
                    'A Supplier Payment payable open item is unavailable.',
                );
            }

            if (
                !$invoice
                    instanceof SupplierInvoice
            ) {
                throw new LogicException(
                    'A Supplier Payment invoice is unavailable.',
                );
            }

            $amount = $this->positiveMoney(
                $allocation->amount,
                'allocation_amount',
            );

            $this->validateAllocationContext(
                payment: $payment,
                allocation: $allocation,
                openItem: $openItem,
                invoice: $invoice,
                amount: $amount,
                currencyCode: $currencyCode,
                paymentExchangeRate:
                    $paymentExchangeRate,
            );

            if (
                $remainingPaymentAmount
                    ->isLessThan($amount)
            ) {
                throw new LogicException(
                    'The Supplier Payment allocation sequence exceeds the payment amount.',
                );
            }

            $allocationPayableBase =
                $this->baseAmountForOpenItemAllocation(
                    openItem: $openItem,
                    amount: $amount,
                );

            $allocationCreditBase =
                $this->baseAmountForPaymentAllocation(
                    amount: $amount,
                    remainingAmount:
                        $remainingPaymentAmount,
                    remainingBaseAmount:
                        $remainingPaymentBaseAmount,
                    paymentExchangeRate:
                        $paymentExchangeRate,
                );

            $exchangeDifference =
                $allocationPayableBase
                    ->minus(
                        $allocationCreditBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $calculatedAllocatedAmount =
                $calculatedAllocatedAmount
                    ->plus($amount)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $payableBaseAmount =
                $payableBaseAmount
                    ->plus(
                        $allocationPayableBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $creditBaseAmount =
                $creditBaseAmount
                    ->plus(
                        $allocationCreditBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $remainingPaymentAmount =
                $remainingPaymentAmount
                    ->minus($amount)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $remainingPaymentBaseAmount =
                $remainingPaymentBaseAmount
                    ->minus(
                        $allocationCreditBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $allocationSnapshots[] = [
                'supplier_payment_allocation_id' =>
                    (int) $allocation->getKey(),

                'supplier_open_item_id' =>
                    (int) $openItem->getKey(),

                'supplier_invoice_id' =>
                    (int) $invoice->getKey(),

                'line_number' =>
                    (int) $allocation
                        ->line_number,

                'amount' =>
                    $amount->__toString(),

                'payable_base_amount' =>
                    $allocationPayableBase
                        ->__toString(),

                'credit_base_amount' =>
                    $allocationCreditBase
                        ->__toString(),

                'exchange_difference_amount' =>
                    $exchangeDifference
                        ->__toString(),
            ];
        }

        if (
            !$calculatedAllocatedAmount
                ->isEqualTo(
                    $storedAllocatedAmount,
                )
            || !$remainingPaymentAmount
                ->isEqualTo(
                    $storedUnallocatedAmount,
                )
        ) {
            throw new LogicException(
                'The Supplier Payment allocation rows do not match its stored totals.',
            );
        }

        $exchangeDifferenceAmount =
            $payableBaseAmount
                ->minus($creditBaseAmount)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );

        $accounts =
            $this->lockRequiredAccounts(
                hasAllocatedAmount:
                    !$storedAllocatedAmount
                        ->isZero(),

                hasUnallocatedAmount:
                    !$storedUnallocatedAmount
                        ->isZero(),

                exchangeDifferenceAmount:
                    $exchangeDifferenceAmount,
            );

        $reference =
            (string) $payment->payment_number;

        $branchId =
            (int) $payment->branch_id;

        $supplierId =
            (int) $payment->supplier_id;

        $lines = [];

        if (!$storedAllocatedAmount->isZero()) {
            $lines[] = $this->line(
                account: $accounts[
                    'accounts_payable_control'
                ],
                branchId: $branchId,
                supplierId: $supplierId,
                reference: $reference,
                description: sprintf(
                    'Accounts Payable settlement for Supplier Payment %s',
                    $reference,
                ),
                debitAmount:
                    $storedAllocatedAmount,
                creditAmount:
                    $this->zeroMoney(),
                baseDebitAmount:
                    $payableBaseAmount,
                baseCreditAmount:
                    $this->zeroMoney(),
            );
        }

        if (!$storedUnallocatedAmount->isZero()) {
            $lines[] = $this->line(
                account:
                    $accounts[
                        'supplier_advances'
                    ],
                branchId: $branchId,
                supplierId: $supplierId,
                reference: $reference,
                description: sprintf(
                    'Unallocated supplier advance for Supplier Payment %s',
                    $reference,
                ),
                debitAmount:
                    $storedUnallocatedAmount,
                creditAmount:
                    $this->zeroMoney(),
                baseDebitAmount:
                    $remainingPaymentBaseAmount,
                baseCreditAmount:
                    $this->zeroMoney(),
            );
        }

        $lines[] = $this->line(
            account: $paymentAccount,
            branchId: $branchId,
            supplierId: null,
            reference: $reference,
            description: sprintf(
                '%s settlement for Supplier Payment %s',
                $paymentAccount->name,
                $reference,
            ),
            debitAmount:
                $this->zeroMoney(),
            creditAmount:
                $totalAmount,
            baseDebitAmount:
                $this->zeroMoney(),
            baseCreditAmount:
                $baseTotalAmount,
        );

        if (
            $exchangeDifferenceAmount
                ->isPositive()
        ) {
            $lines[] = $this->line(
                account: $accounts[
                    'realized_exchange_gain'
                ],
                branchId: $branchId,
                supplierId: null,
                reference: $reference,
                description: sprintf(
                    'Realized exchange gain on Supplier Payment %s',
                    $reference,
                ),
                debitAmount:
                    $this->zeroMoney(),
                creditAmount:
                    $this->zeroMoney(),
                baseDebitAmount:
                    $this->zeroMoney(),
                baseCreditAmount:
                    $exchangeDifferenceAmount,
            );
        } elseif (
            $exchangeDifferenceAmount
                ->isNegative()
        ) {
            $lines[] = $this->line(
                account: $accounts[
                    'realized_exchange_loss'
                ],
                branchId: $branchId,
                supplierId: null,
                reference: $reference,
                description: sprintf(
                    'Realized exchange loss on Supplier Payment %s',
                    $reference,
                ),
                debitAmount:
                    $this->zeroMoney(),
                creditAmount:
                    $this->zeroMoney(),
                baseDebitAmount:
                    $exchangeDifferenceAmount
                        ->abs(),
                baseCreditAmount:
                    $this->zeroMoney(),
            );
        }

        $this->ensureJournalBalances(
            lines: $lines,
            totalAmount: $totalAmount,
        );

        return [
            'posting_key' =>
                $this->postingKey($payment),

            'description' =>
                $this->description(
                    sprintf(
                        'Supplier Payment %s — %s',
                        $reference,
                        (string) $payment
                            ->supplier_name,
                    ),
                ),

            'lines' =>
                $lines,

            'total_amount' =>
                $totalAmount->__toString(),

            'allocated_amount' =>
                $storedAllocatedAmount
                    ->__toString(),

            'unallocated_amount' =>
                $storedUnallocatedAmount
                    ->__toString(),

            'base_total_amount' =>
                $baseTotalAmount
                    ->__toString(),

            'base_allocated_amount' =>
                $creditBaseAmount
                    ->__toString(),

            'base_unallocated_amount' =>
                $remainingPaymentBaseAmount
                    ->__toString(),

            'payable_base_amount' =>
                $payableBaseAmount
                    ->__toString(),

            'exchange_difference_amount' =>
                $exchangeDifferenceAmount
                    ->__toString(),

            'allocations' =>
                $allocationSnapshots,
        ];
    }

    public function postingKey(
        SupplierPayment $supplierPayment,
    ): string {
        return sprintf(
            'supplier_payment:%d:journal:post',
            $supplierPayment->getKey(),
        );
    }

    public function reversalPostingKey(
        SupplierPayment $supplierPayment,
    ): string {
        return sprintf(
            'supplier_payment:%d:journal:reverse',
            $supplierPayment->getKey(),
        );
    }

    /**
     * @param Collection<int, SupplierPaymentAllocation> $allocations
     * @return Collection<int, SupplierOpenItem>
     */
    private function lockPayableOpenItems(
        Collection $allocations,
    ): Collection {
        $ids = $allocations
            ->pluck('supplier_open_item_id')
            ->map(
                static fn (mixed $id): int =>
                    (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return new Collection();
        }

        /**
         * @var Collection<int, SupplierOpenItem>
         *     $openItems
         */
        $openItems =
            SupplierOpenItem::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SupplierOpenItem $openItem,
                    ): int => (int) $openItem
                        ->getKey(),
                );

        if (
            $openItems->count()
            !== count($ids)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Supplier Invoice open items are unavailable.',
                ],
            ]);
        }

        return $openItems;
    }

    /**
     * @param Collection<int, SupplierPaymentAllocation> $allocations
     * @return Collection<int, SupplierInvoice>
     */
    private function lockSupplierInvoices(
        Collection $allocations,
    ): Collection {
        $ids = $allocations
            ->pluck('supplier_invoice_id')
            ->map(
                static fn (mixed $id): int =>
                    (int) $id,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return new Collection();
        }

        /**
         * @var Collection<int, SupplierInvoice>
         *     $invoices
         */
        $invoices =
            SupplierInvoice::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SupplierInvoice $invoice,
                    ): int => (int) $invoice
                        ->getKey(),
                );

        if (
            $invoices->count()
            !== count($ids)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Supplier Invoices are unavailable.',
                ],
            ]);
        }

        return $invoices;
    }

    private function validateAllocationContext(
        SupplierPayment $payment,
        SupplierPaymentAllocation $allocation,
        SupplierOpenItem $openItem,
        SupplierInvoice $invoice,
        BigDecimal $amount,
        string $currencyCode,
        BigDecimal $paymentExchangeRate,
    ): void {
        $invoiceMorphClass =
            (new SupplierInvoice())
                ->getMorphClass();

        if (
            (int) $allocation->tenant_id
                !== (int) $payment->tenant_id
            || (int) $openItem->tenant_id
                !== (int) $payment->tenant_id
            || (int) $invoice->tenant_id
                !== (int) $payment->tenant_id
            || (int) $openItem->branch_id
                !== (int) $payment->branch_id
            || (int) $invoice->branch_id
                !== (int) $payment->branch_id
            || (int) $openItem->supplier_id
                !== (int) $payment->supplier_id
            || (int) $invoice->supplier_id
                !== (int) $payment->supplier_id
            || (int) $allocation
                ->supplier_invoice_id
                !== (int) $invoice->getKey()
            || (int) $allocation
                ->supplier_open_item_id
                !== (int) $openItem->getKey()
            || !$openItem->isInvoice()
            || $openItem->source_type
                !== $invoiceMorphClass
            || (int) $openItem->source_id
                !== (int) $invoice->getKey()
            || !$invoice->isPosted()
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'A Supplier Payment allocation no longer matches its posted Supplier Invoice payable.',
                ],
            ]);
        }

        if (
            !in_array(
                $openItem->status,
                [
                    'open',
                    'partially_settled',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'A selected Supplier Invoice open item is no longer available for payment.',
                ],
            ]);
        }

        if (
            $this->currencyCode(
                $allocation->currency_code,
            ) !== $currencyCode
            || $this->currencyCode(
                $openItem->currency_code,
            ) !== $currencyCode
            || $this->currencyCode(
                $invoice->currency_code,
            ) !== $currencyCode
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'Supplier Payment allocations must retain the same currency as the payment and invoice open items.',
                ],
            ]);
        }

        if (
            !$this->positiveExchangeRate(
                $allocation
                    ->payment_exchange_rate,
            )->isEqualTo(
                $paymentExchangeRate,
            )
        ) {
            throw new LogicException(
                'A Supplier Payment allocation does not retain the payment exchange rate.',
            );
        }

        $invoiceExchangeRate =
            $this->positiveExchangeRate(
                $openItem->exchange_rate,
            );

        if (
            !$this->positiveExchangeRate(
                $allocation
                    ->invoice_exchange_rate,
            )->isEqualTo(
                $invoiceExchangeRate,
            )
        ) {
            throw new LogicException(
                'A Supplier Payment allocation does not retain the payable exchange rate.',
            );
        }

        if (
            $this->nonNegativeMoney(
                $openItem->outstanding_amount,
                'outstanding_amount',
            )->isLessThan($amount)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'A Supplier Payment allocation exceeds the Supplier Invoice outstanding amount.',
                ],
            ]);
        }
    }

    private function ensureDraftAllocation(
        SupplierPaymentAllocation $allocation,
    ): void {
        if (
            !$allocation->isDraft()
            || $allocation
                ->supplier_open_item_allocation_id
                !== null
            || $allocation->applied_at !== null
            || $allocation->reversed_at !== null
        ) {
            throw new LogicException(
                'An approved Supplier Payment contains a non-draft allocation state.',
            );
        }
    }

    private function lockPaymentAccount(
        SupplierPayment $payment,
        bool $requireActive,
    ): Account {
        $account = Account::query()
            ->whereKey(
                $payment->payment_account_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'The Supplier Payment cash or bank account is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$account->isActive()
        ) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'The Supplier Payment cash or bank account is inactive.',
                ],
            ]);
        }

        if (!$account->isPostingAccount()) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'The Supplier Payment account must be a posting account.',
                ],
            ]);
        }

        $controlType =
            $this->methodRegistry
                ->accountControlType(
                    (string) $payment
                        ->payment_method,
                );

        if (
            $account->account_type !== 'asset'
            || $account->account_subtype
                !== $controlType
            || $account->control_type
                !== $controlType
        ) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    "The Supplier Payment method requires an active {$controlType} posting account.",
                ],
            ]);
        }

        return $account;
    }

    /**
     * @return array<string, Account>
     */
    private function lockRequiredAccounts(
        bool $hasAllocatedAmount,
        bool $hasUnallocatedAmount,
        BigDecimal $exchangeDifferenceAmount,
    ): array {
        $accounts = [];

        if ($hasAllocatedAmount) {
            $accounts[
                'accounts_payable_control'
            ] = $this->accountService
                ->findSystemAccount(
                    'accounts_payable_control',
                    true,
                );
        }

        if ($hasUnallocatedAmount) {
            $accounts[
                'supplier_advances'
            ] = $this->accountService
                ->findSystemAccount(
                    'supplier_advances',
                    true,
                );
        }

        if (
            $exchangeDifferenceAmount
                ->isPositive()
        ) {
            $accounts[
                'realized_exchange_gain'
            ] = $this->accountService
                ->findSystemAccount(
                    'realized_exchange_gain',
                    true,
                );
        } elseif (
            $exchangeDifferenceAmount
                ->isNegative()
        ) {
            $accounts[
                'realized_exchange_loss'
            ] = $this->accountService
                ->findSystemAccount(
                    'realized_exchange_loss',
                    true,
                );
        }

        return $accounts;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        Account $account,
        int $branchId,
        ?int $supplierId,
        string $reference,
        string $description,
        BigDecimal $debitAmount,
        BigDecimal $creditAmount,
        BigDecimal $baseDebitAmount,
        BigDecimal $baseCreditAmount,
    ): array {
        return [
            'account_id' =>
                $account->getKey(),

            'branch_id' =>
                $branchId,

            'supplier_id' =>
                $supplierId,

            'customer_id' =>
                null,

            'reference' =>
                $reference,

            'description' =>
                $this->description(
                    $description,
                ),

            'due_date' =>
                null,

            'debit_amount' =>
                $debitAmount->__toString(),

            'credit_amount' =>
                $creditAmount->__toString(),

            'base_debit_amount' =>
                $baseDebitAmount->__toString(),

            'base_credit_amount' =>
                $baseCreditAmount->__toString(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function ensureJournalBalances(
        array $lines,
        BigDecimal $totalAmount,
    ): void {
        $debit = $this->zeroMoney();
        $credit = $this->zeroMoney();
        $baseDebit = $this->zeroMoney();
        $baseCredit = $this->zeroMoney();

        foreach ($lines as $line) {
            $debit = $debit->plus(
                $this->nonNegativeMoney(
                    $line['debit_amount'],
                    'debit_amount',
                ),
            );

            $credit = $credit->plus(
                $this->nonNegativeMoney(
                    $line['credit_amount'],
                    'credit_amount',
                ),
            );

            $baseDebit = $baseDebit->plus(
                $this->nonNegativeMoney(
                    $line['base_debit_amount'],
                    'base_debit_amount',
                ),
            );

            $baseCredit = $baseCredit->plus(
                $this->nonNegativeMoney(
                    $line['base_credit_amount'],
                    'base_credit_amount',
                ),
            );
        }

        if (
            !$debit->isEqualTo($totalAmount)
            || !$credit->isEqualTo($totalAmount)
            || $baseDebit->isZero()
            || !$baseDebit->isEqualTo(
                $baseCredit,
            )
        ) {
            throw new LogicException(
                'The Supplier Payment journal is not balanced.',
            );
        }
    }

    private function baseAmountForOpenItemAllocation(
        SupplierOpenItem $openItem,
        BigDecimal $amount,
    ): BigDecimal {
        $outstanding =
            $this->nonNegativeMoney(
                $openItem->outstanding_amount,
                'outstanding_amount',
            );

        $baseOutstanding =
            $this->nonNegativeMoney(
                $openItem
                    ->base_outstanding_amount,
                'base_outstanding_amount',
            );

        if (
            $amount->isEqualTo(
                $outstanding,
            )
        ) {
            return $baseOutstanding;
        }

        $baseAmount = $this->baseAmount(
            amount: $amount,
            exchangeRate:
                $this->positiveExchangeRate(
                    $openItem->exchange_rate,
                ),
        );

        return $baseAmount->isGreaterThan(
            $baseOutstanding,
        )
            ? $baseOutstanding
            : $baseAmount;
    }

    private function baseAmountForPaymentAllocation(
        BigDecimal $amount,
        BigDecimal $remainingAmount,
        BigDecimal $remainingBaseAmount,
        BigDecimal $paymentExchangeRate,
    ): BigDecimal {
        if (
            $amount->isEqualTo(
                $remainingAmount,
            )
        ) {
            return $remainingBaseAmount;
        }

        $baseAmount = $this->baseAmount(
            amount: $amount,
            exchangeRate:
                $paymentExchangeRate,
        );

        return $baseAmount->isGreaterThan(
            $remainingBaseAmount,
        )
            ? $remainingBaseAmount
            : $baseAmount;
    }

    private function baseAmount(
        BigDecimal $amount,
        BigDecimal $exchangeRate,
    ): BigDecimal {
        return $amount
            ->multipliedBy($exchangeRate)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );
    }

    private function ensurePaymentContext(
        SupplierPayment $payment,
    ): void {
        $tenantId =
            $this->tenantContext->id();

        if (
            $tenantId === null
            || (int) $payment->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The Supplier Payment does not belong to the active tenant.',
            );
        }
    }

    private function currencyCode(
        mixed $value,
    ): string {
        $currencyCode = mb_strtoupper(
            trim((string) $value),
        );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw new LogicException(
                'The Supplier Payment currency code is invalid.',
            );
        }

        return $currencyCode;
    }

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->decimal(
            value: $value,
            field: $field,
            scale: self::MONEY_SCALE,
        );

        if (!$amount->isPositive()) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount must be greater than zero.',
                ],
            ]);
        }

        return $amount;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $amount = $this->decimal(
            value: $value,
            field: $field,
            scale: self::MONEY_SCALE,
        );

        if ($amount->isNegative()) {
            throw new LogicException(
                "The {$field} amount cannot be negative.",
            );
        }

        return $amount;
    }

    private function positiveExchangeRate(
        mixed $value,
    ): BigDecimal {
        $exchangeRate = $this->decimal(
            value: $value,
            field: 'exchange_rate',
            scale: self::EXCHANGE_RATE_SCALE,
        );

        if (!$exchangeRate->isPositive()) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be greater than zero.',
                ],
            ]);
        }

        return $exchangeRate;
    }

    private function decimal(
        mixed $value,
        string $field,
        int $scale,
    ): BigDecimal {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw new LogicException(
                "The {$field} value is not numeric.",
            );
        }

        try {
            return BigDecimal::of(
                (string) $value,
            )->toScale(
                $scale,
                RoundingMode::HalfUp,
            );
        } catch (ArithmeticException $exception) {
            throw new LogicException(
                "The {$field} value is invalid.",
                previous: $exception,
            );
        }
    }

    private function zeroMoney(): BigDecimal
    {
        return BigDecimal::zero()->toScale(
            self::MONEY_SCALE,
        );
    }

    private function description(
        string $description,
    ): string {
        return mb_substr(
            trim($description),
            0,
            self::MAXIMUM_DESCRIPTION_LENGTH,
        );
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Payment journal building must run inside the source document transaction.',
            );
        }
    }
}
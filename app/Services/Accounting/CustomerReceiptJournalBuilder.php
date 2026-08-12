<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\SalesInvoice;
use App\Models\CustomerOpenItem;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
use App\Support\Accounting\CustomerReceiptMethodRegistry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerReceiptJournalBuilder
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AccountService $accountService,
        private readonly CustomerReceiptMethodRegistry $methodRegistry,
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
     *     receivable_base_amount: string,
     *     exchange_difference_amount: string,
     *     allocations: list<array{
     *         customer_receipt_allocation_id: int,
     *         customer_open_item_id: int,
     *         sales_invoice_id: int,
     *         line_number: int,
     *         amount: string,
     *         receivable_base_amount: string,
     *         receipt_base_amount: string,
     *         exchange_difference_amount: string
     *     }>
     * }
     */
    public function buildPosting(
        CustomerReceipt $customerReceipt,
    ): array {
        $this->ensureInsideTransaction();

        $receipt = CustomerReceipt::query()
            ->whereKey(
                $customerReceipt->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureReceiptContext(
            $receipt,
        );

        if (!$receipt->isApproved()) {
            throw ValidationException::withMessages([
                'customer_receipt' => [
                    'Only an approved Customer Receipt can be posted to the General Ledger.',
                ],
            ]);
        }

        if (!$receipt->hasReceiptNumber()) {
            throw new LogicException(
                'The approved Customer Receipt does not retain its receipt number.',
            );
        }

        $currencyCode = $this->currencyCode(
            $receipt->currency_code,
        );

        $receiptExchangeRate =
            $this->positiveExchangeRate(
                $receipt->exchange_rate,
            );

        $totalAmount = $this->positiveMoney(
            $receipt->total_amount,
            'total_amount',
        );

        $storedAllocatedAmount =
            $this->nonNegativeMoney(
                $receipt->allocated_amount,
                'allocated_amount',
            );

        $storedUnallocatedAmount =
            $this->nonNegativeMoney(
                $receipt->unallocated_amount,
                'unallocated_amount',
            );

        if (
            !$storedAllocatedAmount
                ->plus($storedUnallocatedAmount)
                ->isEqualTo($totalAmount)
        ) {
            throw new LogicException(
                'The Customer Receipt allocation totals do not equal its total amount.',
            );
        }

        $receiptAccount =
            $this->lockReceiptAccount(
                receipt: $receipt,
                requireActive: true,
            );

        /**
         * @var Collection<int, CustomerReceiptAllocation>
         *     $receiptAllocations
         */
        $receiptAllocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $openItems =
            $this->lockReceivableOpenItems(
                $receiptAllocations,
            );

        $invoices =
            $this->lockSalesInvoices(
                $receiptAllocations,
            );

        $baseTotalAmount = $this->baseAmount(
            amount: $totalAmount,
            exchangeRate:
                $receiptExchangeRate,
        );

        $remainingReceiptAmount =
            $totalAmount;

        $remainingReceiptBaseAmount =
            $baseTotalAmount;

        $calculatedAllocatedAmount =
            $this->zeroMoney();

        $receivableBaseAmount =
            $this->zeroMoney();

        $receiptBaseAmount =
            $this->zeroMoney();

        $allocationSnapshots = [];

        foreach (
            $receiptAllocations
            as $allocation
        ) {
            $this->ensureDraftAllocation(
                $allocation,
            );

            $openItem = $openItems->get(
                (int) $allocation
                    ->customer_open_item_id,
            );

            $invoice = $invoices->get(
                (int) $allocation
                    ->sales_invoice_id,
            );

            if (
                !$openItem
                    instanceof CustomerOpenItem
            ) {
                throw new LogicException(
                    'A Customer Receipt receivable open item is unavailable.',
                );
            }

            if (
                !$invoice
                    instanceof SalesInvoice
            ) {
                throw new LogicException(
                    'A Customer Receipt invoice is unavailable.',
                );
            }

            $amount = $this->positiveMoney(
                $allocation->amount,
                'allocation_amount',
            );

            $this->validateAllocationContext(
                receipt: $receipt,
                allocation: $allocation,
                openItem: $openItem,
                invoice: $invoice,
                amount: $amount,
                currencyCode: $currencyCode,
                receiptExchangeRate:
                    $receiptExchangeRate,
            );

            if (
                $remainingReceiptAmount
                    ->isLessThan($amount)
            ) {
                throw new LogicException(
                    'The Customer Receipt allocation sequence exceeds the receipt amount.',
                );
            }

            $allocationReceivableBase =
                $this->baseAmountForOpenItemAllocation(
                    openItem: $openItem,
                    amount: $amount,
                );

            $allocationReceiptBase =
                $this->baseAmountForReceiptAllocation(
                    amount: $amount,
                    remainingAmount:
                        $remainingReceiptAmount,
                    remainingBaseAmount:
                        $remainingReceiptBaseAmount,
                    receiptExchangeRate:
                        $receiptExchangeRate,
                );

            $exchangeDifference =
                $allocationReceiptBase
                    ->minus(
                        $allocationReceivableBase,
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

            $receivableBaseAmount =
                $receivableBaseAmount
                    ->plus(
                        $allocationReceivableBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $receiptBaseAmount =
                $receiptBaseAmount
                    ->plus(
                        $allocationReceiptBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $remainingReceiptAmount =
                $remainingReceiptAmount
                    ->minus($amount)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $remainingReceiptBaseAmount =
                $remainingReceiptBaseAmount
                    ->minus(
                        $allocationReceiptBase,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $allocationSnapshots[] = [
                'customer_receipt_allocation_id' =>
                    (int) $allocation->getKey(),

                'customer_open_item_id' =>
                    (int) $openItem->getKey(),

                'sales_invoice_id' =>
                    (int) $invoice->getKey(),

                'line_number' =>
                    (int) $allocation
                        ->line_number,

                'amount' =>
                    $amount->__toString(),

                'receivable_base_amount' =>
                    $allocationReceivableBase
                        ->__toString(),

                'receipt_base_amount' =>
                    $allocationReceiptBase
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
            || !$remainingReceiptAmount
                ->isEqualTo(
                    $storedUnallocatedAmount,
                )
        ) {
            throw new LogicException(
                'The Customer Receipt allocation rows do not match its stored totals.',
            );
        }

        $exchangeDifferenceAmount =
            $receiptBaseAmount
                ->minus($receivableBaseAmount)
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
            (string) $receipt->receipt_number;

        $branchId =
            (int) $receipt->branch_id;

        $customerId =
            (int) $receipt->customer_id;

        $lines = [];

        if (!$storedAllocatedAmount->isZero()) {
            $lines[] = $this->line(
                account: $accounts[
                    'accounts_receivable_control'
                ],
                branchId: $branchId,
                customerId: $customerId,
                reference: $reference,
                description: sprintf(
                    'Accounts Receivable settlement for Customer Receipt %s',
                    $reference,
                ),
                debitAmount:
                    $this->zeroMoney(),
                creditAmount:
                    $storedAllocatedAmount,
                baseDebitAmount:
                    $this->zeroMoney(),
                baseCreditAmount:
                    $receivableBaseAmount,
            );
        }

        if (!$storedUnallocatedAmount->isZero()) {
            $lines[] = $this->line(
                account:
                    $accounts[
                        'customer_advances'
                    ],
                branchId: $branchId,
                customerId: $customerId,
                reference: $reference,
                description: sprintf(
                    'Unallocated customer advance for Customer Receipt %s',
                    $reference,
                ),
                debitAmount:
                    $this->zeroMoney(),
                creditAmount:
                    $storedUnallocatedAmount,
                baseDebitAmount:
                    $this->zeroMoney(),
                baseCreditAmount:
                    $remainingReceiptBaseAmount,
            );
        }

        $lines[] = $this->line(
            account: $receiptAccount,
            branchId: $branchId,
            customerId: null,
            reference: $reference,
            description: sprintf(
                '%s settlement for Customer Receipt %s',
                $receiptAccount->name,
                $reference,
            ),
            debitAmount:
                $totalAmount,
            creditAmount:
                $this->zeroMoney(),
            baseDebitAmount:
                $baseTotalAmount,
            baseCreditAmount:
                $this->zeroMoney(),
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
                customerId: null,
                reference: $reference,
                description: sprintf(
                    'Realized exchange gain on Customer Receipt %s',
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
                customerId: null,
                reference: $reference,
                description: sprintf(
                    'Realized exchange loss on Customer Receipt %s',
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
                $this->postingKey($receipt),

            'description' =>
                $this->description(
                    sprintf(
                        'Customer Receipt %s — %s',
                        $reference,
                        (string) $receipt
                            ->customer_name,
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
                $receiptBaseAmount
                    ->__toString(),

            'base_unallocated_amount' =>
                $remainingReceiptBaseAmount
                    ->__toString(),

            'receivable_base_amount' =>
                $receivableBaseAmount
                    ->__toString(),

            'exchange_difference_amount' =>
                $exchangeDifferenceAmount
                    ->__toString(),

            'allocations' =>
                $allocationSnapshots,
        ];
    }

    public function postingKey(
        CustomerReceipt $customerReceipt,
    ): string {
        return sprintf(
            'customer_receipt:%d:journal:post',
            $customerReceipt->getKey(),
        );
    }

    public function reversalPostingKey(
        CustomerReceipt $customerReceipt,
    ): string {
        return sprintf(
            'customer_receipt:%d:journal:reverse',
            $customerReceipt->getKey(),
        );
    }

    /**
     * @param Collection<int, CustomerReceiptAllocation> $allocations
     * @return Collection<int, CustomerOpenItem>
     */
    private function lockReceivableOpenItems(
        Collection $allocations,
    ): Collection {
        $ids = $allocations
            ->pluck('customer_open_item_id')
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
         * @var Collection<int, CustomerOpenItem>
         *     $openItems
         */
        $openItems =
            CustomerOpenItem::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        CustomerOpenItem $openItem,
                    ): int => (int) $openItem
                        ->getKey(),
                );

        if (
            $openItems->count()
            !== count($ids)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Sales Invoice open items are unavailable.',
                ],
            ]);
        }

        return $openItems;
    }

    /**
     * @param Collection<int, CustomerReceiptAllocation> $allocations
     * @return Collection<int, SalesInvoice>
     */
    private function lockSalesInvoices(
        Collection $allocations,
    ): Collection {
        $ids = $allocations
            ->pluck('sales_invoice_id')
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
         * @var Collection<int, SalesInvoice>
         *     $invoices
         */
        $invoices =
            SalesInvoice::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SalesInvoice $invoice,
                    ): int => (int) $invoice
                        ->getKey(),
                );

        if (
            $invoices->count()
            !== count($ids)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Sales Invoices are unavailable.',
                ],
            ]);
        }

        return $invoices;
    }

    private function validateAllocationContext(
        CustomerReceipt $receipt,
        CustomerReceiptAllocation $allocation,
        CustomerOpenItem $openItem,
        SalesInvoice $invoice,
        BigDecimal $amount,
        string $currencyCode,
        BigDecimal $receiptExchangeRate,
    ): void {
        $invoiceMorphClass =
            (new SalesInvoice())
                ->getMorphClass();

        if (
            (int) $allocation->tenant_id
                !== (int) $receipt->tenant_id
            || (int) $openItem->tenant_id
                !== (int) $receipt->tenant_id
            || (int) $invoice->tenant_id
                !== (int) $receipt->tenant_id
            || (int) $openItem->branch_id
                !== (int) $receipt->branch_id
            || (int) $invoice->branch_id
                !== (int) $receipt->branch_id
            || (int) $openItem->customer_id
                !== (int) $receipt->customer_id
            || (int) $invoice->customer_id
                !== (int) $receipt->customer_id
            || (int) $allocation
                ->sales_invoice_id
                !== (int) $invoice->getKey()
            || (int) $allocation
                ->customer_open_item_id
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
                    'A Customer Receipt allocation no longer matches its posted Sales Invoice receivable.',
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
                    'A selected Sales Invoice open item is no longer available for receipt.',
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
                    'Customer Receipt allocations must retain the same currency as the receipt and invoice open items.',
                ],
            ]);
        }

        if (
            !$this->positiveExchangeRate(
                $allocation
                    ->receipt_exchange_rate,
            )->isEqualTo(
                $receiptExchangeRate,
            )
        ) {
            throw new LogicException(
                'A Customer Receipt allocation does not retain the receipt exchange rate.',
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
                'A Customer Receipt allocation does not retain the receivable exchange rate.',
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
                    'A Customer Receipt allocation exceeds the Sales Invoice outstanding amount.',
                ],
            ]);
        }
    }

    private function ensureDraftAllocation(
        CustomerReceiptAllocation $allocation,
    ): void {
        if (
            !$allocation->isDraft()
            || $allocation
                ->customer_open_item_allocation_id
                !== null
            || $allocation->applied_at !== null
            || $allocation->reversed_at !== null
        ) {
            throw new LogicException(
                'An approved Customer Receipt contains a non-draft allocation state.',
            );
        }
    }

    private function lockReceiptAccount(
        CustomerReceipt $receipt,
        bool $requireActive,
    ): Account {
        $account = Account::query()
            ->whereKey(
                $receipt->receipt_account_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'The Customer Receipt cash or bank account is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$account->isActive()
        ) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'The Customer Receipt cash or bank account is inactive.',
                ],
            ]);
        }

        if (!$account->isPostingAccount()) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'The Customer Receipt account must be a posting account.',
                ],
            ]);
        }

        $controlType =
            $this->methodRegistry
                ->accountControlType(
                    (string) $receipt
                        ->receipt_method,
                );

        if (
            $account->account_type !== 'asset'
            || $account->account_subtype
                !== $controlType
            || $account->control_type
                !== $controlType
        ) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    "The Customer Receipt method requires an active {$controlType} posting account.",
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
                'accounts_receivable_control'
            ] = $this->accountService
                ->findSystemAccount(
                    'accounts_receivable_control',
                    true,
                );
        }

        if ($hasUnallocatedAmount) {
            $accounts[
                'customer_advances'
            ] = $this->accountService
                ->findSystemAccount(
                    'customer_advances',
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
        ?int $customerId,
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
                null,

            'customer_id' =>
                $customerId,

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
                'The Customer Receipt journal is not balanced.',
            );
        }
    }

    private function baseAmountForOpenItemAllocation(
        CustomerOpenItem $openItem,
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

    private function baseAmountForReceiptAllocation(
        BigDecimal $amount,
        BigDecimal $remainingAmount,
        BigDecimal $remainingBaseAmount,
        BigDecimal $receiptExchangeRate,
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
                $receiptExchangeRate,
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

    private function ensureReceiptContext(
        CustomerReceipt $receipt,
    ): void {
        $tenantId =
            $this->tenantContext->id();

        if (
            $tenantId === null
            || (int) $receipt->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The Customer Receipt does not belong to the active tenant.',
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
                'The Customer Receipt currency code is invalid.',
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
                'Customer Receipt journal building must run inside the source document transaction.',
            );
        }
    }
}
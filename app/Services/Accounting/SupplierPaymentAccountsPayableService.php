<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierOpenItem;
use App\Models\SupplierOpenItemAllocation;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Posts Supplier Payments to the Accounts Payable supplier subledger.
 *
 * This service must be called from the real Supplier Payment accounting
 * gateway inside the same transaction as the balanced General Ledger journal.
 */
final class SupplierPaymentAccountsPayableService
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly SupplierOpenItemAllocationService $allocationService,
    ) {
    }

    public function post(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference =
            $this->requiredString(
                value: $journalReference,
                field: 'journal_reference',
                maximumLength: 190,
            );

        $payment = SupplierPayment::query()
            ->whereKey(
                $supplierPayment->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            payment: $payment,
            actor: $actor,
            tenantId: $tenantId,
            requireActiveBranch: true,
        );

        $postingKey =
            $this->postingKey($payment);

        $existingEntry =
            $this->existingEntry(
                postingKey: $postingKey,
                entryType: 'payment',
                source: $payment,
                journalReference:
                    $journalReference,
            );

        if (
            $existingEntry
            instanceof SupplierLedgerEntry
        ) {
            $this->ensureExistingPostingIsComplete(
                payment: $payment,
                ledgerEntry: $existingEntry,
            );

            return $existingEntry->load([
                'accountingPeriod',
                'openItem',
                'createdBy',
            ]);
        }

        if (!$payment->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_payment' => [
                    'Only an approved Supplier Payment can be posted to Accounts Payable.',
                ],
            ]);
        }

        if (!$payment->hasPaymentNumber()) {
            throw new LogicException(
                'The approved Supplier Payment does not retain its payment number.',
            );
        }

        $postingDate = $payment
            ->posting_date
            ->toDateString();

        $this->ensureAccountingPeriod(
            accountingPeriod:
                $accountingPeriod,
            postingDate: $postingDate,
            tenantId: $tenantId,
        );

        $totalAmount =
            $this->positiveMoney(
                value:
                    $payment->total_amount,
                field: 'total_amount',
            );

        $storedAllocatedAmount =
            $this->nonNegativeMoney(
                value:
                    $payment->allocated_amount,
                field: 'allocated_amount',
            );

        $storedUnallocatedAmount =
            $this->nonNegativeMoney(
                value:
                    $payment->unallocated_amount,
                field: 'unallocated_amount',
            );

        if (
            !$storedAllocatedAmount
                ->plus(
                    $storedUnallocatedAmount,
                )
                ->isEqualTo(
                    $totalAmount,
                )
        ) {
            throw new LogicException(
                'The Supplier Payment allocation totals do not equal its total amount.',
            );
        }

        $exchangeRate =
            $this->positiveExchangeRate(
                $payment->exchange_rate,
            );

        $baseTotalAmount =
            $this->baseAmount(
                amount: $totalAmount,
                exchangeRate:
                    $exchangeRate,
            );

        $ledgerEntry =
            SupplierLedgerEntry::query()
                ->create([
                    'branch_id' =>
                        $payment->branch_id,

                    'supplier_id' =>
                        $payment->supplier_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'reference' =>
                        $this->reference(
                            $payment,
                        ),

                    'posting_key' =>
                        $postingKey,

                    'journal_reference' =>
                        $journalReference,

                    'entry_type' =>
                        'payment',

                    'source_type' =>
                        $payment
                            ->getMorphClass(),

                    'source_id' =>
                        $payment->getKey(),

                    'source_document_number' =>
                        $payment
                            ->payment_number,

                    'document_date' =>
                        $payment
                            ->payment_date
                            ->toDateString(),

                    'posting_date' =>
                        $postingDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $payment
                                ->currency_code,
                        ),

                    'exchange_rate' =>
                        $exchangeRate
                            ->__toString(),

                    'debit_amount' =>
                        $totalAmount
                            ->__toString(),

                    'credit_amount' =>
                        '0.000000',

                    'base_debit_amount' =>
                        $baseTotalAmount
                            ->__toString(),

                    'base_credit_amount' =>
                        '0.000000',

                    'description' =>
                        $this->description(
                            sprintf(
                                'Supplier Payment %s',
                                $payment
                                    ->payment_number,
                            ),
                        ),

                    'created_by_user_id' =>
                        $actor->getKey(),

                    'reversal_of_id' =>
                        null,
                ]);

        $paymentOpenItem =
            SupplierOpenItem::query()
                ->create([
                    'branch_id' =>
                        $payment->branch_id,

                    'supplier_id' =>
                        $payment->supplier_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'supplier_ledger_entry_id' =>
                        $ledgerEntry->getKey(),

                    'item_type' =>
                        'payment',

                    'source_type' =>
                        $payment
                            ->getMorphClass(),

                    'source_id' =>
                        $payment->getKey(),

                    'document_number' =>
                        $payment
                            ->payment_number,

                    'document_date' =>
                        $payment
                            ->payment_date
                            ->toDateString(),

                    'posting_date' =>
                        $postingDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $payment
                                ->currency_code,
                        ),

                    'exchange_rate' =>
                        $exchangeRate
                            ->__toString(),

                    'original_amount' =>
                        $totalAmount
                            ->__toString(),

                    'allocated_amount' =>
                        '0.000000',

                    'outstanding_amount' =>
                        $totalAmount
                            ->__toString(),

                    'base_original_amount' =>
                        $baseTotalAmount
                            ->__toString(),

                    'base_allocated_amount' =>
                        '0.000000',

                    'base_outstanding_amount' =>
                        $baseTotalAmount
                            ->__toString(),

                    'status' =>
                        'open',

                    'created_by_user_id' =>
                        $actor->getKey(),

                    'closed_at' =>
                        null,
                ]);

        $this->applyInvoiceAllocations(
            payment: $payment,
            paymentOpenItem:
                $paymentOpenItem,
            accountingPeriod:
                $accountingPeriod,
            actor: $actor,
            tenantTimezone:
                $tenant->timezone,
        );

        $paymentOpenItem->refresh();

        $this->ensurePaymentOpenItemMatchesDocument(
            payment: $payment,
            paymentOpenItem:
                $paymentOpenItem,
        );

        $payment->base_total_amount =
            $paymentOpenItem
                ->base_original_amount;

        $payment->base_allocated_amount =
            $paymentOpenItem
                ->base_allocated_amount;

        $payment->base_unallocated_amount =
            $paymentOpenItem
                ->base_outstanding_amount;

        $payment->save();

        return $ledgerEntry->load([
            'accountingPeriod',
            'openItem',
            'createdBy',
        ]);
    }

    public function reverse(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference =
            $this->requiredString(
                value: $journalReference,
                field: 'journal_reference',
                maximumLength: 190,
            );

        $reason = $this->requiredString(
            value: $reason,
            field: 'reversal_reason',
            maximumLength: 500,
        );

        $payment = SupplierPayment::query()
            ->whereKey(
                $supplierPayment->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            payment: $payment,
            actor: $actor,
            tenantId: $tenantId,
            requireActiveBranch: false,
        );

        $reversalDate =
            $this->dateString(
                value:
                    $reversalPostingDate,
                timezone:
                    $tenant->timezone,
            );

        $this->ensureAccountingPeriod(
            accountingPeriod:
                $accountingPeriod,
            postingDate:
                $reversalDate,
            tenantId:
                $tenantId,
        );

        $reversalPostingKey =
            $this->reversalPostingKey(
                $payment,
            );

        $existingReversal =
            $this->existingEntry(
                postingKey:
                    $reversalPostingKey,
                entryType:
                    'payment_reversal',
                source:
                    $payment,
                journalReference:
                    $journalReference,
            );

        if (
            $existingReversal
            instanceof SupplierLedgerEntry
        ) {
            $this->ensureExistingReversalIsComplete(
                $payment,
            );

            return $existingReversal->load([
                'accountingPeriod',
                'reversalOf',
                'createdBy',
            ]);
        }

        if (!$payment->isPosted()) {
            throw ValidationException::withMessages([
                'supplier_payment' => [
                    'Only a posted Supplier Payment can be reversed in Accounts Payable.',
                ],
            ]);
        }

        $originalEntry =
            $this->lockOriginalEntry(
                postingKey:
                    $this->postingKey(
                        $payment,
                    ),
                entryType:
                    'payment',
                payment:
                    $payment,
            );

        $paymentOpenItem =
            $this->lockOpenItem(
                ledgerEntry:
                    $originalEntry,
                itemType:
                    'payment',
            );

        $this->reverseInvoiceAllocations(
            payment: $payment,
            paymentOpenItem:
                $paymentOpenItem,
            accountingPeriod:
                $accountingPeriod,
            reversalPostingDate:
                $reversalPostingDate,
            reason: $reason,
            actor: $actor,
        );

        $paymentOpenItem->refresh();

        $this->ensurePaymentOpenItemCanBeReversed(
            paymentOpenItem:
                $paymentOpenItem,
        );

        $amount = $this->money(
            $originalEntry->debit_amount,
        );

        $baseAmount = $this->money(
            $originalEntry
                ->base_debit_amount,
        );

        $reversal =
            SupplierLedgerEntry::query()
                ->create([
                    'branch_id' =>
                        $payment->branch_id,

                    'supplier_id' =>
                        $payment->supplier_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'reference' =>
                        $this->reversalReference(
                            $payment,
                        ),

                    'posting_key' =>
                        $reversalPostingKey,

                    'journal_reference' =>
                        $journalReference,

                    'entry_type' =>
                        'payment_reversal',

                    'source_type' =>
                        $payment
                            ->getMorphClass(),

                    'source_id' =>
                        $payment->getKey(),

                    'source_document_number' =>
                        $payment
                            ->payment_number,

                    'document_date' =>
                        $payment
                            ->payment_date
                            ->toDateString(),

                    'posting_date' =>
                        $reversalDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $payment
                                ->currency_code,
                        ),

                    'exchange_rate' =>
                        $originalEntry
                            ->exchange_rate,

                    'debit_amount' =>
                        '0.000000',

                    'credit_amount' =>
                        $amount->__toString(),

                    'base_debit_amount' =>
                        '0.000000',

                    'base_credit_amount' =>
                        $baseAmount
                            ->__toString(),

                    'description' =>
                        $this->description(
                            sprintf(
                                'Reversal of Supplier Payment %s: %s',
                                $payment
                                    ->payment_number,
                                $reason,
                            ),
                        ),

                    'created_by_user_id' =>
                        $actor->getKey(),

                    'reversal_of_id' =>
                        $originalEntry
                            ->getKey(),
                ]);

        $this->markOpenItemReversed(
            $paymentOpenItem,
        );

        return $reversal->load([
            'accountingPeriod',
            'reversalOf',
            'createdBy',
        ]);
    }

    private function applyInvoiceAllocations(
        SupplierPayment $payment,
        SupplierOpenItem $paymentOpenItem,
        AccountingPeriod $accountingPeriod,
        User $actor,
        string $tenantTimezone,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $allocatedAmount =
            $this->zeroMoney();

        foreach (
            $intentAllocations
            as $intentAllocation
        ) {
            if (
                !$intentAllocation->isDraft()
                || $intentAllocation
                    ->supplier_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'An approved Supplier Payment contains a non-draft allocation state.',
                );
            }

            $payableOpenItem =
                SupplierOpenItem::query()
                    ->whereKey(
                        $intentAllocation
                            ->supplier_open_item_id,
                    )
                    ->first();

            if (
                !$payableOpenItem
                    instanceof SupplierOpenItem
            ) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'A Supplier Invoice open item is unavailable.',
                    ],
                ]);
            }

            $allocation =
                $this->allocationService
                    ->apply(
                        payableOpenItem:
                            $payableOpenItem,

                        creditOpenItem:
                            $paymentOpenItem,

                        accountingPeriod:
                            $accountingPeriod,

                        allocationType:
                            'payment',

                        postingKey:
                            $this->allocationPostingKey(
                                payment:
                                    $payment,

                                lineNumber:
                                    (int) $intentAllocation
                                        ->line_number,
                            ),

                        allocationDate:
                            $this->businessDateTime(
                                date: $payment
                                    ->payment_date
                                    ->toDateString(),

                                timezone:
                                    $tenantTimezone,
                            ),

                        postingDate:
                            $this->businessDateTime(
                                date: $payment
                                    ->posting_date
                                    ->toDateString(),

                                timezone:
                                    $tenantTimezone,
                            ),

                        amount:
                            (string) $intentAllocation
                                ->amount,

                        source:
                            $payment,

                        actor:
                            $actor,
                    );

            $this->ensureAllocationMatchesIntent(
                payment: $payment,
                intentAllocation:
                    $intentAllocation,
                paymentOpenItem:
                    $paymentOpenItem,
                allocation: $allocation,
            );

            $intentAllocation
                ->supplier_open_item_allocation_id =
                    $allocation->getKey();

            $intentAllocation
                ->payable_base_amount =
                    $allocation
                        ->payable_base_amount;

            $intentAllocation
                ->credit_base_amount =
                    $allocation
                        ->credit_base_amount;

            $intentAllocation
                ->exchange_difference_amount =
                    $allocation
                        ->exchange_difference_amount;

            $intentAllocation->status =
                'applied';

            $intentAllocation->applied_at =
                CarbonImmutable::now('UTC');

            $intentAllocation->reversed_at =
                null;

            $intentAllocation->save();

            $allocatedAmount =
                $allocatedAmount
                    ->plus(
                        $this->money(
                            $intentAllocation
                                ->amount,
                        ),
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    );
        }

        if (
            !$allocatedAmount->isEqualTo(
                $this->money(
                    $payment->allocated_amount,
                ),
            )
        ) {
            throw new LogicException(
                'The applied Supplier Payment allocations do not match the stored allocated amount.',
            );
        }
    }

    private function reverseInvoiceAllocations(
        SupplierPayment $payment,
        SupplierOpenItem $paymentOpenItem,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $expectedAllocationIds =
            $intentAllocations
                ->map(
                    static function (
                        SupplierPaymentAllocation $allocation,
                    ): int {
                        if (
                            !$allocation->isApplied()
                            || $allocation
                                ->supplier_open_item_allocation_id
                                === null
                        ) {
                            throw new LogicException(
                                'A posted Supplier Payment contains an incomplete allocation state.',
                            );
                        }

                        return (int) $allocation
                            ->supplier_open_item_allocation_id;
                    },
                )
                ->sort()
                ->values()
                ->all();

        $appliedCreditAllocations =
            SupplierOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $paymentOpenItem->getKey(),
                )
                ->where(
                    'status',
                    'applied',
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $actualAllocationIds =
            $appliedCreditAllocations
                ->pluck('id')
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->sort()
                ->values()
                ->all();

        if (
            $actualAllocationIds
            !== $expectedAllocationIds
        ) {
            throw ValidationException::withMessages([
                'supplier_payment' => [
                    'The remaining Supplier Payment credit has been consumed by a later workflow. Reverse those later allocations before reversing the payment.',
                ],
            ]);
        }

        foreach (
            $intentAllocations
            as $intentAllocation
        ) {
            $allocation =
                $appliedCreditAllocations
                    ->firstWhere(
                        'id',
                        (int) $intentAllocation
                            ->supplier_open_item_allocation_id,
                    );

            if (
                !$allocation
                    instanceof SupplierOpenItemAllocation
            ) {
                throw new LogicException(
                    'A Supplier Payment financial allocation is unavailable.',
                );
            }

            $this->ensureAllocationMatchesIntent(
                payment: $payment,
                intentAllocation:
                    $intentAllocation,
                paymentOpenItem:
                    $paymentOpenItem,
                allocation: $allocation,
            );

            $this->allocationService->reverse(
                allocation: $allocation,
                accountingPeriod:
                    $accountingPeriod,
                reversalPostingDate:
                    $reversalPostingDate,
                reason: $reason,
                actor: $actor,
            );

            $intentAllocation->status =
                'reversed';

            $intentAllocation->reversed_at =
                CarbonImmutable::now('UTC');

            $intentAllocation->save();
        }
    }

    private function ensureAllocationMatchesIntent(
        SupplierPayment $payment,
        SupplierPaymentAllocation $intentAllocation,
        SupplierOpenItem $paymentOpenItem,
        SupplierOpenItemAllocation $allocation,
    ): void {
        if (
            $allocation->allocation_type
                !== 'payment'
            || $allocation->source_type
                !== $payment->getMorphClass()
            || (int) $allocation->source_id
                !== (int) $payment->getKey()
            || (int) $allocation
                ->payable_open_item_id
                !== (int) $intentAllocation
                    ->supplier_open_item_id
            || (int) $allocation
                ->credit_open_item_id
                !== (int) $paymentOpenItem
                    ->getKey()
            || $allocation->posting_key
                !== $this->allocationPostingKey(
                    payment: $payment,

                    lineNumber:
                        (int) $intentAllocation
                            ->line_number,
                )
            || !$this->money(
                $allocation->amount,
            )->isEqualTo(
                $this->money(
                    $intentAllocation->amount,
                ),
            )
        ) {
            throw new LogicException(
                'The Supplier Payment financial allocation does not match its allocation intent.',
            );
        }
    }

    private function ensurePaymentOpenItemMatchesDocument(
        SupplierPayment $payment,
        SupplierOpenItem $paymentOpenItem,
    ): void {
        if (
            !$paymentOpenItem->isCredit()
            || $paymentOpenItem->item_type
                !== 'payment'
            || $paymentOpenItem->source_type
                !== $payment->getMorphClass()
            || (int) $paymentOpenItem->source_id
                !== (int) $payment->getKey()
            || !$this->money(
                $paymentOpenItem
                    ->original_amount,
            )->isEqualTo(
                $this->money(
                    $payment->total_amount,
                ),
            )
            || !$this->money(
                $paymentOpenItem
                    ->allocated_amount,
            )->isEqualTo(
                $this->money(
                    $payment->allocated_amount,
                ),
            )
            || !$this->money(
                $paymentOpenItem
                    ->outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $payment
                        ->unallocated_amount,
                ),
            )
        ) {
            throw new LogicException(
                'The Supplier Payment open item does not match the payment totals.',
            );
        }
    }

    private function ensurePaymentOpenItemCanBeReversed(
        SupplierOpenItem $paymentOpenItem,
    ): void {
        if (
            $paymentOpenItem->item_type
                !== 'payment'
            || $paymentOpenItem->isReversed()
            || !$this->money(
                $paymentOpenItem
                    ->allocated_amount,
            )->isZero()
            || !$this->money(
                $paymentOpenItem
                    ->outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $paymentOpenItem
                        ->original_amount,
                ),
            )
            || !$this->money(
                $paymentOpenItem
                    ->base_allocated_amount,
            )->isZero()
            || !$this->money(
                $paymentOpenItem
                    ->base_outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $paymentOpenItem
                        ->base_original_amount,
                ),
            )
            || SupplierOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $paymentOpenItem->getKey(),
                )
                ->where(
                    'status',
                    'applied',
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'supplier_payment' => [
                    'The Supplier Payment still has applied settlements. Reverse those allocations before reversing the payment.',
                ],
            ]);
        }
    }

    private function markOpenItemReversed(
        SupplierOpenItem $paymentOpenItem,
    ): void {
        $this->ensurePaymentOpenItemCanBeReversed(
            $paymentOpenItem,
        );

        $paymentOpenItem->outstanding_amount =
            '0.000000';

        $paymentOpenItem
            ->base_outstanding_amount =
                '0.000000';

        $paymentOpenItem->status =
            'reversed';

        $paymentOpenItem->closed_at =
            CarbonImmutable::now('UTC');

        $paymentOpenItem->save();
    }

    private function ensureExistingPostingIsComplete(
        SupplierPayment $payment,
        SupplierLedgerEntry $ledgerEntry,
    ): void {
        $paymentOpenItem =
            $this->lockOpenItem(
                ledgerEntry: $ledgerEntry,
                itemType: 'payment',
            );

        $this->ensurePaymentOpenItemMatchesDocument(
            payment: $payment,
            paymentOpenItem:
                $paymentOpenItem,
        );

        /**
         * @var Collection<int, SupplierPaymentAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach (
            $intentAllocations
            as $intentAllocation
        ) {
            if (
                !$intentAllocation
                    ->isApplied()
                || $intentAllocation
                    ->supplier_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The existing Supplier Payment posting is incomplete.',
                );
            }
        }
    }

    private function ensureExistingReversalIsComplete(
        SupplierPayment $payment,
    ): void {
        $originalEntry =
            $this->lockOriginalEntry(
                postingKey:
                    $this->postingKey(
                        $payment,
                    ),
                entryType: 'payment',
                payment: $payment,
            );

        $paymentOpenItem =
            $this->lockOpenItem(
                ledgerEntry:
                    $originalEntry,
                itemType:
                    'payment',
            );

        if (!$paymentOpenItem->isReversed()) {
            throw new LogicException(
                'The existing Supplier Payment reversal did not reverse its payment open item.',
            );
        }

        $hasNonReversedIntent =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->where(
                    'status',
                    '!=',
                    'reversed',
                )
                ->exists();

        if ($hasNonReversedIntent) {
            throw new LogicException(
                'The existing Supplier Payment reversal did not reverse every allocation.',
            );
        }
    }

    private function existingEntry(
        string $postingKey,
        string $entryType,
        SupplierPayment $source,
        string $journalReference,
    ): ?SupplierLedgerEntry {
        $entry = SupplierLedgerEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            !$entry
                instanceof SupplierLedgerEntry
        ) {
            return null;
        }

        if (
            $entry->entry_type
                !== $entryType
            || $entry->source_type
                !== $source->getMorphClass()
            || (int) $entry->source_id
                !== (int) $source->getKey()
            || $entry->journal_reference
                !== $journalReference
        ) {
            throw new LogicException(
                'The Supplier Payment Accounts Payable posting key already belongs to a different financial posting.',
            );
        }

        return $entry;
    }

    private function lockOriginalEntry(
        string $postingKey,
        string $entryType,
        SupplierPayment $payment,
    ): SupplierLedgerEntry {
        $entry = SupplierLedgerEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->where(
                'entry_type',
                $entryType,
            )
            ->where(
                'source_type',
                $payment->getMorphClass(),
            )
            ->where(
                'source_id',
                $payment->getKey(),
            )
            ->lockForUpdate()
            ->first();

        if (
            !$entry
                instanceof SupplierLedgerEntry
        ) {
            throw new LogicException(
                'The original Supplier Payment supplier ledger entry is unavailable.',
            );
        }

        return $entry;
    }

    private function lockOpenItem(
        SupplierLedgerEntry $ledgerEntry,
        string $itemType,
    ): SupplierOpenItem {
        $openItem =
            SupplierOpenItem::query()
                ->where(
                    'supplier_ledger_entry_id',
                    $ledgerEntry->getKey(),
                )
                ->where(
                    'item_type',
                    $itemType,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$openItem
                instanceof SupplierOpenItem
        ) {
            throw new LogicException(
                'The Supplier Payment open item is unavailable.',
            );
        }

        return $openItem;
    }

    private function ensureDocumentContext(
        SupplierPayment $payment,
        User $actor,
        int $tenantId,
        bool $requireActiveBranch,
    ): void {
        if (
            (int) $actor->tenant_id
                !== $tenantId
            || (int) $payment->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The Supplier Payment posting context contains records from different tenants.',
            );
        }

        $branch = Branch::query()
            ->whereKey(
                $payment->branch_id,
            )
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive:
                    $requireActiveBranch,
            );
    }

    private function ensureAccountingPeriod(
        AccountingPeriod $accountingPeriod,
        string $postingDate,
        int $tenantId,
    ): void {
        if (
            (int) $accountingPeriod
                ->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The accounting period does not belong to the active tenant.',
            );
        }

        if (!$accountingPeriod->isOpen()) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    "The accounting period {$accountingPeriod->code} is closed.",
                ],
            ]);
        }

        if (
            $postingDate
                < $accountingPeriod
                    ->start_date
                    ->toDateString()
            || $postingDate
                > $accountingPeriod
                    ->end_date
                    ->toDateString()
        ) {
            throw new LogicException(
                'The posting date is outside the supplied accounting period.',
            );
        }
    }

    private function postingKey(
        SupplierPayment $payment,
    ): string {
        return sprintf(
            'supplier_payment:%d:post',
            $payment->getKey(),
        );
    }

    private function reversalPostingKey(
        SupplierPayment $payment,
    ): string {
        return sprintf(
            'supplier_payment:%d:reverse',
            $payment->getKey(),
        );
    }

    private function allocationPostingKey(
        SupplierPayment $payment,
        int $lineNumber,
    ): string {
        return sprintf(
            'supplier_payment:%d:invoice_allocation:%d',
            $payment->getKey(),
            $lineNumber,
        );
    }

    private function reference(
        SupplierPayment $payment,
    ): string {
        return sprintf(
            'AP-SP-%d',
            $payment->getKey(),
        );
    }

    private function reversalReference(
        SupplierPayment $payment,
    ): string {
        return sprintf(
            'AP-SP-%d-REV',
            $payment->getKey(),
        );
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

        if (
            !$amount->isPositive()
            || $amount->isGreaterThan(
                BigDecimal::of(
                    self::MAXIMUM_AMOUNT,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount must be greater than zero and within the supported maximum.',
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

    private function money(
        mixed $value,
    ): BigDecimal {
        return $this->decimal(
            value: $value,
            field: 'amount',
            scale: self::MONEY_SCALE,
        );
    }

    private function positiveExchangeRate(
        mixed $value,
    ): BigDecimal {
        $rate = $this->decimal(
            value: $value,
            field: 'exchange_rate',
            scale:
                self::EXCHANGE_RATE_SCALE,
        );

        if (!$rate->isPositive()) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be greater than zero.',
                ],
            ]);
        }

        return $rate;
    }

    private function baseAmount(
        BigDecimal $amount,
        BigDecimal $exchangeRate,
    ): BigDecimal {
        return $amount
            ->multipliedBy($exchangeRate)
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );
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
                RoundingMode::HALF_UP,
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

    private function requiredString(
        string $value,
        string $field,
        int $maximumLength,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => [
                    'The value is required.',
                ],
            ]);
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function description(
        string $value,
    ): string {
        return mb_substr(
            trim($value),
            0,
            500,
        );
    }

    private function dateString(
        DateTimeInterface $value,
        string $timezone,
    ): string {
        return CarbonImmutable::instance(
            $value,
        )
            ->setTimezone($timezone)
            ->toDateString();
    }

    private function businessDateTime(
        string $date,
        string $timezone,
    ): CarbonImmutable {
        $dateTime =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                $timezone,
            );

        if (
            !$dateTime
                instanceof CarbonImmutable
        ) {
            throw new LogicException(
                'The Supplier Payment business date is invalid.',
            );
        }

        return $dateTime
            ->startOfDay()
            ->utc();
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Payment Accounts Payable posting must run inside the accounting database transaction.',
            );
        }
    }
}
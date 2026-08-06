<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
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
 * Posts Customer Receipts to the Accounts Receivable customer subledger.
 *
 * This service must be called from the real Customer Receipt accounting
 * gateway inside the same transaction as the balanced General Ledger journal.
 */
final class CustomerReceiptAccountsReceivableService
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly CustomerOpenItemAllocationService $allocationService,
    ) {
    }

    public function post(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): CustomerLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference =
            $this->requiredString(
                value: $journalReference,
                field: 'journal_reference',
                maximumLength: 190,
            );

        $receipt = CustomerReceipt::query()
            ->whereKey(
                $customerReceipt->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            receipt: $receipt,
            actor: $actor,
            tenantId: $tenantId,
            requireActiveBranch: true,
        );

        $postingKey =
            $this->postingKey($receipt);

        $existingEntry =
            $this->existingEntry(
                postingKey: $postingKey,
                entryType: 'receipt',
                source: $receipt,
                journalReference:
                    $journalReference,
            );

        if (
            $existingEntry
            instanceof CustomerLedgerEntry
        ) {
            $this->ensureExistingPostingIsComplete(
                receipt: $receipt,
                ledgerEntry: $existingEntry,
            );

            return $existingEntry->load([
                'accountingPeriod',
                'openItem',
                'createdBy',
            ]);
        }

        if (!$receipt->isApproved()) {
            throw ValidationException::withMessages([
                'customer_receipt' => [
                    'Only an approved Customer Receipt can be posted to Accounts Receivable.',
                ],
            ]);
        }

        if (!$receipt->hasReceiptNumber()) {
            throw new LogicException(
                'The approved Customer Receipt does not retain its receipt number.',
            );
        }

        $postingDate = $receipt
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
                    $receipt->total_amount,
                field: 'total_amount',
            );

        $storedAllocatedAmount =
            $this->nonNegativeMoney(
                value:
                    $receipt->allocated_amount,
                field: 'allocated_amount',
            );

        $storedUnallocatedAmount =
            $this->nonNegativeMoney(
                value:
                    $receipt->unallocated_amount,
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
                'The Customer Receipt allocation totals do not equal its total amount.',
            );
        }

        $exchangeRate =
            $this->positiveExchangeRate(
                $receipt->exchange_rate,
            );

        $baseTotalAmount =
            $this->baseAmount(
                amount: $totalAmount,
                exchangeRate:
                    $exchangeRate,
            );

        $ledgerEntry =
            CustomerLedgerEntry::query()
                ->create([
                    'branch_id' =>
                        $receipt->branch_id,

                    'customer_id' =>
                        $receipt->customer_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'reference' =>
                        $this->reference(
                            $receipt,
                        ),

                    'posting_key' =>
                        $postingKey,

                    'journal_reference' =>
                        $journalReference,

                    'entry_type' =>
                        'receipt',

                    'source_type' =>
                        $receipt
                            ->getMorphClass(),

                    'source_id' =>
                        $receipt->getKey(),

                    'source_document_number' =>
                        $receipt
                            ->receipt_number,

                    'document_date' =>
                        $receipt
                            ->receipt_date
                            ->toDateString(),

                    'posting_date' =>
                        $postingDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $receipt
                                ->currency_code,
                        ),

                    'exchange_rate' =>
                        $exchangeRate
                            ->__toString(),

                    'debit_amount' =>
                        '0.000000',

                    'credit_amount' =>
                        $totalAmount
                            ->__toString(),

                    'base_debit_amount' =>
                        '0.000000',

                    'base_credit_amount' =>
                        $baseTotalAmount
                            ->__toString(),

                    'description' =>
                        $this->description(
                            sprintf(
                                'Customer Receipt %s',
                                $receipt
                                    ->receipt_number,
                            ),
                        ),

                    'created_by_user_id' =>
                        $actor->getKey(),

                    'reversal_of_id' =>
                        null,
                ]);

        $receiptOpenItem =
            CustomerOpenItem::query()
                ->create([
                    'branch_id' =>
                        $receipt->branch_id,

                    'customer_id' =>
                        $receipt->customer_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'customer_ledger_entry_id' =>
                        $ledgerEntry->getKey(),

                    'item_type' =>
                        'receipt',

                    'source_type' =>
                        $receipt
                            ->getMorphClass(),

                    'source_id' =>
                        $receipt->getKey(),

                    'document_number' =>
                        $receipt
                            ->receipt_number,

                    'document_date' =>
                        $receipt
                            ->receipt_date
                            ->toDateString(),

                    'posting_date' =>
                        $postingDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $receipt
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
            receipt: $receipt,
            receiptOpenItem:
                $receiptOpenItem,
            accountingPeriod:
                $accountingPeriod,
            actor: $actor,
            tenantTimezone:
                $tenant->timezone,
        );

        $receiptOpenItem->refresh();

        $this->ensureReceiptOpenItemMatchesDocument(
            receipt: $receipt,
            receiptOpenItem:
                $receiptOpenItem,
        );

        $receipt->base_total_amount =
            $receiptOpenItem
                ->base_original_amount;

        $receipt->base_allocated_amount =
            $receiptOpenItem
                ->base_allocated_amount;

        $receipt->base_unallocated_amount =
            $receiptOpenItem
                ->base_outstanding_amount;

        $receipt->save();

        return $ledgerEntry->load([
            'accountingPeriod',
            'openItem',
            'createdBy',
        ]);
    }

    public function reverse(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): CustomerLedgerEntry {
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

        $receipt = CustomerReceipt::query()
            ->whereKey(
                $customerReceipt->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            receipt: $receipt,
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
                $receipt,
            );

        $existingReversal =
            $this->existingEntry(
                postingKey:
                    $reversalPostingKey,
                entryType:
                    'receipt_reversal',
                source:
                    $receipt,
                journalReference:
                    $journalReference,
            );

        if (
            $existingReversal
            instanceof CustomerLedgerEntry
        ) {
            $this->ensureExistingReversalIsComplete(
                $receipt,
            );

            return $existingReversal->load([
                'accountingPeriod',
                'reversalOf',
                'createdBy',
            ]);
        }

        if (!$receipt->isPosted()) {
            throw ValidationException::withMessages([
                'customer_receipt' => [
                    'Only a posted Customer Receipt can be reversed in Accounts Receivable.',
                ],
            ]);
        }

        $originalEntry =
            $this->lockOriginalEntry(
                postingKey:
                    $this->postingKey(
                        $receipt,
                    ),
                entryType:
                    'receipt',
                receipt:
                    $receipt,
            );

        $receiptOpenItem =
            $this->lockOpenItem(
                ledgerEntry:
                    $originalEntry,
                itemType:
                    'receipt',
            );

        $this->reverseInvoiceAllocations(
            receipt: $receipt,
            receiptOpenItem:
                $receiptOpenItem,
            accountingPeriod:
                $accountingPeriod,
            reversalPostingDate:
                $reversalPostingDate,
            reason: $reason,
            actor: $actor,
        );

        $receiptOpenItem->refresh();

        $this->ensureReceiptOpenItemCanBeReversed(
            receiptOpenItem:
                $receiptOpenItem,
        );

        $amount = $this->money(
            $originalEntry->credit_amount,
        );

        $baseAmount = $this->money(
            $originalEntry
                ->base_credit_amount,
        );

        $reversal =
            CustomerLedgerEntry::query()
                ->create([
                    'branch_id' =>
                        $receipt->branch_id,

                    'customer_id' =>
                        $receipt->customer_id,

                    'accounting_period_id' =>
                        $accountingPeriod
                            ->getKey(),

                    'reference' =>
                        $this->reversalReference(
                            $receipt,
                        ),

                    'posting_key' =>
                        $reversalPostingKey,

                    'journal_reference' =>
                        $journalReference,

                    'entry_type' =>
                        'receipt_reversal',

                    'source_type' =>
                        $receipt
                            ->getMorphClass(),

                    'source_id' =>
                        $receipt->getKey(),

                    'source_document_number' =>
                        $receipt
                            ->receipt_number,

                    'document_date' =>
                        $receipt
                            ->receipt_date
                            ->toDateString(),

                    'posting_date' =>
                        $reversalDate,

                    'due_date' =>
                        null,

                    'currency_code' =>
                        mb_strtoupper(
                            (string) $receipt
                                ->currency_code,
                        ),

                    'exchange_rate' =>
                        $originalEntry
                            ->exchange_rate,

                    'debit_amount' =>
                        $amount->__toString(),

                    'credit_amount' =>
                        '0.000000',

                    'base_debit_amount' =>
                        $baseAmount
                            ->__toString(),

                    'base_credit_amount' =>
                        '0.000000',

                    'description' =>
                        $this->description(
                            sprintf(
                                'Reversal of Customer Receipt %s: %s',
                                $receipt
                                    ->receipt_number,
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
            $receiptOpenItem,
        );

        return $reversal->load([
            'accountingPeriod',
            'reversalOf',
            'createdBy',
        ]);
    }

    private function applyInvoiceAllocations(
        CustomerReceipt $receipt,
        CustomerOpenItem $receiptOpenItem,
        AccountingPeriod $accountingPeriod,
        User $actor,
        string $tenantTimezone,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
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
                    ->customer_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'An approved Customer Receipt contains a non-draft allocation state.',
                );
            }

            $receivableOpenItem =
                CustomerOpenItem::query()
                    ->whereKey(
                        $intentAllocation
                            ->customer_open_item_id,
                    )
                    ->first();

            if (
                !$receivableOpenItem
                    instanceof CustomerOpenItem
            ) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'A Sales Invoice open item is unavailable.',
                    ],
                ]);
            }

            $allocation =
                $this->allocationService
                    ->apply(
                        receivableOpenItem:
                            $receivableOpenItem,

                        creditOpenItem:
                            $receiptOpenItem,

                        accountingPeriod:
                            $accountingPeriod,

                        allocationType:
                            'receipt',

                        postingKey:
                            $this->allocationPostingKey(
                                receipt:
                                    $receipt,

                                lineNumber:
                                    (int) $intentAllocation
                                        ->line_number,
                            ),

                        allocationDate:
                            $this->businessDateTime(
                                date: $receipt
                                    ->receipt_date
                                    ->toDateString(),

                                timezone:
                                    $tenantTimezone,
                            ),

                        postingDate:
                            $this->businessDateTime(
                                date: $receipt
                                    ->posting_date
                                    ->toDateString(),

                                timezone:
                                    $tenantTimezone,
                            ),

                        amount:
                            (string) $intentAllocation
                                ->amount,

                        source:
                            $receipt,

                        actor:
                            $actor,
                    );

            $this->ensureAllocationMatchesIntent(
                receipt: $receipt,
                intentAllocation:
                    $intentAllocation,
                receiptOpenItem:
                    $receiptOpenItem,
                allocation: $allocation,
            );

            $intentAllocation
                ->customer_open_item_allocation_id =
                    $allocation->getKey();

            $intentAllocation
                ->receivable_base_amount =
                    $allocation
                        ->receivable_base_amount;

            $intentAllocation
                ->receipt_base_amount =
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
                    $receipt->allocated_amount,
                ),
            )
        ) {
            throw new LogicException(
                'The applied Customer Receipt allocations do not match the stored allocated amount.',
            );
        }
    }

    private function reverseInvoiceAllocations(
        CustomerReceipt $receipt,
        CustomerOpenItem $receiptOpenItem,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $expectedAllocationIds =
            $intentAllocations
                ->map(
                    static function (
                        CustomerReceiptAllocation $allocation,
                    ): int {
                        if (
                            !$allocation->isApplied()
                            || $allocation
                                ->customer_open_item_allocation_id
                                === null
                        ) {
                            throw new LogicException(
                                'A posted Customer Receipt contains an incomplete allocation state.',
                            );
                        }

                        return (int) $allocation
                            ->customer_open_item_allocation_id;
                    },
                )
                ->sort()
                ->values()
                ->all();

        $appliedCreditAllocations =
            CustomerOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $receiptOpenItem->getKey(),
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
                'customer_receipt' => [
                    'The remaining Customer Receipt credit has been consumed by a later workflow. Reverse those later allocations before reversing the receipt.',
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
                            ->customer_open_item_allocation_id,
                    );

            if (
                !$allocation
                    instanceof CustomerOpenItemAllocation
            ) {
                throw new LogicException(
                    'A Customer Receipt financial allocation is unavailable.',
                );
            }

            $this->ensureAllocationMatchesIntent(
                receipt: $receipt,
                intentAllocation:
                    $intentAllocation,
                receiptOpenItem:
                    $receiptOpenItem,
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
        CustomerReceipt $receipt,
        CustomerReceiptAllocation $intentAllocation,
        CustomerOpenItem $receiptOpenItem,
        CustomerOpenItemAllocation $allocation,
    ): void {
        if (
            $allocation->allocation_type
                !== 'receipt'
            || $allocation->source_type
                !== $receipt->getMorphClass()
            || (int) $allocation->source_id
                !== (int) $receipt->getKey()
            || (int) $allocation
                ->receivable_open_item_id
                !== (int) $intentAllocation
                    ->customer_open_item_id
            || (int) $allocation
                ->credit_open_item_id
                !== (int) $receiptOpenItem
                    ->getKey()
            || $allocation->posting_key
                !== $this->allocationPostingKey(
                    receipt: $receipt,

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
                'The Customer Receipt financial allocation does not match its allocation intent.',
            );
        }
    }

    private function ensureReceiptOpenItemMatchesDocument(
        CustomerReceipt $receipt,
        CustomerOpenItem $receiptOpenItem,
    ): void {
        if (
            !$receiptOpenItem->isCredit()
            || $receiptOpenItem->item_type
                !== 'receipt'
            || $receiptOpenItem->source_type
                !== $receipt->getMorphClass()
            || (int) $receiptOpenItem->source_id
                !== (int) $receipt->getKey()
            || !$this->money(
                $receiptOpenItem
                    ->original_amount,
            )->isEqualTo(
                $this->money(
                    $receipt->total_amount,
                ),
            )
            || !$this->money(
                $receiptOpenItem
                    ->allocated_amount,
            )->isEqualTo(
                $this->money(
                    $receipt->allocated_amount,
                ),
            )
            || !$this->money(
                $receiptOpenItem
                    ->outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $receipt
                        ->unallocated_amount,
                ),
            )
        ) {
            throw new LogicException(
                'The Customer Receipt open item does not match the receipt totals.',
            );
        }
    }

    private function ensureReceiptOpenItemCanBeReversed(
        CustomerOpenItem $receiptOpenItem,
    ): void {
        if (
            $receiptOpenItem->item_type
                !== 'receipt'
            || $receiptOpenItem->isReversed()
            || !$this->money(
                $receiptOpenItem
                    ->allocated_amount,
            )->isZero()
            || !$this->money(
                $receiptOpenItem
                    ->outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $receiptOpenItem
                        ->original_amount,
                ),
            )
            || !$this->money(
                $receiptOpenItem
                    ->base_allocated_amount,
            )->isZero()
            || !$this->money(
                $receiptOpenItem
                    ->base_outstanding_amount,
            )->isEqualTo(
                $this->money(
                    $receiptOpenItem
                        ->base_original_amount,
                ),
            )
            || CustomerOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $receiptOpenItem->getKey(),
                )
                ->where(
                    'status',
                    'applied',
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'customer_receipt' => [
                    'The Customer Receipt still has applied settlements. Reverse those allocations before reversing the receipt.',
                ],
            ]);
        }
    }

    private function markOpenItemReversed(
        CustomerOpenItem $receiptOpenItem,
    ): void {
        $this->ensureReceiptOpenItemCanBeReversed(
            $receiptOpenItem,
        );

        $receiptOpenItem->outstanding_amount =
            '0.000000';

        $receiptOpenItem
            ->base_outstanding_amount =
                '0.000000';

        $receiptOpenItem->status =
            'reversed';

        $receiptOpenItem->closed_at =
            CarbonImmutable::now('UTC');

        $receiptOpenItem->save();
    }

    private function ensureExistingPostingIsComplete(
        CustomerReceipt $receipt,
        CustomerLedgerEntry $ledgerEntry,
    ): void {
        $receiptOpenItem =
            $this->lockOpenItem(
                ledgerEntry: $ledgerEntry,
                itemType: 'receipt',
            );

        $this->ensureReceiptOpenItemMatchesDocument(
            receipt: $receipt,
            receiptOpenItem:
                $receiptOpenItem,
        );

        /**
         * @var Collection<int, CustomerReceiptAllocation>
         *     $intentAllocations
         */
        $intentAllocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
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
                    ->customer_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The existing Customer Receipt posting is incomplete.',
                );
            }
        }
    }

    private function ensureExistingReversalIsComplete(
        CustomerReceipt $receipt,
    ): void {
        $originalEntry =
            $this->lockOriginalEntry(
                postingKey:
                    $this->postingKey(
                        $receipt,
                    ),
                entryType: 'receipt',
                receipt: $receipt,
            );

        $receiptOpenItem =
            $this->lockOpenItem(
                ledgerEntry:
                    $originalEntry,
                itemType:
                    'receipt',
            );

        if (!$receiptOpenItem->isReversed()) {
            throw new LogicException(
                'The existing Customer Receipt reversal did not reverse its receipt open item.',
            );
        }

        $hasNonReversedIntent =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->where(
                    'status',
                    '!=',
                    'reversed',
                )
                ->exists();

        if ($hasNonReversedIntent) {
            throw new LogicException(
                'The existing Customer Receipt reversal did not reverse every allocation.',
            );
        }
    }

    private function existingEntry(
        string $postingKey,
        string $entryType,
        CustomerReceipt $source,
        string $journalReference,
    ): ?CustomerLedgerEntry {
        $entry = CustomerLedgerEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            !$entry
                instanceof CustomerLedgerEntry
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
                'The Customer Receipt Accounts Receivable posting key already belongs to a different financial posting.',
            );
        }

        return $entry;
    }

    private function lockOriginalEntry(
        string $postingKey,
        string $entryType,
        CustomerReceipt $receipt,
    ): CustomerLedgerEntry {
        $entry = CustomerLedgerEntry::query()
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
                $receipt->getMorphClass(),
            )
            ->where(
                'source_id',
                $receipt->getKey(),
            )
            ->lockForUpdate()
            ->first();

        if (
            !$entry
                instanceof CustomerLedgerEntry
        ) {
            throw new LogicException(
                'The original Customer Receipt customer ledger entry is unavailable.',
            );
        }

        return $entry;
    }

    private function lockOpenItem(
        CustomerLedgerEntry $ledgerEntry,
        string $itemType,
    ): CustomerOpenItem {
        $openItem =
            CustomerOpenItem::query()
                ->where(
                    'customer_ledger_entry_id',
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
                instanceof CustomerOpenItem
        ) {
            throw new LogicException(
                'The Customer Receipt open item is unavailable.',
            );
        }

        return $openItem;
    }

    private function ensureDocumentContext(
        CustomerReceipt $receipt,
        User $actor,
        int $tenantId,
        bool $requireActiveBranch,
    ): void {
        if (
            (int) $actor->tenant_id
                !== $tenantId
            || (int) $receipt->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The Customer Receipt posting context contains records from different tenants.',
            );
        }

        $branch = Branch::query()
            ->whereKey(
                $receipt->branch_id,
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
        CustomerReceipt $receipt,
    ): string {
        return sprintf(
            'customer_receipt:%d:post',
            $receipt->getKey(),
        );
    }

    private function reversalPostingKey(
        CustomerReceipt $receipt,
    ): string {
        return sprintf(
            'customer_receipt:%d:reverse',
            $receipt->getKey(),
        );
    }

    private function allocationPostingKey(
        CustomerReceipt $receipt,
        int $lineNumber,
    ): string {
        return sprintf(
            'customer_receipt:%d:invoice_allocation:%d',
            $receipt->getKey(),
            $lineNumber,
        );
    }

    private function reference(
        CustomerReceipt $receipt,
    ): string {
        return sprintf(
            'AR-CR-%d',
            $receipt->getKey(),
        );
    }

    private function reversalReference(
        CustomerReceipt $receipt,
    ): string {
        return sprintf(
            'AR-CR-%d-REV',
            $receipt->getKey(),
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
                'The Customer Receipt business date is invalid.',
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
                'Customer Receipt Accounts Receivable posting must run inside the accounting database transaction.',
            );
        }
    }
}
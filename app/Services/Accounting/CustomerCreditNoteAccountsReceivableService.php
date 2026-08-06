<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerCreditNote;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerCreditNoteAccountsReceivableService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CustomerOpenItemAllocationService $allocationService,
    ) {
    }

    /**
     * @return array{
     *     ledger_entry: CustomerLedgerEntry,
     *     open_item: CustomerOpenItem,
     *     allocation: CustomerOpenItemAllocation|null
     * }
     */
    public function post(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): array {
        $this->ensureInsideTransaction();
        $this->ensureContext($creditNote, $accountingPeriod, $actor);

        $postingKey = $this->postingKey($creditNote);

        $existing = CustomerLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof CustomerLedgerEntry) {
            $openItem = CustomerOpenItem::query()
                ->where('customer_ledger_entry_id', $existing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allocation = CustomerOpenItemAllocation::query()
                ->where('source_type', $creditNote->getMorphClass())
                ->where('source_id', $creditNote->getKey())
                ->where('allocation_type', 'credit_note')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            return [
                'ledger_entry' => $existing,
                'open_item' => $openItem,
                'allocation' => $allocation,
            ];
        }

        $amount = BigDecimal::of((string) $creditNote->total_amount)
            ->toScale(self::SCALE, RoundingMode::UNNECESSARY);

        if (!$amount->isGreaterThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                'total_amount' => [
                    'The Customer Credit Note total must be greater than zero.',
                ],
            ]);
        }

        $baseAmount = $amount
            ->multipliedBy(
                BigDecimal::of((string) $creditNote->exchange_rate),
            )
            ->toScale(self::SCALE, RoundingMode::HALF_UP);

        $ledger = CustomerLedgerEntry::query()->create([
            'branch_id' => $creditNote->branch_id,
            'customer_id' => $creditNote->customer_id,
            'accounting_period_id' => $accountingPeriod->getKey(),
            'reference' => sprintf(
                'customer-credit-note:%d:ledger:post',
                (int) $creditNote->getKey(),
            ),
            'posting_key' => $postingKey,
            'journal_reference' => $journalReference,
            'entry_type' => 'credit_note',
            'source_type' => $creditNote->getMorphClass(),
            'source_id' => $creditNote->getKey(),
            'source_document_number' => $creditNote->credit_note_number,
            'document_date' => $creditNote->credit_note_date,
            'posting_date' => $creditNote->posting_date,
            'due_date' => null,
            'currency_code' => $creditNote->currency_code,
            'exchange_rate' => $creditNote->exchange_rate,
            'debit_amount' => '0.000000',
            'credit_amount' => $amount->__toString(),
            'base_debit_amount' => '0.000000',
            'base_credit_amount' => $baseAmount->__toString(),
            'description' => mb_substr(
                sprintf(
                    'Customer Credit Note %s — %s',
                    (string) $creditNote->credit_note_number,
                    (string) $creditNote->customer_name,
                ),
                0,
                500,
            ),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => null,
        ]);

        $creditOpenItem = CustomerOpenItem::query()->create([
            'branch_id' => $creditNote->branch_id,
            'customer_id' => $creditNote->customer_id,
            'accounting_period_id' => $accountingPeriod->getKey(),
            'customer_ledger_entry_id' => $ledger->getKey(),
            'item_type' => 'credit',
            'source_type' => $creditNote->getMorphClass(),
            'source_id' => $creditNote->getKey(),
            'document_number' => $creditNote->credit_note_number,
            'document_date' => $creditNote->credit_note_date,
            'posting_date' => $creditNote->posting_date,
            'due_date' => null,
            'currency_code' => $creditNote->currency_code,
            'exchange_rate' => $creditNote->exchange_rate,
            'original_amount' => $amount->__toString(),
            'allocated_amount' => '0.000000',
            'outstanding_amount' => $amount->__toString(),
            'base_original_amount' => $baseAmount->__toString(),
            'base_allocated_amount' => '0.000000',
            'base_outstanding_amount' => $baseAmount->__toString(),
            'status' => 'open',
            'created_by_user_id' => $actor->getKey(),
            'closed_at' => null,
        ]);

        $invoice = SalesInvoice::query()
            ->whereKey($creditNote->sales_invoice_id)
            ->lockForUpdate()
            ->first();

        if (!$invoice instanceof SalesInvoice || !$invoice->isPosted()) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => [
                    'The source Sales Invoice must remain posted while the Customer Credit Note is posted.',
                ],
            ]);
        }

        $invoiceOpenItem = CustomerOpenItem::query()
            ->where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->getKey())
            ->where('item_type', 'invoice')
            ->lockForUpdate()
            ->first();

        $allocation = null;

        if (
            $invoiceOpenItem instanceof CustomerOpenItem
            && !$invoiceOpenItem->isReversed()
        ) {
            $invoiceOutstanding = BigDecimal::of(
                (string) $invoiceOpenItem->outstanding_amount,
            );

            $allocationAmount = $invoiceOutstanding->isLessThan($amount)
                ? $invoiceOutstanding
                : $amount;

            if ($allocationAmount->isGreaterThan(BigDecimal::zero())) {
                $allocation = $this->allocationService->apply(
                    receivableOpenItem: $invoiceOpenItem,
                    creditOpenItem: $creditOpenItem,
                    accountingPeriod: $accountingPeriod,
                    allocationType: 'credit_note',
                    postingKey: $this->allocationPostingKey($creditNote),
                    allocationDate: $creditNote->credit_note_date,
                    postingDate: $creditNote->posting_date,
                    amount: $allocationAmount
                        ->toScale(self::SCALE, RoundingMode::HALF_UP)
                        ->__toString(),
                    source: $creditNote,
                    actor: $actor,
                );
            }
        }

        return [
            'ledger_entry' => $ledger,
            'open_item' => $creditOpenItem->refresh(),
            'allocation' => $allocation,
        ];
    }

    public function reverse(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): CustomerLedgerEntry {
        $this->ensureInsideTransaction();
        $this->ensureContext($creditNote, $accountingPeriod, $actor);

        $original = CustomerLedgerEntry::query()
            ->where('posting_key', $this->postingKey($creditNote))
            ->lockForUpdate()
            ->first();

        if (!$original instanceof CustomerLedgerEntry) {
            throw new LogicException(
                'The original Customer Credit Note ledger entry is unavailable.',
            );
        }

        $existing = CustomerLedgerEntry::query()
            ->where('posting_key', $this->reversalPostingKey($creditNote))
            ->lockForUpdate()
            ->first();

        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }

        $creditOpenItem = CustomerOpenItem::query()
            ->where('customer_ledger_entry_id', $original->getKey())
            ->lockForUpdate()
            ->first();

        if (!$creditOpenItem instanceof CustomerOpenItem) {
            throw new LogicException(
                'The Customer Credit Note open item is unavailable.',
            );
        }

        $appliedAllocations = CustomerOpenItemAllocation::query()
            ->where('credit_open_item_id', $creditOpenItem->getKey())
            ->where('status', 'applied')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($appliedAllocations as $allocation) {
            if (
                $creditNote->customer_open_item_allocation_id === null
                || (int) $allocation->getKey()
                    !== (int) $creditNote->customer_open_item_allocation_id
                || $allocation->source_type
                    !== $creditNote->getMorphClass()
                || (int) $allocation->source_id
                    !== (int) $creditNote->getKey()
            ) {
                throw ValidationException::withMessages([
                    'customer_credit_note' => [
                        'The Customer Credit Note cannot be reversed because its remaining credit has been allocated by a later transaction.',
                    ],
                ]);
            }

            $this->allocationService->reverse(
                allocation: $allocation,
                accountingPeriod: $accountingPeriod,
                reversalPostingDate: $reversalPostingDate,
                reason: $reason,
                actor: $actor,
            );
        }

        $creditOpenItem->refresh();

        if (
            (string) $creditOpenItem->allocated_amount !== '0.000000'
            || (string) $creditOpenItem->outstanding_amount
                !== (string) $creditOpenItem->original_amount
        ) {
            throw new LogicException(
                'The Customer Credit Note open item did not return to its original balance before reversal.',
            );
        }

        $reversal = CustomerLedgerEntry::query()->create([
            'branch_id' => $creditNote->branch_id,
            'customer_id' => $creditNote->customer_id,
            'accounting_period_id' => $accountingPeriod->getKey(),
            'reference' => sprintf(
                'customer-credit-note:%d:ledger:reverse',
                (int) $creditNote->getKey(),
            ),
            'posting_key' => $this->reversalPostingKey($creditNote),
            'journal_reference' => $journalReference,
            'entry_type' => 'credit_note_reversal',
            'source_type' => $creditNote->getMorphClass(),
            'source_id' => $creditNote->getKey(),
            'source_document_number' => $creditNote->credit_note_number,
            'document_date' => $creditNote->credit_note_date,
            'posting_date' => $reversalPostingDate,
            'due_date' => null,
            'currency_code' => $creditNote->currency_code,
            'exchange_rate' => $creditNote->exchange_rate,
            'debit_amount' => (string) $original->credit_amount,
            'credit_amount' => '0.000000',
            'base_debit_amount' => (string) $original->base_credit_amount,
            'base_credit_amount' => '0.000000',
            'description' => mb_substr(
                sprintf(
                    'Reverse Customer Credit Note %s — %s',
                    (string) $creditNote->credit_note_number,
                    trim($reason),
                ),
                0,
                500,
            ),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => $original->getKey(),
        ]);

        $creditOpenItem->outstanding_amount = '0.000000';
        $creditOpenItem->base_outstanding_amount = '0.000000';
        $creditOpenItem->status = 'reversed';
        $creditOpenItem->closed_at = now();
        $creditOpenItem->save();

        return $reversal;
    }

    public function postingKey(CustomerCreditNote $creditNote): string
    {
        return sprintf(
            'customer_credit_note:%d:customer_ledger:post',
            (int) $creditNote->getKey(),
        );
    }

    public function reversalPostingKey(CustomerCreditNote $creditNote): string
    {
        return sprintf(
            'customer_credit_note:%d:customer_ledger:reverse',
            (int) $creditNote->getKey(),
        );
    }

    public function allocationPostingKey(CustomerCreditNote $creditNote): string
    {
        return sprintf(
            'customer_credit_note:%d:source_invoice:auto_allocate',
            (int) $creditNote->getKey(),
        );
    }

    private function ensureContext(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if (
            (int) $creditNote->tenant_id !== $tenantId
            || (int) $accountingPeriod->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'Customer Credit Note Accounts Receivable posting crossed a tenant boundary.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Credit Note Accounts Receivable posting must run inside the source transaction.',
            );
        }
    }
}
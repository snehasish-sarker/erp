<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\SupplierDebitNote;
use App\Models\SupplierDebitNoteAllocation;
use App\Models\SupplierInvoice;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierOpenItem;
use App\Models\SupplierOpenItemAllocation;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Posts the Accounts Payable supplier subledger and open items.
 *
 * This service deliberately does not create general-ledger journals. A real
 * accounting gateway must first create a complete balanced journal and then
 * call this service inside the very same database transaction.
 */
final class AccountsPayablePostingService
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

    public function postSupplierInvoice(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference = $this->requiredString(
            value: $journalReference,
            field: 'journal_reference',
            maximumLength: 190,
        );

        $lockedInvoice = SupplierInvoice::query()
            ->whereKey($supplierInvoice->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            document: $lockedInvoice,
            actor: $actor,
            tenantId: $tenantId,
            branchId: (int) $lockedInvoice->branch_id,
            requireActiveBranch: true,
        );

        $postingKey = $this->invoicePostingKey(
            $lockedInvoice,
        );

        $existingEntry = $this->existingEntry(
            postingKey: $postingKey,
            entryType: 'invoice',
            source: $lockedInvoice,
            journalReference: $journalReference,
        );

        if ($existingEntry instanceof SupplierLedgerEntry) {
            $this->ensureOpenItemExists(
                ledgerEntry: $existingEntry,
                itemType: 'invoice',
            );

            return $existingEntry->load([
                'accountingPeriod',
                'openItem',
                'createdBy',
            ]);
        }

        if (!$lockedInvoice->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'Only an approved Supplier Invoice can be posted to Accounts Payable.',
                ],
            ]);
        }

        if (!$lockedInvoice->hasDocumentNumber()) {
            throw new LogicException(
                'The approved Supplier Invoice does not retain its document number.',
            );
        }

        $postingDate = $lockedInvoice
            ->posting_date
            ->toDateString();

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $postingDate,
            tenantId: $tenantId,
        );

        $amount = $this->positiveMoney(
            value: $lockedInvoice->total_amount,
            field: 'total_amount',
        );

        $exchangeRate = $this->positiveExchangeRate(
            $lockedInvoice->exchange_rate,
        );

        $baseAmount = $this->baseAmount(
            amount: $amount,
            exchangeRate: $exchangeRate,
        );

        $ledgerEntry = SupplierLedgerEntry::query()
            ->create([
                'branch_id' => $lockedInvoice->branch_id,
                'supplier_id' => $lockedInvoice->supplier_id,
                'accounting_period_id' =>
                    $accountingPeriod->getKey(),
                'reference' => $this->invoiceReference(
                    $lockedInvoice,
                ),
                'posting_key' => $postingKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'invoice',
                'source_type' =>
                    $lockedInvoice->getMorphClass(),
                'source_id' => $lockedInvoice->getKey(),
                'source_document_number' =>
                    $lockedInvoice->document_number,
                'document_date' =>
                    $lockedInvoice->invoice_date->toDateString(),
                'posting_date' => $postingDate,
                'due_date' =>
                    $lockedInvoice->due_date?->toDateString(),
                'currency_code' => strtoupper(
                    $lockedInvoice->currency_code,
                ),
                'exchange_rate' =>
                    $exchangeRate->__toString(),
                'debit_amount' => '0.000000',
                'credit_amount' => $amount->__toString(),
                'base_debit_amount' => '0.000000',
                'base_credit_amount' =>
                    $baseAmount->__toString(),
                'description' => $this->description(
                    sprintf(
                        'Supplier Invoice %s',
                        $lockedInvoice->document_number,
                    ),
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => null,
            ]);

        SupplierOpenItem::query()->create([
            'branch_id' => $lockedInvoice->branch_id,
            'supplier_id' => $lockedInvoice->supplier_id,
            'accounting_period_id' =>
                $accountingPeriod->getKey(),
            'supplier_ledger_entry_id' =>
                $ledgerEntry->getKey(),
            'item_type' => 'invoice',
            'source_type' =>
                $lockedInvoice->getMorphClass(),
            'source_id' => $lockedInvoice->getKey(),
            'document_number' =>
                $lockedInvoice->document_number,
            'document_date' =>
                $lockedInvoice->invoice_date->toDateString(),
            'posting_date' => $postingDate,
            'due_date' =>
                $lockedInvoice->due_date?->toDateString(),
            'currency_code' => strtoupper(
                $lockedInvoice->currency_code,
            ),
            'exchange_rate' =>
                $exchangeRate->__toString(),
            'original_amount' => $amount->__toString(),
            'allocated_amount' => '0.000000',
            'outstanding_amount' => $amount->__toString(),
            'base_original_amount' =>
                $baseAmount->__toString(),
            'base_allocated_amount' => '0.000000',
            'base_outstanding_amount' =>
                $baseAmount->__toString(),
            'status' => 'open',
            'created_by_user_id' => $actor->getKey(),
            'closed_at' => null,
        ]);

        return $ledgerEntry->load([
            'accountingPeriod',
            'openItem',
            'createdBy',
        ]);
    }

    public function reverseSupplierInvoice(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference = $this->requiredString(
            value: $journalReference,
            field: 'journal_reference',
            maximumLength: 190,
        );

        $reason = $this->requiredString(
            value: $reason,
            field: 'reversal_reason',
            maximumLength: 500,
        );

        $lockedInvoice = SupplierInvoice::query()
            ->whereKey($supplierInvoice->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            document: $lockedInvoice,
            actor: $actor,
            tenantId: $tenantId,
            branchId: (int) $lockedInvoice->branch_id,
            requireActiveBranch: false,
        );

        $reversalPostingDateString = $this->dateString(
            value: $reversalPostingDate,
            timezone: $tenant->timezone,
        );

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $reversalPostingDateString,
            tenantId: $tenantId,
        );

        $reversalPostingKey =
            $this->invoiceReversalPostingKey(
                $lockedInvoice,
            );

        $existingReversal = $this->existingEntry(
            postingKey: $reversalPostingKey,
            entryType: 'invoice_reversal',
            source: $lockedInvoice,
            journalReference: $journalReference,
        );

        if ($existingReversal instanceof SupplierLedgerEntry) {
            return $existingReversal->load([
                'accountingPeriod',
                'reversalOf',
                'createdBy',
            ]);
        }

        if (!$lockedInvoice->isPosted()) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'Only a posted Supplier Invoice can be reversed in Accounts Payable.',
                ],
            ]);
        }

        $originalEntry = $this->lockOriginalEntry(
            postingKey: $this->invoicePostingKey(
                $lockedInvoice,
            ),
            entryType: 'invoice',
            source: $lockedInvoice,
        );

        $openItem = $this->lockOpenItem(
            ledgerEntry: $originalEntry,
            itemType: 'invoice',
        );

        $this->ensureInvoiceOpenItemCanBeReversed(
            $openItem,
        );

        $amount = $this->money(
            $originalEntry->credit_amount,
        );

        $baseAmount = $this->money(
            $originalEntry->base_credit_amount,
        );

        $reversal = SupplierLedgerEntry::query()
            ->create([
                'branch_id' => $lockedInvoice->branch_id,
                'supplier_id' => $lockedInvoice->supplier_id,
                'accounting_period_id' =>
                    $accountingPeriod->getKey(),
                'reference' =>
                    $this->invoiceReversalReference(
                        $lockedInvoice,
                    ),
                'posting_key' => $reversalPostingKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'invoice_reversal',
                'source_type' =>
                    $lockedInvoice->getMorphClass(),
                'source_id' => $lockedInvoice->getKey(),
                'source_document_number' =>
                    $lockedInvoice->document_number,
                'document_date' =>
                    $lockedInvoice->invoice_date->toDateString(),
                'posting_date' =>
                    $reversalPostingDateString,
                'due_date' => null,
                'currency_code' => strtoupper(
                    $lockedInvoice->currency_code,
                ),
                'exchange_rate' =>
                    $originalEntry->exchange_rate,
                'debit_amount' => $amount->__toString(),
                'credit_amount' => '0.000000',
                'base_debit_amount' =>
                    $baseAmount->__toString(),
                'base_credit_amount' => '0.000000',
                'description' => $this->description(
                    sprintf(
                        'Reversal of Supplier Invoice %s: %s',
                        $lockedInvoice->document_number,
                        $reason,
                    ),
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => $originalEntry->getKey(),
            ]);

        $this->markOpenItemReversed(
            $openItem,
        );

        return $reversal->load([
            'accountingPeriod',
            'reversalOf',
            'createdBy',
        ]);
    }

    public function postSupplierDebitNote(
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference = $this->requiredString(
            value: $journalReference,
            field: 'journal_reference',
            maximumLength: 190,
        );

        $lockedDebitNote = SupplierDebitNote::query()
            ->whereKey($supplierDebitNote->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            document: $lockedDebitNote,
            actor: $actor,
            tenantId: $tenantId,
            branchId: (int) $lockedDebitNote->branch_id,
            requireActiveBranch: true,
        );

        $postingKey = $this->debitNotePostingKey(
            $lockedDebitNote,
        );

        $existingEntry = $this->existingEntry(
            postingKey: $postingKey,
            entryType: 'debit_note',
            source: $lockedDebitNote,
            journalReference: $journalReference,
        );

        if ($existingEntry instanceof SupplierLedgerEntry) {
            $this->ensureOpenItemExists(
                ledgerEntry: $existingEntry,
                itemType: 'credit',
            );

            return $existingEntry->load([
                'accountingPeriod',
                'openItem',
                'createdBy',
            ]);
        }

        if (!$lockedDebitNote->isApproved()) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'Only an approved Supplier Debit Note can be posted to Accounts Payable.',
                ],
            ]);
        }

        if (!$lockedDebitNote->hasDebitNoteNumber()) {
            throw new LogicException(
                'The approved Supplier Debit Note does not retain its document number.',
            );
        }

        $postingDate = $lockedDebitNote
            ->posting_date
            ->toDateString();

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $postingDate,
            tenantId: $tenantId,
        );

        $amount = $this->positiveMoney(
            value: $lockedDebitNote->total_amount,
            field: 'total_amount',
        );

        $exchangeRate = $this->positiveExchangeRate(
            $lockedDebitNote->exchange_rate,
        );

        $baseAmount = $this->baseAmount(
            amount: $amount,
            exchangeRate: $exchangeRate,
        );

        $ledgerEntry = SupplierLedgerEntry::query()
            ->create([
                'branch_id' => $lockedDebitNote->branch_id,
                'supplier_id' => $lockedDebitNote->supplier_id,
                'accounting_period_id' =>
                    $accountingPeriod->getKey(),
                'reference' => $this->debitNoteReference(
                    $lockedDebitNote,
                ),
                'posting_key' => $postingKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'debit_note',
                'source_type' =>
                    $lockedDebitNote->getMorphClass(),
                'source_id' => $lockedDebitNote->getKey(),
                'source_document_number' =>
                    $lockedDebitNote->debit_note_number,
                'document_date' =>
                    $lockedDebitNote
                        ->debit_note_date
                        ->toDateString(),
                'posting_date' => $postingDate,
                'due_date' => null,
                'currency_code' => strtoupper(
                    $lockedDebitNote->currency_code,
                ),
                'exchange_rate' =>
                    $exchangeRate->__toString(),
                'debit_amount' => $amount->__toString(),
                'credit_amount' => '0.000000',
                'base_debit_amount' =>
                    $baseAmount->__toString(),
                'base_credit_amount' => '0.000000',
                'description' => $this->description(
                    sprintf(
                        'Supplier Debit Note %s',
                        $lockedDebitNote->debit_note_number,
                    ),
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => null,
            ]);

        $creditOpenItem = SupplierOpenItem::query()
            ->create([
                'branch_id' => $lockedDebitNote->branch_id,
                'supplier_id' => $lockedDebitNote->supplier_id,
                'accounting_period_id' =>
                    $accountingPeriod->getKey(),
                'supplier_ledger_entry_id' =>
                    $ledgerEntry->getKey(),
                'item_type' => 'credit',
                'source_type' =>
                    $lockedDebitNote->getMorphClass(),
                'source_id' => $lockedDebitNote->getKey(),
                'document_number' =>
                    $lockedDebitNote->debit_note_number,
                'document_date' =>
                    $lockedDebitNote
                        ->debit_note_date
                        ->toDateString(),
                'posting_date' => $postingDate,
                'due_date' => null,
                'currency_code' => strtoupper(
                    $lockedDebitNote->currency_code,
                ),
                'exchange_rate' =>
                    $exchangeRate->__toString(),
                'original_amount' => $amount->__toString(),
                'allocated_amount' => '0.000000',
                'outstanding_amount' => $amount->__toString(),
                'base_original_amount' =>
                    $baseAmount->__toString(),
                'base_allocated_amount' => '0.000000',
                'base_outstanding_amount' =>
                    $baseAmount->__toString(),
                'status' => 'open',
                'created_by_user_id' => $actor->getKey(),
                'closed_at' => null,
            ]);

        $this->applyDebitNoteInvoiceAllocation(
            supplierDebitNote: $lockedDebitNote,
            creditOpenItem: $creditOpenItem,
            accountingPeriod: $accountingPeriod,
            actor: $actor,
            tenantTimezone: $tenant->timezone,
        );

        return $ledgerEntry->load([
            'accountingPeriod',
            'openItem',
            'createdBy',
        ]);
    }

    public function reverseSupplierDebitNote(
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): SupplierLedgerEntry {
        $this->ensureInsideTransaction();

        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $journalReference = $this->requiredString(
            value: $journalReference,
            field: 'journal_reference',
            maximumLength: 190,
        );

        $reason = $this->requiredString(
            value: $reason,
            field: 'reversal_reason',
            maximumLength: 500,
        );

        $lockedDebitNote = SupplierDebitNote::query()
            ->whereKey($supplierDebitNote->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureDocumentContext(
            document: $lockedDebitNote,
            actor: $actor,
            tenantId: $tenantId,
            branchId: (int) $lockedDebitNote->branch_id,
            requireActiveBranch: false,
        );

        $reversalDate = $this->dateString(
            value: $reversalPostingDate,
            timezone: $tenant->timezone,
        );

        $this->ensureAccountingPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $reversalDate,
            tenantId: $tenantId,
        );

        $reversalPostingKey =
            $this->debitNoteReversalPostingKey(
                $lockedDebitNote,
            );

        $existingReversal = $this->existingEntry(
            postingKey: $reversalPostingKey,
            entryType: 'debit_note_reversal',
            source: $lockedDebitNote,
            journalReference: $journalReference,
        );

        if ($existingReversal instanceof SupplierLedgerEntry) {
            return $existingReversal->load([
                'accountingPeriod',
                'reversalOf',
                'createdBy',
            ]);
        }

        if (!$lockedDebitNote->isPosted()) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'Only a posted Supplier Debit Note can be reversed in Accounts Payable.',
                ],
            ]);
        }

        $originalEntry = $this->lockOriginalEntry(
            postingKey: $this->debitNotePostingKey(
                $lockedDebitNote,
            ),
            entryType: 'debit_note',
            source: $lockedDebitNote,
        );

        $creditOpenItem = $this->lockOpenItem(
            ledgerEntry: $originalEntry,
            itemType: 'credit',
        );

        $this->reverseDebitNoteAllocations(
            supplierDebitNote: $lockedDebitNote,
            creditOpenItem: $creditOpenItem,
            accountingPeriod: $accountingPeriod,
            reversalPostingDate: $reversalPostingDate,
            reason: $reason,
            actor: $actor,
        );

        $creditOpenItem->refresh();

        $this->ensureCreditOpenItemCanBeReversed(
            $creditOpenItem,
        );

        $amount = $this->money(
            $originalEntry->debit_amount,
        );

        $baseAmount = $this->money(
            $originalEntry->base_debit_amount,
        );

        $reversal = SupplierLedgerEntry::query()
            ->create([
                'branch_id' => $lockedDebitNote->branch_id,
                'supplier_id' => $lockedDebitNote->supplier_id,
                'accounting_period_id' =>
                    $accountingPeriod->getKey(),
                'reference' =>
                    $this->debitNoteReversalReference(
                        $lockedDebitNote,
                    ),
                'posting_key' => $reversalPostingKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'debit_note_reversal',
                'source_type' =>
                    $lockedDebitNote->getMorphClass(),
                'source_id' => $lockedDebitNote->getKey(),
                'source_document_number' =>
                    $lockedDebitNote->debit_note_number,
                'document_date' =>
                    $lockedDebitNote
                        ->debit_note_date
                        ->toDateString(),
                'posting_date' => $reversalDate,
                'due_date' => null,
                'currency_code' => strtoupper(
                    $lockedDebitNote->currency_code,
                ),
                'exchange_rate' =>
                    $originalEntry->exchange_rate,
                'debit_amount' => '0.000000',
                'credit_amount' => $amount->__toString(),
                'base_debit_amount' => '0.000000',
                'base_credit_amount' =>
                    $baseAmount->__toString(),
                'description' => $this->description(
                    sprintf(
                        'Reversal of Supplier Debit Note %s: %s',
                        $lockedDebitNote->debit_note_number,
                        $reason,
                    ),
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => $originalEntry->getKey(),
            ]);

        $this->markOpenItemReversed(
            $creditOpenItem,
        );

        return $reversal->load([
            'accountingPeriod',
            'reversalOf',
            'createdBy',
        ]);
    }

    private function applyDebitNoteInvoiceAllocation(
        SupplierDebitNote $supplierDebitNote,
        SupplierOpenItem $creditOpenItem,
        AccountingPeriod $accountingPeriod,
        User $actor,
        string $tenantTimezone,
    ): void {
        $allocatedAmount = $this->money(
            $supplierDebitNote->allocated_amount,
        );

        if ($allocatedAmount->isZero()) {
            if ($supplierDebitNote->supplier_invoice_id !== null) {
                throw new LogicException(
                    'The Supplier Debit Note references an invoice but has no allocated amount.',
                );
            }

            return;
        }

        if ($supplierDebitNote->supplier_invoice_id === null) {
            throw new LogicException(
                'The Supplier Debit Note has an allocated amount without a Supplier Invoice.',
            );
        }

        $legacyAllocation =
            SupplierDebitNoteAllocation::query()
                ->where(
                    'supplier_debit_note_id',
                    $supplierDebitNote->getKey(),
                )
                ->lockForUpdate()
                ->first();

        if (
            !$legacyAllocation
                instanceof SupplierDebitNoteAllocation
            || !$legacyAllocation->isReserved()
            || (int) $legacyAllocation
                ->supplier_invoice_id
                !== (int) $supplierDebitNote
                    ->supplier_invoice_id
            || $this->money(
                $legacyAllocation->amount,
            )->compareTo($allocatedAmount) !== 0
        ) {
            throw new LogicException(
                'The reserved Supplier Debit Note allocation is inconsistent with the financial allocation.',
            );
        }

        $invoiceOpenItem = SupplierOpenItem::query()
            ->where(
                'source_type',
                (new SupplierInvoice())
                    ->getMorphClass(),
            )
            ->where(
                'source_id',
                $supplierDebitNote->supplier_invoice_id,
            )
            ->where('item_type', 'invoice')
            ->first();

        if (!$invoiceOpenItem instanceof SupplierOpenItem) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The linked Supplier Invoice does not have an Accounts Payable open item.',
                ],
            ]);
        }

        $allocation = $this->allocationService->apply(
            payableOpenItem: $invoiceOpenItem,
            creditOpenItem: $creditOpenItem,
            accountingPeriod: $accountingPeriod,
            allocationType: 'debit_note',
            postingKey:
                $this->debitNoteAllocationPostingKey(
                    $supplierDebitNote,
                ),
            allocationDate: $this->businessDateTime(
                date: $supplierDebitNote
                    ->debit_note_date
                    ->toDateString(),
                timezone: $tenantTimezone,
            ),
            postingDate: $this->businessDateTime(
                date: $supplierDebitNote
                    ->posting_date
                    ->toDateString(),
                timezone: $tenantTimezone,
            ),
            amount: $allocatedAmount->__toString(),
            source: $supplierDebitNote,
            actor: $actor,
        );

        if (
            !$this->money(
                $allocation
                    ->exchange_difference_amount,
            )->isZero()
        ) {
            throw new LogicException(
                'A Supplier Debit Note allocated to its source invoice cannot create an exchange difference.',
            );
        }
    }

    private function reverseDebitNoteAllocations(
        SupplierDebitNote $supplierDebitNote,
        SupplierOpenItem $creditOpenItem,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): void {
        $appliedAllocations =
            SupplierOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $creditOpenItem->getKey(),
                )
                ->where('status', 'applied')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        if ($appliedAllocations->isEmpty()) {
            return;
        }

        if ($appliedAllocations->count() !== 1) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'The Supplier Debit Note credit has later allocations. Reverse those allocations before reversing the Debit Note.',
                ],
            ]);
        }

        $allocation = $appliedAllocations->first();

        if (
            !$allocation
                instanceof SupplierOpenItemAllocation
            || $allocation->allocation_type
                !== 'debit_note'
            || $allocation->posting_key
                !== $this->debitNoteAllocationPostingKey(
                    $supplierDebitNote,
                )
            || $allocation->source_type
                !== $supplierDebitNote->getMorphClass()
            || (int) $allocation->source_id
                !== (int) $supplierDebitNote->getKey()
        ) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'The Supplier Debit Note credit has been used by a later settlement. Reverse that settlement first.',
                ],
            ]);
        }

        $this->allocationService->reverse(
            allocation: $allocation,
            accountingPeriod: $accountingPeriod,
            reversalPostingDate:
                $reversalPostingDate,
            reason: $reason,
            actor: $actor,
        );
    }

    private function ensureInvoiceOpenItemCanBeReversed(
        SupplierOpenItem $openItem,
    ): void {
        if (
            !$openItem->isInvoice()
            || $openItem->isReversed()
            || !$this->money(
                $openItem->allocated_amount,
            )->isZero()
            || $this->money(
                $openItem->outstanding_amount,
            )->compareTo(
                $this->money(
                    $openItem->original_amount,
                ),
            ) !== 0
            || SupplierOpenItemAllocation::query()
                ->where(
                    'payable_open_item_id',
                    $openItem->getKey(),
                )
                ->where('status', 'applied')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'The Supplier Invoice has applied credits or payments. Reverse those allocations before reversing the invoice.',
                ],
            ]);
        }
    }

    private function ensureCreditOpenItemCanBeReversed(
        SupplierOpenItem $openItem,
    ): void {
        if (
            !$openItem->isCredit()
            || $openItem->isReversed()
            || !$this->money(
                $openItem->allocated_amount,
            )->isZero()
            || $this->money(
                $openItem->outstanding_amount,
            )->compareTo(
                $this->money(
                    $openItem->original_amount,
                ),
            ) !== 0
            || SupplierOpenItemAllocation::query()
                ->where(
                    'credit_open_item_id',
                    $openItem->getKey(),
                )
                ->where('status', 'applied')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'supplier_debit_note' => [
                    'The Supplier Debit Note credit is still allocated. Reverse its settlements before reversing the Debit Note.',
                ],
            ]);
        }
    }

    private function markOpenItemReversed(
        SupplierOpenItem $openItem,
    ): void {
        if (
            !$this->money(
                $openItem->allocated_amount,
            )->isZero()
        ) {
            throw new LogicException(
                'An allocated supplier open item cannot be marked as reversed.',
            );
        }

        $openItem->outstanding_amount = '0.000000';
        $openItem->base_outstanding_amount = '0.000000';
        $openItem->status = 'reversed';
        $openItem->closed_at =
            CarbonImmutable::now('UTC');
        $openItem->save();
    }

    private function lockOriginalEntry(
        string $postingKey,
        string $entryType,
        Model $source,
    ): SupplierLedgerEntry {
        $entry = SupplierLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->where('entry_type', $entryType)
            ->where(
                'source_type',
                $source->getMorphClass(),
            )
            ->where('source_id', $source->getKey())
            ->lockForUpdate()
            ->first();

        if (!$entry instanceof SupplierLedgerEntry) {
            throw new LogicException(
                'The original Accounts Payable supplier ledger entry is unavailable.',
            );
        }

        return $entry;
    }

    private function lockOpenItem(
        SupplierLedgerEntry $ledgerEntry,
        string $itemType,
    ): SupplierOpenItem {
        $openItem = SupplierOpenItem::query()
            ->where(
                'supplier_ledger_entry_id',
                $ledgerEntry->getKey(),
            )
            ->where('item_type', $itemType)
            ->lockForUpdate()
            ->first();

        if (!$openItem instanceof SupplierOpenItem) {
            throw new LogicException(
                'The Accounts Payable supplier open item is unavailable.',
            );
        }

        return $openItem;
    }

    private function ensureOpenItemExists(
        SupplierLedgerEntry $ledgerEntry,
        string $itemType,
    ): void {
        $exists = SupplierOpenItem::query()
            ->where(
                'supplier_ledger_entry_id',
                $ledgerEntry->getKey(),
            )
            ->where('item_type', $itemType)
            ->exists();

        if (!$exists) {
            throw new LogicException(
                'The existing supplier ledger entry does not retain its open item.',
            );
        }
    }

    private function existingEntry(
        string $postingKey,
        string $entryType,
        Model $source,
        string $journalReference,
    ): ?SupplierLedgerEntry {
        $entry = SupplierLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if (!$entry instanceof SupplierLedgerEntry) {
            return null;
        }

        if (
            $entry->entry_type !== $entryType
            || $entry->source_type
                !== $source->getMorphClass()
            || (int) $entry->source_id
                !== (int) $source->getKey()
            || $entry->journal_reference
                !== $journalReference
        ) {
            throw new LogicException(
                'The Accounts Payable posting key already belongs to a different financial posting.',
            );
        }

        return $entry;
    }

    private function ensureDocumentContext(
        Model $document,
        User $actor,
        int $tenantId,
        int $branchId,
        bool $requireActiveBranch,
    ): void {
        if (
            (int) $actor->tenant_id !== $tenantId
            || (int) $document->getAttribute(
                'tenant_id',
            ) !== $tenantId
        ) {
            throw new LogicException(
                'The Accounts Payable posting context contains records from different tenants.',
            );
        }

        $branch = Branch::query()
            ->whereKey($branchId)
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: $requireActiveBranch,
        );
    }

    private function ensureAccountingPeriod(
        AccountingPeriod $accountingPeriod,
        string $postingDate,
        int $tenantId,
    ): void {
        if (
            (int) $accountingPeriod->tenant_id
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
                < $accountingPeriod->start_date->toDateString()
            || $postingDate
                > $accountingPeriod->end_date->toDateString()
        ) {
            throw new LogicException(
                'The posting date is outside the supplied accounting period.',
            );
        }
    }

    private function invoicePostingKey(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'supplier_invoice:%d:post',
            $supplierInvoice->getKey(),
        );
    }

    private function invoiceReversalPostingKey(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'supplier_invoice:%d:reverse',
            $supplierInvoice->getKey(),
        );
    }

    private function debitNotePostingKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier_debit_note:%d:post',
            $supplierDebitNote->getKey(),
        );
    }

    private function debitNoteReversalPostingKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier_debit_note:%d:reverse',
            $supplierDebitNote->getKey(),
        );
    }

    private function debitNoteAllocationPostingKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier_debit_note:%d:invoice_allocation',
            $supplierDebitNote->getKey(),
        );
    }

    private function invoiceReference(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'AP-SI-%d',
            $supplierInvoice->getKey(),
        );
    }

    private function invoiceReversalReference(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'AP-SI-%d-REV',
            $supplierInvoice->getKey(),
        );
    }

    private function debitNoteReference(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'AP-SDN-%d',
            $supplierDebitNote->getKey(),
        );
    }

    private function debitNoteReversalReference(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'AP-SDN-%d-REV',
            $supplierDebitNote->getKey(),
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

    private function money(mixed $value): BigDecimal
    {
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
            scale: self::EXCHANGE_RATE_SCALE,
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
            return BigDecimal::of((string) $value)
                ->toScale(
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

        if (mb_strlen($value) > $maximumLength) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function description(string $value): string
    {
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
        return CarbonImmutable::instance($value)
            ->setTimezone($timezone)
            ->toDateString();
    }

    private function businessDateTime(
        string $date,
        string $timezone,
    ): CarbonImmutable {
        $dateTime = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            $timezone,
        );

        if (!$dateTime instanceof CarbonImmutable) {
            throw new LogicException(
                'The Accounts Payable business date is invalid.',
            );
        }

        return $dateTime->startOfDay()->utc();
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }

        throw new LogicException(
            'Accounts Payable posting must run inside the accounting database transaction.',
        );
    }
}
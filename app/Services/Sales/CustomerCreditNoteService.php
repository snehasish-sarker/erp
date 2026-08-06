<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\CustomerCreditNoteAccountingGateway;
use App\Models\Branch;
use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteDispatchAllocation;
use App\Models\CustomerCreditNoteLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDispatchAllocation;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\CustomerCreditNoteAccountsReceivableService;
use App\Services\Inventory\CustomerCreditNoteInventoryService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerCreditNoteService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly CustomerCreditNoteAccountingGateway $accountingGateway,
        private readonly CustomerCreditNoteAccountsReceivableService $accountsReceivableService,
        private readonly CustomerCreditNoteInventoryService $inventoryService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): CustomerCreditNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $normalized = $this->normalizeInput($data, $tenant);

        return DB::transaction(
            function () use ($normalized, $actor): CustomerCreditNote {
                $invoice = $this->lockSourceInvoice(
                    salesInvoiceId: $normalized['sales_invoice_id'],
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $existingDraft = CustomerCreditNote::query()
                    ->where('sales_invoice_id', $invoice->getKey())
                    ->whereNotNull('draft_key')
                    ->lockForUpdate()
                    ->first();

                if ($existingDraft instanceof CustomerCreditNote) {
                    throw ValidationException::withMessages([
                        'sales_invoice_id' => [
                            'This Sales Invoice already has an editable Customer Credit Note draft.',
                        ],
                    ]);
                }

                $salesOrder = $this->lockSalesOrder($invoice);
                $built = $this->buildLines(
                    invoice: $invoice,
                    inputLines: $normalized['lines'],
                    excludingCreditNoteId: null,
                );

                $this->ensureReturnWarehouse(
                    salesOrder: $salesOrder,
                    lines: $built['lines'],
                );

                $totals = $this->totals($built['lines']);

                $creditNote = CustomerCreditNote::query()->create([
                    'sales_invoice_id' => $invoice->getKey(),
                    'sales_order_id' => $invoice->sales_order_id,
                    'branch_id' => $invoice->branch_id,
                    'warehouse_id' => $salesOrder->warehouse_id,
                    'customer_id' => $invoice->customer_id,
                    'document_number_allocation_id' => null,
                    'customer_ledger_entry_id' => null,
                    'customer_open_item_id' => null,
                    'customer_open_item_allocation_id' => null,
                    'credit_note_number' => null,
                    'draft_key' => $this->draftKey($invoice),
                    'credit_note_date' => $normalized['credit_note_date'],
                    'posting_date' => $normalized['posting_date'],
                    'sales_invoice_number' => (string) $invoice->invoice_number,
                    'sales_order_number' => (string) $invoice->sales_order_number,
                    'customer_name' => $invoice->customer_name,
                    'customer_code' => $invoice->customer_code,
                    'customer_type' => $invoice->customer_type,
                    'customer_contact_person' => $invoice->customer_contact_person,
                    'customer_email' => $invoice->customer_email,
                    'customer_phone' => $invoice->customer_phone,
                    'customer_tax_number' => $invoice->customer_tax_number,
                    'billing_address' => $invoice->billing_address,
                    'return_address' => $normalized['return_address'],
                    'currency_code' => $invoice->currency_code,
                    'exchange_rate' => $invoice->exchange_rate,
                    ...$totals,
                    'reason' => $normalized['reason'],
                    'notes' => $normalized['notes'],
                    'status' => 'draft',
                    'revision' => 1,
                    'created_by_user_id' => $actor->getKey(),
                ]);

                $this->replaceLinesAndAllocations(
                    creditNote: $creditNote,
                    built: $built,
                );

                return $this->loadCreditNote($creditNote);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        CustomerCreditNote $creditNote,
        array $data,
        User $actor,
    ): CustomerCreditNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);
        $normalized = $this->normalizeInput($data, $tenant);

        return DB::transaction(
            function () use ($creditNote, $normalized, $actor): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureEditable($locked);

                if (
                    (int) $locked->sales_invoice_id
                    !== $normalized['sales_invoice_id']
                ) {
                    throw ValidationException::withMessages([
                        'sales_invoice_id' => [
                            'The source Sales Invoice cannot be changed after the credit-note draft is created.',
                        ],
                    ]);
                }

                $invoice = $this->lockSourceInvoice(
                    salesInvoiceId: (int) $locked->sales_invoice_id,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $salesOrder = $this->lockSalesOrder($invoice);
                $built = $this->buildLines(
                    invoice: $invoice,
                    inputLines: $normalized['lines'],
                    excludingCreditNoteId: (int) $locked->getKey(),
                );

                $this->ensureReturnWarehouse(
                    salesOrder: $salesOrder,
                    lines: $built['lines'],
                );

                $totals = $this->totals($built['lines']);

                $locked->fill([
                    'warehouse_id' => $salesOrder->warehouse_id,
                    'credit_note_date' => $normalized['credit_note_date'],
                    'posting_date' => $normalized['posting_date'],
                    'return_address' => $normalized['return_address'],
                    ...$totals,
                    'reason' => $normalized['reason'],
                    'notes' => $normalized['notes'],
                    'revision' => (int) $locked->revision + 1,
                ]);

                $locked->save();

                $this->replaceLinesAndAllocations(
                    creditNote: $locked,
                    built: $built,
                );

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function delete(
        CustomerCreditNote $creditNote,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);

        DB::transaction(
            function () use ($creditNote, $actor): void {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: false,
                );

                if (!$locked->canBeDeleted()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an unnumbered Customer Credit Note draft can be deleted.',
                        ],
                    ]);
                }

                $locked->delete();
            },
            attempts: 5,
        );
    }

    public function submit(
        CustomerCreditNote $creditNote,
        User $actor,
    ): CustomerCreditNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);

        return DB::transaction(
            function () use ($creditNote, $actor, $tenant): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canBeSubmitted()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Customer Credit Note can be submitted.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: true,
                );

                $this->refreshDocumentFromStoredLines($locked, $actor);

                if (!$locked->hasCreditNoteNumber()) {
                    $allocation = $this->documentNumberService->allocate(
                        documentType: 'credit_note',
                        branchId: (int) $locked->branch_id,
                        idempotencyKey: $this->numberAllocationKey($locked),
                        allocatableType: CustomerCreditNote::class,
                        allocatableId: (int) $locked->getKey(),
                        allocatedAt: CarbonImmutable::parse(
                            $locked->credit_note_date,
                            $tenant->timezone,
                        ),
                    );

                    $locked->document_number_allocation_id = $allocation->getKey();
                    $locked->credit_note_number = $allocation->number;
                }

                $locked->status = 'submitted';
                $locked->submitted_by_user_id = $actor->getKey();
                $locked->submitted_at = now();
                $locked->approved_by_user_id = null;
                $locked->approved_at = null;
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        CustomerCreditNote $creditNote,
        User $actor,
    ): CustomerCreditNote {
        $tenantId = $this->activeTenantId();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);

        return DB::transaction(
            function () use ($creditNote, $actor): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canReturnToDraft()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a submitted or approved Customer Credit Note can return to draft.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: true,
                );

                $locked->status = 'draft';
                $locked->submitted_by_user_id = null;
                $locked->submitted_at = null;
                $locked->approved_by_user_id = null;
                $locked->approved_at = null;
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function approve(
        CustomerCreditNote $creditNote,
        User $actor,
    ): CustomerCreditNote {
        $tenantId = $this->activeTenantId();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);

        return DB::transaction(
            function () use ($creditNote, $actor): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canBeApproved()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a submitted Customer Credit Note can be approved.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: true,
                );

                $this->refreshDocumentFromStoredLines($locked, $actor);

                $locked->status = 'approved';
                $locked->approved_by_user_id = $actor->getKey();
                $locked->approved_at = now();
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function cancel(
        CustomerCreditNote $creditNote,
        string $reason,
        User $actor,
    ): CustomerCreditNote {
        $tenantId = $this->activeTenantId();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);
        $reason = $this->requiredReason($reason, 'cancellation_reason');

        return DB::transaction(
            function () use ($creditNote, $reason, $actor): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canBeCancelled()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft, submitted, or approved Customer Credit Note can be cancelled.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: false,
                );

                $locked->status = 'cancelled';
                $locked->draft_key = null;
                $locked->cancelled_by_user_id = $actor->getKey();
                $locked->cancelled_at = now();
                $locked->cancellation_reason = $reason;
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function post(
        CustomerCreditNote $creditNote,
        User $actor,
    ): CustomerCreditNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);

        return DB::transaction(
            function () use ($creditNote, $actor, $tenant): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canBePosted()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an approved Customer Credit Note can be posted.',
                        ],
                    ]);
                }

                $invoice = $this->lockSourceInvoice(
                    salesInvoiceId: (int) $locked->sales_invoice_id,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $salesOrder = $this->lockSalesOrder($invoice);
                $this->refreshDocumentFromStoredLines($locked, $actor);

                $lines = CustomerCreditNoteLine::query()
                    ->where('customer_credit_note_id', $locked->getKey())
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'The Customer Credit Note does not contain any lines.',
                        ],
                    ]);
                }

                $occurredAt = CarbonImmutable::parse(
                    $locked->posting_date,
                    $tenant->timezone,
                )->startOfDay();

                foreach ($lines as $line) {
                    if (!$line->restoresInventory()) {
                        continue;
                    }

                    if ($salesOrder->warehouse_id === null) {
                        throw ValidationException::withMessages([
                            'warehouse_id' => [
                                'The source Sales Order does not retain a fulfillment warehouse for this stock return.',
                            ],
                        ]);
                    }

                    $ledger = $this->inventoryService->postLine(
                        creditNote: $locked,
                        line: $line,
                        actor: $actor,
                        occurredAt: $occurredAt,
                    );

                    $line->unit_cost = $ledger->unit_cost;
                    $line->total_cost = $ledger->total_cost;
                    $line->stock_ledger_entry_id = $ledger->getKey();
                    $line->save();
                }

                $this->refreshTotalsFromPersistedLines($locked);

                $accountingPeriod = $this->accountingPeriodService
                    ->lockOpenPeriod($locked->posting_date);

                $journalReferences = $this->accountingGateway->post(
                    creditNote: $locked->load('lines'),
                    accountingPeriod: $accountingPeriod,
                    actor: $actor,
                );

                $ar = $this->accountsReceivableService->post(
                    creditNote: $locked,
                    accountingPeriod: $accountingPeriod,
                    journalReference: $journalReferences['accounting_reference'],
                    actor: $actor,
                );

                $this->applySourceQuantities(
                    invoice: $invoice,
                    salesOrder: $salesOrder,
                    creditNoteLines: $lines,
                    reverse: false,
                );

                $locked->status = 'posted';
                $locked->draft_key = null;
                $locked->posted_by_user_id = $actor->getKey();
                $locked->posted_at = now();
                $locked->accounting_posting_reference = $journalReferences[
                    'accounting_reference'
                ];
                $locked->inventory_posting_reference = $journalReferences[
                    'inventory_reference'
                ];
                $locked->customer_ledger_entry_id = $ar['ledger_entry']->getKey();
                $locked->customer_open_item_id = $ar['open_item']->getKey();
                $locked->customer_open_item_allocation_id = $ar['allocation']?->getKey();
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    public function reverse(
        CustomerCreditNote $creditNote,
        string $reversalPostingDate,
        string $reason,
        User $actor,
    ): CustomerCreditNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureCreditNoteTenant($creditNote, $tenantId);
        $reason = $this->requiredReason($reason, 'reversal_reason');
        $date = $this->date(
            value: $reversalPostingDate,
            field: 'reversal_posting_date',
            timezone: $tenant->timezone,
        );

        return DB::transaction(
            function () use (
                $creditNote,
                $date,
                $reason,
                $actor,
                $tenant,
            ): CustomerCreditNote {
                $locked = CustomerCreditNote::query()
                    ->whereKey($creditNote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->canBeReversed()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a posted Customer Credit Note can be reversed.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $locked->branch_id,
                    actor: $actor,
                    requireActive: true,
                );

                $invoice = SalesInvoice::query()
                    ->whereKey($locked->sales_invoice_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$invoice->isPosted()) {
                    throw ValidationException::withMessages([
                        'sales_invoice_id' => [
                            'The source Sales Invoice must remain posted before the Customer Credit Note can be reversed.',
                        ],
                    ]);
                }

                $salesOrder = $this->lockSalesOrder($invoice);

                $lines = CustomerCreditNoteLine::query()
                    ->where('customer_credit_note_id', $locked->getKey())
                    ->orderByDesc('stock_ledger_entry_id')
                    ->orderByDesc('line_number')
                    ->lockForUpdate()
                    ->get();

                $occurredAt = CarbonImmutable::parse(
                    $date,
                    $tenant->timezone,
                );

                foreach ($lines as $line) {
                    if (!$line->restoresInventory()) {
                        continue;
                    }

                    $reversal = $this->inventoryService->reverseLine(
                        creditNote: $locked,
                        line: $line,
                        actor: $actor,
                        occurredAt: $occurredAt,
                    );

                    $line->reversal_stock_ledger_entry_id = $reversal->getKey();
                    $line->save();
                }

                $period = $this->accountingPeriodService
                    ->lockOpenPeriod($occurredAt);

                $journalReferences = $this->accountingGateway->reverse(
                    creditNote: $locked->load('lines'),
                    accountingPeriod: $period,
                    reversalPostingDate: $occurredAt,
                    reason: $reason,
                    actor: $actor,
                );

                $this->accountsReceivableService->reverse(
                    creditNote: $locked,
                    accountingPeriod: $period,
                    reversalPostingDate: $occurredAt,
                    journalReference: $journalReferences['accounting_reference'],
                    reason: $reason,
                    actor: $actor,
                );

                $this->applySourceQuantities(
                    invoice: $invoice,
                    salesOrder: $salesOrder,
                    creditNoteLines: $lines,
                    reverse: true,
                );

                $locked->status = 'reversed';
                $locked->reversal_posting_date = $date;
                $locked->reversed_by_user_id = $actor->getKey();
                $locked->reversed_at = now();
                $locked->reversal_reason = $reason;
                $locked->accounting_reversal_reference = $journalReferences[
                    'accounting_reference'
                ];
                $locked->inventory_reversal_reference = $journalReferences[
                    'inventory_reference'
                ];
                $locked->save();

                return $this->loadCreditNote($locked->refresh());
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     sales_invoice_id: int,
     *     credit_note_date: string,
     *     posting_date: string,
     *     return_address: string|null,
     *     reason: string,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(array $data, Tenant $tenant): array
    {
        $invoiceId = filter_var(
            $data['sales_invoice_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($invoiceId === false) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => [
                    'The selected Sales Invoice is invalid.',
                ],
            ]);
        }

        $lines = $data['lines'] ?? null;

        if (!is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Customer Credit Note must contain at least one line.',
                ],
            ]);
        }

        return [
            'sales_invoice_id' => $invoiceId,
            'credit_note_date' => $this->date(
                $data['credit_note_date'] ?? null,
                'credit_note_date',
                $tenant->timezone,
            ),
            'posting_date' => $this->date(
                $data['posting_date'] ?? null,
                'posting_date',
                $tenant->timezone,
            ),
            'return_address' => $this->text(
                $data['return_address'] ?? null,
                4000,
            ),
            'reason' => $this->requiredReason(
                (string) ($data['reason'] ?? ''),
                'reason',
            ),
            'notes' => $this->text(
                $data['notes'] ?? null,
                4000,
            ),
            'lines' => array_values($lines),
        ];
    }

    /**
     * @param list<array<string, mixed>> $inputLines
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     allocations: array<int, list<array<string, mixed>>>
     * }
     */
    private function buildLines(
        SalesInvoice $invoice,
        array $inputLines,
        ?int $excludingCreditNoteId,
    ): array {
        $invoiceLines = SalesInvoiceLine::query()
            ->where('sales_invoice_id', $invoice->getKey())
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $postedComponents = CustomerCreditNoteLine::query()
            ->join(
                'customer_credit_notes',
                'customer_credit_notes.id',
                '=',
                'customer_credit_note_lines.customer_credit_note_id',
            )
            ->where('customer_credit_notes.sales_invoice_id', $invoice->getKey())
            ->where('customer_credit_notes.status', 'posted')
            ->whereNull('customer_credit_notes.deleted_at')
            ->when(
                $excludingCreditNoteId !== null,
                static fn ($query) => $query->where(
                    'customer_credit_notes.id',
                    '!=',
                    $excludingCreditNoteId,
                ),
            )
            ->selectRaw(
                'customer_credit_note_lines.sales_invoice_line_id,
                SUM(customer_credit_note_lines.gross_amount) AS gross_amount,
                SUM(customer_credit_note_lines.discount_amount) AS discount_amount,
                SUM(customer_credit_note_lines.subtotal) AS subtotal,
                SUM(customer_credit_note_lines.tax_amount) AS tax_amount,
                SUM(customer_credit_note_lines.line_total) AS line_total',
            )
            ->groupBy('customer_credit_note_lines.sales_invoice_line_id')
            ->get()
            ->keyBy('sales_invoice_line_id');

        $sourceAllocations = SalesInvoiceDispatchAllocation::query()
            ->whereIn(
                'sales_invoice_line_id',
                $invoiceLines->keys()->all(),
            )
            ->orderBy('sales_invoice_line_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('sales_invoice_line_id');

        $usedSourceAllocation = CustomerCreditNoteDispatchAllocation::query()
            ->join(
                'customer_credit_note_lines',
                'customer_credit_note_lines.id',
                '=',
                'customer_credit_note_dispatch_allocations.customer_credit_note_line_id',
            )
            ->join(
                'customer_credit_notes',
                'customer_credit_notes.id',
                '=',
                'customer_credit_note_lines.customer_credit_note_id',
            )
            ->where('customer_credit_notes.sales_invoice_id', $invoice->getKey())
            ->whereIn(
                'customer_credit_notes.status',
                ['draft', 'submitted', 'approved', 'posted'],
            )
            ->whereNull('customer_credit_notes.deleted_at')
            ->when(
                $excludingCreditNoteId !== null,
                static fn ($query) => $query->where(
                    'customer_credit_notes.id',
                    '!=',
                    $excludingCreditNoteId,
                ),
            )
            ->selectRaw(
                'customer_credit_note_dispatch_allocations.sales_invoice_dispatch_allocation_id,
                SUM(customer_credit_note_dispatch_allocations.allocated_quantity) AS used_quantity',
            )
            ->groupBy(
                'customer_credit_note_dispatch_allocations.sales_invoice_dispatch_allocation_id',
            )
            ->pluck('used_quantity', 'sales_invoice_dispatch_allocation_id');

        $builtLines = [];
        $allocations = [];
        $seen = [];

        foreach (array_values($inputLines) as $index => $input) {
            if (!is_array($input)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each Customer Credit Note line must be an object.',
                    ],
                ]);
            }

            $invoiceLineId = filter_var(
                $input['sales_invoice_line_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if ($invoiceLineId === false || isset($seen[$invoiceLineId])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_invoice_line_id" => [
                        'The selected Sales Invoice line is invalid or duplicated.',
                    ],
                ]);
            }

            $seen[$invoiceLineId] = true;
            $sourceLine = $invoiceLines->get($invoiceLineId);

            if (!$sourceLine instanceof SalesInvoiceLine) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_invoice_line_id" => [
                        'The selected line does not belong to the source Sales Invoice.',
                    ],
                ]);
            }

            $lineType = trim((string) ($input['line_type'] ?? ''));

            if (!in_array($lineType, ['quantity', 'amount'], true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.line_type" => [
                        'The credit line type must be quantity or amount.',
                    ],
                ]);
            }

            $description = $this->text(
                $input['description'] ?? $sourceLine->description,
                4000,
            );

            $components = $postedComponents->get($sourceLine->getKey());

            $remainingGross = $this->remainingComponent(
                (string) $sourceLine->gross_amount,
                (string) ($components?->gross_amount ?? '0'),
            );

            $remainingDiscount = $this->remainingComponent(
                (string) $sourceLine->discount_amount,
                (string) ($components?->discount_amount ?? '0'),
            );

            $remainingSubtotal = $this->remainingComponent(
                BigDecimal::of((string) $sourceLine->gross_amount)
                    ->minus(BigDecimal::of((string) $sourceLine->discount_amount))
                    ->toScale(self::SCALE, RoundingMode::HALF_UP)
                    ->__toString(),
                (string) ($components?->subtotal ?? '0'),
            );

            $remainingTax = $this->remainingComponent(
                (string) $sourceLine->tax_amount,
                (string) ($components?->tax_amount ?? '0'),
            );

            $remainingTotal = $this->remainingComponent(
                (string) $sourceLine->line_total,
                (string) ($components?->line_total ?? '0'),
            );

            if (!$remainingTotal->isGreaterThan(BigDecimal::zero())) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_invoice_line_id" => [
                        "The invoice line {$sourceLine->product_name} has already been fully credited.",
                    ],
                ]);
            }

            $lineNumber = count($builtLines) + 1;
            $lineAllocations = [];

            if ($lineType === 'quantity') {
                $creditQuantity = $this->decimalValue(
                    $input['credit_quantity'] ?? null,
                    "lines.{$index}.credit_quantity",
                    false,
                );

                $availableQuantity = BigDecimal::of(
                    (string) $sourceLine->invoiced_quantity,
                )->minus(
                    BigDecimal::of((string) $sourceLine->credited_quantity),
                );

                if ($availableQuantity->isLessThan($creditQuantity)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.credit_quantity" => [
                            "Only {$this->decimal($availableQuantity)} remains creditable for {$sourceLine->product_name}.",
                        ],
                    ]);
                }

                $returnToStock = filter_var(
                    $input['return_to_stock'] ?? false,
                    FILTER_VALIDATE_BOOL,
                );

                if ($returnToStock && !$sourceLine->isStockItem()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.return_to_stock" => [
                            'Only stock products can be returned to inventory.',
                        ],
                    ]);
                }

                $sourceQuantity = BigDecimal::of(
                    (string) $sourceLine->invoiced_quantity,
                );

                if ($creditQuantity->isEqualTo($availableQuantity)) {
                    $gross = $remainingGross;
                    $discount = $remainingDiscount;
                    $subtotal = $remainingSubtotal;
                    $tax = $remainingTax;
                    $lineTotal = $remainingTotal;
                } else {
                    $gross = $this->prorated(
                        BigDecimal::of((string) $sourceLine->gross_amount),
                        $creditQuantity,
                        $sourceQuantity,
                        $remainingGross,
                    );

                    $discount = $this->prorated(
                        BigDecimal::of((string) $sourceLine->discount_amount),
                        $creditQuantity,
                        $sourceQuantity,
                        $remainingDiscount,
                    );

                    $subtotal = $gross
                        ->minus($discount)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);

                    if ($subtotal->isGreaterThan($remainingSubtotal)) {
                        $subtotal = $remainingSubtotal;
                    }

                    $tax = $this->prorated(
                        BigDecimal::of((string) $sourceLine->tax_amount),
                        $creditQuantity,
                        $sourceQuantity,
                        $remainingTax,
                    );

                    $lineTotal = $subtotal
                        ->plus($tax)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);

                    if ($lineTotal->isGreaterThan($remainingTotal)) {
                        $lineTotal = $remainingTotal;
                        $tax = $lineTotal
                            ->minus($subtotal)
                            ->toScale(self::SCALE, RoundingMode::HALF_UP);
                    }
                }

                $remainingToAllocate = $creditQuantity;
                $cost = BigDecimal::zero();
                $sources = $sourceAllocations->get(
                    $sourceLine->getKey(),
                    collect(),
                );

                foreach ($sources as $sourceAllocation) {
                    if (!$sourceAllocation instanceof SalesInvoiceDispatchAllocation) {
                        continue;
                    }

                    $used = BigDecimal::of(
                        (string) (
                            $usedSourceAllocation[$sourceAllocation->getKey()]
                            ?? '0'
                        ),
                    );

                    $available = BigDecimal::of(
                        (string) $sourceAllocation->allocated_quantity,
                    )->minus($used);

                    if (!$available->isGreaterThan(BigDecimal::zero())) {
                        continue;
                    }

                    $take = $available->isLessThan($remainingToAllocate)
                        ? $available
                        : $remainingToAllocate;

                    $unitCost = BigDecimal::of(
                        (string) $sourceAllocation->unit_cost,
                    );

                    $allocationCost = $take
                        ->multipliedBy($unitCost)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);

                    $lineAllocations[] = [
                        'sales_invoice_dispatch_allocation_id' =>
                            $sourceAllocation->getKey(),
                        'customer_dispatch_line_id' =>
                            $sourceAllocation->customer_dispatch_line_id,
                        'allocated_quantity' => $this->decimal($take),
                        'unit_cost' => $this->decimal($unitCost),
                        'total_cost' => $this->decimal($allocationCost),
                    ];

                    $cost = $cost->plus($allocationCost);
                    $remainingToAllocate = $remainingToAllocate->minus($take);

                    if ($remainingToAllocate->isZero()) {
                        break;
                    }
                }

                if (!$remainingToAllocate->isZero()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.credit_quantity" => [
                            "The original dispatch-cost sources for {$sourceLine->product_name} are insufficient or already used by another credit note.",
                        ],
                    ]);
                }

                $unitCost = $creditQuantity->isZero()
                    ? BigDecimal::zero()->toScale(self::SCALE)
                    : $cost->dividedBy(
                        $creditQuantity,
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    );

                $builtLines[] = [
                    'sales_invoice_line_id' => $sourceLine->getKey(),
                    'sales_order_line_id' => $sourceLine->sales_order_line_id,
                    'product_id' => $sourceLine->product_id,
                    'unit_id' => $sourceLine->unit_id,
                    'line_number' => $lineNumber,
                    'line_type' => 'quantity',
                    'product_name' => $sourceLine->product_name,
                    'product_sku' => $sourceLine->product_sku,
                    'product_type' => $sourceLine->product_type,
                    'unit_name' => $sourceLine->unit_name,
                    'unit_code' => $sourceLine->unit_code,
                    'description' => $description,
                    'credit_quantity' => $this->decimal($creditQuantity),
                    'return_to_stock' => $returnToStock,
                    'unit_price' => (string) $sourceLine->unit_price,
                    'gross_amount' => $this->decimal($gross),
                    'discount_amount' => $this->decimal($discount),
                    'subtotal' => $this->decimal($subtotal),
                    'tax_rate' => (string) $sourceLine->tax_rate,
                    'tax_amount' => $this->decimal($tax),
                    'line_total' => $this->decimal($lineTotal),
                    'unit_cost' => $this->decimal($unitCost),
                    'total_cost' => $this->decimal($cost),
                ];

                $allocations[$lineNumber] = $lineAllocations;
                continue;
            }

            $creditAmount = $this->decimalValue(
                $input['credit_amount'] ?? null,
                "lines.{$index}.credit_amount",
                false,
            );

            if ($remainingTotal->isLessThan($creditAmount)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.credit_amount" => [
                        "Only {$this->decimal($remainingTotal)} remains creditable for {$sourceLine->product_name}.",
                    ],
                ]);
            }

            if ($creditAmount->isEqualTo($remainingTotal)) {
                $subtotal = $remainingSubtotal;
                $tax = $remainingTax;
            } else {
                $tax = $remainingTotal->isZero()
                    ? BigDecimal::zero()->toScale(self::SCALE)
                    : $creditAmount
                        ->multipliedBy($remainingTax)
                        ->dividedBy(
                            $remainingTotal,
                            self::SCALE,
                            RoundingMode::HALF_UP,
                        );

                if ($tax->isGreaterThan($remainingTax)) {
                    $tax = $remainingTax;
                }

                $subtotal = $creditAmount
                    ->minus($tax)
                    ->toScale(self::SCALE, RoundingMode::HALF_UP);

                if ($subtotal->isGreaterThan($remainingSubtotal)) {
                    $subtotal = $remainingSubtotal;
                    $tax = $creditAmount
                        ->minus($subtotal)
                        ->toScale(self::SCALE, RoundingMode::HALF_UP);
                }
            }

            $builtLines[] = [
                'sales_invoice_line_id' => $sourceLine->getKey(),
                'sales_order_line_id' => $sourceLine->sales_order_line_id,
                'product_id' => $sourceLine->product_id,
                'unit_id' => $sourceLine->unit_id,
                'line_number' => $lineNumber,
                'line_type' => 'amount',
                'product_name' => $sourceLine->product_name,
                'product_sku' => $sourceLine->product_sku,
                'product_type' => $sourceLine->product_type,
                'unit_name' => $sourceLine->unit_name,
                'unit_code' => $sourceLine->unit_code,
                'description' => $description,
                'credit_quantity' => '0.000000',
                'return_to_stock' => false,
                'unit_price' => '0.000000',
                'gross_amount' => $this->decimal($subtotal),
                'discount_amount' => '0.000000',
                'subtotal' => $this->decimal($subtotal),
                'tax_rate' => (string) $sourceLine->tax_rate,
                'tax_amount' => $this->decimal($tax),
                'line_total' => $this->decimal($creditAmount),
                'unit_cost' => '0.000000',
                'total_cost' => '0.000000',
            ];

            $allocations[$lineNumber] = [];
        }

        if ($builtLines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Enter a positive quantity or amount for at least one credit-note line.',
                ],
            ]);
        }

        return [
            'lines' => $builtLines,
            'allocations' => $allocations,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function ensureReturnWarehouse(
        SalesOrder $salesOrder,
        array $lines,
    ): void {
        $requiresWarehouse = false;

        foreach ($lines as $line) {
            if ((bool) ($line['return_to_stock'] ?? false)) {
                $requiresWarehouse = true;
                break;
            }
        }

        if (!$requiresWarehouse) {
            return;
        }

        if ($salesOrder->warehouse_id === null) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A fulfillment warehouse is required before any credited stock quantity can be returned to inventory.',
                ],
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array<string, string>
     */
    private function totals(array $lines): array
    {
        $gross = BigDecimal::zero();
        $discount = BigDecimal::zero();
        $subtotal = BigDecimal::zero();
        $tax = BigDecimal::zero();
        $total = BigDecimal::zero();
        $quantityCredit = BigDecimal::zero();
        $amountOnlyCredit = BigDecimal::zero();
        $returnedQuantity = BigDecimal::zero();
        $inventoryValue = BigDecimal::zero();

        foreach ($lines as $line) {
            $lineTotal = BigDecimal::of((string) $line['line_total']);

            $gross = $gross->plus(BigDecimal::of((string) $line['gross_amount']));
            $discount = $discount->plus(BigDecimal::of((string) $line['discount_amount']));
            $subtotal = $subtotal->plus(BigDecimal::of((string) $line['subtotal']));
            $tax = $tax->plus(BigDecimal::of((string) $line['tax_amount']));
            $total = $total->plus($lineTotal);

            if ($line['line_type'] === 'quantity') {
                $quantityCredit = $quantityCredit->plus($lineTotal);

                if ((bool) $line['return_to_stock']) {
                    $returnedQuantity = $returnedQuantity->plus(
                        BigDecimal::of((string) $line['credit_quantity']),
                    );

                    $inventoryValue = $inventoryValue->plus(
                        BigDecimal::of((string) $line['total_cost']),
                    );
                }
            } else {
                $amountOnlyCredit = $amountOnlyCredit->plus($lineTotal);
            }
        }

        if (!$total->isGreaterThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Customer Credit Note total must be greater than zero.',
                ],
            ]);
        }

        return [
            'gross_amount' => $this->decimal($gross),
            'discount_amount' => $this->decimal($discount),
            'subtotal' => $this->decimal($subtotal),
            'tax_amount' => $this->decimal($tax),
            'total_amount' => $this->decimal($total),
            'quantity_credit_amount' => $this->decimal($quantityCredit),
            'amount_only_credit_amount' => $this->decimal($amountOnlyCredit),
            'returned_quantity' => $this->decimal($returnedQuantity),
            'inventory_return_value' => $this->decimal($inventoryValue),
        ];
    }

    /**
     * @param array{
     *     lines: list<array<string, mixed>>,
     *     allocations: array<int, list<array<string, mixed>>>
     * } $built
     */
    private function replaceLinesAndAllocations(
        CustomerCreditNote $creditNote,
        array $built,
    ): void {
        CustomerCreditNoteLine::query()
            ->where('customer_credit_note_id', $creditNote->getKey())
            ->lockForUpdate()
            ->get()
            ->each(
                static function (CustomerCreditNoteLine $line): void {
                    $line->delete();
                },
            );

        foreach ($built['lines'] as $lineData) {
            $line = $creditNote->lines()->create($lineData);

            foreach (
                $built['allocations'][(int) $lineData['line_number']] ?? []
                as $allocation
            ) {
                $line->dispatchAllocations()->create($allocation);
            }
        }
    }

    private function refreshDocumentFromStoredLines(
        CustomerCreditNote $creditNote,
        User $actor,
    ): void {
        $invoice = $this->lockSourceInvoice(
            salesInvoiceId: (int) $creditNote->sales_invoice_id,
            actor: $actor,
            requireActiveBranch: true,
        );

        $storedLines = CustomerCreditNoteLine::query()
            ->where('customer_credit_note_id', $creditNote->getKey())
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        $input = $storedLines
            ->map(
                static fn (CustomerCreditNoteLine $line): array => [
                    'sales_invoice_line_id' => $line->sales_invoice_line_id,
                    'line_type' => $line->line_type,
                    'credit_quantity' => (string) $line->credit_quantity,
                    'credit_amount' => (string) $line->line_total,
                    'return_to_stock' => $line->return_to_stock,
                    'description' => $line->description,
                ],
            )
            ->values()
            ->all();

        $built = $this->buildLines(
            invoice: $invoice,
            inputLines: $input,
            excludingCreditNoteId: (int) $creditNote->getKey(),
        );

        $this->ensureReturnWarehouse(
            salesOrder: $this->lockSalesOrder($invoice),
            lines: $built['lines'],
        );

        $creditNote->fill($this->totals($built['lines']));
        $creditNote->save();

        $this->replaceLinesAndAllocations(
            creditNote: $creditNote,
            built: $built,
        );
    }

    private function refreshTotalsFromPersistedLines(
        CustomerCreditNote $creditNote,
    ): void {
        $lines = CustomerCreditNoteLine::query()
            ->where('customer_credit_note_id', $creditNote->getKey())
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get()
            ->map(
                static fn (CustomerCreditNoteLine $line): array => [
                    'line_type' => $line->line_type,
                    'return_to_stock' => $line->return_to_stock,
                    'credit_quantity' => (string) $line->credit_quantity,
                    'gross_amount' => (string) $line->gross_amount,
                    'discount_amount' => (string) $line->discount_amount,
                    'subtotal' => (string) $line->subtotal,
                    'tax_amount' => (string) $line->tax_amount,
                    'line_total' => (string) $line->line_total,
                    'total_cost' => (string) $line->total_cost,
                ],
            )
            ->values()
            ->all();

        $creditNote->fill($this->totals($lines));
        $creditNote->save();
    }

    /**
     * @param EloquentCollection<int, CustomerCreditNoteLine> $creditNoteLines
     */
    private function applySourceQuantities(
        SalesInvoice $invoice,
        SalesOrder $salesOrder,
        EloquentCollection $creditNoteLines,
        bool $reverse,
    ): void {
        $invoiceLines = SalesInvoiceLine::query()
            ->where('sales_invoice_id', $invoice->getKey())
            ->whereIn(
                'id',
                $creditNoteLines->pluck('sales_invoice_line_id')->all(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $orderLines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->getKey())
            ->whereIn(
                'id',
                $creditNoteLines->pluck('sales_order_line_id')->all(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($creditNoteLines as $creditLine) {
            $invoiceLine = $invoiceLines->get(
                (int) $creditLine->sales_invoice_line_id,
            );

            if (!$invoiceLine instanceof SalesInvoiceLine) {
                throw new LogicException(
                    'A source Sales Invoice line is unavailable during credit-note posting.',
                );
            }

            $currentAmount = BigDecimal::of(
                (string) $invoiceLine->credited_amount,
            );

            $lineAmount = BigDecimal::of(
                (string) $creditLine->line_total,
            );

            $nextAmount = $reverse
                ? $currentAmount->minus($lineAmount)
                : $currentAmount->plus($lineAmount);

            if (
                $nextAmount->isLessThan(BigDecimal::zero())
                || $nextAmount->isGreaterThan(
                    BigDecimal::of((string) $invoiceLine->line_total),
                )
            ) {
                throw new LogicException(
                    'The Sales Invoice credited amount would move outside its valid range.',
                );
            }

            $invoiceLine->credited_amount = $this->decimal($nextAmount);

            if ($creditLine->isQuantityCredit()) {
                $currentQuantity = BigDecimal::of(
                    (string) $invoiceLine->credited_quantity,
                );

                $quantity = BigDecimal::of(
                    (string) $creditLine->credit_quantity,
                );

                $nextQuantity = $reverse
                    ? $currentQuantity->minus($quantity)
                    : $currentQuantity->plus($quantity);

                if (
                    $nextQuantity->isLessThan(BigDecimal::zero())
                    || $nextQuantity->isGreaterThan(
                        BigDecimal::of((string) $invoiceLine->invoiced_quantity),
                    )
                ) {
                    throw new LogicException(
                        'The Sales Invoice credited quantity would move outside its valid range.',
                    );
                }

                $invoiceLine->credited_quantity = $this->decimal($nextQuantity);
            }

            $invoiceLine->save();

            if (!$creditLine->restoresInventory()) {
                continue;
            }

            $orderLine = $orderLines->get(
                (int) $creditLine->sales_order_line_id,
            );

            if (!$orderLine instanceof SalesOrderLine) {
                throw new LogicException(
                    'A source Sales Order line is unavailable during sales-return posting.',
                );
            }

            $currentReturned = BigDecimal::of(
                (string) $orderLine->returned_quantity,
            );

            $quantity = BigDecimal::of(
                (string) $creditLine->credit_quantity,
            );

            $nextReturned = $reverse
                ? $currentReturned->minus($quantity)
                : $currentReturned->plus($quantity);

            if (
                $nextReturned->isLessThan(BigDecimal::zero())
                || $nextReturned->isGreaterThan(
                    BigDecimal::of((string) $orderLine->invoiced_quantity),
                )
            ) {
                throw new LogicException(
                    'The Sales Order returned quantity would move outside its valid range.',
                );
            }

            $orderLine->returned_quantity = $this->decimal($nextReturned);
            $orderLine->save();
        }
    }

    private function lockSourceInvoice(
        int $salesInvoiceId,
        User $actor,
        bool $requireActiveBranch,
    ): SalesInvoice {
        $invoice = SalesInvoice::query()
            ->whereKey($salesInvoiceId)
            ->lockForUpdate()
            ->first();

        if (!$invoice instanceof SalesInvoice) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => [
                    'The selected Sales Invoice is unavailable.',
                ],
            ]);
        }

        $this->authorizeBranch(
            branchId: (int) $invoice->branch_id,
            actor: $actor,
            requireActive: $requireActiveBranch,
        );

        if (!$invoice->isPosted()) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => [
                    'Only a posted Sales Invoice can be credited.',
                ],
            ]);
        }

        if (!$invoice->hasInvoiceNumber()) {
            throw new LogicException(
                'The posted Sales Invoice does not retain its document number.',
            );
        }

        return $invoice;
    }

    private function lockSalesOrder(SalesInvoice $invoice): SalesOrder
    {
        $salesOrder = SalesOrder::query()
            ->whereKey($invoice->sales_order_id)
            ->lockForUpdate()
            ->first();

        if (!$salesOrder instanceof SalesOrder) {
            throw new LogicException(
                'The source Sales Order is unavailable.',
            );
        }

        if (
            (int) $salesOrder->tenant_id !== (int) $invoice->tenant_id
            || (int) $salesOrder->branch_id !== (int) $invoice->branch_id
            || (int) $salesOrder->customer_id !== (int) $invoice->customer_id
        ) {
            throw new LogicException(
                'The Sales Invoice and Sales Order context does not match.',
            );
        }

        return $salesOrder;
    }

    private function authorizeBranch(
        int $branchId,
        User $actor,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey($branchId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: $requireActive,
        );
    }

    private function ensureEditable(CustomerCreditNote $creditNote): void
    {
        if (!$creditNote->canBeEdited()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only a draft Customer Credit Note can be edited.',
                ],
            ]);
        }
    }

    private function loadCreditNote(
        CustomerCreditNote $creditNote,
    ): CustomerCreditNote {
        return $creditNote->load([
            'salesInvoice:id,invoice_number,status,total_amount,currency_code',
            'salesOrder:id,document_number,status',
            'branch:id,name,code,status',
            'warehouse:id,branch_id,name,code,status',
            'customer:id,name,code,status',
            'lines.dispatchAllocations.salesInvoiceDispatchAllocation',
            'lines.dispatchAllocations.customerDispatchLine.dispatch:id,dispatch_number,dispatch_date,status',
            'lines.stockLedgerEntry',
            'lines.reversalStockLedgerEntry',
            'customerLedgerEntry',
            'customerOpenItem',
            'automaticAllocation',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);
    }

    private function remainingComponent(
        string $source,
        string $used,
    ): BigDecimal {
        $remaining = BigDecimal::of($source)
            ->minus(BigDecimal::of($used))
            ->toScale(self::SCALE, RoundingMode::HALF_UP);

        if ($remaining->isLessThan(BigDecimal::zero())) {
            throw new LogicException(
                'Previously posted Customer Credit Notes exceed a source invoice component.',
            );
        }

        return $remaining;
    }

    private function prorated(
        BigDecimal $sourceAmount,
        BigDecimal $quantity,
        BigDecimal $sourceQuantity,
        BigDecimal $remainingMaximum,
    ): BigDecimal {
        if ($sourceQuantity->isZero() || $sourceAmount->isZero()) {
            return BigDecimal::zero()->toScale(self::SCALE);
        }

        $value = $sourceAmount
            ->multipliedBy($quantity)
            ->dividedBy(
                $sourceQuantity,
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        return $value->isGreaterThan($remainingMaximum)
            ? $remainingMaximum
            : $value;
    }

    private function draftKey(SalesInvoice $invoice): string
    {
        return sprintf(
            'sales-invoice:%d:customer-credit-note-draft',
            (int) $invoice->getKey(),
        );
    }

    private function numberAllocationKey(
        CustomerCreditNote $creditNote,
    ): string {
        return sprintf(
            'customer-credit-note:%d:%d',
            (int) $creditNote->tenant_id,
            (int) $creditNote->getKey(),
        );
    }

    private function date(
        mixed $value,
        string $field,
        string $timezone,
    ): string {
        if (!is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        $value = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        if (
            !$date instanceof CarbonImmutable
            || $date->format('Y-m-d') !== $value
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
    }

    private function text(mixed $value, int $maximum): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'customer_credit_note' => [
                    'Text fields must contain valid text.',
                ],
            ]);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximum) {
            throw ValidationException::withMessages([
                'customer_credit_note' => [
                    "A Customer Credit Note text field exceeds {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function requiredReason(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 500) {
            throw ValidationException::withMessages([
                $field => [
                    'A reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return $value;
    }

    private function decimalValue(
        mixed $value,
        string $field,
        bool $allowZero,
    ): BigDecimal {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be a valid number.',
                ],
            ]);
        }

        $value = trim((string) $value);

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be a non-negative number.',
                ],
            ]);
        }

        try {
            $decimal = BigDecimal::of($value)
                ->toScale(self::SCALE, RoundingMode::UNNECESSARY);
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    'The value may not exceed 6 decimal places.',
                ],
            ]);
        }

        if (
            !$allowZero
            && !$decimal->isGreaterThan(BigDecimal::zero())
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be greater than zero.',
                ],
            ]);
        }

        return $decimal;
    }

    private function decimal(BigDecimal $value): string
    {
        return $value
            ->toScale(self::SCALE, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorTenant(User $actor, int $tenantId): void
    {
        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The selected user does not belong to the active tenant.',
            );
        }
    }

    private function ensureCreditNoteTenant(
        CustomerCreditNote $creditNote,
        int $tenantId,
    ): void {
        if ((int) $creditNote->tenant_id !== $tenantId) {
            throw new LogicException(
                'The selected Customer Credit Note belongs to another tenant.',
            );
        }
    }
}
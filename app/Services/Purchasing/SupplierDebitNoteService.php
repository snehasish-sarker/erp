<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Events\Purchasing\SupplierDebitNotePosted;
use App\Events\Purchasing\SupplierDebitNoteReversed;
use App\Models\Branch;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\SupplierDebitNote;
use App\Models\SupplierDebitNoteAllocation;
use App\Models\SupplierDebitNoteLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Purchasing\SupplierDebitNoteStatusRegistry;
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

final class SupplierDebitNoteService
{
    private const SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly SupplierDebitNoteCalculator $calculator,
        private readonly SupplierDebitNoteStatusRegistry $statusRegistry,
        private readonly SupplierDebitNoteAccountingGateway $accountingGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $normalized,
                $actor,
            ): SupplierDebitNote {
                $purchaseReturn =
                    $this->resolvePurchaseReturn(
                        purchaseReturnId:
                            $normalized[
                                'purchase_return_id'
                            ],

                        actor: $actor,

                        requireActiveBranch:
                            true,
                    );

                $existingDebitNote =
                    SupplierDebitNote::query()
                        ->where(
                            'purchase_return_id',
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->withTrashed()
                        ->lockForUpdate()
                        ->first();

                if (
                    $existingDebitNote
                    instanceof SupplierDebitNote
                ) {
                    throw ValidationException::withMessages([
                        'purchase_return_id' => [
                            'A Supplier Debit Note already exists for the selected Purchase Return.',
                        ],
                    ]);
                }

                $supplierInvoice =
                    $this->resolveSupplierInvoice(
                        supplierInvoiceId:
                            $normalized[
                                'supplier_invoice_id'
                            ],

                        purchaseReturn:
                            $purchaseReturn,
                    );

                $lines = $this->buildLines(
                    purchaseReturn:
                        $purchaseReturn,

                    supplierInvoice:
                        $supplierInvoice,

                    inputLines:
                        $normalized['lines'],
                );

                $totals =
                    $this->calculator
                        ->calculateTotals(
                            $lines,
                        );

                $allocationTotals =
                    $this->allocationTotals(
                        totalAmount:
                            BigDecimal::of(
                                $totals[
                                    'total_amount'
                                ],
                            ),

                        supplierInvoice:
                            $supplierInvoice,
                    );

                $currency =
                    $this->currencyData(
                        purchaseReturn:
                            $purchaseReturn,

                        supplierInvoice:
                            $supplierInvoice,
                    );

                $supplierDebitNote =
                    SupplierDebitNote::query()
                        ->create([
                            'purchase_return_id' =>
                                $purchaseReturn
                                    ->getKey(),

                            'supplier_invoice_id' =>
                                $supplierInvoice
                                    ?->getKey(),

                            'purchase_order_id' =>
                                $purchaseReturn
                                    ->purchase_order_id,

                            'goods_receipt_id' =>
                                $purchaseReturn
                                    ->goods_receipt_id,

                            'branch_id' =>
                                $purchaseReturn
                                    ->branch_id,

                            'supplier_id' =>
                                $purchaseReturn
                                    ->supplier_id,

                            'debit_note_date' =>
                                $normalized[
                                    'debit_note_date'
                                ],

                            'posting_date' =>
                                $normalized[
                                    'posting_date'
                                ],

                            'currency_code' =>
                                $currency[
                                    'currency_code'
                                ],

                            'exchange_rate' =>
                                $currency[
                                    'exchange_rate'
                                ],

                            'supplier_name' =>
                                $purchaseReturn
                                    ->supplier_name,

                            'supplier_code' =>
                                $purchaseReturn
                                    ->supplier_code,

                            'purchase_return_number' =>
                                $purchaseReturn
                                    ->return_number,

                            'supplier_invoice_number' =>
                                $supplierInvoice
                                    ?->supplier_invoice_number,

                            'purchase_order_number' =>
                                $purchaseReturn
                                    ->purchase_order_number,

                            'goods_receipt_number' =>
                                $purchaseReturn
                                    ->goods_receipt_number,

                            'source_purchase_return_revision' =>
                                (int) $purchaseReturn
                                    ->revision,

                            'status' => 'draft',

                            ...$totals,
                            ...$allocationTotals,

                            'purchase_return_supplier_value' =>
                                $purchaseReturn
                                    ->total_supplier_value,

                            'purchase_return_inventory_value' =>
                                $purchaseReturn
                                    ->total_inventory_value,

                            'purchase_return_cost_variance' =>
                                $purchaseReturn
                                    ->total_cost_variance,

                            'supplier_reference' =>
                                $normalized[
                                    'supplier_reference'
                                ],

                            'reason' =>
                                $normalized[
                                    'reason'
                                ],

                            'notes' =>
                                $normalized['notes'],

                            'revision' => 1,

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                $this->replaceLines(
                    supplierDebitNote:
                        $supplierDebitNote,

                    lines: $lines,
                );

                $this->replaceDraftAllocation(
                    supplierDebitNote:
                        $supplierDebitNote,

                    supplierInvoice:
                        $supplierInvoice,
                );

                return $this->loadDebitNote(
                    $supplierDebitNote,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        SupplierDebitNote $supplierDebitNote,
        array $data,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $normalized,
                $actor,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: false,
                );

                if (
                    !$this->statusRegistry
                        ->isEditable(
                            $lockedDebitNote
                                ->status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Supplier Debit Note can be edited.',
                        ],
                    ]);
                }

                if (
                    (int) $lockedDebitNote
                        ->purchase_return_id
                    !== $normalized[
                        'purchase_return_id'
                    ]
                ) {
                    throw ValidationException::withMessages([
                        'purchase_return_id' => [
                            'The source Purchase Return cannot be changed after the Supplier Debit Note has been created.',
                        ],
                    ]);
                }

                $this->ensureNumberedIdentityUnchanged(
                    supplierDebitNote:
                        $lockedDebitNote,

                    debitNoteDate:
                        $normalized[
                            'debit_note_date'
                        ],
                );

                $purchaseReturn =
                    $this->resolvePurchaseReturn(
                        purchaseReturnId:
                            (int) $lockedDebitNote
                                ->purchase_return_id,

                        actor: $actor,

                        requireActiveBranch:
                            true,
                    );

                $supplierInvoice =
                    $this->resolveSupplierInvoice(
                        supplierInvoiceId:
                            $normalized[
                                'supplier_invoice_id'
                            ],

                        purchaseReturn:
                            $purchaseReturn,
                    );

                $lines = $this->buildLines(
                    purchaseReturn:
                        $purchaseReturn,

                    supplierInvoice:
                        $supplierInvoice,

                    inputLines:
                        $normalized['lines'],
                );

                $totals =
                    $this->calculator
                        ->calculateTotals(
                            $lines,
                        );

                $allocationTotals =
                    $this->allocationTotals(
                        totalAmount:
                            BigDecimal::of(
                                $totals[
                                    'total_amount'
                                ],
                            ),

                        supplierInvoice:
                            $supplierInvoice,
                    );

                $currency =
                    $this->currencyData(
                        purchaseReturn:
                            $purchaseReturn,

                        supplierInvoice:
                            $supplierInvoice,
                    );

                $lockedDebitNote->fill([
                    'supplier_invoice_id' =>
                        $supplierInvoice
                            ?->getKey(),

                    'debit_note_date' =>
                        $normalized[
                            'debit_note_date'
                        ],

                    'posting_date' =>
                        $normalized[
                            'posting_date'
                        ],

                    'currency_code' =>
                        $currency[
                            'currency_code'
                        ],

                    'exchange_rate' =>
                        $currency[
                            'exchange_rate'
                        ],

                    'supplier_invoice_number' =>
                        $supplierInvoice
                            ?->supplier_invoice_number,

                    'source_purchase_return_revision' =>
                        (int) $purchaseReturn
                            ->revision,

                    ...$totals,
                    ...$allocationTotals,

                    'purchase_return_supplier_value' =>
                        $purchaseReturn
                            ->total_supplier_value,

                    'purchase_return_inventory_value' =>
                        $purchaseReturn
                            ->total_inventory_value,

                    'purchase_return_cost_variance' =>
                        $purchaseReturn
                            ->total_cost_variance,

                    'supplier_reference' =>
                        $normalized[
                            'supplier_reference'
                        ],

                    'reason' =>
                        $normalized['reason'],

                    'notes' =>
                        $normalized['notes'],

                    'revision' =>
                        (int) $lockedDebitNote
                            ->revision + 1,
                ]);

                $lockedDebitNote->save();

                $this->replaceLines(
                    supplierDebitNote:
                        $lockedDebitNote,

                    lines: $lines,
                );

                $this->replaceDraftAllocation(
                    supplierDebitNote:
                        $lockedDebitNote,

                    supplierInvoice:
                        $supplierInvoice,
                );

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $supplierDebitNote,
                $actor,
            ): void {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: false,
                );

                if (
                    !$lockedDebitNote
                        ->canBeDeleted()
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an unnumbered, never-submitted Supplier Debit Note draft can be deleted.',
                        ],
                    ]);
                }

                $this->deleteAllocations(
                    $lockedDebitNote,
                );

                $this->deleteLines(
                    $lockedDebitNote,
                );

                /*
                * Draft Supplier Debit Notes must be physically deleted.
                *
                * purchase_return_id has a unique database constraint and
                * create() deliberately checks withTrashed(). Soft deleting
                * the draft would permanently prevent another Debit Note
                * from being created for the Purchase Return.
                */
                $lockedDebitNote->forceDelete();
            },
            attempts: 5,
        );
    }

    public function submit(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $actor,
                $tenant,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'submitted',
                );

                $this->validateSourceIntegrity(
                    $lockedDebitNote,
                );

                if (
                    !$lockedDebitNote
                        ->hasDebitNoteNumber()
                ) {
                    $allocation =
                        $this->documentNumberService
                            ->allocate(
                                documentType:
                                    'debit_note',

                                branchId:
                                    (int) $lockedDebitNote
                                        ->branch_id,

                                idempotencyKey:
                                    $this->numberAllocationKey(
                                        $lockedDebitNote,
                                    ),

                                allocatableType:
                                    SupplierDebitNote::class,

                                allocatableId:
                                    (int) $lockedDebitNote
                                        ->getKey(),

                                allocatedAt:
                                    $this->businessDateTime(
                                        date:
                                            $lockedDebitNote
                                                ->debit_note_date
                                                ->toDateString(),

                                        tenant:
                                            $tenant,
                                    ),
                            );

                    $lockedDebitNote
                        ->document_number_allocation_id =
                            $allocation
                                ->getKey();

                    $lockedDebitNote
                        ->debit_note_number =
                            $allocation
                                ->number;
                }

                $lockedDebitNote->status =
                    'submitted';

                $lockedDebitNote
                    ->submitted_by_user_id =
                        $actor->getKey();

                $lockedDebitNote->submitted_at =
                    CarbonImmutable::now('UTC');

                $lockedDebitNote->save();

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $actor,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'draft',
                );

                $lockedDebitNote->status =
                    'draft';

                $lockedDebitNote
                    ->submitted_by_user_id =
                        null;

                $lockedDebitNote
                    ->submitted_at =
                        null;

                $lockedDebitNote->revision =
                    (int) $lockedDebitNote
                        ->revision + 1;

                $lockedDebitNote->save();

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function approve(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): SupplierDebitNote {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $actor,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'approved',
                );

                if (
                    !$lockedDebitNote
                        ->hasDebitNoteNumber()
                ) {
                    throw new LogicException(
                        'A submitted Supplier Debit Note must have a document number before approval.',
                    );
                }

                $this->validateSourceIntegrity(
                    $lockedDebitNote,
                );

                $this->reserveInvoiceAllocation(
                    $lockedDebitNote,
                );

                $lockedDebitNote->status =
                    'approved';

                $lockedDebitNote
                    ->approved_by_user_id =
                        $actor->getKey();

                $lockedDebitNote->approved_at =
                    CarbonImmutable::now('UTC');

                $lockedDebitNote->save();

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        SupplierDebitNote $supplierDebitNote,
        string $reason,
        User $actor,
    ): SupplierDebitNote {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $reason,
                $actor,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'cancelled',
                );

                if (
                    $lockedDebitNote
                        ->isApproved()
                ) {
                    $this->releaseInvoiceReservation(
                        $lockedDebitNote,
                        markCancelled: true,
                    );
                } else {
                    $this->cancelDraftAllocations(
                        $lockedDebitNote,
                    );
                }

                $lockedDebitNote->status =
                    'cancelled';

                $lockedDebitNote
                    ->cancelled_by_user_id =
                        $actor->getKey();

                $lockedDebitNote->cancelled_at =
                    CarbonImmutable::now('UTC');

                $lockedDebitNote
                    ->cancellation_reason =
                        $reason;

                $lockedDebitNote->save();

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'posted',
                );

                $this->validateSourceIntegrity(
                    $lockedDebitNote,
                );

                $this->validatePostingAllocation(
                    $lockedDebitNote,
                );

                $postingDate =
                    $this->businessDateTime(
                        date:
                            $lockedDebitNote
                                ->posting_date
                                ->toDateString(),

                        tenant: $tenant,
                    );

                $accountingPeriod =
                    $this->accountingPeriodService
                        ->lockOpenPeriod(
                            $postingDate,
                        );

                /*
                 * The gateway must create a complete,
                 * balanced journal and Accounts Payable
                 * supplier-subledger posting or throw.
                 *
                 * The default gateway deliberately throws,
                 * so the document and invoice allocation
                 * remain unchanged until accounting exists.
                 */
                $accountingReference =
                    $this->accountingGateway
                        ->post(
                            supplierDebitNote:
                                $lockedDebitNote,

                            accountingPeriod:
                                $accountingPeriod,

                            actor: $actor,
                        );

                $accountingReference =
                    $this->requiredAccountingReference(
                        $accountingReference,
                    );

                $this->applyInvoiceAllocation(
                    $lockedDebitNote,
                );

                $lockedDebitNote->status =
                    'posted';

                $lockedDebitNote
                    ->posted_by_user_id =
                        $actor->getKey();

                $lockedDebitNote->posted_at =
                    CarbonImmutable::now('UTC');

                $lockedDebitNote
                    ->accounting_posting_reference =
                        $accountingReference;

                $lockedDebitNote->save();

                SupplierDebitNotePosted::dispatch(
                    tenantId: $tenantId,

                    supplierDebitNoteId:
                        (int) $lockedDebitNote
                            ->getKey(),

                    purchaseReturnId:
                        (int) $lockedDebitNote
                            ->purchase_return_id,

                    supplierInvoiceId:
                        $lockedDebitNote
                            ->supplier_invoice_id
                        !== null
                            ? (int) $lockedDebitNote
                                ->supplier_invoice_id
                            : null,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        SupplierDebitNote $supplierDebitNote,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): SupplierDebitNote {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDebitNoteBelongsToTenant(
            supplierDebitNote:
                $supplierDebitNote,

            tenantId: $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'reversal_reason',
        );

        $normalizedReversalDate =
            $this->normalizeDate(
                value: $reversalPostingDate,

                field:
                    'reversal_posting_date',

                tenant: $tenant,
            );

        return DB::transaction(
            function () use (
                $supplierDebitNote,
                $normalizedReversalDate,
                $reason,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierDebitNote {
                $lockedDebitNote =
                    SupplierDebitNote::query()
                        ->whereKey(
                            $supplierDebitNote
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeDebitNoteBranch(
                    actor: $actor,

                    supplierDebitNote:
                        $lockedDebitNote,

                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierDebitNote:
                        $lockedDebitNote,

                    nextStatus: 'reversed',
                );

                if (
                    $normalizedReversalDate
                    < $lockedDebitNote
                        ->posting_date
                        ->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'reversal_posting_date' => [
                            'The reversal posting date cannot be before the original posting date.',
                        ],
                    ]);
                }

                if (
                    $lockedDebitNote
                        ->accounting_posting_reference
                    === null
                    || trim(
                        $lockedDebitNote
                            ->accounting_posting_reference,
                    ) === ''
                ) {
                    throw new LogicException(
                        'The posted Supplier Debit Note does not contain an accounting posting reference.',
                    );
                }

                $this->validateAppliedAllocation(
                    $lockedDebitNote,
                );

                $reversalDate =
                    $this->businessDateTime(
                        date:
                            $normalizedReversalDate,

                        tenant: $tenant,
                    );

                $accountingPeriod =
                    $this->accountingPeriodService
                        ->lockOpenPeriod(
                            $reversalDate,
                        );

                $accountingReversalReference =
                    $this->accountingGateway
                        ->reverse(
                            supplierDebitNote:
                                $lockedDebitNote,

                            accountingPeriod:
                                $accountingPeriod,

                            reversalPostingDate:
                                $reversalDate,

                            reason: $reason,

                            actor: $actor,
                        );

                $accountingReversalReference =
                    $this->requiredAccountingReference(
                        $accountingReversalReference,
                    );

                $this->reverseInvoiceAllocation(
                    $lockedDebitNote,
                );

                $lockedDebitNote->status =
                    'reversed';

                $lockedDebitNote
                    ->reversal_posting_date =
                        $normalizedReversalDate;

                $lockedDebitNote
                    ->reversed_by_user_id =
                        $actor->getKey();

                $lockedDebitNote->reversed_at =
                    CarbonImmutable::now('UTC');

                $lockedDebitNote
                    ->reversal_reason =
                        $reason;

                $lockedDebitNote
                    ->accounting_reversal_reference =
                        $accountingReversalReference;

                $lockedDebitNote->save();

                SupplierDebitNoteReversed::dispatch(
                    tenantId: $tenantId,

                    supplierDebitNoteId:
                        (int) $lockedDebitNote
                            ->getKey(),

                    purchaseReturnId:
                        (int) $lockedDebitNote
                            ->purchase_return_id,

                    supplierInvoiceId:
                        $lockedDebitNote
                            ->supplier_invoice_id
                        !== null
                            ? (int) $lockedDebitNote
                                ->supplier_invoice_id
                            : null,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadDebitNote(
                    $lockedDebitNote->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     purchase_return_id: int,
     *     supplier_invoice_id: int|null,
     *     debit_note_date: string,
     *     posting_date: string,
     *     supplier_reference: string|null,
     *     reason: string,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $debitNoteDate =
            $this->normalizeDate(
                value:
                    $data[
                        'debit_note_date'
                    ] ?? null,

                field:
                    'debit_note_date',

                tenant: $tenant,
            );

        $postingDate =
            $this->normalizeDate(
                value:
                    $data[
                        'posting_date'
                    ] ?? $debitNoteDate,

                field:
                    'posting_date',

                tenant: $tenant,
            );

        if (
            $postingDate
            < $debitNoteDate
        ) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date cannot be before the Debit Note date.',
                ],
            ]);
        }

        $lines = $data['lines'] ?? null;

        if (
            !is_array($lines)
            || $lines === []
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Supplier Debit Note must contain all Purchase Return lines.',
                ],
            ]);
        }

        if (count($lines) > 500) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Supplier Debit Note may not contain more than 500 lines.',
                ],
            ]);
        }

        return [
            'purchase_return_id' =>
                $this->requiredId(
                    value:
                        $data[
                            'purchase_return_id'
                        ] ?? null,

                    field:
                        'purchase_return_id',

                    message:
                        'The selected Purchase Return is invalid.',
                ),

            'supplier_invoice_id' =>
                $this->nullableId(
                    value:
                        $data[
                            'supplier_invoice_id'
                        ] ?? null,

                    field:
                        'supplier_invoice_id',

                    message:
                        'The selected Supplier Invoice is invalid.',
                ),

            'debit_note_date' =>
                $debitNoteDate,

            'posting_date' =>
                $postingDate,

            'supplier_reference' =>
                $this->nullableString(
                    value:
                        $data[
                            'supplier_reference'
                        ] ?? null,

                    maximum: 160,

                    field:
                        'supplier_reference',
                ),

            'reason' =>
                $this->requiredReason(
                    reason:
                        is_string(
                            $data['reason'] ?? null,
                        )
                            ? (string) $data[
                                'reason'
                            ]
                            : '',

                    field: 'reason',
                ),

            'notes' =>
                $this->nullableString(
                    value:
                        $data['notes'] ?? null,

                    maximum: 4000,

                    field: 'notes',
                ),

            'lines' =>
                array_values($lines),
        ];
    }

    private function resolvePurchaseReturn(
        int $purchaseReturnId,
        User $actor,
        bool $requireActiveBranch,
    ): PurchaseReturn {
        $purchaseReturn =
            PurchaseReturn::query()
                ->with([
                    'purchaseOrder',
                    'goodsReceipt',
                    'supplier',
                ])
                ->whereKey(
                    $purchaseReturnId,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$purchaseReturn
            instanceof PurchaseReturn
        ) {
            throw ValidationException::withMessages([
                'purchase_return_id' => [
                    'The selected Purchase Return could not be found.',
                ],
            ]);
        }

        if (
            !$purchaseReturn
                ->isPosted()
        ) {
            throw ValidationException::withMessages([
                'purchase_return_id' => [
                    'Only a posted Purchase Return can create a Supplier Debit Note.',
                ],
            ]);
        }

        if (
            !$purchaseReturn
                ->hasReturnNumber()
        ) {
            throw new LogicException(
                'A posted Purchase Return must have a return number.',
            );
        }

        $branch = Branch::query()
            ->whereKey(
                $purchaseReturn
                    ->branch_id,
            )
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,

                requireActive:
                    $requireActiveBranch,
            );

        return $purchaseReturn;
    }

    private function resolveSupplierInvoice(
        ?int $supplierInvoiceId,
        PurchaseReturn $purchaseReturn,
    ): ?SupplierInvoice {
        if ($supplierInvoiceId === null) {
            return null;
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $supplierInvoiceId,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$supplierInvoice
            instanceof SupplierInvoice
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The selected Supplier Invoice could not be found.',
                ],
            ]);
        }

        if (
            (int) $supplierInvoice
                ->purchase_order_id
            !== (int) $purchaseReturn
                ->purchase_order_id
            || (int) $supplierInvoice
                ->supplier_id
            !== (int) $purchaseReturn
                ->supplier_id
            || (int) $supplierInvoice
                ->branch_id
            !== (int) $purchaseReturn
                ->branch_id
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The Supplier Invoice does not belong to the source Purchase Order, supplier, and branch.',
                ],
            ]);
        }

        if (
            !in_array(
                $supplierInvoice->status,
                [
                    'approved',
                    'posted',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'Only an approved or posted Supplier Invoice can be linked to a Supplier Debit Note.',
                ],
            ]);
        }

        return $supplierInvoice;
    }

    /**
     * @param list<array<string, mixed>> $inputLines
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(
        PurchaseReturn $purchaseReturn,
        ?SupplierInvoice $supplierInvoice,
        array $inputLines,
    ): array {
        $sourceLines =
            PurchaseReturnLine::query()
                ->where(
                    'purchase_return_id',
                    $purchaseReturn
                        ->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        if ($sourceLines->isEmpty()) {
            throw new LogicException(
                'The posted Purchase Return has no lines.',
            );
        }

        if (
            count($inputLines)
            !== $sourceLines->count()
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Every Purchase Return line must be included exactly once on the Supplier Debit Note.',
                ],
            ]);
        }

        $inputBySourceLineId = [];

        foreach (
            $inputLines
            as $index => $inputLine
        ) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each Supplier Debit Note line must be an object.',
                    ],
                ]);
            }

            $purchaseReturnLineId =
                $this->requiredId(
                    value:
                        $inputLine[
                            'purchase_return_line_id'
                        ] ?? null,

                    field:
                        "lines.{$index}.purchase_return_line_id",

                    message:
                        'The selected Purchase Return line is invalid.',
                );

            if (
                isset(
                    $inputBySourceLineId[
                        $purchaseReturnLineId
                    ],
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_return_line_id" => [
                        'A Purchase Return line may only appear once.',
                    ],
                ]);
            }

            $inputBySourceLineId[
                $purchaseReturnLineId
            ] = [
                'index' => $index,
                'data' => $inputLine,
            ];
        }

        $builtLines = [];

        foreach (
            $sourceLines
            as $sourceLine
        ) {
            $inputEntry =
                $inputBySourceLineId[
                    (int) $sourceLine
                        ->getKey()
                ] ?? null;

            if (!is_array($inputEntry)) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Purchase Return line {$sourceLine->line_number} is missing from the Supplier Debit Note.",
                    ],
                ]);
            }

            $index = (int) $inputEntry[
                'index'
            ];

            /** @var array<string, mixed> $inputLine */
            $inputLine = $inputEntry['data'];

            $returnQuantity =
                $this->positiveDecimal(
                    value:
                        $inputLine[
                            'return_quantity'
                        ] ?? $sourceLine
                            ->return_quantity,

                    field:
                        "lines.{$index}.return_quantity",

                    scale: self::SCALE,
                );

            $sourceQuantity =
                BigDecimal::of(
                    (string) $sourceLine
                        ->return_quantity,
                )->toScale(
                    self::SCALE,
                    RoundingMode::Unnecessary,
                );

            if (
                !$returnQuantity
                    ->isEqualTo(
                        $sourceQuantity,
                    )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.return_quantity" => [
                        'The Supplier Debit Note quantity must exactly match the posted Purchase Return quantity.',
                    ],
                ]);
            }

            $supplierInvoiceLine =
                $this->resolveSupplierInvoiceLine(
                    supplierInvoice:
                        $supplierInvoice,

                    supplierInvoiceLineId:
                        $this->nullableId(
                            value:
                                $inputLine[
                                    'supplier_invoice_line_id'
                                ] ?? null,

                            field:
                                "lines.{$index}.supplier_invoice_line_id",

                            message:
                                'The selected Supplier Invoice line is invalid.',
                        ),

                    sourceLine:
                        $sourceLine,

                    field:
                        "lines.{$index}.supplier_invoice_line_id",
                );

            $unitPrice =
                $this->nonNegativeDecimal(
                    value:
                        $inputLine[
                            'unit_price'
                        ] ?? $sourceLine
                            ->supplier_unit_cost,

                    field:
                        "lines.{$index}.unit_price",

                    scale: self::SCALE,
                );

            $discountPerUnit =
                $this->nonNegativeDecimal(
                    value:
                        $inputLine[
                            'discount_per_unit'
                        ] ?? '0',

                    field:
                        "lines.{$index}.discount_per_unit",

                    scale: self::SCALE,
                );

            $taxRate =
                $this->nonNegativeDecimal(
                    value:
                        $inputLine[
                            'tax_rate'
                        ] ?? '0',

                    field:
                        "lines.{$index}.tax_rate",

                    scale: self::SCALE,
                );

            if (
                $taxRate->isGreaterThan(
                    BigDecimal::of('100'),
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tax_rate" => [
                        'The tax rate may not exceed 100 percent.',
                    ],
                ]);
            }

            $calculated =
                $this->calculator
                    ->calculateLine(
                        returnQuantity:
                            $returnQuantity,

                        unitPrice:
                            $unitPrice,

                        discountPerUnit:
                            $discountPerUnit,

                        taxRate:
                            $taxRate,
                    );

            $builtLines[] = [
                'purchase_return_line_id' =>
                    $sourceLine
                        ->getKey(),

                'supplier_invoice_line_id' =>
                    $supplierInvoiceLine
                        ?->getKey(),

                'product_id' =>
                    $sourceLine
                        ->product_id,

                'unit_id' =>
                    $sourceLine
                        ->unit_id,

                'line_number' =>
                    (int) $sourceLine
                        ->line_number,

                'product_name' =>
                    $sourceLine
                        ->product_name,

                'product_sku' =>
                    $sourceLine
                        ->product_sku,

                'unit_name' =>
                    $sourceLine
                        ->unit_name,

                'unit_code' =>
                    $sourceLine
                        ->unit_code,

                ...$calculated,

                'purchase_return_supplier_unit_cost' =>
                    $sourceLine
                        ->supplier_unit_cost,

                'purchase_return_supplier_total_cost' =>
                    $sourceLine
                        ->supplier_total_cost,

                'purchase_return_inventory_unit_cost' =>
                    $sourceLine
                        ->inventory_unit_cost,

                'purchase_return_inventory_total_cost' =>
                    $sourceLine
                        ->inventory_total_cost,

                'purchase_return_cost_variance' =>
                    $sourceLine
                        ->cost_variance_amount,

                'description' =>
                    $this->nullableString(
                        value:
                            $inputLine[
                                'description'
                            ] ?? null,

                        maximum: 500,

                        field:
                            "lines.{$index}.description",
                    ),

                'notes' =>
                    $this->nullableString(
                        value:
                            $inputLine[
                                'notes'
                            ] ?? null,

                        maximum: 2000,

                        field:
                            "lines.{$index}.notes",
                    ),
            ];
        }

        return $builtLines;
    }

    private function resolveSupplierInvoiceLine(
        ?SupplierInvoice $supplierInvoice,
        ?int $supplierInvoiceLineId,
        PurchaseReturnLine $sourceLine,
        string $field,
    ): ?SupplierInvoiceLine {
        if ($supplierInvoice === null) {
            if ($supplierInvoiceLineId !== null) {
                throw ValidationException::withMessages([
                    $field => [
                        'A Supplier Invoice line cannot be selected without linking a Supplier Invoice.',
                    ],
                ]);
            }

            return null;
        }

        if ($supplierInvoiceLineId === null) {
            throw ValidationException::withMessages([
                $field => [
                    'Each Debit Note line must be mapped to a Supplier Invoice line when an invoice is linked.',
                ],
            ]);
        }

        $supplierInvoiceLine =
            SupplierInvoiceLine::query()
                ->whereKey(
                    $supplierInvoiceLineId,
                )
                ->where(
                    'supplier_invoice_id',
                    $supplierInvoice
                        ->getKey(),
                )
                ->lockForUpdate()
                ->first();

        if (
            !$supplierInvoiceLine
            instanceof SupplierInvoiceLine
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected Supplier Invoice line does not belong to the linked invoice.',
                ],
            ]);
        }

        if (
            (int) $supplierInvoiceLine
                ->product_id
            !== (int) $sourceLine
                ->product_id
            || (int) $supplierInvoiceLine
                ->unit_id
            !== (int) $sourceLine
                ->unit_id
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The Supplier Invoice line product and unit must match the Purchase Return line.',
                ],
            ]);
        }

        return $supplierInvoiceLine;
    }

    private function validateSourceIntegrity(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $purchaseReturn =
            PurchaseReturn::query()
                ->whereKey(
                    $supplierDebitNote
                        ->purchase_return_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (
            !$purchaseReturn->isPosted()
        ) {
            throw ValidationException::withMessages([
                'purchase_return_id' => [
                    'The source Purchase Return is no longer posted.',
                ],
            ]);
        }

        if (
            (int) $purchaseReturn
                ->revision
            !== (int) $supplierDebitNote
                ->source_purchase_return_revision
        ) {
            throw ValidationException::withMessages([
                'purchase_return_id' => [
                    'The source Purchase Return revision no longer matches this Supplier Debit Note.',
                ],
            ]);
        }

        if (
            (int) $purchaseReturn
                ->branch_id
            !== (int) $supplierDebitNote
                ->branch_id
            || (int) $purchaseReturn
                ->supplier_id
            !== (int) $supplierDebitNote
                ->supplier_id
            || (int) $purchaseReturn
                ->purchase_order_id
            !== (int) $supplierDebitNote
                ->purchase_order_id
            || (int) $purchaseReturn
                ->goods_receipt_id
            !== (int) $supplierDebitNote
                ->goods_receipt_id
        ) {
            throw new LogicException(
                'The Supplier Debit Note source identity no longer matches its Purchase Return.',
            );
        }

        $sourceLines =
            PurchaseReturnLine::query()
                ->where(
                    'purchase_return_id',
                    $purchaseReturn
                        ->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        $debitNoteLines =
            $this->lockDebitNoteLines(
                $supplierDebitNote,
            );

        if (
            $sourceLines->count()
            !== $debitNoteLines->count()
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Supplier Debit Note no longer contains every source Purchase Return line.',
                ],
            ]);
        }

        $debitNoteLinesBySource =
            $debitNoteLines->keyBy(
                'purchase_return_line_id',
            );

        foreach (
            $sourceLines
            as $sourceLine
        ) {
            $debitNoteLine =
                $debitNoteLinesBySource->get(
                    (int) $sourceLine
                        ->getKey(),
                );

            if (
                !$debitNoteLine
                instanceof SupplierDebitNoteLine
            ) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Purchase Return line {$sourceLine->line_number} is missing from the Supplier Debit Note.",
                    ],
                ]);
            }

            $sourceQuantity =
                BigDecimal::of(
                    (string) $sourceLine
                        ->return_quantity,
                );

            $debitQuantity =
                BigDecimal::of(
                    (string) $debitNoteLine
                        ->return_quantity,
                );

            if (
                !$sourceQuantity
                    ->isEqualTo(
                        $debitQuantity,
                    )
                || (int) $sourceLine
                    ->product_id
                    !== (int) $debitNoteLine
                        ->product_id
                || (int) $sourceLine
                    ->unit_id
                    !== (int) $debitNoteLine
                        ->unit_id
            ) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Supplier Debit Note line {$debitNoteLine->line_number} no longer matches the source Purchase Return.",
                    ],
                ]);
            }
        }
    }

    private function reserveInvoiceAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            if (
                $supplierDebitNote
                    ->supplier_invoice_id
                !== null
                || !$this->decimal(
                    $supplierDebitNote
                        ->allocated_amount,
                )->isZero()
            ) {
                throw new LogicException(
                    'The Supplier Debit Note allocation state is inconsistent.',
                );
            }

            return;
        }

        if (!$allocation->isDraft()) {
            throw new LogicException(
                'Only a draft Supplier Debit Note allocation can be reserved.',
            );
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $this->ensureAllocationInvoiceMatches(
            supplierDebitNote:
                $supplierDebitNote,

            supplierInvoice:
                $supplierInvoice,

            allocation:
                $allocation,
        );

        if (
            !in_array(
                $supplierInvoice->status,
                [
                    'approved',
                    'posted',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The linked Supplier Invoice must remain approved or posted before the Debit Note can be approved.',
                ],
            ]);
        }

        $amount = $this->decimal(
            $allocation->amount,
        );

        $available =
            $supplierInvoice
                ->availableDebitNoteAmount()
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        if (
            $amount->isGreaterThan(
                $available,
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The Supplier Debit Note amount exceeds the amount still available against the linked Supplier Invoice.',
                ],
            ]);
        }

        $supplierInvoice
            ->debit_note_reserved_amount =
                $this->decimal(
                    $supplierInvoice
                        ->debit_note_reserved_amount,
                )
                    ->plus($amount)
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString();

        $supplierInvoice->save();

        $allocation->status =
            'reserved';

        $allocation->reserved_at =
            CarbonImmutable::now('UTC');

        $allocation->save();
    }

    private function releaseInvoiceReservation(
        SupplierDebitNote $supplierDebitNote,
        bool $markCancelled,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            return;
        }

        if (!$allocation->isReserved()) {
            throw new LogicException(
                'Only a reserved Supplier Debit Note allocation can be released.',
            );
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $amount = $this->decimal(
            $allocation->amount,
        );

        $reserved =
            $this->decimal(
                $supplierInvoice
                    ->debit_note_reserved_amount,
            );

        if (
            $reserved->isLessThan(
                $amount,
            )
        ) {
            throw new LogicException(
                'The Supplier Invoice reserved Debit Note amount is lower than the allocation being released.',
            );
        }

        $supplierInvoice
            ->debit_note_reserved_amount =
                $reserved
                    ->minus($amount)
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString();

        $supplierInvoice->save();

        $allocation->status =
            $markCancelled
                ? 'cancelled'
                : 'draft';

        $allocation->reserved_at =
            null;

        if ($markCancelled) {
            $allocation->cancelled_at =
                CarbonImmutable::now('UTC');
        }

        $allocation->save();
    }

    private function validatePostingAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            if (
                $supplierDebitNote
                    ->supplier_invoice_id
                !== null
                || !$this->decimal(
                    $supplierDebitNote
                        ->allocated_amount,
                )->isZero()
            ) {
                throw new LogicException(
                    'The Supplier Debit Note allocation state is inconsistent.',
                );
            }

            return;
        }

        if (!$allocation->isReserved()) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The Supplier Invoice allocation is not reserved.',
                ],
            ]);
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $this->ensureAllocationInvoiceMatches(
            supplierDebitNote:
                $supplierDebitNote,

            supplierInvoice:
                $supplierInvoice,

            allocation:
                $allocation,
        );

        if (
            $supplierInvoice->status
            !== 'posted'
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'A linked Supplier Invoice must be financially posted before the Supplier Debit Note can be posted.',
                ],
            ]);
        }

        $reserved =
            $this->decimal(
                $supplierInvoice
                    ->debit_note_reserved_amount,
            );

        $amount = $this->decimal(
            $allocation->amount,
        );

        if (
            $reserved->isLessThan(
                $amount,
            )
        ) {
            throw new LogicException(
                'The Supplier Invoice reserved Debit Note amount is lower than the posting allocation.',
            );
        }
    }

    private function applyInvoiceAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            return;
        }

        if (!$allocation->isReserved()) {
            throw new LogicException(
                'Only a reserved Supplier Debit Note allocation can be applied.',
            );
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $amount = $this->decimal(
            $allocation->amount,
        );

        $reserved =
            $this->decimal(
                $supplierInvoice
                    ->debit_note_reserved_amount,
            );

        if (
            $reserved->isLessThan(
                $amount,
            )
        ) {
            throw new LogicException(
                'The Supplier Invoice reserved Debit Note amount is lower than the applied amount.',
            );
        }

        $supplierInvoice
            ->debit_note_reserved_amount =
                $reserved
                    ->minus($amount)
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    )
                    ->__toString();

        $supplierInvoice->debited_amount =
            $this->decimal(
                $supplierInvoice
                    ->debited_amount,
            )
                ->plus($amount)
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $supplierInvoice->save();

        $allocation->status =
            'applied';

        $allocation->applied_at =
            CarbonImmutable::now('UTC');

        $allocation->save();
    }

    private function validateAppliedAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            return;
        }

        if (!$allocation->isApplied()) {
            throw new LogicException(
                'The Supplier Debit Note allocation has not been applied.',
            );
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $amount = $this->decimal(
            $allocation->amount,
        );

        if (
            $this->decimal(
                $supplierInvoice
                    ->debited_amount,
            )->isLessThan(
                $amount,
            )
        ) {
            throw new LogicException(
                'The Supplier Invoice debited amount is lower than the allocation being reversed.',
            );
        }
    }

    private function reverseInvoiceAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocation =
            $this->lockSingleAllocation(
                $supplierDebitNote,
            );

        if ($allocation === null) {
            return;
        }

        if (!$allocation->isApplied()) {
            throw new LogicException(
                'Only an applied Supplier Debit Note allocation can be reversed.',
            );
        }

        $supplierInvoice =
            SupplierInvoice::query()
                ->whereKey(
                    $allocation
                        ->supplier_invoice_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $amount = $this->decimal(
            $allocation->amount,
        );

        $debited =
            $this->decimal(
                $supplierInvoice
                    ->debited_amount,
            );

        if (
            $debited->isLessThan(
                $amount,
            )
        ) {
            throw new LogicException(
                'The Supplier Invoice debited amount is lower than the allocation being reversed.',
            );
        }

        $supplierInvoice->debited_amount =
            $debited
                ->minus($amount)
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString();

        $supplierInvoice->save();

        $allocation->status =
            'reversed';

        $allocation->reversed_at =
            CarbonImmutable::now('UTC');

        $allocation->save();
    }

    private function cancelDraftAllocations(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocations =
            $this->lockAllocations(
                $supplierDebitNote,
            );

        foreach ($allocations as $allocation) {
            if (!$allocation->isDraft()) {
                throw new LogicException(
                    'Only draft Supplier Debit Note allocations can be cancelled before approval.',
                );
            }

            $allocation->status =
                'cancelled';

            $allocation->cancelled_at =
                CarbonImmutable::now('UTC');

            $allocation->save();
        }
    }

    private function replaceDraftAllocation(
        SupplierDebitNote $supplierDebitNote,
        ?SupplierInvoice $supplierInvoice,
    ): void {
        $this->deleteAllocations(
            $supplierDebitNote,
        );

        if ($supplierInvoice === null) {
            return;
        }

        $supplierDebitNote
            ->allocations()
            ->create([
                'supplier_invoice_id' =>
                    $supplierInvoice
                        ->getKey(),

                'amount' =>
                    $supplierDebitNote
                        ->total_amount,

                'status' => 'draft',
            ]);
    }

    /**
     * @return array{
     *     allocated_amount: string,
     *     unallocated_amount: string
     * }
     */
    private function allocationTotals(
        BigDecimal $totalAmount,
        ?SupplierInvoice $supplierInvoice,
    ): array {
        $totalAmount =
            $totalAmount->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        if ($supplierInvoice === null) {
            return [
                'allocated_amount' =>
                    '0.000000',

                'unallocated_amount' =>
                    $totalAmount
                        ->__toString(),
            ];
        }

        return [
            'allocated_amount' =>
                $totalAmount
                    ->__toString(),

            'unallocated_amount' =>
                '0.000000',
        ];
    }

    /**
     * @return array{
     *     currency_code: string,
     *     exchange_rate: string
     * }
     */
    private function currencyData(
        PurchaseReturn $purchaseReturn,
        ?SupplierInvoice $supplierInvoice,
    ): array {
        if ($supplierInvoice !== null) {
            return [
                'currency_code' =>
                    strtoupper(
                        $supplierInvoice
                            ->currency_code,
                    ),

                'exchange_rate' =>
                    $this->positiveDecimal(
                        value:
                            $supplierInvoice
                                ->exchange_rate,

                        field:
                            'exchange_rate',

                        scale:
                            self::EXCHANGE_RATE_SCALE,
                    )
                        ->__toString(),
            ];
        }

        $purchaseReturn->loadMissing(
            'purchaseOrder',
        );

        $purchaseOrder =
            $purchaseReturn
                ->purchaseOrder;

        if ($purchaseOrder === null) {
            throw new LogicException(
                'The source Purchase Order is unavailable.',
            );
        }

        return [
            'currency_code' =>
                strtoupper(
                    $purchaseOrder
                        ->currency_code,
                ),

            'exchange_rate' =>
                $this->positiveDecimal(
                    value:
                        $purchaseOrder
                            ->exchange_rate,

                    field:
                        'exchange_rate',

                    scale:
                        self::EXCHANGE_RATE_SCALE,
                )
                    ->__toString(),
        ];
    }

    private function ensureAllocationInvoiceMatches(
        SupplierDebitNote $supplierDebitNote,
        SupplierInvoice $supplierInvoice,
        SupplierDebitNoteAllocation $allocation,
    ): void {
        if (
            (int) $allocation
                ->supplier_debit_note_id
            !== (int) $supplierDebitNote
                ->getKey()
            || (int) $allocation
                ->supplier_invoice_id
            !== (int) $supplierInvoice
                ->getKey()
            || (int) $supplierDebitNote
                ->supplier_invoice_id
            !== (int) $supplierInvoice
                ->getKey()
            || (int) $supplierInvoice
                ->supplier_id
            !== (int) $supplierDebitNote
                ->supplier_id
            || (int) $supplierInvoice
                ->branch_id
            !== (int) $supplierDebitNote
                ->branch_id
            || (int) $supplierInvoice
                ->purchase_order_id
            !== (int) $supplierDebitNote
                ->purchase_order_id
        ) {
            throw new LogicException(
                'The Supplier Debit Note allocation does not match its linked Supplier Invoice.',
            );
        }
    }

    /**
     * @return Collection<int, SupplierDebitNoteLine>
     */
    private function lockDebitNoteLines(
        SupplierDebitNote $supplierDebitNote,
    ): Collection {
        return SupplierDebitNoteLine::query()
            ->where(
                'supplier_debit_note_id',
                $supplierDebitNote
                    ->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return Collection<int, SupplierDebitNoteAllocation>
     */
    private function lockAllocations(
        SupplierDebitNote $supplierDebitNote,
    ): Collection {
        return SupplierDebitNoteAllocation::query()
            ->where(
                'supplier_debit_note_id',
                $supplierDebitNote
                    ->getKey(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function lockSingleAllocation(
        SupplierDebitNote $supplierDebitNote,
    ): ?SupplierDebitNoteAllocation {
        $allocations =
            $this->lockAllocations(
                $supplierDebitNote,
            );

        if ($allocations->count() > 1) {
            throw new LogicException(
                'The Supplier Debit Note contains more than one allocation.',
            );
        }

        $allocation =
            $allocations->first();

        return $allocation
            instanceof SupplierDebitNoteAllocation
                ? $allocation
                : null;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        SupplierDebitNote $supplierDebitNote,
        array $lines,
    ): void {
        $this->deleteLines(
            $supplierDebitNote,
        );

        foreach ($lines as $line) {
            $supplierDebitNote
                ->lines()
                ->create($line);
        }
    }

    private function deleteLines(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $lines =
            $this->lockDebitNoteLines(
                $supplierDebitNote,
            );

        foreach ($lines as $line) {
            $line->delete();
        }
    }

    private function deleteAllocations(
        SupplierDebitNote $supplierDebitNote,
    ): void {
        $allocations =
            $this->lockAllocations(
                $supplierDebitNote,
            );

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isDraft()
            ) {
                throw new LogicException(
                    'Only draft Supplier Debit Note allocations can be replaced or deleted.',
                );
            }

            $allocation->delete();
        }
    }

    private function ensureTransition(
        SupplierDebitNote $supplierDebitNote,
        string $nextStatus,
    ): void {
        if (
            $this->statusRegistry
                ->canTransition(
                    currentStatus:
                        $supplierDebitNote
                            ->status,

                    nextStatus:
                        $nextStatus,
                )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                "The Supplier Debit Note cannot move from {$supplierDebitNote->status} to {$nextStatus}.",
            ],
        ]);
    }

    private function ensureNumberedIdentityUnchanged(
        SupplierDebitNote $supplierDebitNote,
        string $debitNoteDate,
    ): void {
        if (
            !$supplierDebitNote
                ->hasDebitNoteNumber()
        ) {
            return;
        }

        if (
            $supplierDebitNote
                ->debit_note_date
                ?->toDateString()
            !== $debitNoteDate
        ) {
            throw ValidationException::withMessages([
                'debit_note_date' => [
                    'The Debit Note date cannot be changed after its number has been allocated.',
                ],
            ]);
        }
    }

    private function authorizeDebitNoteBranch(
        User $actor,
        SupplierDebitNote $supplierDebitNote,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey(
                $supplierDebitNote
                    ->branch_id,
            )
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function loadDebitNote(
        SupplierDebitNote $supplierDebitNote,
    ): SupplierDebitNote {
        return $supplierDebitNote->load([
            'purchaseReturn',
            'supplierInvoice',
            'purchaseOrder',
            'goodsReceipt',
            'branch',
            'supplier',
            'documentNumberAllocation',

            'lines.purchaseReturnLine',
            'lines.supplierInvoiceLine',
            'lines.product',
            'lines.unit',

            'allocations.supplierInvoice',

            'createdBy',
            'submittedBy',
            'approvedBy',
            'postedBy',
            'reversedBy',
            'cancelledBy',
        ]);
    }

    private function decimal(
        mixed $value,
    ): BigDecimal {
        return BigDecimal::of(
            (string) ($value ?? '0'),
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );
    }

    private function positiveDecimal(
        mixed $value,
        string $field,
        int $scale,
    ): BigDecimal {
        $decimal =
            $this->normalizedDecimal(
                value: $value,
                field: $field,
                scale: $scale,
            );

        if (!$decimal->isPositive()) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be greater than zero.',
                ],
            ]);
        }

        return $decimal;
    }

    private function nonNegativeDecimal(
        mixed $value,
        string $field,
        int $scale,
    ): BigDecimal {
        $decimal =
            $this->normalizedDecimal(
                value: $value,
                field: $field,
                scale: $scale,
            );

        if (
            $decimal->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value cannot be negative.',
                ],
            ]);
        }

        return $decimal;
    }

    private function normalizedDecimal(
        mixed $value,
        string $field,
        int $scale,
    ): BigDecimal {
        if (
            $value === null
            || $value === ''
            || !is_scalar($value)
            || preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                trim((string) $value),
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be a valid number.',
                ],
            ]);
        }

        try {
            $decimal =
                BigDecimal::of(
                    trim((string) $value),
                )->toScale(
                    $scale,
                    RoundingMode::Unnecessary,
                );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not contain more than {$scale} decimal places.",
                ],
            ]);
        }

        if (
            $decimal->abs()
                ->isGreaterThan(
                    BigDecimal::of(
                        self::MAXIMUM_AMOUNT,
                    ),
                )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value exceeds the supported maximum.',
                ],
            ]);
        }

        return $decimal;
    }

    private function normalizeDate(
        mixed $value,
        string $field,
        Tenant $tenant,
    ): string {
        if (
            $value
            instanceof DateTimeInterface
        ) {
            return CarbonImmutable::instance(
                $value,
            )
                ->setTimezone(
                    $tenant->timezone,
                )
                ->toDateString();
        }

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date is required.',
                ],
            ]);
        }

        $value = trim($value);

        $date =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                $tenant->timezone,
            );

        if (
            !$date instanceof CarbonImmutable
            || $date->format('Y-m-d')
                !== $value
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $date->toDateString();
    }

    private function businessDateTime(
        string $date,
        Tenant $tenant,
    ): CarbonImmutable {
        $dateTime =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                $tenant->timezone,
            );

        if (
            !$dateTime
            instanceof CarbonImmutable
        ) {
            throw new LogicException(
                'The Supplier Debit Note business date is invalid.',
            );
        }

        return $dateTime
            ->startOfDay()
            ->utc();
    }

    private function requiredId(
        mixed $value,
        string $field,
        string $message,
    ): int {
        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^[1-9]\d*$/',
                trim($value),
            ) === 1
        ) {
            return (int) trim($value);
        }

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }

    private function nullableId(
        mixed $value,
        string $field,
        string $message,
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->requiredId(
            value: $value,
            field: $field,
            message: $message,
        );
    }

    private function nullableString(
        mixed $value,
        int $maximum,
        string $field,
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be text.',
                ],
            ]);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maximum
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function requiredReason(
        string $reason,
        string $field,
    ): string {
        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'A reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return $reason;
    }

    private function requiredAccountingReference(
        string $reference,
    ): string {
        $reference = trim($reference);

        if (
            $reference === ''
            || mb_strlen($reference) > 190
        ) {
            throw new LogicException(
                'The accounting gateway returned an invalid posting reference.',
            );
        }

        return $reference;
    }

    private function numberAllocationKey(
        SupplierDebitNote $supplierDebitNote,
    ): string {
        return sprintf(
            'supplier-debit-note:%d:document-number',
            (int) $supplierDebitNote
                ->getKey(),
        );
    }

    private function activeTenantId(): int
    {
        return (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $actor->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The user does not belong to the active tenant.',
        );
    }

    private function ensureDebitNoteBelongsToTenant(
        SupplierDebitNote $supplierDebitNote,
        int $tenantId,
    ): void {
        if (
            (int) $supplierDebitNote
                ->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The Supplier Debit Note does not belong to the active tenant.',
        );
    }
}
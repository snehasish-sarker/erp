<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Events\Purchasing\SupplierInvoicePosted;
use App\Events\Purchasing\SupplierInvoiceReversed;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Purchasing\SupplierInvoiceMatchStatusRegistry;
use App\Support\Purchasing\SupplierInvoiceStatusRegistry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierInvoiceService
{
    private const EXCHANGE_RATE_SCALE = 8;

    private const MAXIMUM_EXCHANGE_RATE =
        '999999999999.99999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly SupplierInvoiceMatchingService $matchingService,
        private readonly SupplierInvoiceStatusRegistry $statusRegistry,
        private readonly SupplierInvoiceMatchStatusRegistry $matchStatusRegistry,
        private readonly SupplierInvoiceAccountingGateway $accountingGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): SupplierInvoice {
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
                $tenant,
                $actor,
                $normalized,
            ): SupplierInvoice {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $purchaseOrder = $this->resolvePurchaseOrder(
                    purchaseOrderId:
                        $normalized['purchase_order_id'],
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $supplier = $this->resolveSupplier(
                    purchaseOrder: $purchaseOrder,
                );

                $commercial = $this->commercialAttributes(
                    normalized: $normalized,
                    purchaseOrder: $purchaseOrder,
                    supplier: $supplier,
                    tenant: $tenant,
                );

                $this->ensureUniqueSupplierInvoiceNumber(
                    supplierId: (int) $supplier->getKey(),
                    numberHash:
                        $normalized[
                            'supplier_invoice_number_hash'
                        ],
                );

                $matching = $this->matchingService->build(
                    purchaseOrder: $purchaseOrder,
                    inputLines: $normalized['lines'],
                    otherCharges: $normalized['other_charges'],
                    roundingAdjustment:
                        $normalized['rounding_adjustment'],
                );

                $supplierInvoice =
                    SupplierInvoice::query()->create([
                        'branch_id' =>
                            $purchaseOrder->branch_id,

                        'supplier_id' =>
                            $supplier->getKey(),

                        'purchase_order_id' =>
                            $purchaseOrder->getKey(),

                        'supplier_invoice_number' =>
                            $normalized[
                                'supplier_invoice_number'
                            ],

                        'supplier_invoice_number_normalized' =>
                            $normalized[
                                'supplier_invoice_number_normalized'
                            ],

                        'supplier_invoice_number_hash' =>
                            $normalized[
                                'supplier_invoice_number_hash'
                            ],

                        'invoice_date' =>
                            $normalized['invoice_date'],

                        'posting_date' =>
                            $normalized['posting_date'],

                        ...$commercial,
                        ...$matching['totals'],

                        'status' => 'draft',

                        'match_status' =>
                            $matching['match_status'],

                        'notes' => $normalized['notes'],

                        'matching_notes' =>
                            $normalized['matching_notes'],

                        'revision' => 1,

                        'created_by_user_id' =>
                            $actor->getKey(),
                    ]);

                $this->replaceLines(
                    supplierInvoice: $supplierInvoice,
                    lines: $matching['lines'],
                );

                return $this->loadInvoice(
                    $supplierInvoice,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        SupplierInvoice $supplierInvoice,
        array $data,
        User $actor,
    ): SupplierInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $tenant,
                $supplierInvoice,
                $actor,
                $normalized,
            ): SupplierInvoice {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                $this->ensureEditable(
                    $lockedInvoice,
                );

                $this->ensureNumberedDocumentIdentityUnchanged(
                    supplierInvoice: $lockedInvoice,
                    purchaseOrderId:
                        $normalized['purchase_order_id'],
                    invoiceDate:
                        $normalized['invoice_date'],
                );

                $purchaseOrder = $this->resolvePurchaseOrder(
                    purchaseOrderId:
                        $normalized['purchase_order_id'],
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $supplier = $this->resolveSupplier(
                    purchaseOrder: $purchaseOrder,
                );

                $commercial = $this->commercialAttributes(
                    normalized: $normalized,
                    purchaseOrder: $purchaseOrder,
                    supplier: $supplier,
                    tenant: $tenant,
                );

                $this->ensureUniqueSupplierInvoiceNumber(
                    supplierId:
                        (int) $supplier->getKey(),

                    numberHash:
                        $normalized[
                            'supplier_invoice_number_hash'
                        ],

                    ignoreInvoiceId:
                        (int) $lockedInvoice->getKey(),
                );

                $matching = $this->matchingService->build(
                    purchaseOrder: $purchaseOrder,
                    inputLines: $normalized['lines'],
                    otherCharges:
                        $normalized['other_charges'],
                    roundingAdjustment:
                        $normalized['rounding_adjustment'],
                );

                $lockedInvoice->fill([
                    'branch_id' =>
                        $purchaseOrder->branch_id,

                    'supplier_id' =>
                        $supplier->getKey(),

                    'purchase_order_id' =>
                        $purchaseOrder->getKey(),

                    'supplier_invoice_number' =>
                        $normalized[
                            'supplier_invoice_number'
                        ],

                    'supplier_invoice_number_normalized' =>
                        $normalized[
                            'supplier_invoice_number_normalized'
                        ],

                    'supplier_invoice_number_hash' =>
                        $normalized[
                            'supplier_invoice_number_hash'
                        ],

                    'invoice_date' =>
                        $normalized['invoice_date'],

                    'posting_date' =>
                        $normalized['posting_date'],

                    ...$commercial,
                    ...$matching['totals'],

                    'match_status' =>
                        $matching['match_status'],

                    'notes' =>
                        $normalized['notes'],

                    'matching_notes' =>
                        $normalized['matching_notes'],

                    'revision' =>
                        (int) $lockedInvoice->revision + 1,
                ]);

                $lockedInvoice->save();

                $this->replaceLines(
                    supplierInvoice: $lockedInvoice,
                    lines: $matching['lines'],
                );

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $supplierInvoice,
                $actor,
            ): void {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                if (!$lockedInvoice->canBeDeleted()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an unnumbered, never-validated draft supplier invoice can be deleted.',
                        ],
                    ]);
                }

                $this->deleteLines(
                    $lockedInvoice,
                );

                $lockedInvoice->delete();
            },
            attempts: 5,
        );
    }

    public function validate(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): SupplierInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $actor,
                $tenant,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'validated',
                );

                if (
                    !$this->matchStatusRegistry
                        ->allowsValidation(
                            $lockedInvoice->match_status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'match_status' => [
                            'The supplier invoice must be fully matched before validation.',
                        ],
                    ]);
                }

                if (
                    !$lockedInvoice
                        ->hasMatchingReservation()
                ) {
                    $this->matchingService->reserve(
                        $lockedInvoice,
                    );

                    $lockedInvoice->matching_reserved_at =
                        CarbonImmutable::now('UTC');
                }

                if (!$lockedInvoice->hasDocumentNumber()) {
                    $allocation =
                        $this->documentNumberService->allocate(
                            documentType:
                                'supplier_invoice',

                            branchId:
                                (int) $lockedInvoice->branch_id,

                            idempotencyKey:
                                $this->allocationKey(
                                    $lockedInvoice,
                                ),

                            allocatableType:
                                SupplierInvoice::class,

                            allocatableId:
                                (int) $lockedInvoice->getKey(),

                            allocatedAt:
                                $this->businessDateTime(
                                    date:
                                        $lockedInvoice
                                            ->invoice_date
                                            ->toDateString(),

                                    tenant: $tenant,
                                ),
                        );

                    $lockedInvoice
                        ->document_number_allocation_id =
                            $allocation->getKey();

                    $lockedInvoice->document_number =
                        $allocation->number;
                }

                if ($lockedInvoice->isDisputed()) {
                    $lockedInvoice
                        ->approved_by_user_id = null;

                    $lockedInvoice->approved_at = null;
                }

                $lockedInvoice->status = 'validated';

                $lockedInvoice->validated_by_user_id =
                    $actor->getKey();

                $lockedInvoice->validated_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice->disputed_by_user_id = null;
                $lockedInvoice->disputed_at = null;
                $lockedInvoice->dispute_reason = null;

                $lockedInvoice->save();

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): SupplierInvoice {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $actor,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'draft',
                );

                $this->ensureNoActiveSupplierDebitNotes(
                    $lockedInvoice,
                );

                $this->matchingService->release(
                    $lockedInvoice,
                );

                $lockedInvoice->status = 'draft';
                $lockedInvoice->matching_reserved_at = null;
                $lockedInvoice->validated_by_user_id = null;
                $lockedInvoice->validated_at = null;
                $lockedInvoice->approved_by_user_id = null;
                $lockedInvoice->approved_at = null;
                $lockedInvoice->disputed_by_user_id = null;
                $lockedInvoice->disputed_at = null;
                $lockedInvoice->dispute_reason = null;

                $lockedInvoice->revision =
                    (int) $lockedInvoice->revision + 1;

                $lockedInvoice->save();

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function approve(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): SupplierInvoice {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $actor,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'approved',
                );

                if (
                    !$lockedInvoice->hasDocumentNumber()
                    || !$lockedInvoice
                        ->hasMatchingReservation()
                ) {
                    throw new LogicException(
                        'A validated supplier invoice must have a document number and matching reservation before approval.',
                    );
                }

                $lockedInvoice->status = 'approved';

                $lockedInvoice->approved_by_user_id =
                    $actor->getKey();

                $lockedInvoice->approved_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice->save();

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function dispute(
        SupplierInvoice $supplierInvoice,
        string $reason,
        User $actor,
    ): SupplierInvoice {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'dispute_reason',
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $reason,
                $actor,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'disputed',
                );

                $this->ensureNoActiveSupplierDebitNotes(
                    $lockedInvoice,
                );

                /*
                 * A disputed invoice must not continue consuming
                 * uninvoiced Goods Receipt quantities. Validation
                 * will reserve them again after the dispute is fixed.
                 */
                $this->matchingService->release(
                    $lockedInvoice,
                );

                $lockedInvoice->status = 'disputed';
                $lockedInvoice->matching_reserved_at = null;

                $lockedInvoice->disputed_by_user_id =
                    $actor->getKey();

                $lockedInvoice->disputed_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice->dispute_reason = $reason;
                $lockedInvoice->save();

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        SupplierInvoice $supplierInvoice,
        string $reason,
        User $actor,
    ): SupplierInvoice {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $reason,
                $actor,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'cancelled',
                );

                $this->ensureNoActiveSupplierDebitNotes(
                    $lockedInvoice,
                );

                $this->matchingService->release(
                    $lockedInvoice,
                );

                $lockedInvoice->status = 'cancelled';
                $lockedInvoice->matching_reserved_at = null;

                $lockedInvoice->cancelled_by_user_id =
                    $actor->getKey();

                $lockedInvoice->cancelled_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice->cancellation_reason =
                    $reason;

                $lockedInvoice->save();

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): SupplierInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: true,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'posted',
                );

                if (
                    !$lockedInvoice->hasDocumentNumber()
                    || !$lockedInvoice
                        ->hasMatchingReservation()
                ) {
                    throw new LogicException(
                        'An approved supplier invoice must retain its document number and matching reservation before posting.',
                    );
                }

                $postingDate = $this->businessDateTime(
                    date:
                        $lockedInvoice
                            ->posting_date
                            ->toDateString(),

                    tenant: $tenant,
                );

                $accountingPeriod =
                    $this->accountingPeriodService
                        ->lockOpenPeriod(
                            $postingDate,
                        );

                $accountingReference =
                    $this->normalizeAccountingReference(
                        reference:
                            $this->accountingGateway->post(
                                supplierInvoice:
                                    $lockedInvoice,

                                accountingPeriod:
                                    $accountingPeriod,

                                actor: $actor,
                            ),

                        operation: 'posting',
                    );

                $lockedInvoice->status = 'posted';

                $lockedInvoice->posted_by_user_id =
                    $actor->getKey();

                $lockedInvoice->posted_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice
                    ->accounting_posting_reference =
                        $accountingReference;

                $lockedInvoice->save();

                SupplierInvoicePosted::dispatch(
                    tenantId: $tenantId,

                    supplierInvoiceId:
                        (int) $lockedInvoice->getKey(),

                    purchaseOrderId:
                        (int) $lockedInvoice
                            ->purchase_order_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        SupplierInvoice $supplierInvoice,
        string $reason,
        DateTimeInterface|string $reversalPostingDate,
        User $actor,
    ): SupplierInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureInvoiceBelongsToTenant(
            supplierInvoice: $supplierInvoice,
            tenantId: $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'reversal_reason',
        );

        $normalizedReversalDate =
            $this->normalizeDate(
                value: $reversalPostingDate,
                field: 'reversal_posting_date',
                tenant: $tenant,
            );

        return DB::transaction(
            function () use (
                $supplierInvoice,
                $reason,
                $normalizedReversalDate,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierInvoice {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey(
                        $supplierInvoice->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeInvoiceBranch(
                    actor: $actor,
                    supplierInvoice: $lockedInvoice,
                    requireActive: false,
                );

                $this->ensureTransition(
                    supplierInvoice: $lockedInvoice,
                    nextStatus: 'reversed',
                );

                $this->ensureNoActiveSupplierDebitNotes(
                    $lockedInvoice,
                );

                if (
                    $normalizedReversalDate
                    < $lockedInvoice
                        ->posting_date
                        ->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'reversal_posting_date' => [
                            'The reversal posting date cannot be before the original posting date.',
                        ],
                    ]);
                }

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

                $accountingReference =
                    $this->normalizeAccountingReference(
                        reference:
                            $this->accountingGateway
                                ->reverse(
                                    supplierInvoice:
                                        $lockedInvoice,

                                    accountingPeriod:
                                        $accountingPeriod,

                                    reversalPostingDate:
                                        $reversalDate,

                                    reason: $reason,
                                    actor: $actor,
                                ),

                        operation: 'reversal',
                    );

                $this->matchingService->release(
                    $lockedInvoice,
                );

                $lockedInvoice->status = 'reversed';
                $lockedInvoice->matching_reserved_at = null;

                $lockedInvoice->reversal_posting_date =
                    $normalizedReversalDate;

                $lockedInvoice->reversed_by_user_id =
                    $actor->getKey();

                $lockedInvoice->reversed_at =
                    CarbonImmutable::now('UTC');

                $lockedInvoice->reversal_reason =
                    $reason;

                $lockedInvoice
                    ->accounting_reversal_reference =
                        $accountingReference;

                $lockedInvoice->save();

                SupplierInvoiceReversed::dispatch(
                    tenantId: $tenantId,

                    supplierInvoiceId:
                        (int) $lockedInvoice->getKey(),

                    purchaseOrderId:
                        (int) $lockedInvoice
                            ->purchase_order_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadInvoice(
                    $lockedInvoice->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     purchase_order_id: int,
     *     supplier_invoice_number: string,
     *     supplier_invoice_number_normalized: string,
     *     supplier_invoice_number_hash: string,
     *     invoice_date: string,
     *     posting_date: string,
     *     due_date: string|null,
     *     currency_code: string|null,
     *     exchange_rate: mixed,
     *     other_charges: mixed,
     *     rounding_adjustment: mixed,
     *     notes: string|null,
     *     matching_notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $purchaseOrderId = $this->requiredId(
            value:
                $data['purchase_order_id'] ?? null,

            field: 'purchase_order_id',

            message:
                'The selected Purchase Order is invalid.',
        );

        $supplierInvoiceNumber =
            $this->requiredString(
                value:
                    $data[
                        'supplier_invoice_number'
                    ] ?? null,

                field:
                    'supplier_invoice_number',

                maximumLength: 160,
            );

        $normalizedNumber =
            $this->normalizeSupplierInvoiceNumber(
                $supplierInvoiceNumber,
            );

        $invoiceDate = $this->normalizeDate(
            value: $data['invoice_date'] ?? null,
            field: 'invoice_date',
            tenant: $tenant,
        );

        $postingDate = $this->normalizeDate(
            value:
                $data['posting_date']
                    ?? $invoiceDate,

            field: 'posting_date',
            tenant: $tenant,
        );

        if ($postingDate < $invoiceDate) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date cannot be before the invoice date.',
                ],
            ]);
        }

        $dueDate = null;

        if (
            array_key_exists('due_date', $data)
            && $data['due_date'] !== null
            && $data['due_date'] !== ''
        ) {
            $dueDate = $this->normalizeDate(
                value: $data['due_date'],
                field: 'due_date',
                tenant: $tenant,
            );
        }

        if (
            $dueDate !== null
            && $dueDate < $invoiceDate
        ) {
            throw ValidationException::withMessages([
                'due_date' => [
                    'The due date cannot be before the invoice date.',
                ],
            ]);
        }

        $currencyCode = null;

        if (
            isset($data['currency_code'])
            && $data['currency_code'] !== ''
        ) {
            if (!is_string($data['currency_code'])) {
                throw ValidationException::withMessages([
                    'currency_code' => [
                        'The currency code must be text.',
                    ],
                ]);
            }

            $currencyCode = strtoupper(
                trim($data['currency_code']),
            );

            if (
                preg_match(
                    '/^[A-Z]{3}$/',
                    $currencyCode,
                ) !== 1
            ) {
                throw ValidationException::withMessages([
                    'currency_code' => [
                        'The currency code must be a valid three-letter ISO code.',
                    ],
                ]);
            }
        }

        $lines = $data['lines'] ?? null;

        if (!is_array($lines)) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The supplier invoice lines must be an array.',
                ],
            ]);
        }

        return [
            'purchase_order_id' =>
                $purchaseOrderId,

            'supplier_invoice_number' =>
                $supplierInvoiceNumber,

            'supplier_invoice_number_normalized' =>
                $normalizedNumber,

            'supplier_invoice_number_hash' =>
                hash(
                    'sha256',
                    $normalizedNumber,
                ),

            'invoice_date' => $invoiceDate,
            'posting_date' => $postingDate,
            'due_date' => $dueDate,
            'currency_code' => $currencyCode,

            'exchange_rate' =>
                $data['exchange_rate'] ?? null,

            'other_charges' =>
                $data['other_charges'] ?? 0,

            'rounding_adjustment' =>
                $data['rounding_adjustment'] ?? 0,

            'notes' => $this->nullableString(
                value: $data['notes'] ?? null,
                field: 'notes',
            ),

            'matching_notes' =>
                $this->nullableString(
                    value:
                        $data[
                            'matching_notes'
                        ] ?? null,

                    field: 'matching_notes',
                ),

            'lines' => array_values($lines),
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, int|string|null>
     */
    private function commercialAttributes(
        array $normalized,
        PurchaseOrder $purchaseOrder,
        Supplier $supplier,
        Tenant $tenant,
    ): array {
        $currencyCode =
            $normalized['currency_code']
                ?? $purchaseOrder->currency_code;

        if (
            $currencyCode
            !== $purchaseOrder->currency_code
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The supplier invoice currency must match the Purchase Order currency.',
                ],
            ]);
        }

        $exchangeRate =
            $this->normalizeExchangeRate(
                $normalized['exchange_rate']
                    ?? $purchaseOrder->exchange_rate,
            );

        $invoiceDate =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $normalized['invoice_date'],
                $tenant->timezone,
            );

        if (
            !$invoiceDate
                instanceof CarbonImmutable
        ) {
            throw new LogicException(
                'The normalized supplier invoice date is invalid.',
            );
        }

        $paymentTermsDays =
            (int) $supplier->payment_terms_days;

        $dueDate =
            $normalized['due_date']
                ?? $invoiceDate
                    ->addDays($paymentTermsDays)
                    ->toDateString();

        return [
            'due_date' => $dueDate,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,

            'supplier_name' =>
                $purchaseOrder->supplier_name,

            'supplier_code' =>
                $purchaseOrder->supplier_code,

            'supplier_tax_number' =>
                $purchaseOrder
                    ->supplier_tax_number,

            'supplier_address' =>
                $purchaseOrder->supplier_address,

            'payment_terms_days' =>
                $paymentTermsDays,

            'purchase_order_number' =>
                $purchaseOrder->document_number,
        ];
    }

    private function resolvePurchaseOrder(
        int $purchaseOrderId,
        User $actor,
        bool $requireActiveBranch,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::query()
            ->whereKey($purchaseOrderId)
            ->lockForUpdate()
            ->first();

        if (
            !$purchaseOrder
                instanceof PurchaseOrder
        ) {
            throw ValidationException::withMessages([
                'purchase_order_id' => [
                    'The selected Purchase Order could not be found.',
                ],
            ]);
        }

        if (
            !in_array(
                $purchaseOrder->status,
                [
                    'approved',
                    'partially_received',
                    'received',
                    'closed',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'purchase_order_id' => [
                    'Only approved or received Purchase Orders can be used for supplier invoices.',
                ],
            ]);
        }

        $branch = Branch::query()
            ->whereKey(
                $purchaseOrder->branch_id,
            )
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive:
                    $requireActiveBranch,
            );

        return $purchaseOrder;
    }

    private function resolveSupplier(
        PurchaseOrder $purchaseOrder,
    ): Supplier {
        $supplier = Supplier::query()
            ->whereKey(
                $purchaseOrder->supplier_id,
            )
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (!$supplier instanceof Supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The Purchase Order supplier is no longer active.',
                ],
            ]);
        }

        return $supplier;
    }

    private function ensureUniqueSupplierInvoiceNumber(
        int $supplierId,
        string $numberHash,
        ?int $ignoreInvoiceId = null,
    ): void {
        $query = SupplierInvoice::query()
            ->withTrashed()
            ->where(
                'supplier_id',
                $supplierId,
            )
            ->where(
                'supplier_invoice_number_hash',
                $numberHash,
            );

        if ($ignoreInvoiceId !== null) {
            $query->where(
                $query
                    ->getModel()
                    ->qualifyColumn('id'),

                '!=',
                $ignoreInvoiceId,
            );
        }

        if (!$query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'supplier_invoice_number' => [
                'This supplier invoice number already exists for the selected supplier.',
            ],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        SupplierInvoice $supplierInvoice,
        array $lines,
    ): void {
        $this->deleteLines(
            $supplierInvoice,
        );

        foreach ($lines as $line) {
            $matches = $line['matches'] ?? [];

            unset($line['matches']);

            $supplierInvoiceLine =
                $supplierInvoice
                    ->lines()
                    ->create($line);

            foreach ($matches as $match) {
                $supplierInvoiceLine
                    ->matches()
                    ->create([
                        ...$match,

                        'supplier_invoice_id' =>
                            $supplierInvoice->getKey(),
                    ]);
            }
        }
    }

    private function deleteLines(
        SupplierInvoice $supplierInvoice,
    ): void {
        $lines = SupplierInvoiceLine::query()
            ->where(
                'supplier_invoice_id',
                $supplierInvoice->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            $matches = $line
                ->matches()
                ->lockForUpdate()
                ->get();

            foreach ($matches as $match) {
                $match->delete();
            }

            $line->delete();
        }
    }

    private function ensureNumberedDocumentIdentityUnchanged(
        SupplierInvoice $supplierInvoice,
        int $purchaseOrderId,
        string $invoiceDate,
    ): void {
        if (!$supplierInvoice->hasDocumentNumber()) {
            return;
        }

        if (
            (int) $supplierInvoice
                ->purchase_order_id
            !== $purchaseOrderId
        ) {
            throw ValidationException::withMessages([
                'purchase_order_id' => [
                    'The Purchase Order cannot be changed after the internal supplier invoice number has been allocated.',
                ],
            ]);
        }

        if (
            $supplierInvoice
                ->invoice_date
                ?->toDateString()
            !== $invoiceDate
        ) {
            throw ValidationException::withMessages([
                'invoice_date' => [
                    'The invoice date cannot be changed after the internal supplier invoice number has been allocated.',
                ],
            ]);
        }
    }

    private function ensureEditable(
        SupplierInvoice $supplierInvoice,
    ): void {
        if (
            $this->statusRegistry->isEditable(
                $supplierInvoice->status,
            )
            && !$supplierInvoice
                ->hasMatchingReservation()
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Only an unreserved draft supplier invoice can be edited.',
            ],
        ]);
    }

    private function ensureTransition(
        SupplierInvoice $supplierInvoice,
        string $nextStatus,
    ): void {
        if (
            $this->statusRegistry->canTransition(
                currentStatus:
                    $supplierInvoice->status,

                nextStatus: $nextStatus,
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                "The supplier invoice cannot move from {$supplierInvoice->status} to {$nextStatus}.",
            ],
        ]);
    }

    private function authorizeInvoiceBranch(
        User $actor,
        SupplierInvoice $supplierInvoice,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey(
                $supplierInvoice->branch_id,
            )
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function loadInvoice(
        SupplierInvoice $supplierInvoice,
    ): SupplierInvoice {
        return $supplierInvoice->load([
            'branch',
            'supplier',
            'purchaseOrder',
            'documentNumberAllocation',
            'lines.purchaseOrderLine',
            'lines.product',
            'lines.unit',
            'lines.matches.goodsReceipt',
            'lines.matches.goodsReceiptLine',
            'createdBy',
            'validatedBy',
            'approvedBy',
            'disputedBy',
            'postedBy',
            'reversedBy',
            'cancelledBy',
        ]);
    }

    private function normalizeAccountingReference(
        string $reference,
        string $operation,
    ): string {
        $reference = trim($reference);

        if ($reference === '') {
            throw new LogicException(
                "The supplier invoice accounting gateway returned an empty {$operation} reference.",
            );
        }

        if (mb_strlen($reference) > 190) {
            throw new LogicException(
                "The supplier invoice accounting gateway returned a {$operation} reference longer than 190 characters.",
            );
        }

        return $reference;
    }

    private function normalizeSupplierInvoiceNumber(
        string $number,
    ): string {
        $uppercase = mb_strtoupper(
            trim($number),
            'UTF-8',
        );

        /*
         * Remove spacing, punctuation and symbols so values such as:
         *
         * INV-001
         * inv 001
         * INV/001
         *
         * produce the same fixed SHA-256 duplicate-detection key.
         */
        $normalized = preg_replace(
            '/[\p{Z}\s\p{P}\p{S}]+/u',
            '',
            $uppercase,
        );

        if (
            !is_string($normalized)
            || $normalized === ''
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' => [
                    'The supplier invoice number must contain at least one letter or number.',
                ],
            ]);
        }

        if (mb_strlen($normalized) > 190) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' => [
                    'The normalized supplier invoice number is too long.',
                ],
            ]);
        }

        return $normalized;
    }

    private function normalizeExchangeRate(
        mixed $value,
    ): string {
        if (
            $value === null
            || $value === ''
            || !is_scalar($value)
            || preg_match(
                '/^\d+(?:\.\d+)?$/',
                trim((string) $value),
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be a valid positive number.',
                ],
            ]);
        }

        $rate = BigDecimal::of(
            trim((string) $value),
        );

        if ($rate->isZero()) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be greater than zero.',
                ],
            ]);
        }

        try {
            $rate = $rate->toScale(
                self::EXCHANGE_RATE_SCALE,
                RoundingMode::UNNECESSARY,
            );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate may not contain more than 8 decimal places.',
                ],
            ]);
        }

        if (
            $rate->isGreaterThan(
                BigDecimal::of(
                    self::MAXIMUM_EXCHANGE_RATE,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate exceeds the supported maximum value.',
                ],
            ]);
        }

        return $rate->__toString();
    }

    private function normalizeDate(
        mixed $value,
        string $field,
        Tenant $tenant,
    ): string {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(
                $value,
            )
                ->setTimezone($tenant->timezone)
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

        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            trim($value),
            $tenant->timezone,
        );

        if (
            !$date instanceof CarbonImmutable
            || $date->format('Y-m-d')
                !== trim($value)
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
                'The supplier invoice business date is invalid.',
            );
        }

        return $dateTime->startOfDay();
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

    private function requiredString(
        mixed $value,
        string $field,
        int $maximumLength,
    ): string {
        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value is required.',
                ],
            ]);
        }

        $normalized = trim($value);

        if (
            mb_strlen($normalized)
            > $maximumLength
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $normalized;
    }

    private function nullableString(
        mixed $value,
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

        $normalized = trim($value);

        return $normalized === ''
            ? null
            : $normalized;
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

    private function allocationKey(
        SupplierInvoice $supplierInvoice,
    ): string {
        return sprintf(
            'supplier-invoice:%d:document-number',
            (int) $supplierInvoice->getKey(),
        );
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
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

    private function ensureInvoiceBelongsToTenant(
        SupplierInvoice $supplierInvoice,
        int $tenantId,
    ): void {
        if (
            (int) $supplierInvoice->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The supplier invoice does not belong to the active tenant.',
        );
    }

    private function ensureNoActiveSupplierDebitNotes(
        SupplierInvoice $supplierInvoice,
    ): void {
        $activeSupplierDebitNote =
            SupplierDebitNote::query()
                ->where(
                    'supplier_invoice_id',
                    $supplierInvoice
                        ->getKey(),
                )
                ->whereIn(
                    'status',
                    [
                        'draft',
                        'submitted',
                        'approved',
                        'posted',
                    ],
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

        if (
            $activeSupplierDebitNote
            instanceof SupplierDebitNote
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'The Supplier Invoice cannot be changed to this status while an active Supplier Debit Note references it. Cancel, delete, or reverse the Supplier Debit Note first.',
                ],
            ]);
        }

        $reservedAmount =
            BigDecimal::of(
                (string) $supplierInvoice
                    ->debit_note_reserved_amount,
            );

        $debitedAmount =
            BigDecimal::of(
                (string) $supplierInvoice
                    ->debited_amount,
            );

        if (
            $reservedAmount->isGreaterThan(
                BigDecimal::zero(),
            )
            || $debitedAmount->isGreaterThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice' => [
                    'The Supplier Invoice contains reserved or posted Supplier Debit Note amounts and cannot be invalidated.',
                ],
            ]);
        }
    }
}
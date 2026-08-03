<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Events\Purchasing\PurchaseReturnPosted;
use App\Events\Purchasing\PurchaseReturnReversed;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\SupplierDebitNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceMatch;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\InventoryPostingService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Purchasing\PurchaseReturnStatusRegistry;
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

final class PurchaseReturnService
{
    private const SCALE = 6;

    private const MAXIMUM_QUANTITY =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly InventoryPostingService $inventoryPostingService,
        private readonly PurchaseReturnCalculator $calculator,
        private readonly PurchaseReturnStatusRegistry $statusRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): PurchaseReturn {
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
            ): PurchaseReturn {
                $goodsReceipt =
                    $this->resolveGoodsReceipt(
                        goodsReceiptId:
                            $normalized[
                                'goods_receipt_id'
                            ],

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

                        goodsReceipt:
                            $goodsReceipt,
                    );

                $lines = $this->buildLines(
                    goodsReceipt:
                        $goodsReceipt,

                    inputLines:
                        $normalized['lines'],
                );

                $purchaseReturn =
                    PurchaseReturn::query()
                        ->create([
                            'purchase_order_id' =>
                                $goodsReceipt
                                    ->purchase_order_id,

                            'goods_receipt_id' =>
                                $goodsReceipt
                                    ->getKey(),

                            'supplier_invoice_id' =>
                                $supplierInvoice
                                    ?->getKey(),

                            'branch_id' =>
                                $goodsReceipt
                                    ->branch_id,

                            'warehouse_id' =>
                                $goodsReceipt
                                    ->warehouse_id,

                            'supplier_id' =>
                                $goodsReceipt
                                    ->supplier_id,

                            'return_date' =>
                                $normalized[
                                    'return_date'
                                ],

                            'posting_date' =>
                                $normalized[
                                    'posting_date'
                                ],

                            'supplier_reference' =>
                                $normalized[
                                    'supplier_reference'
                                ],

                            'supplier_name' =>
                                $goodsReceipt
                                    ->supplier_name,

                            'supplier_code' =>
                                $goodsReceipt
                                    ->supplier_code,

                            'purchase_order_number' =>
                                $goodsReceipt
                                    ->purchase_order_number,

                            'goods_receipt_number' =>
                                $goodsReceipt
                                    ->receipt_number,

                            'supplier_invoice_number' =>
                                $supplierInvoice
                                    ?->supplier_invoice_number,

                            'status' => 'draft',

                            ...$this
                                ->calculator
                                ->calculateTotals(
                                    $lines,
                                ),

                            'return_reason' =>
                                $normalized[
                                    'return_reason'
                                ],

                            'notes' =>
                                $normalized['notes'],

                            'revision' => 1,

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                $this->replaceLines(
                    purchaseReturn:
                        $purchaseReturn,

                    lines:
                        $lines,
                );

                return $this->loadReturn(
                    $purchaseReturn,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        PurchaseReturn $purchaseReturn,
        array $data,
        User $actor,
    ): PurchaseReturn {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $normalized,
                $actor,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        false,
                );

                if (
                    !$this
                        ->statusRegistry
                        ->isEditable(
                            $lockedReturn
                                ->status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Purchase Return can be edited.',
                        ],
                    ]);
                }

                $this
                    ->ensureNumberedIdentityUnchanged(
                        purchaseReturn:
                            $lockedReturn,

                        goodsReceiptId:
                            $normalized[
                                'goods_receipt_id'
                            ],

                        returnDate:
                            $normalized[
                                'return_date'
                            ],
                    );

                $goodsReceipt =
                    $this->resolveGoodsReceipt(
                        goodsReceiptId:
                            $normalized[
                                'goods_receipt_id'
                            ],

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

                        goodsReceipt:
                            $goodsReceipt,
                    );

                $lines = $this->buildLines(
                    goodsReceipt:
                        $goodsReceipt,

                    inputLines:
                        $normalized['lines'],
                );

                $lockedReturn->fill([
                    'purchase_order_id' =>
                        $goodsReceipt
                            ->purchase_order_id,

                    'goods_receipt_id' =>
                        $goodsReceipt
                            ->getKey(),

                    'supplier_invoice_id' =>
                        $supplierInvoice
                            ?->getKey(),

                    'branch_id' =>
                        $goodsReceipt
                            ->branch_id,

                    'warehouse_id' =>
                        $goodsReceipt
                            ->warehouse_id,

                    'supplier_id' =>
                        $goodsReceipt
                            ->supplier_id,

                    'return_date' =>
                        $normalized[
                            'return_date'
                        ],

                    'posting_date' =>
                        $normalized[
                            'posting_date'
                        ],

                    'supplier_reference' =>
                        $normalized[
                            'supplier_reference'
                        ],

                    'supplier_name' =>
                        $goodsReceipt
                            ->supplier_name,

                    'supplier_code' =>
                        $goodsReceipt
                            ->supplier_code,

                    'purchase_order_number' =>
                        $goodsReceipt
                            ->purchase_order_number,

                    'goods_receipt_number' =>
                        $goodsReceipt
                            ->receipt_number,

                    'supplier_invoice_number' =>
                        $supplierInvoice
                            ?->supplier_invoice_number,

                    ...$this
                        ->calculator
                        ->calculateTotals(
                            $lines,
                        ),

                    'return_reason' =>
                        $normalized[
                            'return_reason'
                        ],

                    'notes' =>
                        $normalized['notes'],

                    'revision' =>
                        (int) $lockedReturn
                            ->revision + 1,
                ]);

                $lockedReturn->save();

                $this->replaceLines(
                    purchaseReturn:
                        $lockedReturn,

                    lines:
                        $lines,
                );

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): void {
        $tenantId =
            $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        DB::transaction(
            function () use (
                $purchaseReturn,
                $actor,
            ): void {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        false,
                );

                if (
                    !$lockedReturn
                        ->canBeDeleted()
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an unnumbered, never-submitted draft Purchase Return can be deleted.',
                        ],
                    ]);
                }

                $this->deleteLines(
                    $lockedReturn,
                );

                $lockedReturn->delete();
            },
            attempts: 5,
        );
    }

    public function submit(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): PurchaseReturn {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $actor,
                $tenant,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        true,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'submitted',
                );

                $this->validateCurrentAvailability(
                    $lockedReturn,
                );

                if (
                    !$lockedReturn
                        ->hasReturnNumber()
                ) {
                    $allocation =
                        $this
                            ->documentNumberService
                            ->allocate(
                                documentType:
                                    'purchase_return',

                                branchId:
                                    (int) $lockedReturn
                                        ->branch_id,

                                idempotencyKey:
                                    $this
                                        ->allocationKey(
                                            $lockedReturn,
                                        ),

                                allocatableType:
                                    PurchaseReturn::class,

                                allocatableId:
                                    (int) $lockedReturn
                                        ->getKey(),

                                allocatedAt:
                                    $this
                                        ->businessDateTime(
                                            date:
                                                $lockedReturn
                                                    ->return_date
                                                    ->toDateString(),

                                            tenant:
                                                $tenant,
                                        ),
                            );

                    $lockedReturn
                        ->document_number_allocation_id =
                            $allocation
                                ->getKey();

                    $lockedReturn
                        ->return_number =
                            $allocation
                                ->number;
                }

                $lockedReturn->status =
                    'submitted';

                $lockedReturn
                    ->submitted_by_user_id =
                        $actor->getKey();

                $lockedReturn->submitted_at =
                    CarbonImmutable::now(
                        'UTC',
                    );

                $lockedReturn->save();

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): PurchaseReturn {
        $tenantId =
            $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $actor,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        false,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'draft',
                );

                $lockedReturn->status =
                    'draft';

                $lockedReturn
                    ->submitted_by_user_id =
                        null;

                $lockedReturn->submitted_at =
                    null;

                $lockedReturn->revision =
                    (int) $lockedReturn
                        ->revision + 1;

                $lockedReturn->save();

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function approve(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): PurchaseReturn {
        $tenantId =
            $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $actor,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        true,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'approved',
                );

                if (
                    !$lockedReturn
                        ->hasReturnNumber()
                ) {
                    throw new LogicException(
                        'A submitted Purchase Return must have a document number before approval.',
                    );
                }

                $this->reserveQuantities(
                    $lockedReturn,
                );

                $lockedReturn->status =
                    'approved';

                $lockedReturn
                    ->approved_by_user_id =
                        $actor->getKey();

                $lockedReturn->approved_at =
                    CarbonImmutable::now(
                        'UTC',
                    );

                $lockedReturn->save();

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        PurchaseReturn $purchaseReturn,
        string $reason,
        User $actor,
    ): PurchaseReturn {
        $tenantId =
            $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $reason,
                $actor,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        false,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'cancelled',
                );

                if (
                    $lockedReturn
                        ->isApproved()
                ) {
                    $this
                        ->releaseReservations(
                            $lockedReturn,
                        );
                }

                $lockedReturn->status =
                    'cancelled';

                $lockedReturn
                    ->cancelled_by_user_id =
                        $actor->getKey();

                $lockedReturn->cancelled_at =
                    CarbonImmutable::now(
                        'UTC',
                    );

                $lockedReturn
                    ->cancellation_reason =
                        $reason;

                $lockedReturn->save();

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): PurchaseReturn {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $actor,
                $tenant,
                $tenantId,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        true,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'posted',
                );

                $goodsReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $lockedReturn
                                ->goods_receipt_id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$goodsReceipt
                        ->isPosted()
                ) {
                    throw ValidationException::withMessages([
                        'goods_receipt_id' => [
                            'The source Goods Receipt is no longer posted.',
                        ],
                    ]);
                }

                $occurredAt =
                    $this->businessDateTime(
                        date:
                            $lockedReturn
                                ->posting_date
                                ->toDateString(),

                        tenant:
                            $tenant,
                    );

                $lines =
                    $this->lockReturnLines(
                        purchaseReturn:
                            $lockedReturn,

                        reverseOrder:
                            false,
                    );

                if ($lines->isEmpty()) {
                    throw new LogicException(
                        'The approved Purchase Return has no lines.',
                    );
                }

                foreach ($lines as $line) {
                    $sourceLine =
                        $this->lockSourceLine(
                            purchaseReturn:
                                $lockedReturn,

                            line:
                                $line,
                        );

                    $quantity =
                        $this->lineQuantity(
                            $line,
                        );

                    $reserved =
                        BigDecimal::of(
                            (string) $sourceLine
                                ->return_reserved_quantity,
                        )->toScale(
                            self::SCALE,
                            RoundingMode::UNNECESSARY,
                        );

                    if (
                        $reserved
                            ->isLessThan(
                                $quantity,
                            )
                    ) {
                        throw ValidationException::withMessages([
                            'lines' => [
                                "The approved return reservation for {$line->product_name} is no longer available.",
                            ],
                        ]);
                    }

                    $entry =
                        $this
                            ->inventoryPostingService
                            ->postPurchaseReturnLine(
                                purchaseReturn:
                                    $lockedReturn,

                                line:
                                    $line,

                                actor:
                                    $actor,

                                occurredAt:
                                    $occurredAt,
                            );

                    if ($entry !== null) {
                        $line->inventory_unit_cost =
                            $entry->unit_cost;

                        $line->inventory_total_cost =
                            $entry->total_cost;

                        $line->cost_variance_amount =
                            BigDecimal::of(
                                (string) $line
                                    ->supplier_total_cost,
                            )
                                ->minus(
                                    BigDecimal::of(
                                        (string) $entry
                                            ->total_cost,
                                    ),
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                        $line->save();
                    }

                    $sourceLine
                        ->return_reserved_quantity =
                            $reserved
                                ->minus(
                                    $quantity,
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                    $sourceLine
                        ->returned_quantity =
                            BigDecimal::of(
                                (string) $sourceLine
                                    ->returned_quantity,
                            )
                                ->plus(
                                    $quantity,
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                    $sourceLine->save();
                }

                $lockedReturn->fill([
                    ...$this
                        ->calculator
                        ->calculateTotals(
                            $this->totalsInput(
                                $lockedReturn,
                            ),
                        ),

                    'status' => 'posted',

                    'posted_by_user_id' =>
                        $actor->getKey(),

                    'posted_at' =>
                        CarbonImmutable::now(
                            'UTC',
                        ),
                ]);

                $lockedReturn->save();

                PurchaseReturnPosted::dispatch(
                    tenantId:
                        $tenantId,

                    purchaseReturnId:
                        (int) $lockedReturn
                            ->getKey(),

                    goodsReceiptId:
                        (int) $goodsReceipt
                            ->getKey(),

                    actorId:
                        (int) $actor
                            ->getKey(),
                );

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        PurchaseReturn $purchaseReturn,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): PurchaseReturn {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReturnBelongsToTenant(
            purchaseReturn:
                $purchaseReturn,

            tenantId:
                $tenantId,
        );

        $reason = $this->requiredReason(
            reason: $reason,
            field: 'reversal_reason',
        );

        $normalizedReversalDate =
            $this->normalizeDate(
                value:
                    $reversalPostingDate,

                field:
                    'reversal_posting_date',

                tenant:
                    $tenant,
            );

        return DB::transaction(
            function () use (
                $purchaseReturn,
                $normalizedReversalDate,
                $reason,
                $actor,
                $tenant,
                $tenantId,
            ): PurchaseReturn {
                $lockedReturn =
                    PurchaseReturn::query()
                        ->whereKey(
                            $purchaseReturn
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReturnBranch(
                    actor: $actor,

                    purchaseReturn:
                        $lockedReturn,

                    requireActive:
                        false,
                );

                $this->ensureTransition(
                    purchaseReturn:
                        $lockedReturn,

                    nextStatus:
                        'reversed',
                );

                $activeSupplierDebitNote =
                    SupplierDebitNote::query()
                        ->where(
                            'purchase_return_id',
                            $lockedReturn
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
                        'purchase_return' => [
                            'The Purchase Return cannot be reversed while an active Supplier Debit Note references it. Cancel, delete, or reverse the Supplier Debit Note first.',
                        ],
                    ]);
                }

                if (
                    $normalizedReversalDate
                    < $lockedReturn
                        ->posting_date
                        ->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'reversal_posting_date' => [
                            'The reversal posting date cannot be before the original posting date.',
                        ],
                    ]);
                }

                $occurredAt =
                    $this->businessDateTime(
                        date:
                            $normalizedReversalDate,

                        tenant:
                            $tenant,
                    );

                $lines =
                    $this->lockReturnLines(
                        purchaseReturn:
                            $lockedReturn,

                        reverseOrder:
                            true,
                    );

                if ($lines->isEmpty()) {
                    throw new LogicException(
                        'The posted Purchase Return has no lines.',
                    );
                }

                foreach ($lines as $line) {
                    $sourceLine =
                        $this->lockSourceLine(
                            purchaseReturn:
                                $lockedReturn,

                            line:
                                $line,
                        );

                    $quantity =
                        $this->lineQuantity(
                            $line,
                        );

                    $returned =
                        BigDecimal::of(
                            (string) $sourceLine
                                ->returned_quantity,
                        )->toScale(
                            self::SCALE,
                            RoundingMode::UNNECESSARY,
                        );

                    if (
                        $returned
                            ->isLessThan(
                                $quantity,
                            )
                    ) {
                        throw new LogicException(
                            'The source Goods Receipt returned quantity is lower than the Purchase Return reversal quantity.',
                        );
                    }

                    $this
                        ->inventoryPostingService
                        ->reversePurchaseReturnLine(
                            purchaseReturn:
                                $lockedReturn,

                            line:
                                $line,

                            actor:
                                $actor,

                            occurredAt:
                                $occurredAt,
                        );

                    $sourceLine
                        ->returned_quantity =
                            $returned
                                ->minus(
                                    $quantity,
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                    $sourceLine->save();
                }

                $lockedReturn->status =
                    'reversed';

                $lockedReturn
                    ->reversal_posting_date =
                        $normalizedReversalDate;

                $lockedReturn
                    ->reversed_by_user_id =
                        $actor->getKey();

                $lockedReturn->reversed_at =
                    CarbonImmutable::now(
                        'UTC',
                    );

                $lockedReturn
                    ->reversal_reason =
                        $reason;

                $lockedReturn->save();

                PurchaseReturnReversed::dispatch(
                    tenantId:
                        $tenantId,

                    purchaseReturnId:
                        (int) $lockedReturn
                            ->getKey(),

                    goodsReceiptId:
                        (int) $lockedReturn
                            ->goods_receipt_id,

                    actorId:
                        (int) $actor
                            ->getKey(),
                );

                return $this->loadReturn(
                    $lockedReturn->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     goods_receipt_id: int,
     *     supplier_invoice_id: int|null,
     *     return_date: string,
     *     posting_date: string,
     *     supplier_reference: string|null,
     *     return_reason: string,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $returnDate =
            $this->normalizeDate(
                value:
                    $data[
                        'return_date'
                    ] ?? null,

                field:
                    'return_date',

                tenant:
                    $tenant,
            );

        $postingDate =
            $this->normalizeDate(
                value:
                    $data[
                        'posting_date'
                    ] ?? $returnDate,

                field:
                    'posting_date',

                tenant:
                    $tenant,
            );

        if ($postingDate < $returnDate) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date cannot be before the return date.',
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
                    'A Purchase Return must contain at least one line.',
                ],
            ]);
        }

        if (count($lines) > 500) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Purchase Return may not contain more than 500 lines.',
                ],
            ]);
        }

        $returnReason =
            $data['return_reason'] ?? null;

        return [
            'goods_receipt_id' =>
                $this->requiredId(
                    value:
                        $data[
                            'goods_receipt_id'
                        ] ?? null,

                    field:
                        'goods_receipt_id',

                    message:
                        'The selected Goods Receipt is invalid.',
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

            'return_date' =>
                $returnDate,

            'posting_date' =>
                $postingDate,

            'supplier_reference' =>
                $this->nullableString(
                    value:
                        $data[
                            'supplier_reference'
                        ] ?? null,

                    maximum:
                        160,

                    field:
                        'supplier_reference',
                ),

            'return_reason' =>
                $this->requiredReason(
                    reason:
                        is_string(
                            $returnReason,
                        )
                            ? $returnReason
                            : '',

                    field:
                        'return_reason',
                ),

            'notes' =>
                $this->nullableString(
                    value:
                        $data[
                            'notes'
                        ] ?? null,

                    maximum:
                        4000,

                    field:
                        'notes',
                ),

            'lines' =>
                array_values($lines),
        ];
    }

    private function resolveGoodsReceipt(
        int $goodsReceiptId,
        User $actor,
        bool $requireActiveBranch,
    ): GoodsReceipt {
        $goodsReceipt =
            GoodsReceipt::query()
                ->whereKey(
                    $goodsReceiptId,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$goodsReceipt
                instanceof GoodsReceipt
        ) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => [
                    'The selected Goods Receipt could not be found.',
                ],
            ]);
        }

        if (
            !$goodsReceipt
                ->isPosted()
        ) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => [
                    'Only a posted Goods Receipt can be returned.',
                ],
            ]);
        }

        $branch = Branch::query()
            ->whereKey(
                $goodsReceipt
                    ->branch_id,
            )
            ->firstOrFail();

        $this
            ->branchAccessService
            ->authorizeBranch(
                user: $actor,

                branch: $branch,

                requireActive:
                    $requireActiveBranch,
            );

        return $goodsReceipt;
    }

    private function resolveSupplierInvoice(
        ?int $supplierInvoiceId,
        GoodsReceipt $goodsReceipt,
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
                !== (int) $goodsReceipt
                    ->purchase_order_id
            || (int) $supplierInvoice
                ->supplier_id
                !== (int) $goodsReceipt
                    ->supplier_id
            || (int) $supplierInvoice
                ->branch_id
                !== (int) $goodsReceipt
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
                    'validated',
                    'approved',
                    'posted',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'Only a validated, approved, or posted Supplier Invoice can be linked to a Purchase Return.',
                ],
            ]);
        }

        $receiptMatch =
            SupplierInvoiceMatch::query()
                ->where(
                    'supplier_invoice_id',
                    $supplierInvoice
                        ->getKey(),
                )
                ->where(
                    'goods_receipt_id',
                    $goodsReceipt
                        ->getKey(),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

        if (
            !$receiptMatch
                instanceof SupplierInvoiceMatch
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => [
                    'The selected Supplier Invoice does not contain a match for the source Goods Receipt.',
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
        GoodsReceipt $goodsReceipt,
        array $inputLines,
    ): array {
        $builtLines = [];
        $usedSourceLineIds = [];

        foreach (
            $inputLines
            as $index => $inputLine
        ) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each Purchase Return line must be an object.',
                    ],
                ]);
            }

            $sourceLineId =
                $this->requiredId(
                    value:
                        $inputLine[
                            'goods_receipt_line_id'
                        ] ?? null,

                    field:
                        "lines.{$index}.goods_receipt_line_id",

                    message:
                        'The selected Goods Receipt line is invalid.',
                );

            if (
                isset(
                    $usedSourceLineIds[
                        $sourceLineId
                    ],
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.goods_receipt_line_id" => [
                        'A Goods Receipt line may only appear once on a Purchase Return.',
                    ],
                ]);
            }

            $usedSourceLineIds[
                $sourceLineId
            ] = true;

            $sourceLine =
                GoodsReceiptLine::query()
                    ->with('unit')
                    ->whereKey(
                        $sourceLineId,
                    )
                    ->where(
                        'goods_receipt_id',
                        $goodsReceipt
                            ->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

            if (
                !$sourceLine
                    instanceof GoodsReceiptLine
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.goods_receipt_line_id" => [
                        'The selected line does not belong to this Goods Receipt.',
                    ],
                ]);
            }

            if (
                $sourceLine
                    ->product_type
                === 'service'
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.goods_receipt_line_id" => [
                        'Service lines cannot be included in a physical Purchase Return.',
                    ],
                ]);
            }

            $returnQuantity =
                $this->normalizeQuantity(
                    value:
                        $inputLine[
                            'return_quantity'
                        ] ?? null,

                    field:
                        "lines.{$index}.return_quantity",
                );

            $this->ensureUnitPrecision(
                quantity:
                    $returnQuantity,

                unit:
                    $sourceLine->unit,

                field:
                    "lines.{$index}.return_quantity",
            );

            $calculated =
                $this
                    ->calculator
                    ->calculateLine(
                        goodsReceiptLine:
                            $sourceLine,

                        returnQuantity:
                            $returnQuantity,
                    );

            if (
                $returnQuantity
                    ->isGreaterThan(
                        BigDecimal::of(
                            $calculated[
                                'returnable_quantity_snapshot'
                            ],
                        ),
                    )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.return_quantity" => [
                        "The return quantity for {$sourceLine->product_name} exceeds the accepted quantity that is still available to return.",
                    ],
                ]);
            }

            $builtLines[] = [
                'goods_receipt_line_id' =>
                    $sourceLine
                        ->getKey(),

                'purchase_order_line_id' =>
                    $sourceLine
                        ->purchase_order_line_id,

                'product_id' =>
                    $sourceLine
                        ->product_id,

                'unit_id' =>
                    $sourceLine
                        ->unit_id,

                'line_number' =>
                    $index + 1,

                'product_name' =>
                    $sourceLine
                        ->product_name,

                'product_sku' =>
                    $sourceLine
                        ->product_sku,

                'product_type' =>
                    $sourceLine
                        ->product_type,

                'unit_name' =>
                    $sourceLine
                        ->unit_name,

                'unit_code' =>
                    $sourceLine
                        ->unit_code,

                ...$calculated,

                'batch_number' =>
                    $sourceLine
                        ->batch_number,

                'serial_numbers' =>
                    $this
                        ->serializedReturnNumbers(
                            sourceLine:
                                $sourceLine,

                            returnQuantity:
                                $returnQuantity,

                            field:
                                "lines.{$index}.return_quantity",
                        ),

                'return_reason' =>
                    $this->nullableString(
                        value:
                            $inputLine[
                                'return_reason'
                            ] ?? null,

                        maximum:
                            500,

                        field:
                            "lines.{$index}.return_reason",
                    ),

                'notes' =>
                    $this->nullableString(
                        value:
                            $inputLine[
                                'notes'
                            ] ?? null,

                        maximum:
                            2000,

                        field:
                            "lines.{$index}.notes",
                    ),
            ];
        }

        return $builtLines;
    }

    private function validateCurrentAvailability(
        PurchaseReturn $purchaseReturn,
    ): void {
        $lines =
            $this->lockReturnLines(
                purchaseReturn:
                    $purchaseReturn,

                reverseOrder:
                    false,
            );

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Purchase Return must contain at least one line.',
                ],
            ]);
        }

        foreach ($lines as $line) {
            $this
                ->assertQuantityAvailable(
                    line:
                        $line,

                    sourceLine:
                        $this
                            ->lockSourceLine(
                                purchaseReturn:
                                    $purchaseReturn,

                                line:
                                    $line,
                            ),
                );
        }
    }

    private function reserveQuantities(
        PurchaseReturn $purchaseReturn,
    ): void {
        $lines =
            $this->lockReturnLines(
                purchaseReturn:
                    $purchaseReturn,

                reverseOrder:
                    false,
            );

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Purchase Return must contain at least one line.',
                ],
            ]);
        }

        foreach ($lines as $line) {
            $sourceLine =
                $this->lockSourceLine(
                    purchaseReturn:
                        $purchaseReturn,

                    line:
                        $line,
                );

            $this
                ->assertQuantityAvailable(
                    line:
                        $line,

                    sourceLine:
                        $sourceLine,
                );

            $quantity =
                $this->lineQuantity(
                    $line,
                );

            $reserved =
                BigDecimal::of(
                    (string) $sourceLine
                        ->return_reserved_quantity,
                )->toScale(
                    self::SCALE,
                    RoundingMode::UNNECESSARY,
                );

            $line
                ->accepted_quantity_snapshot =
                    $sourceLine
                        ->accepted_quantity;

            $line
                ->previously_returned_quantity_snapshot =
                    $sourceLine
                        ->returned_quantity;

            $line
                ->previously_reserved_quantity_snapshot =
                    $sourceLine
                        ->return_reserved_quantity;

            $line
                ->returnable_quantity_snapshot =
                    BigDecimal::of(
                        (string) $sourceLine
                            ->accepted_quantity,
                    )
                        ->minus(
                            BigDecimal::of(
                                (string) $sourceLine
                                    ->returned_quantity,
                            ),
                        )
                        ->minus(
                            $reserved,
                        )
                        ->toScale(
                            self::SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString();

            $line->save();

            $sourceLine
                ->return_reserved_quantity =
                    $reserved
                        ->plus(
                            $quantity,
                        )
                        ->toScale(
                            self::SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString();

            $sourceLine->save();
        }
    }

    private function releaseReservations(
        PurchaseReturn $purchaseReturn,
    ): void {
        $lines =
            $this->lockReturnLines(
                purchaseReturn:
                    $purchaseReturn,

                reverseOrder:
                    true,
            );

        foreach ($lines as $line) {
            $sourceLine =
                $this->lockSourceLine(
                    purchaseReturn:
                        $purchaseReturn,

                    line:
                        $line,
                );

            $quantity =
                $this->lineQuantity(
                    $line,
                );

            $reserved =
                BigDecimal::of(
                    (string) $sourceLine
                        ->return_reserved_quantity,
                )->toScale(
                    self::SCALE,
                    RoundingMode::UNNECESSARY,
                );

            if (
                $reserved
                    ->isLessThan(
                        $quantity,
                    )
            ) {
                throw new LogicException(
                    'The Goods Receipt return reservation is lower than the approved Purchase Return quantity.',
                );
            }

            $sourceLine
                ->return_reserved_quantity =
                    $reserved
                        ->minus(
                            $quantity,
                        )
                        ->toScale(
                            self::SCALE,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString();

            $sourceLine->save();
        }
    }

    private function assertQuantityAvailable(
        PurchaseReturnLine $line,
        GoodsReceiptLine $sourceLine,
    ): void {
        $accepted = BigDecimal::of(
            (string) $sourceLine
                ->accepted_quantity,
        );

        $returned = BigDecimal::of(
            (string) $sourceLine
                ->returned_quantity,
        );

        $reserved = BigDecimal::of(
            (string) $sourceLine
                ->return_reserved_quantity,
        );

        $available =
            $accepted
                ->minus(
                    $returned,
                )
                ->minus(
                    $reserved,
                );

        $quantity =
            $this->lineQuantity(
                $line,
            );

        if (
            $quantity
                ->isGreaterThan(
                    $available,
                )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "The return quantity for {$line->product_name} exceeds the quantity currently available to return.",
                ],
            ]);
        }

        $this->serializedReturnNumbers(
            sourceLine:
                $sourceLine,

            returnQuantity:
                $quantity,

            field:
                'lines',
        );
    }

    /**
     * @return list<string>|null
     */
    private function serializedReturnNumbers(
        GoodsReceiptLine $sourceLine,
        BigDecimal $returnQuantity,
        string $field,
    ): ?array {
        $serialNumbers =
            $sourceLine->serial_numbers;

        if (
            !is_array($serialNumbers)
            || $serialNumbers === []
        ) {
            return null;
        }

        $accepted = BigDecimal::of(
            (string) $sourceLine
                ->accepted_quantity,
        );

        $returned = BigDecimal::of(
            (string) $sourceLine
                ->returned_quantity,
        );

        $reserved = BigDecimal::of(
            (string) $sourceLine
                ->return_reserved_quantity,
        );

        if (
            !$returned->isZero()
            || !$reserved->isZero()
            || !$returnQuantity
                ->isEqualTo(
                    $accepted,
                )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "Serialized product {$sourceLine->product_name} must be returned as the complete original accepted receipt line until a dedicated serial-number registry is implemented.",
                ],
            ]);
        }

        $normalized = [];

        foreach (
            $serialNumbers
            as $serialNumber
        ) {
            if (
                !is_string(
                    $serialNumber,
                )
                || trim(
                    $serialNumber,
                ) === ''
            ) {
                throw new LogicException(
                    'The source Goods Receipt contains an invalid serial-number snapshot.',
                );
            }

            $normalized[] =
                trim(
                    $serialNumber,
                );
        }

        return array_values(
            array_unique(
                $normalized,
            ),
        );
    }

    /**
     * @return Collection<int, PurchaseReturnLine>
     */
    private function lockReturnLines(
        PurchaseReturn $purchaseReturn,
        bool $reverseOrder,
    ): Collection {
        $query =
            PurchaseReturnLine::query()
                ->where(
                    'purchase_return_id',
                    $purchaseReturn
                        ->getKey(),
                );

        if ($reverseOrder) {
            $query->orderByDesc(
                'line_number',
            );
        } else {
            $query->orderBy(
                'line_number',
            );
        }

        return $query
            ->lockForUpdate()
            ->get();
    }

    private function lockSourceLine(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
    ): GoodsReceiptLine {
        return GoodsReceiptLine::query()
            ->whereKey(
                $line
                    ->goods_receipt_line_id,
            )
            ->where(
                'goods_receipt_id',
                $purchaseReturn
                    ->goods_receipt_id,
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lineQuantity(
        PurchaseReturnLine $line,
    ): BigDecimal {
        return BigDecimal::of(
            (string) $line
                ->return_quantity,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private function totalsInput(
        PurchaseReturn $purchaseReturn,
    ): array {
        return PurchaseReturnLine::query()
            ->where(
                'purchase_return_id',
                $purchaseReturn
                    ->getKey(),
            )
            ->orderBy(
                'line_number',
            )
            ->get()
            ->map(
                static fn (
                    PurchaseReturnLine $line,
                ): array => [
                    'return_quantity' =>
                        (string) $line
                            ->return_quantity,

                    'supplier_total_cost' =>
                        (string) $line
                            ->supplier_total_cost,

                    'inventory_total_cost' =>
                        (string) $line
                            ->inventory_total_cost,

                    'cost_variance_amount' =>
                        (string) $line
                            ->cost_variance_amount,
                ],
            )
            ->all();
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        PurchaseReturn $purchaseReturn,
        array $lines,
    ): void {
        $this->deleteLines(
            $purchaseReturn,
        );

        foreach ($lines as $line) {
            $purchaseReturn
                ->lines()
                ->create($line);
        }
    }

    private function deleteLines(
        PurchaseReturn $purchaseReturn,
    ): void {
        $lines =
            $this->lockReturnLines(
                purchaseReturn:
                    $purchaseReturn,

                reverseOrder:
                    false,
            );

        foreach ($lines as $line) {
            $line->delete();
        }
    }

    private function ensureNumberedIdentityUnchanged(
        PurchaseReturn $purchaseReturn,
        int $goodsReceiptId,
        string $returnDate,
    ): void {
        if (
            !$purchaseReturn
                ->hasReturnNumber()
        ) {
            return;
        }

        if (
            (int) $purchaseReturn
                ->goods_receipt_id
            !== $goodsReceiptId
        ) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => [
                    'The source Goods Receipt cannot be changed after the Purchase Return number has been allocated.',
                ],
            ]);
        }

        if (
            $purchaseReturn
                ->return_date
                ?->toDateString()
            !== $returnDate
        ) {
            throw ValidationException::withMessages([
                'return_date' => [
                    'The return date cannot be changed after the Purchase Return number has been allocated.',
                ],
            ]);
        }
    }

    private function ensureTransition(
        PurchaseReturn $purchaseReturn,
        string $nextStatus,
    ): void {
        if (
            $this
                ->statusRegistry
                ->canTransition(
                    currentStatus:
                        $purchaseReturn
                            ->status,

                    nextStatus:
                        $nextStatus,
                )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                "The Purchase Return cannot move from {$purchaseReturn->status} to {$nextStatus}.",
            ],
        ]);
    }

    private function authorizeReturnBranch(
        User $actor,
        PurchaseReturn $purchaseReturn,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey(
                $purchaseReturn
                    ->branch_id,
            )
            ->firstOrFail();

        $this
            ->branchAccessService
            ->authorizeBranch(
                user: $actor,

                branch: $branch,

                requireActive:
                    $requireActive,
            );
    }

    private function loadReturn(
        PurchaseReturn $purchaseReturn,
    ): PurchaseReturn {
        return $purchaseReturn->load([
            'branch',
            'warehouse',
            'supplier',
            'purchaseOrder',
            'goodsReceipt',
            'supplierInvoice',
            'documentNumberAllocation',
            'lines.goodsReceiptLine',
            'lines.purchaseOrderLine',
            'lines.product',
            'lines.unit',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'postedBy',
            'reversedBy',
            'cancelledBy',
        ]);
    }

    private function normalizeQuantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        if (
            $value === null
            || $value === ''
            || !is_scalar($value)
            || preg_match(
                '/^\d+(?:\.\d+)?$/',
                trim(
                    (string) $value,
                ),
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The return quantity must be a valid positive number.',
                ],
            ]);
        }

        $quantity = BigDecimal::of(
            trim(
                (string) $value,
            ),
        );

        if ($quantity->isZero()) {
            throw ValidationException::withMessages([
                $field => [
                    'The return quantity must be greater than zero.',
                ],
            ]);
        }

        try {
            $quantity =
                $quantity->toScale(
                    self::SCALE,
                    RoundingMode::UNNECESSARY,
                );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    'The return quantity may not contain more than 6 decimal places.',
                ],
            ]);
        }

        if (
            $quantity
                ->isGreaterThan(
                    BigDecimal::of(
                        self::MAXIMUM_QUANTITY,
                    ),
                )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The return quantity exceeds the supported maximum value.',
                ],
            ]);
        }

        return $quantity;
    }

    private function ensureUnitPrecision(
        BigDecimal $quantity,
        ?Unit $unit,
        string $field,
    ): void {
        if (!$unit instanceof Unit) {
            throw ValidationException::withMessages([
                $field => [
                    'The Goods Receipt line unit is unavailable.',
                ],
            ]);
        }

        $allowedScale =
            $unit->allowsDecimal()
                ? (int) $unit
                    ->decimal_places
                : 0;

        try {
            $quantity->toScale(
                $allowedScale,
                RoundingMode::UNNECESSARY,
            );
        } catch (ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The return quantity may not contain more than {$allowedScale} decimal places for unit {$unit->code}.",
                ],
            ]);
        }
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
            !$date
                instanceof CarbonImmutable
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
                'The Purchase Return business date is invalid.',
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

    private function allocationKey(
        PurchaseReturn $purchaseReturn,
    ): string {
        return sprintf(
            'purchase-return:%d:document-number',
            (int) $purchaseReturn
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

    private function ensureReturnBelongsToTenant(
        PurchaseReturn $purchaseReturn,
        int $tenantId,
    ): void {
        if (
            (int) $purchaseReturn
                ->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The Purchase Return does not belong to the active tenant.',
        );
    }
}
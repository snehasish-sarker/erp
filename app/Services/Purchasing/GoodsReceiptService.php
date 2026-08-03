<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Events\Purchasing\GoodsReceiptPosted;
use App\Events\Purchasing\GoodsReceiptReversed;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\InventoryPostingService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Purchasing\GoodsReceiptInspectionStatusRegistry;
use App\Support\Purchasing\GoodsReceiptStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use App\Models\PurchaseReturn;

final class GoodsReceiptService
{
    private const SCALE = 6;

    private const MAXIMUM_QUANTITY =
        '99999999999999.999999';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly InventoryPostingService $inventoryPostingService,
        private readonly GoodsReceiptStatusRegistry $statusRegistry,
        private readonly GoodsReceiptInspectionStatusRegistry $inspectionStatusRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): GoodsReceipt {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

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
                $actor,
                $normalized,
            ): GoodsReceipt {
                $purchaseOrder =
                    $this->resolvePurchaseOrder(
                        purchaseOrderId:
                            $normalized[
                                'purchase_order_id'
                            ],

                        actor: $actor,
                        requireActiveBranch: true,
                    );

                $builtLines = $this->buildLines(
                    purchaseOrder:
                        $purchaseOrder,

                    lines:
                        $normalized['lines'],
                );

                $totals = $this->calculateTotals(
                    $builtLines,
                );

                $goodsReceipt =
                    GoodsReceipt::query()
                        ->create([
                            'purchase_order_id' =>
                                $purchaseOrder
                                    ->getKey(),

                            'branch_id' =>
                                $purchaseOrder
                                    ->branch_id,

                            'warehouse_id' =>
                                $purchaseOrder
                                    ->warehouse_id,

                            'supplier_id' =>
                                $purchaseOrder
                                    ->supplier_id,

                            'receipt_date' =>
                                $normalized[
                                    'receipt_date'
                                ],

                            'supplier_delivery_note' =>
                                $normalized[
                                    'supplier_delivery_note'
                                ],

                            'supplier_name' =>
                                $purchaseOrder
                                    ->supplier_name,

                            'supplier_code' =>
                                $purchaseOrder
                                    ->supplier_code,

                            'purchase_order_number' =>
                                $purchaseOrder
                                    ->document_number,

                            'status' => 'draft',

                            'inspection_status' =>
                                $normalized[
                                    'inspection_status'
                                ],

                            ...$totals,

                            'notes' =>
                                $normalized[
                                    'notes'
                                ],

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                $this->replaceLines(
                    goodsReceipt:
                        $goodsReceipt,

                    lines:
                        $builtLines,
                );

                return $this->loadReceipt(
                    $goodsReceipt,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        GoodsReceipt $goodsReceipt,
        array $data,
        User $actor,
    ): GoodsReceipt {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            goodsReceipt:
                $goodsReceipt,

            tenantId:
                $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $goodsReceipt,
                $actor,
                $normalized,
            ): GoodsReceipt {
                $lockedReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $goodsReceipt
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->ensureEditable(
                    $lockedReceipt,
                );

                $purchaseOrder =
                    $this->resolvePurchaseOrder(
                        purchaseOrderId:
                            $normalized[
                                'purchase_order_id'
                            ],

                        actor: $actor,
                        requireActiveBranch: true,
                    );

                $builtLines = $this->buildLines(
                    purchaseOrder:
                        $purchaseOrder,

                    lines:
                        $normalized['lines'],
                );

                $totals = $this->calculateTotals(
                    $builtLines,
                );

                $lockedReceipt->fill([
                    'purchase_order_id' =>
                        $purchaseOrder
                            ->getKey(),

                    'branch_id' =>
                        $purchaseOrder
                            ->branch_id,

                    'warehouse_id' =>
                        $purchaseOrder
                            ->warehouse_id,

                    'supplier_id' =>
                        $purchaseOrder
                            ->supplier_id,

                    'receipt_date' =>
                        $normalized[
                            'receipt_date'
                        ],

                    'supplier_delivery_note' =>
                        $normalized[
                            'supplier_delivery_note'
                        ],

                    'supplier_name' =>
                        $purchaseOrder
                            ->supplier_name,

                    'supplier_code' =>
                        $purchaseOrder
                            ->supplier_code,

                    'purchase_order_number' =>
                        $purchaseOrder
                            ->document_number,

                    'inspection_status' =>
                        $normalized[
                            'inspection_status'
                        ],

                    ...$totals,

                    'notes' =>
                        $normalized[
                            'notes'
                        ],
                ]);

                $lockedReceipt->save();

                $this->replaceLines(
                    goodsReceipt:
                        $lockedReceipt,

                    lines:
                        $builtLines,
                );

                return $this->loadReceipt(
                    $lockedReceipt
                        ->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        GoodsReceipt $goodsReceipt,
        User $actor,
    ): GoodsReceipt {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            goodsReceipt:
                $goodsReceipt,

            tenantId:
                $tenantId,
        );

        return DB::transaction(
            function () use (
                $goodsReceipt,
                $actor,
                $tenant,
                $tenantId,
            ): GoodsReceipt {
                $lockedReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $goodsReceipt
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$this->statusRegistry
                        ->canPost(
                            $lockedReceipt
                                ->status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only draft Goods Receipts can be posted.',
                        ],
                    ]);
                }

                if (
                    !$this
                        ->inspectionStatusRegistry
                        ->allowsPosting(
                            $lockedReceipt
                                ->inspection_status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'inspection_status' => [
                            'The Goods Receipt cannot be posted while inspection is pending or failed.',
                        ],
                    ]);
                }

                $purchaseOrder =
                    PurchaseOrder::query()
                        ->whereKey(
                            $lockedReceipt
                                ->purchase_order_id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizePurchaseOrderBranch(
                    purchaseOrder:
                        $purchaseOrder,

                    actor:
                        $actor,

                    requireActive:
                        true,
                );

                if (
                    !$purchaseOrder
                        ->isReceivable()
                ) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' => [
                            'The selected Purchase Order is no longer available for receiving.',
                        ],
                    ]);
                }

                $receiptLines =
                    GoodsReceiptLine::query()
                        ->where(
                            'goods_receipt_id',
                            $lockedReceipt
                                ->getKey(),
                        )
                        ->orderBy(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get();

                if ($receiptLines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'The Goods Receipt must contain at least one line.',
                        ],
                    ]);
                }

                /*
                 * The Goods Receipt date is a tenant-local
                 * business date.
                 */
                $receiptEffectiveAt =
                    $this->receiptEffectiveAt(
                        goodsReceipt:
                            $lockedReceipt,

                        tenant:
                            $tenant,
                    );

                if (
                    !$lockedReceipt
                        ->hasReceiptNumber()
                ) {
                    $allocation =
                        $this
                            ->documentNumberService
                            ->allocate(
                                documentType:
                                    'goods_receipt',

                                branchId:
                                    (int) $lockedReceipt
                                        ->branch_id,

                                idempotencyKey:
                                    $this->allocationKey(
                                        $lockedReceipt,
                                    ),

                                allocatableType:
                                    GoodsReceipt::class,

                                allocatableId:
                                    (int) $lockedReceipt
                                        ->getKey(),

                                allocatedAt:
                                    $receiptEffectiveAt,
                            );

                    $lockedReceipt
                        ->document_number_allocation_id =
                            $allocation
                                ->getKey();

                    $lockedReceipt
                        ->receipt_number =
                            $allocation
                                ->number;
                }

                /*
                 * posted_at records the actual system time
                 * at which the user posted the receipt.
                 */
                $postedAt =
                    CarbonImmutable::now(
                        'UTC',
                    );

                /*
                 * Stock movements use the tenant-local
                 * receipt date converted to UTC.
                 */
                $stockOccurredAt =
                    $receiptEffectiveAt
                        ->setTimezone(
                            'UTC',
                        );

                foreach (
                    $receiptLines
                    as $line
                ) {
                    $purchaseOrderLine =
                        PurchaseOrderLine::query()
                            ->whereKey(
                                $line
                                    ->purchase_order_line_id,
                            )
                            ->where(
                                'purchase_order_id',
                                $purchaseOrder
                                    ->getKey(),
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $accepted =
                        BigDecimal::of(
                            (string) $line
                                ->accepted_quantity,
                        );

                    $ordered =
                        BigDecimal::of(
                            (string) $purchaseOrderLine
                                ->ordered_quantity,
                        );

                    $alreadyReceived =
                        BigDecimal::of(
                            (string) $purchaseOrderLine
                                ->received_quantity,
                        );

                    $outstanding =
                        $ordered->minus(
                            $alreadyReceived,
                        );

                    if (
                        $accepted
                            ->isGreaterThan(
                                $outstanding,
                            )
                    ) {
                        throw ValidationException::withMessages([
                            'lines' => [
                                "Accepted quantity for {$line->product_name} exceeds the current outstanding Purchase Order quantity.",
                            ],
                        ]);
                    }

                    $purchaseOrderLine
                        ->received_quantity =
                            $alreadyReceived
                                ->plus(
                                    $accepted,
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                    $purchaseOrderLine
                        ->save();

                    $this
                        ->inventoryPostingService
                        ->postGoodsReceiptLine(
                            goodsReceipt:
                                $lockedReceipt,

                            line:
                                $line,

                            actor:
                                $actor,

                            occurredAt:
                                $stockOccurredAt,
                        );
                }

                $this
                    ->recalculatePurchaseOrderStatus(
                        $purchaseOrder,
                    );

                $lockedReceipt->status =
                    'posted';

                $lockedReceipt
                    ->posted_by_user_id =
                        $actor->getKey();

                $lockedReceipt->posted_at =
                    $postedAt;

                $lockedReceipt->save();

                /*
                 * Future perpetual-accounting integration:
                 *
                 * Dr Inventory
                 * Cr Goods Received Not Invoiced
                 */

                GoodsReceiptPosted::dispatch(
                    tenantId:
                        $tenantId,

                    goodsReceiptId:
                        (int) $lockedReceipt
                            ->getKey(),

                    purchaseOrderId:
                        (int) $purchaseOrder
                            ->getKey(),

                    actorId:
                        (int) $actor
                            ->getKey(),
                );

                return $this->loadReceipt(
                    $lockedReceipt
                        ->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        GoodsReceipt $goodsReceipt,
        string $reason,
        User $actor,
    ): GoodsReceipt {
        $tenantId =
            (int) $this
                ->tenantContext
                ->tenant()
                ->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            goodsReceipt:
                $goodsReceipt,

            tenantId:
                $tenantId,
        );

        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason)
                > 500
        ) {
            throw ValidationException::withMessages([
                'reversal_reason' => [
                    'A reversal reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $goodsReceipt,
                $reason,
                $actor,
                $tenantId,
            ): GoodsReceipt {
                $lockedReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $goodsReceipt
                                ->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$this->statusRegistry
                        ->canReverse(
                            $lockedReceipt
                                ->status,
                        )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only posted Goods Receipts can be reversed.',
                        ],
                    ]);
                }

                $activePurchaseReturn =
                        PurchaseReturn::query()
                            ->where(
                                'goods_receipt_id',
                                $lockedReceipt
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
                        $activePurchaseReturn
                        instanceof PurchaseReturn
                    ) {
                        throw ValidationException::withMessages([
                            'goods_receipt' => [
                                'The Goods Receipt cannot be reversed while an active Purchase Return references it. Cancel, delete, or reverse the Purchase Return first.',
                            ],
                        ]);
                    }

                $purchaseOrder =
                    PurchaseOrder::query()
                        ->whereKey(
                            $lockedReceipt
                                ->purchase_order_id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizePurchaseOrderBranch(
                    purchaseOrder:
                        $purchaseOrder,

                    actor:
                        $actor,

                    requireActive:
                        false,
                );

                if (
                    in_array(
                        $purchaseOrder
                            ->status,
                        [
                            'closed',
                            'cancelled',
                        ],
                        true,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' => [
                            'A Goods Receipt cannot be reversed after its Purchase Order is closed or cancelled.',
                        ],
                    ]);
                }

                /*
                 * Reverse lines in the opposite order from
                 * their original posting order.
                 */
                $receiptLines =
                    GoodsReceiptLine::query()
                        ->where(
                            'goods_receipt_id',
                            $lockedReceipt
                                ->getKey(),
                        )
                        ->orderByDesc(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get();

                        foreach ($receiptLines as $receiptLine) {
    $invoicedQuantity =
        BigDecimal::of(
            (string) $receiptLine
                ->invoiced_quantity,
        );

    $reservedReturnQuantity =
        BigDecimal::of(
            (string) $receiptLine
                ->return_reserved_quantity,
        );

    $returnedQuantity =
        BigDecimal::of(
            (string) $receiptLine
                ->returned_quantity,
        );

    if (
        $invoicedQuantity
            ->isGreaterThan(
                BigDecimal::zero(),
            )
    ) {
        throw ValidationException::withMessages([
            'goods_receipt' => [
                "Goods Receipt {$lockedReceipt->receipt_number} cannot be reversed because {$receiptLine->product_name} has been reserved or used by a Supplier Invoice.",
            ],
        ]);
    }

    if (
        $reservedReturnQuantity
            ->isGreaterThan(
                BigDecimal::zero(),
            )
        || $returnedQuantity
            ->isGreaterThan(
                BigDecimal::zero(),
            )
    ) {
        throw ValidationException::withMessages([
            'goods_receipt' => [
                "Goods Receipt {$lockedReceipt->receipt_number} cannot be reversed because {$receiptLine->product_name} has Purchase Return activity.",
            ],
        ]);
    }
}

                /*
                 * Reversal is a new corrective movement, so
                 * it uses the actual reversal timestamp.
                 */
                $reversedAt =
                    CarbonImmutable::now(
                        'UTC',
                    );

                foreach (
                    $receiptLines
                    as $line
                ) {
                    $purchaseOrderLine =
                        PurchaseOrderLine::query()
                            ->whereKey(
                                $line
                                    ->purchase_order_line_id,
                            )
                            ->where(
                                'purchase_order_id',
                                $purchaseOrder
                                    ->getKey(),
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $accepted =
                        BigDecimal::of(
                            (string) $line
                                ->accepted_quantity,
                        );

                    $currentReceived =
                        BigDecimal::of(
                            (string) $purchaseOrderLine
                                ->received_quantity,
                        );

                    if (
                        $currentReceived
                            ->isLessThan(
                                $accepted,
                            )
                    ) {
                        throw new LogicException(
                            'The Purchase Order received quantity is lower than the Goods Receipt reversal quantity.',
                        );
                    }

                    $this
                        ->inventoryPostingService
                        ->reverseGoodsReceiptLine(
                            goodsReceipt:
                                $lockedReceipt,

                            line:
                                $line,

                            actor:
                                $actor,

                            occurredAt:
                                $reversedAt,
                        );

                    $purchaseOrderLine
                        ->received_quantity =
                            $currentReceived
                                ->minus(
                                    $accepted,
                                )
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HALF_UP,
                                )
                                ->__toString();

                    $purchaseOrderLine
                        ->save();
                }

                $this
                    ->recalculatePurchaseOrderStatus(
                        $purchaseOrder,
                    );

                $lockedReceipt->status =
                    'reversed';

                $lockedReceipt
                    ->reversed_by_user_id =
                        $actor->getKey();

                $lockedReceipt
                    ->reversed_at =
                        $reversedAt;

                $lockedReceipt
                    ->reversal_reason =
                        $reason;

                $lockedReceipt->save();

                GoodsReceiptReversed::dispatch(
                    tenantId:
                        $tenantId,

                    goodsReceiptId:
                        (int) $lockedReceipt
                            ->getKey(),

                    purchaseOrderId:
                        (int) $purchaseOrder
                            ->getKey(),

                    actorId:
                        (int) $actor
                            ->getKey(),
                );

                return $this->loadReceipt(
                    $lockedReceipt
                        ->refresh(),
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
     *     receipt_date: string,
     *     supplier_delivery_note: string|null,
     *     inspection_status: string,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $purchaseOrderId =
            $this->requiredId(
                value:
                    $data[
                        'purchase_order_id'
                    ] ?? null,

                field:
                    'purchase_order_id',

                message:
                    'The selected Purchase Order is invalid.',
            );

        $receiptDate =
            $this->normalizeDate(
                value:
                    $data[
                        'receipt_date'
                    ] ?? null,

                field:
                    'receipt_date',

                timezone:
                    $tenant->timezone,
            );

        $inspectionStatus =
            mb_strtolower(
                trim(
                    (string) (
                        $data[
                            'inspection_status'
                        ] ?? 'not_required'
                    ),
                ),
            );

        if (
            !$this
                ->inspectionStatusRegistry
                ->exists(
                    $inspectionStatus,
                )
        ) {
            throw ValidationException::withMessages([
                'inspection_status' => [
                    'The selected inspection status is invalid.',
                ],
            ]);
        }

        $lines =
            $data['lines'] ?? null;

        if (
            !is_array($lines)
            || $lines === []
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Goods Receipt must contain at least one line.',
                ],
            ]);
        }

        if (count($lines) > 500) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Goods Receipt may not contain more than 500 lines.',
                ],
            ]);
        }

        return [
            'purchase_order_id' =>
                $purchaseOrderId,

            'receipt_date' =>
                $receiptDate,

            'supplier_delivery_note' =>
                $this->nullableString(
                    value:
                        $data[
                            'supplier_delivery_note'
                        ] ?? null,

                    maximum:
                        160,

                    field:
                        'supplier_delivery_note',

                    label:
                        'supplier delivery note',
                ),

            'inspection_status' =>
                $inspectionStatus,

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

                    label:
                        'notes',
                ),

            'lines' =>
                array_values(
                    $lines,
                ),
        ];
    }

    private function resolvePurchaseOrder(
        int $purchaseOrderId,
        User $actor,
        bool $requireActiveBranch,
    ): PurchaseOrder {
        $purchaseOrder =
            PurchaseOrder::query()
                ->with([
                    'lines.product',
                    'lines.unit',
                ])
                ->whereKey(
                    $purchaseOrderId,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$purchaseOrder
                instanceof PurchaseOrder
        ) {
            throw ValidationException::withMessages([
                'purchase_order_id' => [
                    'The selected Purchase Order is unavailable.',
                ],
            ]);
        }

        $this->authorizePurchaseOrderBranch(
            purchaseOrder:
                $purchaseOrder,

            actor:
                $actor,

            requireActive:
                $requireActiveBranch,
        );

        if (
            !$purchaseOrder
                ->isReceivable()
        ) {
            throw ValidationException::withMessages([
                'purchase_order_id' => [
                    'Only approved or partially received Purchase Orders can be received.',
                ],
            ]);
        }

        return $purchaseOrder;
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(
        PurchaseOrder $purchaseOrder,
        array $lines,
    ): array {
        $builtLines = [];

        $usedPurchaseOrderLineIds = [];

        foreach (
            $lines
            as $index => $line
        ) {
            if (!is_array($line)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each Goods Receipt line must be an object.',
                    ],
                ]);
            }

            $purchaseOrderLineId =
                $this->requiredId(
                    value:
                        $line[
                            'purchase_order_line_id'
                        ] ?? null,

                    field:
                        "lines.{$index}.purchase_order_line_id",

                    message:
                        'The selected Purchase Order line is invalid.',
                );

            if (
                in_array(
                    $purchaseOrderLineId,
                    $usedPurchaseOrderLineIds,
                    true,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => [
                        'Each Purchase Order line may appear only once on a Goods Receipt.',
                    ],
                ]);
            }

            $usedPurchaseOrderLineIds[] =
                $purchaseOrderLineId;

            $purchaseOrderLine =
                PurchaseOrderLine::query()
                    ->with([
                        'product',
                        'unit',
                    ])
                    ->whereKey(
                        $purchaseOrderLineId,
                    )
                    ->where(
                        'purchase_order_id',
                        $purchaseOrder
                            ->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

            if (
                !$purchaseOrderLine
                    instanceof PurchaseOrderLine
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => [
                        'The selected line does not belong to the selected Purchase Order.',
                    ],
                ]);
            }

            $receiptQuantity =
                $this->normalizeDecimal(
                    value:
                        $line[
                            'receipt_quantity'
                        ] ?? null,

                    field:
                        "lines.{$index}.receipt_quantity",

                    label:
                        'receipt quantity',

                    allowZero:
                        false,
                );

            $acceptedQuantity =
                $this->normalizeDecimal(
                    value:
                        $line[
                            'accepted_quantity'
                        ] ?? 0,

                    field:
                        "lines.{$index}.accepted_quantity",

                    label:
                        'accepted quantity',

                    allowZero:
                        true,
                );

            $rejectedQuantity =
                $this->normalizeDecimal(
                    value:
                        $line[
                            'rejected_quantity'
                        ] ?? 0,

                    field:
                        "lines.{$index}.rejected_quantity",

                    label:
                        'rejected quantity',

                    allowZero:
                        true,
                );

            $varianceReason =
                $this->nullableString(
                    value:
                        $line[
                            'variance_reason'
                        ] ?? null,

                    maximum:
                        500,

                    field:
                        "lines.{$index}.variance_reason",

                    label:
                        'variance reason',
                );

            if (
                BigDecimal::of(
                    $rejectedQuantity,
                )->isGreaterThan(
                    BigDecimal::zero(),
                )
                && $varianceReason
                    === null
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.variance_reason" => [
                        'A variance reason is required when the rejected quantity is greater than zero.',
                    ],
                ]);
            }

            $calculatedReceiptQuantity =
                BigDecimal::of(
                    $acceptedQuantity,
                )
                    ->plus(
                        BigDecimal::of(
                            $rejectedQuantity,
                        ),
                    )
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    );

            if (
                !$calculatedReceiptQuantity
                    ->isEqualTo(
                        BigDecimal::of(
                            $receiptQuantity,
                        ),
                    )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.receipt_quantity" => [
                        'Receipt quantity must equal accepted quantity plus rejected quantity.',
                    ],
                ]);
            }

            $ordered =
                BigDecimal::of(
                    (string) $purchaseOrderLine
                        ->ordered_quantity,
                );

            $previouslyReceived =
                BigDecimal::of(
                    (string) $purchaseOrderLine
                        ->received_quantity,
                );

            $outstanding =
                $ordered->minus(
                    $previouslyReceived,
                );

            if (
                BigDecimal::of(
                    $acceptedQuantity,
                )->isGreaterThan(
                    $outstanding,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.accepted_quantity" => [
                        'Accepted quantity exceeds the outstanding Purchase Order quantity.',
                    ],
                ]);
            }

            $unit =
                $purchaseOrderLine
                    ->unit;

            if (!$unit instanceof Unit) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => [
                        'The Purchase Order line unit is unavailable.',
                    ],
                ]);
            }

            $this->ensureUnitPrecision(
                quantity:
                    $receiptQuantity,

                unit:
                    $unit,

                field:
                    "lines.{$index}.receipt_quantity",
            );

            $this->ensureUnitPrecision(
                quantity:
                    $acceptedQuantity,

                unit:
                    $unit,

                field:
                    "lines.{$index}.accepted_quantity",
            );

            $this->ensureUnitPrecision(
                quantity:
                    $rejectedQuantity,

                unit:
                    $unit,

                field:
                    "lines.{$index}.rejected_quantity",
            );

            $manufacturingDate =
                $this->normalizeNullableDate(
                    value:
                        $line[
                            'manufacturing_date'
                        ] ?? null,

                    field:
                        "lines.{$index}.manufacturing_date",
                );

            $expiryDate =
                $this->normalizeNullableDate(
                    value:
                        $line[
                            'expiry_date'
                        ] ?? null,

                    field:
                        "lines.{$index}.expiry_date",
                );

            if (
                $manufacturingDate
                    !== null
                && $expiryDate
                    !== null
                && $expiryDate
                    < $manufacturingDate
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.expiry_date" => [
                        'Expiry date cannot be earlier than manufacturing date.',
                    ],
                ]);
            }

            $serialNumbers =
                $this->normalizeSerialNumbers(
                    value:
                        $line[
                            'serial_numbers'
                        ] ?? null,

                    acceptedQuantity:
                        $acceptedQuantity,

                    field:
                        "lines.{$index}.serial_numbers",
                );

            $unitCost =
                $this->provisionalUnitCost(
                    $purchaseOrderLine,
                );

            $totalCost =
                BigDecimal::of(
                    $acceptedQuantity,
                )
                    ->multipliedBy(
                        BigDecimal::of(
                            $unitCost,
                        ),
                    )
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString();

            $builtLines[] = [
                'purchase_order_line_id' =>
                    $purchaseOrderLine
                        ->getKey(),

                'product_id' =>
                    $purchaseOrderLine
                        ->product_id,

                'unit_id' =>
                    $purchaseOrderLine
                        ->unit_id,

                'line_number' =>
                    $index + 1,

                'product_name' =>
                    $purchaseOrderLine
                        ->product_name,

                'product_sku' =>
                    $purchaseOrderLine
                        ->product_sku,

                'product_type' =>
                    $purchaseOrderLine
                        ->product_type,

                'unit_name' =>
                    $purchaseOrderLine
                        ->unit_name,

                'unit_code' =>
                    $purchaseOrderLine
                        ->unit_code,

                'ordered_quantity_snapshot' =>
                    (string) $purchaseOrderLine
                        ->ordered_quantity,

                'previously_received_quantity_snapshot' =>
                    (string) $purchaseOrderLine
                        ->received_quantity,

                'receipt_quantity' =>
                    $receiptQuantity,

                'accepted_quantity' =>
                    $acceptedQuantity,

                'rejected_quantity' =>
                    $rejectedQuantity,

                'unit_cost' =>
                    $unitCost,

                'total_cost' =>
                    $totalCost,

                'batch_number' =>
                    $this->nullableString(
                        value:
                            $line[
                                'batch_number'
                            ] ?? null,

                        maximum:
                            120,

                        field:
                            "lines.{$index}.batch_number",

                        label:
                            'batch number',
                    ),

                'manufacturing_date' =>
                    $manufacturingDate,

                'expiry_date' =>
                    $expiryDate,

                'serial_numbers' =>
                    $serialNumbers,

                'storage_location' =>
                    $this->nullableString(
                        value:
                            $line[
                                'storage_location'
                            ] ?? null,

                        maximum:
                            160,

                        field:
                            "lines.{$index}.storage_location",

                        label:
                            'storage location',
                    ),

                'variance_reason' =>
                    $varianceReason,
            ];
        }

        return $builtLines;
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return array{
     *     total_received_quantity: string,
     *     total_accepted_quantity: string,
     *     total_rejected_quantity: string,
     *     total_inventory_value: string
     * }
     */
    private function calculateTotals(
        array $lines,
    ): array {
        $received =
            BigDecimal::zero()
                ->toScale(
                    self::SCALE,
                );

        $accepted =
            BigDecimal::zero()
                ->toScale(
                    self::SCALE,
                );

        $rejected =
            BigDecimal::zero()
                ->toScale(
                    self::SCALE,
                );

        $inventoryValue =
            BigDecimal::zero()
                ->toScale(
                    self::SCALE,
                );

        foreach ($lines as $line) {
            $received =
                $received->plus(
                    BigDecimal::of(
                        (string) $line[
                            'receipt_quantity'
                        ],
                    ),
                );

            $accepted =
                $accepted->plus(
                    BigDecimal::of(
                        (string) $line[
                            'accepted_quantity'
                        ],
                    ),
                );

            $rejected =
                $rejected->plus(
                    BigDecimal::of(
                        (string) $line[
                            'rejected_quantity'
                        ],
                    ),
                );

            if (
                $line[
                    'product_type'
                ] === 'stock'
            ) {
                $inventoryValue =
                    $inventoryValue->plus(
                        BigDecimal::of(
                            (string) $line[
                                'total_cost'
                            ],
                        ),
                    );
            }
        }

        return [
            'total_received_quantity' =>
                $received
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'total_accepted_quantity' =>
                $accepted
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'total_rejected_quantity' =>
                $rejected
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'total_inventory_value' =>
                $inventoryValue
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        GoodsReceipt $goodsReceipt,
        array $lines,
    ): void {
        GoodsReceiptLine::query()
            ->where(
                'goods_receipt_id',
                $goodsReceipt
                    ->getKey(),
            )
            ->lockForUpdate()
            ->get()
            ->each(
                static function (
                    GoodsReceiptLine $line,
                ): void {
                    $line->delete();
                },
            );

        foreach ($lines as $line) {
            $goodsReceipt
                ->lines()
                ->create($line);
        }
    }

    private function provisionalUnitCost(
        PurchaseOrderLine $line,
    ): string {
        $orderedQuantity =
            BigDecimal::of(
                (string) $line
                    ->ordered_quantity,
            );

        if (
            $orderedQuantity->isZero()
        ) {
            throw new LogicException(
                'A Purchase Order line cannot have zero ordered quantity.',
            );
        }

        $netAmount =
            BigDecimal::of(
                (string) $line
                    ->gross_amount,
            )->minus(
                BigDecimal::of(
                    (string) $line
                        ->discount_amount,
                ),
            );

        return $netAmount
            ->dividedBy(
                $orderedQuantity,
                self::SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }

    private function recalculatePurchaseOrderStatus(
        PurchaseOrder $purchaseOrder,
    ): void {
        $lines =
            PurchaseOrderLine::query()
                ->where(
                    'purchase_order_id',
                    $purchaseOrder
                        ->getKey(),
                )
                ->lockForUpdate()
                ->get();

        $hasReceivedQuantity =
            false;

        $fullyReceived =
            $lines->isNotEmpty();

        foreach ($lines as $line) {
            $ordered =
                BigDecimal::of(
                    (string) $line
                        ->ordered_quantity,
                );

            $received =
                BigDecimal::of(
                    (string) $line
                        ->received_quantity,
                );

            if (
                $received->isGreaterThan(
                    BigDecimal::zero(),
                )
            ) {
                $hasReceivedQuantity =
                    true;
            }

            if (
                $received->isLessThan(
                    $ordered,
                )
            ) {
                $fullyReceived =
                    false;
            }
        }

        if ($fullyReceived) {
            $purchaseOrder->status =
                'received';
        } elseif ($hasReceivedQuantity) {
            $purchaseOrder->status =
                'partially_received';
        } else {
            $purchaseOrder->status =
                'approved';
        }

        $purchaseOrder->save();
    }

    private function authorizePurchaseOrderBranch(
        PurchaseOrder $purchaseOrder,
        User $actor,
        bool $requireActive,
    ): void {
        $branch =
            Branch::query()
                ->whereKey(
                    $purchaseOrder
                        ->branch_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user:
                    $actor,

                branch:
                    $branch,

                requireActive:
                    $requireActive,
            );
    }

    private function ensureEditable(
        GoodsReceipt $goodsReceipt,
    ): void {
        if (
            $this->statusRegistry
                ->isEditable(
                    $goodsReceipt
                        ->status,
                )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Only draft Goods Receipts can be modified.',
            ],
        ]);
    }

    private function ensureUnitPrecision(
        string $quantity,
        Unit $unit,
        string $field,
    ): void {
        $decimal =
            BigDecimal::of(
                $quantity,
            );

        if (!$unit->allowsDecimal()) {
            if (
                !$decimal->isEqualTo(
                    $decimal->toScale(
                        0,
                        RoundingMode::DOWN,
                    ),
                )
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        'The selected unit does not allow decimal quantities.',
                    ],
                ]);
            }

            return;
        }

        $allowedPlaces =
            min(
                max(
                    (int) $unit
                        ->decimal_places,
                    0,
                ),
                self::SCALE,
            );

        try {
            $decimal->toScale(
                $allowedPlaces,
                RoundingMode::UNNECESSARY,
            );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The quantity may not contain more than {$allowedPlaces} decimal places for the selected unit.",
                ],
            ]);
        }
    }

    /**
     * @return list<string>|null
     */
    private function normalizeSerialNumbers(
        mixed $value,
        string $acceptedQuantity,
        string $field,
    ): ?array {
        if (
            $value === null
            || $value === ''
            || $value === []
        ) {
            return null;
        }

        if (!is_array($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'Serial numbers must be provided as a list.',
                ],
            ]);
        }

        if (count($value) > 1000) {
            throw ValidationException::withMessages([
                $field => [
                    'A Goods Receipt line may not contain more than 1,000 serial numbers.',
                ],
            ]);
        }

        $serialNumbers = [];

        foreach (
            $value
            as $serialNumber
        ) {
            if (!is_string($serialNumber)) {
                throw ValidationException::withMessages([
                    $field => [
                        'Every serial number must be text.',
                    ],
                ]);
            }

            $serialNumber =
                trim(
                    $serialNumber,
                );

            if (
                $serialNumber === ''
                || mb_strlen(
                    $serialNumber,
                ) > 190
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        'Serial numbers are required and may not exceed 190 characters.',
                    ],
                ]);
            }

            $serialNumbers[] =
                $serialNumber;
        }

        if (
            count($serialNumbers)
            !== count(
                array_unique(
                    $serialNumbers,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'Duplicate serial numbers are not allowed on the same Goods Receipt line.',
                ],
            ]);
        }

        $accepted =
            BigDecimal::of(
                $acceptedQuantity,
            );

        $acceptedWhole =
            $accepted->toScale(
                0,
                RoundingMode::DOWN,
            );

        if (
            !$accepted->isEqualTo(
                $acceptedWhole,
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'Serial numbers cannot be assigned to a decimal accepted quantity.',
                ],
            ]);
        }

        if (
            count($serialNumbers)
            !== (int) $acceptedWhole
                ->__toString()
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The number of serial numbers must equal the accepted quantity.',
                ],
            ]);
        }

        return array_values(
            $serialNumbers,
        );
    }

    private function normalizeDecimal(
        mixed $value,
        string $field,
        string $label,
        bool $allowZero,
    ): string {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }

        if (
            is_float($value)
            && !is_finite($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }

        $value =
            trim(
                (string) $value,
            );

        if (
            preg_match(
                '/^\d+(?:\.\d+)?$/',
                $value,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a non-negative number.",
                ],
            ]);
        }

        $decimal =
            BigDecimal::of(
                $value,
            );

        if (
            !$allowZero
            && $decimal->isZero()
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be greater than zero.",
                ],
            ]);
        }

        try {
            $decimal =
                $decimal->toScale(
                    self::SCALE,
                    RoundingMode::UNNECESSARY,
                );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not contain more than six decimal places.",
                ],
            ]);
        }

        if (
            $decimal->isGreaterThan(
                BigDecimal::of(
                    self::MAXIMUM_QUANTITY,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} exceeds the supported maximum value.",
                ],
            ]);
        }

        return $decimal
            ->__toString();
    }

    private function normalizeDate(
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

        $date =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                $timezone,
            );

        if (
            !$date instanceof CarbonImmutable
            || $date->format(
                'Y-m-d',
            ) !== $value
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
    }

    private function normalizeNullableDate(
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
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        $value = trim($value);

        $date =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value,
            );

        if (
            !$date instanceof CarbonImmutable
            || $date->format(
                'Y-m-d',
            ) !== $value
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
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
            return (int) trim(
                $value,
            );
        }

        throw ValidationException::withMessages([
            $field => [
                $message,
            ],
        ]);
    }

    private function nullableString(
        mixed $value,
        int $maximum,
        string $field,
        string $label,
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
                    "The {$label} must be text.",
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
                    "The {$label} may not exceed {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function receiptEffectiveAt(
        GoodsReceipt $goodsReceipt,
        Tenant $tenant,
    ): CarbonImmutable {
        $receiptDate =
            $goodsReceipt
                ->receipt_date
                ?->format(
                    'Y-m-d',
                );

        if ($receiptDate === null) {
            throw new LogicException(
                'The Goods Receipt does not have a valid receipt date.',
            );
        }

        $effectiveAt =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $receiptDate,
                $tenant->timezone,
            );

        if (
            !$effectiveAt
                instanceof CarbonImmutable
            || $effectiveAt->format(
                'Y-m-d',
            ) !== $receiptDate
        ) {
            throw new LogicException(
                'The Goods Receipt receipt date could not be converted to a stock-ledger timestamp.',
            );
        }

        return $effectiveAt;
    }

    private function allocationKey(
        GoodsReceipt $goodsReceipt,
    ): string {
        return sprintf(
            'goods-receipt:%d:%d',
            (int) $goodsReceipt
                ->tenant_id,

            (int) $goodsReceipt
                ->getKey(),
        );
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
            'The selected user does not belong to the active tenant.',
        );
    }

    private function ensureReceiptBelongsToTenant(
        GoodsReceipt $goodsReceipt,
        int $tenantId,
    ): void {
        if (
            (int) $goodsReceipt
                ->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The selected Goods Receipt belongs to another tenant.',
        );
    }

    private function loadReceipt(
        GoodsReceipt $goodsReceipt,
    ): GoodsReceipt {
        return $goodsReceipt->load([
            'purchaseOrder',
            'branch',
            'warehouse',
            'supplier',
            'lines.product',
            'lines.unit',
            'lines.purchaseOrderLine',
            'createdBy',
            'postedBy',
            'reversedBy',
            'documentNumberAllocation',
        ]);
    }

    public function delete(
        GoodsReceipt $goodsReceipt,
        User $actor,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            goodsReceipt: $goodsReceipt,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $goodsReceipt,
                $actor,
            ): void {
                $lockedReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $goodsReceipt->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeReceiptBranch(
                    goodsReceipt: $lockedReceipt,
                    actor: $actor,
                    requireActive: false,
                );

                $this->ensureDeletable(
                    $lockedReceipt,
                );

                $lines =
                    GoodsReceiptLine::query()
                        ->where(
                            'goods_receipt_id',
                            $lockedReceipt->getKey(),
                        )
                        ->orderBy('line_number')
                        ->lockForUpdate()
                        ->get();

                /*
                 * Delete draft lines through Eloquent so their
                 * Auditable deletion events are recorded before
                 * deleting the Goods Receipt header.
                 */
                foreach ($lines as $line) {
                    $line->delete();
                }

                $lockedReceipt->delete();
            },
            attempts: 5,
        );
    }
    private function authorizeReceiptBranch(
        GoodsReceipt $goodsReceipt,
        User $actor,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey(
                $goodsReceipt->branch_id,
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function ensureDeletable(
        GoodsReceipt $goodsReceipt,
    ): void {
        if (!$goodsReceipt->canBeDeleted()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only unnumbered draft Goods Receipts can be deleted.',
                ],
            ]);
        }

        if (
            $goodsReceipt
                ->stockLedgerEntries()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The Goods Receipt cannot be deleted because stock-ledger entries already exist.',
                ],
            ]);
        }
    }
}

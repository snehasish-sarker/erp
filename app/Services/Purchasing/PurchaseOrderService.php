<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductWarehouseSetting;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Purchasing\PurchaseOrderStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PurchaseOrderService
{
    private const MONEY_SCALE = 6;

    private const EXCHANGE_RATE_SCALE = 8;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly PurchaseOrderCalculator $calculator,
        private readonly PurchaseOrderStatusRegistry $statusRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): PurchaseOrder {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeOrderInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $tenant,
                $actor,
                $normalized,
            ): PurchaseOrder {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = $this->resolveBranch(
                    actor: $actor,
                    branchId: $normalized['branch_id'],
                );

                $warehouse = $this->resolveWarehouse(
                    warehouseId: $normalized['warehouse_id'],
                    branch: $branch,
                );

                $supplier = $this->resolveSupplier(
                    supplierId: $normalized['supplier_id'],
                );

                $lines = $this->buildLines(
                    lines: $normalized['lines'],
                    branch: $branch,
                    warehouse: $warehouse,
                );

                $totals = $this->calculator->calculateOrder(
                    calculatedLines: $lines,
                    shippingAmount:
                        $normalized['shipping_amount'],
                    otherCharges:
                        $normalized['other_charges'],
                );

                $purchaseOrder = PurchaseOrder::query()
                    ->create([
                        ...$this->headerAttributes(
                            normalized: $normalized,
                            tenant: $tenant,
                            branch: $branch,
                            warehouse: $warehouse,
                            supplier: $supplier,
                        ),
                        ...$totals,
                        'status' => 'draft',
                        'revision' => 1,
                        'created_by_user_id' =>
                            $actor->getKey(),
                    ]);

                $this->replaceLines(
                    purchaseOrder: $purchaseOrder,
                    lines: $lines,
                );

                return $purchaseOrder->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        PurchaseOrder $purchaseOrder,
        array $data,
        User $actor,
    ): PurchaseOrder {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeOrderInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $tenant,
                $purchaseOrder,
                $actor,
                $normalized,
            ): PurchaseOrder {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureEditable($lockedOrder);
                $this->ensureOrderHasNoReceipts($lockedOrder);

                $branch = $this->resolveBranch(
                    actor: $actor,
                    branchId: $normalized['branch_id'],
                );

                $warehouse = $this->resolveWarehouse(
                    warehouseId: $normalized['warehouse_id'],
                    branch: $branch,
                );

                $supplier = $this->resolveSupplier(
                    supplierId: $normalized['supplier_id'],
                );

                $lines = $this->buildLines(
                    lines: $normalized['lines'],
                    branch: $branch,
                    warehouse: $warehouse,
                );

                $totals = $this->calculator->calculateOrder(
                    calculatedLines: $lines,
                    shippingAmount:
                        $normalized['shipping_amount'],
                    otherCharges:
                        $normalized['other_charges'],
                );

                $lockedOrder->fill([
                    ...$this->headerAttributes(
                        normalized: $normalized,
                        tenant: $tenant,
                        branch: $branch,
                        warehouse: $warehouse,
                        supplier: $supplier,
                    ),
                    ...$totals,
                    'revision' =>
                        (int) $lockedOrder->revision + 1,
                ]);

                $lockedOrder->save();

                $this->replaceLines(
                    purchaseOrder: $lockedOrder,
                    lines: $lines,
                );

                return $lockedOrder->refresh()->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                    'submittedBy',
                    'approvedBy',
                ]);
            },
            attempts: 5,
        );
    }

    public function delete(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $purchaseOrder,
                $actor,
            ): void {
                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeOrderBranch(
                    actor: $actor,
                    purchaseOrder: $lockedOrder,
                    requireActive: false,
                );

                $this->ensureEditable($lockedOrder);
                $this->ensureOrderHasNoReceipts($lockedOrder);

                /*
                 * Keep line records attached to the soft-deleted header so
                 * the draft's audit history remains reconstructable.
                 */
                $lockedOrder->delete();
            },
            attempts: 5,
        );
    }

    public function submit(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): PurchaseOrder {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseOrder,
                $actor,
                $tenant,
            ): PurchaseOrder {
                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureTransition(
                    purchaseOrder: $lockedOrder,
                    nextStatus: 'submitted',
                );

                $this->validateCurrentConfiguration(
                    purchaseOrder: $lockedOrder,
                    actor: $actor,
                );

                if (!$lockedOrder->hasDocumentNumber()) {
                    $allocation =
                        $this->documentNumberService->allocate(
                            documentType: 'purchase_order',
                            branchId:
                                (int) $lockedOrder->branch_id,
                            idempotencyKey:
                                $this->allocationKey($lockedOrder),
                            allocatableType:
                                PurchaseOrder::class,
                            allocatableId:
                                (int) $lockedOrder->getKey(),
                            allocatedAt:
                                CarbonImmutable::parse(
                                    $lockedOrder->order_date,
                                    $tenant->timezone,
                                ),
                        );

                    $lockedOrder
                        ->document_number_allocation_id =
                            $allocation->getKey();

                    $lockedOrder->document_number =
                        $allocation->number;
                }

                $lockedOrder->status = 'submitted';
                $lockedOrder->submitted_by_user_id =
                    $actor->getKey();
                $lockedOrder->submitted_at = now();
                $lockedOrder->save();

                return $lockedOrder->refresh()->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                    'submittedBy',
                    'documentNumberAllocation',
                ]);
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): PurchaseOrder {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseOrder,
                $actor,
            ): PurchaseOrder {
                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeOrderBranch(
                    actor: $actor,
                    purchaseOrder: $lockedOrder,
                    requireActive: false,
                );

                $this->ensureTransition(
                    purchaseOrder: $lockedOrder,
                    nextStatus: 'draft',
                );

                $this->ensureOrderHasNoReceipts($lockedOrder);

                $lockedOrder->status = 'draft';
                $lockedOrder->submitted_by_user_id = null;
                $lockedOrder->submitted_at = null;
                $lockedOrder->approved_by_user_id = null;
                $lockedOrder->approved_at = null;
                $lockedOrder->revision =
                    (int) $lockedOrder->revision + 1;
                $lockedOrder->save();

                return $lockedOrder->refresh()->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                    'documentNumberAllocation',
                ]);
            },
            attempts: 5,
        );
    }

    public function approve(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): PurchaseOrder {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $purchaseOrder,
                $actor,
            ): PurchaseOrder {
                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureTransition(
                    purchaseOrder: $lockedOrder,
                    nextStatus: 'approved',
                );

                $this->validateCurrentConfiguration(
                    purchaseOrder: $lockedOrder,
                    actor: $actor,
                );

                if (!$lockedOrder->hasDocumentNumber()) {
                    throw new LogicException(
                        'A submitted purchase order must have a document number before approval.',
                    );
                }

                $lockedOrder->status = 'approved';
                $lockedOrder->approved_by_user_id =
                    $actor->getKey();
                $lockedOrder->approved_at = now();
                $lockedOrder->save();

                return $lockedOrder->refresh()->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                    'submittedBy',
                    'approvedBy',
                    'documentNumberAllocation',
                ]);
            },
            attempts: 5,
        );
    }

    public function cancel(
        PurchaseOrder $purchaseOrder,
        string $reason,
        User $actor,
    ): PurchaseOrder {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureOrderBelongsToTenant(
            purchaseOrder: $purchaseOrder,
            tenantId: $tenantId,
        );

        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                'cancellation_reason' => [
                    'A cancellation reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $purchaseOrder,
                $reason,
                $actor,
            ): PurchaseOrder {
                $lockedOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeOrderBranch(
                    actor: $actor,
                    purchaseOrder: $lockedOrder,
                    requireActive: false,
                );

                $this->ensureTransition(
                    purchaseOrder: $lockedOrder,
                    nextStatus: 'cancelled',
                );

                $lockedOrder->status = 'cancelled';
                $lockedOrder->cancelled_by_user_id =
                    $actor->getKey();
                $lockedOrder->cancelled_at = now();
                $lockedOrder->cancellation_reason =
                    $reason;
                $lockedOrder->save();

                return $lockedOrder->refresh()->load([
                    'branch',
                    'warehouse',
                    'supplier',
                    'lines.product',
                    'lines.unit',
                    'createdBy',
                    'submittedBy',
                    'approvedBy',
                    'cancelledBy',
                    'documentNumberAllocation',
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     branch_id: int,
     *     warehouse_id: int|null,
     *     supplier_id: int,
     *     order_date: string,
     *     expected_delivery_date: string|null,
     *     supplier_reference: string|null,
     *     currency_code: string,
     *     exchange_rate: string,
     *     delivery_address: string|null,
     *     payment_terms_days: int|null,
     *     shipping_amount: string,
     *     other_charges: string,
     *     terms_and_conditions: string|null,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeOrderInput(
        array $data,
        Tenant $tenant,
    ): array {
        $branchId = $this->requiredId(
            value: $data['branch_id'] ?? null,
            field: 'branch_id',
            message: 'The selected branch is invalid.',
        );

        $warehouseId = $this->nullableId(
            value: $data['warehouse_id'] ?? null,
            field: 'warehouse_id',
            message: 'The selected warehouse is invalid.',
        );

        $supplierId = $this->requiredId(
            value: $data['supplier_id'] ?? null,
            field: 'supplier_id',
            message: 'The selected supplier is invalid.',
        );

        $orderDate = $this->normalizeDate(
            value: $data['order_date'] ?? null,
            field: 'order_date',
            timezone: $tenant->timezone,
        );

        $expectedDeliveryDate =
            $this->normalizeNullableDate(
                value:
                    $data['expected_delivery_date']
                        ?? null,
                field: 'expected_delivery_date',
                timezone: $tenant->timezone,
            );

        if (
            $expectedDeliveryDate !== null
            && $expectedDeliveryDate < $orderDate
        ) {
            throw ValidationException::withMessages([
                'expected_delivery_date' => [
                    'The expected delivery date cannot be earlier than the order date.',
                ],
            ]);
        }

        $supplierReference = $this->nullableString(
            value: $data['supplier_reference'] ?? null,
            maximum: 120,
            field: 'supplier_reference',
            label: 'supplier reference',
        );

        $currencyCode = mb_strtoupper(
            trim(
                (string) (
                    $data['currency_code']
                        ?? $tenant->currency_code
                ),
            ),
        );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency code must contain exactly three letters.',
                ],
            ]);
        }

        $exchangeRate = $this->normalizeDecimal(
            value: $data['exchange_rate'] ?? '1',
            scale: self::EXCHANGE_RATE_SCALE,
            field: 'exchange_rate',
            label: 'exchange rate',
            allowZero: false,
            maximumWholeDigits: 12,
        );

        if (
            $currencyCode === $tenant->currency_code
            && !BigDecimal::of($exchangeRate)
                ->isEqualTo(BigDecimal::of('1'))
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be 1 when using the tenant base currency.',
                ],
            ]);
        }

        $paymentTermsDays = null;

        if (
            array_key_exists(
                'payment_terms_days',
                $data,
            )
            && $data['payment_terms_days'] !== null
            && $data['payment_terms_days'] !== ''
        ) {
            $paymentTermsDays = filter_var(
                $data['payment_terms_days'],
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                        'max_range' => 3650,
                    ],
                ],
            );

            if ($paymentTermsDays === false) {
                throw ValidationException::withMessages([
                    'payment_terms_days' => [
                        'Payment terms must be between 0 and 3,650 days.',
                    ],
                ]);
            }
        }

        $lines = $data['lines'] ?? null;

        if (
            !is_array($lines)
            || $lines === []
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A purchase order must contain at least one line.',
                ],
            ]);
        }

        return [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplierId,
            'order_date' => $orderDate,
            'expected_delivery_date' =>
                $expectedDeliveryDate,
            'supplier_reference' =>
                $supplierReference,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,

            'delivery_address' => $this->nullableString(
                value:
                    $data['delivery_address'] ?? null,
                maximum: 4000,
                field: 'delivery_address',
                label: 'delivery address',
            ),

            'payment_terms_days' =>
                $paymentTermsDays,

            'shipping_amount' =>
                $this->normalizeDecimal(
                    value:
                        $data['shipping_amount'] ?? 0,
                    scale: self::MONEY_SCALE,
                    field: 'shipping_amount',
                    label: 'shipping amount',
                    allowZero: true,
                    maximumWholeDigits: 14,
                ),

            'other_charges' =>
                $this->normalizeDecimal(
                    value:
                        $data['other_charges'] ?? 0,
                    scale: self::MONEY_SCALE,
                    field: 'other_charges',
                    label: 'other charges',
                    allowZero: true,
                    maximumWholeDigits: 14,
                ),

            'terms_and_conditions' =>
                $this->nullableString(
                    value:
                        $data['terms_and_conditions']
                            ?? null,
                    maximum: 10000,
                    field: 'terms_and_conditions',
                    label: 'terms and conditions',
                ),

            'notes' => $this->nullableString(
                value: $data['notes'] ?? null,
                maximum: 4000,
                field: 'notes',
                label: 'notes',
            ),

            'lines' => array_values($lines),
        ];
    }

    private function resolveBranch(
        User $actor,
        int $branchId,
    ): Branch {
        $branch = Branch::query()
            ->whereKey($branchId)
            ->lockForUpdate()
            ->first();

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is unavailable.',
                ],
            ]);
        }

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: true,
        );

        return $branch;
    }

    private function resolveWarehouse(
        ?int $warehouseId,
        Branch $branch,
    ): ?Warehouse {
        if ($warehouseId === null) {
            return null;
        }

        $warehouse = Warehouse::query()
            ->whereKey($warehouseId)
            ->lockForUpdate()
            ->first();

        if (!$warehouse instanceof Warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The selected warehouse is unavailable.',
                ],
            ]);
        }

        if (
            (int) $warehouse->branch_id
            !== (int) $branch->getKey()
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The selected warehouse does not belong to the selected branch.',
                ],
            ]);
        }

        if ($warehouse->status !== 'active') {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The selected warehouse is inactive.',
                ],
            ]);
        }

        return $warehouse;
    }

    private function resolveSupplier(
        int $supplierId,
    ): Supplier {
        $supplier = Supplier::query()
            ->whereKey($supplierId)
            ->lockForUpdate()
            ->first();

        if (!$supplier instanceof Supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The selected supplier is unavailable.',
                ],
            ]);
        }

        if (!$supplier->isActive()) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The selected supplier is inactive.',
                ],
            ]);
        }

        return $supplier;
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(
        array $lines,
        Branch $branch,
        ?Warehouse $warehouse,
    ): array {
        $builtLines = [];

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each purchase order line must be an object.',
                    ],
                ]);
            }

            $productId = $this->requiredId(
                value: $line['product_id'] ?? null,
                field: "lines.{$index}.product_id",
                message:
                    'The selected product is invalid.',
            );

            $unitId = $this->requiredId(
                value: $line['unit_id'] ?? null,
                field: "lines.{$index}.unit_id",
                message:
                    'The selected unit is invalid.',
            );

            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (!$product instanceof Product) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'The selected product is unavailable.',
                    ],
                ]);
            }

            if (
                !$product->isActive()
                || !$product->is_purchasable
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'The selected product is not available for purchasing.',
                    ],
                ]);
            }

            $branchSetting =
                ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where(
                        'branch_id',
                        $branch->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

            if (
                !$branchSetting
                    instanceof ProductBranchSetting
                || !$branchSetting->isActive()
                || !$branchSetting->is_purchasable
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'The selected product is not enabled for purchasing in this branch.',
                    ],
                ]);
            }

            if ($product->isStockItem()) {
                if (!$warehouse instanceof Warehouse) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => [
                            'A receiving warehouse is required when the purchase order contains stock products.',
                        ],
                    ]);
                }

                $warehouseSetting =
                    ProductWarehouseSetting::query()
                        ->where(
                            'product_id',
                            $product->getKey(),
                        )
                        ->where(
                            'branch_id',
                            $branch->getKey(),
                        )
                        ->where(
                            'warehouse_id',
                            $warehouse->getKey(),
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    !$warehouseSetting
                        instanceof ProductWarehouseSetting
                    || !$warehouseSetting->isActive()
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.product_id" => [
                            'The selected stock product is not enabled for the receiving warehouse.',
                        ],
                    ]);
                }
            }

            $unit = Unit::query()
                ->whereKey($unitId)
                ->lockForUpdate()
                ->first();

            if (!$unit instanceof Unit) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_id" => [
                        'The selected unit is unavailable.',
                    ],
                ]);
            }

            if (!$unit->isActive()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_id" => [
                        'The selected unit is inactive.',
                    ],
                ]);
            }

            if (
                (int) $product->base_unit_id
                !== (int) $unit->getKey()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_id" => [
                        'The selected unit must match the product base unit until unit conversion support is introduced.',
                    ],
                ]);
            }

            $calculated =
                $this->calculator->calculateLine(
                    line: $line,
                    lineIndex: $index,
                );

            $this->ensureQuantityMatchesUnitPrecision(
                quantity:
                    $calculated['ordered_quantity'],
                unit: $unit,
                field:
                    "lines.{$index}.ordered_quantity",
            );

            $builtLines[] = [
                'product_id' => $product->getKey(),
                'unit_id' => $unit->getKey(),
                'line_number' => $index + 1,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_type' =>
                    $product->product_type,
                'unit_name' => $unit->name,
                'unit_code' => $unit->code,

                'description' => $this->nullableString(
                    value:
                        $line['description'] ?? null,
                    maximum: 4000,
                    field:
                        "lines.{$index}.description",
                    label: 'line description',
                ),

                'received_quantity' => '0.000000',
                ...$calculated,
            ];
        }

        return $builtLines;
    }

    /**
     * @param array{
     *     branch_id: int,
     *     warehouse_id: int|null,
     *     supplier_id: int,
     *     order_date: string,
     *     expected_delivery_date: string|null,
     *     supplier_reference: string|null,
     *     currency_code: string,
     *     exchange_rate: string,
     *     delivery_address: string|null,
     *     payment_terms_days: int|null,
     *     shipping_amount: string,
     *     other_charges: string,
     *     terms_and_conditions: string|null,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * } $normalized
     *
     * @return array<string, mixed>
     */
    private function headerAttributes(
        array $normalized,
        Tenant $tenant,
        Branch $branch,
        ?Warehouse $warehouse,
        Supplier $supplier,
    ): array {
        return [
            'branch_id' => $branch->getKey(),

            'warehouse_id' =>
                $warehouse?->getKey(),

            'supplier_id' => $supplier->getKey(),

            'order_date' =>
                $normalized['order_date'],

            'expected_delivery_date' =>
                $normalized['expected_delivery_date'],

            'supplier_reference' =>
                $normalized['supplier_reference'],

            'currency_code' =>
                $normalized['currency_code'],

            'exchange_rate' =>
                $normalized['exchange_rate'],

            'supplier_name' => $supplier->name,
            'supplier_code' => $supplier->code,

            'supplier_contact_person' =>
                $supplier->contact_person,

            'supplier_email' => $supplier->email,
            'supplier_phone' => $supplier->phone,

            'supplier_tax_number' =>
                $supplier->tax_number,

            'supplier_address' =>
                $this->supplierAddress($supplier),

            'delivery_address' =>
                $normalized['delivery_address']
                ?? $warehouse?->address
                ?? $branch->address
                ?? $tenant->address,

            'payment_terms_days' =>
                $normalized['payment_terms_days']
                ?? (int) $supplier->payment_terms_days,

            'terms_and_conditions' =>
                $normalized['terms_and_conditions'],

            'notes' => $normalized['notes'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        PurchaseOrder $purchaseOrder,
        array $lines,
    ): void {
        PurchaseOrderLine::query()
            ->where(
                'purchase_order_id',
                $purchaseOrder->getKey(),
            )
            ->lockForUpdate()
            ->get()
            ->each(
                static function (
                    PurchaseOrderLine $line,
                ): void {
                    $line->delete();
                },
            );

        foreach ($lines as $line) {
            $purchaseOrder->lines()->create($line);
        }
    }

    private function validateCurrentConfiguration(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): void {
        $branch = $this->resolveBranch(
            actor: $actor,
            branchId:
                (int) $purchaseOrder->branch_id,
        );

        $warehouse = $this->resolveWarehouse(
            warehouseId:
                $purchaseOrder->warehouse_id !== null
                    ? (int) $purchaseOrder->warehouse_id
                    : null,
            branch: $branch,
        );

        $supplier = $this->resolveSupplier(
            supplierId:
                (int) $purchaseOrder->supplier_id,
        );

        if (
            $supplier->name
                !== $purchaseOrder->supplier_name
            || $supplier->code
                !== $purchaseOrder->supplier_code
        ) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The supplier master record changed after this draft was prepared. Return to edit and save the purchase order again before continuing.',
                ],
            ]);
        }

        $purchaseOrder->loadMissing('lines');

        if ($purchaseOrder->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A purchase order must contain at least one line.',
                ],
            ]);
        }

        foreach (
            $purchaseOrder->lines
            as $index => $line
        ) {
            $product = Product::query()
                ->whereKey($line->product_id)
                ->lockForUpdate()
                ->first();

            if (
                !$product instanceof Product
                || !$product->isActive()
                || !$product->is_purchasable
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'A product on this purchase order is no longer available for purchasing.',
                    ],
                ]);
            }

            if (
                $product->name
                    !== $line->product_name
                || $product->sku
                    !== $line->product_sku
                || $product->product_type
                    !== $line->product_type
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'A product master record changed after this draft was prepared. Return to edit and save the purchase order again before continuing.',
                    ],
                ]);
            }

            $branchSetting =
                ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where(
                        'branch_id',
                        $branch->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

            if (
                !$branchSetting
                    instanceof ProductBranchSetting
                || !$branchSetting->isActive()
                || !$branchSetting->is_purchasable
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => [
                        'A product on this purchase order is no longer enabled for purchasing in the selected branch.',
                    ],
                ]);
            }

            if ($product->isStockItem()) {
                if (!$warehouse instanceof Warehouse) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => [
                            'A receiving warehouse is required when the purchase order contains stock products.',
                        ],
                    ]);
                }

                $warehouseSetting =
                    ProductWarehouseSetting::query()
                        ->where(
                            'product_id',
                            $product->getKey(),
                        )
                        ->where(
                            'branch_id',
                            $branch->getKey(),
                        )
                        ->where(
                            'warehouse_id',
                            $warehouse->getKey(),
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    !$warehouseSetting
                        instanceof ProductWarehouseSetting
                    || !$warehouseSetting->isActive()
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.product_id" => [
                            'A stock product on this purchase order is no longer enabled for the receiving warehouse.',
                        ],
                    ]);
                }
            }

            $unit = Unit::query()
                ->whereKey($line->unit_id)
                ->lockForUpdate()
                ->first();

            if (
                !$unit instanceof Unit
                || !$unit->isActive()
                || (int) $product->base_unit_id
                    !== (int) $unit->getKey()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_id" => [
                        'A unit on this purchase order is no longer valid for its product.',
                    ],
                ]);
            }

            if (
                $unit->name !== $line->unit_name
                || $unit->code !== $line->unit_code
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_id" => [
                        'A unit master record changed after this draft was prepared. Return to edit and save the purchase order again before continuing.',
                    ],
                ]);
            }
        }
    }

    private function ensureEditable(
        PurchaseOrder $purchaseOrder,
    ): void {
        if (
            $this->statusRegistry->isEditable(
                $purchaseOrder->status,
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Only draft purchase orders can be modified or deleted.',
            ],
        ]);
    }

    private function ensureTransition(
        PurchaseOrder $purchaseOrder,
        string $nextStatus,
    ): void {
        if (
            $this->statusRegistry->canTransition(
                currentStatus:
                    $purchaseOrder->status,
                nextStatus: $nextStatus,
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                sprintf(
                    'The purchase order cannot move from %s to %s.',
                    $this->statusRegistry->label(
                        $purchaseOrder->status,
                    ),
                    $this->statusRegistry->label(
                        $nextStatus,
                    ),
                ),
            ],
        ]);
    }

    private function ensureOrderHasNoReceipts(
        PurchaseOrder $purchaseOrder,
    ): void {
        $hasReceivedQuantity =
            PurchaseOrderLine::query()
                ->where(
                    'purchase_order_id',
                    $purchaseOrder->getKey(),
                )
                ->where(
                    'received_quantity',
                    '>',
                    0,
                )
                ->lockForUpdate()
                ->exists();

        if (!$hasReceivedQuantity) {
            return;
        }

        throw ValidationException::withMessages([
            'purchase_order' => [
                'This purchase order cannot be changed because receipt quantities already exist.',
            ],
        ]);
    }

    private function authorizeOrderBranch(
        User $actor,
        PurchaseOrder $purchaseOrder,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey($purchaseOrder->branch_id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->branchAccessService->authorizeBranch(
            user: $actor,
            branch: $branch,
            requireActive: $requireActive,
        );
    }

    private function ensureQuantityMatchesUnitPrecision(
        string $quantity,
        Unit $unit,
        string $field,
    ): void {
        $decimal = BigDecimal::of($quantity);

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

        $allowedPlaces = min(
            max((int) $unit->decimal_places, 0),
            6,
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

    private function supplierAddress(
        Supplier $supplier,
    ): ?string {
        $parts = array_values(
            array_filter(
                [
                    $supplier->address_line_1,
                    $supplier->address_line_2,
                    $supplier->city,
                    $supplier->state,
                    $supplier->postal_code,
                    $supplier->country_code,
                ],
                static fn (mixed $value): bool =>
                    is_string($value)
                    && trim($value) !== '',
            ),
        );

        return $parts === []
            ? null
            : implode(', ', $parts);
    }

    private function allocationKey(
        PurchaseOrder $purchaseOrder,
    ): string {
        return sprintf(
            'purchase-order:%d:%d',
            (int) $purchaseOrder->tenant_id,
            (int) $purchaseOrder->getKey(),
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
        if ((int) $actor->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected user does not belong to the active tenant.',
        );
    }

    private function ensureOrderBelongsToTenant(
        PurchaseOrder $purchaseOrder,
        int $tenantId,
    ): void {
        if (
            (int) $purchaseOrder->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The selected purchase order belongs to another tenant.',
        );
    }

    private function requiredId(
        mixed $value,
        string $field,
        string $message,
    ): int {
        $id = $this->nullableId(
            value: $value,
            field: $field,
            message: $message,
        );

        if ($id !== null) {
            return $id;
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

        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            $timezone,
        );

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

        return $date->format('Y-m-d');
    }

    private function normalizeNullableDate(
        mixed $value,
        string $field,
        string $timezone,
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->normalizeDate(
            value: $value,
            field: $field,
            timezone: $timezone,
        );
    }

    private function normalizeDecimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
        bool $allowZero,
        int $maximumWholeDigits,
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

        $value = trim((string) $value);

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

        $decimal = BigDecimal::of($value);

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
            $decimal = $decimal->toScale(
                $scale,
                RoundingMode::UNNECESSARY,
            );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not contain more than {$scale} decimal places.",
                ],
            ]);
        }

        $maximum = BigDecimal::of(
            str_repeat('9', $maximumWholeDigits)
            . '.'
            . str_repeat('9', $scale),
        );

        if ($decimal->isGreaterThan($maximum)) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} exceeds the supported maximum value.",
                ],
            ]);
        }

        return $decimal->__toString();
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

        if (mb_strlen($value) > $maximum) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not exceed {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }
}
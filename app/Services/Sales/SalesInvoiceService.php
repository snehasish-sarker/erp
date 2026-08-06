<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\SalesInvoiceAccountingGateway;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDispatch;
use App\Models\CustomerDispatchLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDispatchAllocation;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\CustomerBalanceService;
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

final class SalesInvoiceService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly SalesInvoiceAccountingGateway $accountingGateway,
        private readonly CustomerBalanceService $customerBalanceService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): SalesInvoice {
        $tenant = $this->tenantContext->tenant();
        $this->ensureActorTenant($actor, (int) $tenant->getKey());
        $normalized = $this->normalizeInput($data, $tenant);

        return DB::transaction(
            function () use ($normalized, $actor, $tenant): SalesInvoice {
                $salesOrder = $this->lockSalesOrder(
                    salesOrderId: $normalized['sales_order_id'],
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $existingDraft = SalesInvoice::query()
                    ->where('sales_order_id', $salesOrder->getKey())
                    ->whereNotNull('draft_key')
                    ->lockForUpdate()
                    ->first();

                if ($existingDraft instanceof SalesInvoice) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => [
                            'This Sales Order already has an editable Sales Invoice draft.',
                        ],
                    ]);
                }

                $customer = $this->lockCustomer($salesOrder);
                $built = $this->buildInvoiceLines(
                    salesOrder: $salesOrder,
                    inputLines: $normalized['lines'],
                    excludingInvoiceId: null,
                );

                $totals = $this->totals(
                    lines: $built['lines'],
                    shippingAmount: $normalized['shipping_amount'],
                    otherCharges: $normalized['other_charges'],
                );

                $this->ensureCreditLimit(
                    customer: $customer,
                    invoiceBaseAmount: BigDecimal::of($totals['total_amount'])
                        ->multipliedBy(
                            BigDecimal::of((string) $salesOrder->exchange_rate),
                        ),
                );

                $invoice = SalesInvoice::query()->create([
                    'branch_id' => $salesOrder->branch_id,
                    'customer_id' => $salesOrder->customer_id,
                    'sales_order_id' => $salesOrder->getKey(),
                    'document_number_allocation_id' => null,
                    'invoice_number' => null,
                    'draft_key' => $this->draftKey($salesOrder),
                    'invoice_date' => $normalized['invoice_date'],
                    'posting_date' => $normalized['posting_date'],
                    'due_date' => $normalized['due_date'],
                    'sales_order_number' => (string) $salesOrder->document_number,
                    'customer_name' => $salesOrder->customer_name,
                    'customer_code' => $salesOrder->customer_code,
                    'customer_type' => $salesOrder->customer_type,
                    'customer_contact_person' => $salesOrder->customer_contact_person,
                    'customer_email' => $salesOrder->customer_email,
                    'customer_phone' => $salesOrder->customer_phone,
                    'customer_tax_number' => $salesOrder->customer_tax_number,
                    'billing_address' => $normalized['billing_address']
                        ?? $salesOrder->billing_address,
                    'shipping_address' => $normalized['shipping_address']
                        ?? $salesOrder->shipping_address,
                    'payment_terms_days' => $salesOrder->payment_terms_days,
                    'credit_limit_snapshot' => $salesOrder->credit_limit_snapshot,
                    'currency_code' => $salesOrder->currency_code,
                    'exchange_rate' => $salesOrder->exchange_rate,
                    ...$totals,
                    'notes' => $normalized['notes'],
                    'status' => 'draft',
                    'revision' => 1,
                    'created_by_user_id' => $actor->getKey(),
                ]);

                $this->replaceLinesAndAllocations(
                    salesInvoice: $invoice,
                    built: $built,
                );

                return $this->loadInvoice($invoice);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        SalesInvoice $salesInvoice,
        array $data,
        User $actor,
    ): SalesInvoice {
        $tenant = $this->tenantContext->tenant();
        $this->ensureActorTenant($actor, (int) $tenant->getKey());
        $this->ensureInvoiceTenant($salesInvoice, (int) $tenant->getKey());
        $normalized = $this->normalizeInput($data, $tenant);

        return DB::transaction(
            function () use (
                $salesInvoice,
                $normalized,
                $actor,
            ): SalesInvoice {
                $lockedInvoice = SalesInvoice::query()
                    ->whereKey($salesInvoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureEditable($lockedInvoice);

                if (
                    (int) $lockedInvoice->sales_order_id
                    !== $normalized['sales_order_id']
                ) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => [
                            'The Sales Order cannot be changed after the invoice draft is created.',
                        ],
                    ]);
                }

                $salesOrder = $this->lockSalesOrder(
                    salesOrderId: (int) $lockedInvoice->sales_order_id,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $customer = $this->lockCustomer($salesOrder);
                $built = $this->buildInvoiceLines(
                    salesOrder: $salesOrder,
                    inputLines: $normalized['lines'],
                    excludingInvoiceId: (int) $lockedInvoice->getKey(),
                );

                $totals = $this->totals(
                    lines: $built['lines'],
                    shippingAmount: $normalized['shipping_amount'],
                    otherCharges: $normalized['other_charges'],
                );

                $this->ensureCreditLimit(
                    customer: $customer,
                    invoiceBaseAmount: BigDecimal::of($totals['total_amount'])
                        ->multipliedBy(
                            BigDecimal::of((string) $salesOrder->exchange_rate),
                        ),
                );

                $lockedInvoice->fill([
                    'invoice_date' => $normalized['invoice_date'],
                    'posting_date' => $normalized['posting_date'],
                    'due_date' => $normalized['due_date'],
                    'billing_address' => $normalized['billing_address']
                        ?? $salesOrder->billing_address,
                    'shipping_address' => $normalized['shipping_address']
                        ?? $salesOrder->shipping_address,
                    ...$totals,
                    'notes' => $normalized['notes'],
                    'revision' => (int) $lockedInvoice->revision + 1,
                ]);

                $lockedInvoice->save();

                $this->replaceLinesAndAllocations(
                    salesInvoice: $lockedInvoice,
                    built: $built,
                );

                return $this->loadInvoice($lockedInvoice->refresh());
            },
            attempts: 5,
        );
    }

    public function delete(
        SalesInvoice $salesInvoice,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureInvoiceTenant($salesInvoice, $tenantId);

        DB::transaction(
            function () use ($salesInvoice, $actor): void {
                $lockedInvoice = SalesInvoice::query()
                    ->whereKey($salesInvoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeBranch(
                    branchId: (int) $lockedInvoice->branch_id,
                    actor: $actor,
                    requireActive: false,
                );

                $this->ensureEditable($lockedInvoice);
                $lockedInvoice->delete();
            },
            attempts: 5,
        );
    }

    public function post(
        SalesInvoice $salesInvoice,
        User $actor,
    ): SalesInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureInvoiceTenant($salesInvoice, $tenantId);

        return DB::transaction(
            function () use ($salesInvoice, $actor, $tenant): SalesInvoice {
                $lockedInvoice = SalesInvoice::query()
                    ->whereKey($salesInvoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$lockedInvoice->canBePosted()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Sales Invoice can be posted.',
                        ],
                    ]);
                }

                $salesOrder = $this->lockSalesOrder(
                    salesOrderId: (int) $lockedInvoice->sales_order_id,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $customer = $this->lockCustomer($salesOrder);

                $savedLines = SalesInvoiceLine::query()
                    ->where('sales_invoice_id', $lockedInvoice->getKey())
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                $inputLines = $savedLines
                    ->map(
                        static fn (SalesInvoiceLine $line): array => [
                            'sales_order_line_id' => $line->sales_order_line_id,
                            'invoiced_quantity' => (string) $line->invoiced_quantity,
                            'description' => $line->description,
                        ],
                    )
                    ->values()
                    ->all();

                $built = $this->buildInvoiceLines(
                    salesOrder: $salesOrder,
                    inputLines: $inputLines,
                    excludingInvoiceId: (int) $lockedInvoice->getKey(),
                );

                $totals = $this->totals(
                    lines: $built['lines'],
                    shippingAmount: BigDecimal::of((string) $lockedInvoice->shipping_amount),
                    otherCharges: BigDecimal::of((string) $lockedInvoice->other_charges),
                );

                $this->ensureCreditLimit(
                    customer: $customer,
                    invoiceBaseAmount: BigDecimal::of($totals['total_amount'])
                        ->multipliedBy(
                            BigDecimal::of((string) $lockedInvoice->exchange_rate),
                        ),
                );

                $lockedInvoice->fill($totals);
                $lockedInvoice->save();

                $this->replaceLinesAndAllocations(
                    salesInvoice: $lockedInvoice,
                    built: $built,
                );

                if (!$lockedInvoice->hasInvoiceNumber()) {
                    $allocation = $this->documentNumberService->allocate(
                        documentType: 'sales_invoice',
                        branchId: (int) $lockedInvoice->branch_id,
                        idempotencyKey: $this->numberAllocationKey($lockedInvoice),
                        allocatableType: SalesInvoice::class,
                        allocatableId: (int) $lockedInvoice->getKey(),
                        allocatedAt: CarbonImmutable::parse(
                            $lockedInvoice->invoice_date,
                            $tenant->timezone,
                        ),
                    );

                    $lockedInvoice->document_number_allocation_id = $allocation->getKey();
                    $lockedInvoice->invoice_number = $allocation->number;
                    $lockedInvoice->save();
                }

                $accountingPeriod = $this->accountingPeriodService
                    ->lockOpenPeriod($lockedInvoice->posting_date);

                $accountingReference = $this->accountingGateway->post(
                    salesInvoice: $lockedInvoice->load('lines'),
                    accountingPeriod: $accountingPeriod,
                    actor: $actor,
                );

                $this->applyInvoicedQuantities(
                    salesOrder: $salesOrder,
                    invoiceLines: SalesInvoiceLine::query()
                        ->where('sales_invoice_id', $lockedInvoice->getKey())
                        ->orderBy('line_number')
                        ->lockForUpdate()
                        ->get(),
                    reverse: false,
                );

                $lockedInvoice->status = 'posted';
                $lockedInvoice->draft_key = null;
                $lockedInvoice->posted_by_user_id = $actor->getKey();
                $lockedInvoice->posted_at = now();
                $lockedInvoice->accounting_posting_reference = $accountingReference;
                $lockedInvoice->save();

                $this->synchronizeSalesOrderStatus($salesOrder);

                return $this->loadInvoice($lockedInvoice->refresh());
            },
            attempts: 5,
        );
    }

    public function reverse(
        SalesInvoice $salesInvoice,
        string $reversalPostingDate,
        string $reason,
        User $actor,
    ): SalesInvoice {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();
        $this->ensureActorTenant($actor, $tenantId);
        $this->ensureInvoiceTenant($salesInvoice, $tenantId);

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reversal_reason' => [
                    'A reversal reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        $reversalDate = $this->date(
            value: $reversalPostingDate,
            field: 'reversal_posting_date',
            timezone: $tenant->timezone,
        );

        return DB::transaction(
            function () use (
                $salesInvoice,
                $actor,
                $reason,
                $reversalDate,
                $tenant,
            ): SalesInvoice {
                $lockedInvoice = SalesInvoice::query()
                    ->whereKey($salesInvoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$lockedInvoice->canBeReversed()) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a posted Sales Invoice can be reversed.',
                        ],
                    ]);
                }

                $this->authorizeBranch(
                    branchId: (int) $lockedInvoice->branch_id,
                    actor: $actor,
                    requireActive: true,
                );

                $salesOrder = SalesOrder::query()
                    ->whereKey($lockedInvoice->sales_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $invoiceLines = SalesInvoiceLine::query()
                    ->where('sales_invoice_id', $lockedInvoice->getKey())
                    ->orderBy('line_number')
                    ->lockForUpdate()
                    ->get();

                $date = CarbonImmutable::parse(
                    $reversalDate,
                    $tenant->timezone,
                );

                $period = $this->accountingPeriodService
                    ->lockOpenPeriod($date);

                $reference = $this->accountingGateway->reverse(
                    salesInvoice: $lockedInvoice->load('lines'),
                    accountingPeriod: $period,
                    reversalPostingDate: $date,
                    reason: $reason,
                    actor: $actor,
                );

                $this->applyInvoicedQuantities(
                    salesOrder: $salesOrder,
                    invoiceLines: $invoiceLines,
                    reverse: true,
                );

                $lockedInvoice->status = 'reversed';
                $lockedInvoice->reversal_posting_date = $reversalDate;
                $lockedInvoice->reversed_by_user_id = $actor->getKey();
                $lockedInvoice->reversed_at = now();
                $lockedInvoice->reversal_reason = $reason;
                $lockedInvoice->accounting_reversal_reference = $reference;
                $lockedInvoice->save();

                $this->synchronizeSalesOrderStatus($salesOrder);

                return $this->loadInvoice($lockedInvoice->refresh());
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     sales_order_id: int,
     *     invoice_date: string,
     *     posting_date: string,
     *     due_date: string,
     *     billing_address: string|null,
     *     shipping_address: string|null,
     *     shipping_amount: BigDecimal,
     *     other_charges: BigDecimal,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $salesOrderId = filter_var(
            $data['sales_order_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($salesOrderId === false) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order is invalid.',
                ],
            ]);
        }

        $lines = $data['lines'] ?? null;

        if (!is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A Sales Invoice must contain at least one line.',
                ],
            ]);
        }

        return [
            'sales_order_id' => $salesOrderId,

            'invoice_date' => $this->date(
                $data['invoice_date'] ?? null,
                'invoice_date',
                $tenant->timezone,
            ),

            'posting_date' => $this->date(
                $data['posting_date'] ?? null,
                'posting_date',
                $tenant->timezone,
            ),

            'due_date' => $this->date(
                $data['due_date'] ?? null,
                'due_date',
                $tenant->timezone,
            ),

            'billing_address' => $this->text(
                $data['billing_address'] ?? null,
                4000,
            ),

            'shipping_address' => $this->text(
                $data['shipping_address'] ?? null,
                4000,
            ),

            'shipping_amount' => $this->quantity(
                $data['shipping_amount'] ?? '0',
                'shipping_amount',
                true,
            ),

            'other_charges' => $this->quantity(
                $data['other_charges'] ?? '0',
                'other_charges',
                true,
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
     *
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     allocations: array<int, list<array<string, mixed>>>
     * }
     */
    private function buildInvoiceLines(
        SalesOrder $salesOrder,
        array $inputLines,
        ?int $excludingInvoiceId,
    ): array {
        $orderLines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->getKey())
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $dispatchLines = CustomerDispatchLine::query()
            ->select('customer_dispatch_lines.*')
            ->join(
                'customer_dispatches',
                'customer_dispatches.id',
                '=',
                'customer_dispatch_lines.customer_dispatch_id',
            )
            ->where('customer_dispatches.sales_order_id', $salesOrder->getKey())
            ->where('customer_dispatches.status', 'posted')
            ->whereNull('customer_dispatches.deleted_at')
            ->orderBy('customer_dispatches.dispatch_date')
            ->orderBy('customer_dispatches.id')
            ->orderBy('customer_dispatch_lines.id')
            ->lockForUpdate()
            ->get()
            ->groupBy('sales_order_line_id');

        $usedByDispatchLine = SalesInvoiceDispatchAllocation::query()
            ->join(
                'sales_invoice_lines',
                'sales_invoice_lines.id',
                '=',
                'sales_invoice_dispatch_allocations.sales_invoice_line_id',
            )
            ->join(
                'sales_invoices',
                'sales_invoices.id',
                '=',
                'sales_invoice_lines.sales_invoice_id',
            )
            ->where('sales_invoices.sales_order_id', $salesOrder->getKey())
            ->whereIn('sales_invoices.status', ['draft', 'posted'])
            ->whereNull('sales_invoices.deleted_at')
            ->when(
                $excludingInvoiceId !== null,
                static fn ($query) => $query->where(
                    'sales_invoices.id',
                    '!=',
                    $excludingInvoiceId,
                ),
            )
            ->selectRaw(
                'sales_invoice_dispatch_allocations.customer_dispatch_line_id, SUM(sales_invoice_dispatch_allocations.allocated_quantity) AS used_quantity',
            )
            ->groupBy(
                'sales_invoice_dispatch_allocations.customer_dispatch_line_id',
            )
            ->pluck('used_quantity', 'customer_dispatch_line_id');

        $builtLines = [];
        $allocations = [];
        $seen = [];

        foreach (array_values($inputLines) as $index => $inputLine) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each invoice line must be an object.',
                    ],
                ]);
            }

            $lineId = filter_var(
                $inputLine['sales_order_line_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ],
            );

            if ($lineId === false || isset($seen[$lineId])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The selected Sales Order line is invalid or duplicated.',
                    ],
                ]);
            }

            $seen[$lineId] = true;
            $orderLine = $orderLines->get($lineId);

            if (!$orderLine instanceof SalesOrderLine) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The selected line does not belong to this Sales Order.',
                    ],
                ]);
            }

            $quantity = $this->quantity(
                $inputLine['invoiced_quantity'] ?? null,
                "lines.{$index}.invoiced_quantity",
                true,
            );

            if ($quantity->isZero()) {
                continue;
            }

            $remainingDispatched = BigDecimal::of(
                (string) $orderLine->dispatched_quantity,
            )->minus(
                BigDecimal::of(
                    (string) $orderLine->invoiced_quantity,
                ),
            );

            if ($remainingDispatched->isLessThan($quantity)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.invoiced_quantity" => [
                        "Only {$this->decimal($remainingDispatched)} dispatched quantity remains invoiceable for {$orderLine->product_name}.",
                    ],
                ]);
            }

            $sources = $dispatchLines->get($lineId, collect());
            $remainingToAllocate = $quantity;
            $lineAllocations = [];
            $lineCost = BigDecimal::zero();

            foreach ($sources as $dispatchLine) {
                if (!$dispatchLine instanceof CustomerDispatchLine) {
                    continue;
                }

                $used = BigDecimal::of(
                    (string) ($usedByDispatchLine[$dispatchLine->getKey()] ?? '0'),
                );

                $available = BigDecimal::of(
                    (string) $dispatchLine->dispatched_quantity,
                )->minus($used);

                if (!$available->isGreaterThan(BigDecimal::zero())) {
                    continue;
                }

                $take = $available->isLessThan($remainingToAllocate)
                    ? $available
                    : $remainingToAllocate;

                $unitCost = BigDecimal::of(
                    (string) $dispatchLine->unit_cost,
                );

                $cost = $take
                    ->multipliedBy($unitCost)
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    );

                $lineAllocations[] = [
                    'customer_dispatch_line_id' => $dispatchLine->getKey(),
                    'allocated_quantity' => $this->decimal($take),
                    'unit_cost' => $this->decimal($unitCost),
                    'total_cost' => $this->decimal($cost),
                ];

                $lineCost = $lineCost->plus($cost);
                $remainingToAllocate = $remainingToAllocate->minus($take);

                if ($remainingToAllocate->isZero()) {
                    break;
                }
            }

            if (!$remainingToAllocate->isZero()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.invoiced_quantity" => [
                        "Posted dispatch sources for {$orderLine->product_name} are insufficient or already assigned to another invoice.",
                    ],
                ]);
            }

            $orderedQuantity = BigDecimal::of(
                (string) $orderLine->ordered_quantity,
            );

            $gross = $quantity
                ->multipliedBy(
                    BigDecimal::of((string) $orderLine->unit_price),
                )
                ->toScale(self::SCALE, RoundingMode::HALF_UP);

            $discount = $orderedQuantity->isZero()
                ? BigDecimal::zero()
                : BigDecimal::of((string) $orderLine->discount_amount)
                    ->multipliedBy($quantity)
                    ->dividedBy(
                        $orderedQuantity,
                        self::SCALE,
                        RoundingMode::HALF_UP,
                    );

            $taxable = $gross->minus($discount);

            $tax = $taxable
                ->multipliedBy(
                    BigDecimal::of((string) $orderLine->tax_rate),
                )
                ->dividedBy(
                    BigDecimal::of('100'),
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );

            $lineTotal = $taxable->plus($tax);
            $lineNumber = count($builtLines) + 1;

            $builtLines[] = [
                'sales_order_line_id' => $orderLine->getKey(),
                'product_id' => $orderLine->product_id,
                'unit_id' => $orderLine->unit_id,
                'line_number' => $lineNumber,
                'product_name' => $orderLine->product_name,
                'product_sku' => $orderLine->product_sku,
                'product_type' => $orderLine->product_type,
                'unit_name' => $orderLine->unit_name,
                'unit_code' => $orderLine->unit_code,
                'description' => $this->text(
                    $inputLine['description'] ?? $orderLine->description,
                    4000,
                ),
                'invoiced_quantity' => $this->decimal($quantity),
                'unit_price' => (string) $orderLine->unit_price,
                'gross_amount' => $this->decimal($gross),
                'discount_amount' => $this->decimal($discount),
                'tax_rate' => (string) $orderLine->tax_rate,
                'tax_amount' => $this->decimal($tax),
                'line_total' => $this->decimal($lineTotal),
                'unit_cost' => $quantity->isZero()
                    ? '0.000000'
                    : $this->decimal(
                        $lineCost->dividedBy(
                            $quantity,
                            self::SCALE,
                            RoundingMode::HALF_UP,
                        ),
                    ),
                'total_cost' => $this->decimal($lineCost),
            ];

            $allocations[$lineNumber] = $lineAllocations;
        }

        if ($builtLines === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Enter an invoice quantity greater than zero for at least one line.',
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
     *
     * @return array<string, string>
     */
    private function totals(
        array $lines,
        BigDecimal $shippingAmount,
        BigDecimal $otherCharges,
    ): array {
        $subtotal = BigDecimal::zero();
        $discount = BigDecimal::zero();
        $tax = BigDecimal::zero();
        $cost = BigDecimal::zero();

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus(
                BigDecimal::of((string) $line['gross_amount']),
            );

            $discount = $discount->plus(
                BigDecimal::of((string) $line['discount_amount']),
            );

            $tax = $tax->plus(
                BigDecimal::of((string) $line['tax_amount']),
            );

            $cost = $cost->plus(
                BigDecimal::of((string) $line['total_cost']),
            );
        }

        $total = $subtotal
            ->minus($discount)
            ->plus($tax)
            ->plus($shippingAmount)
            ->plus($otherCharges);

        return [
            'subtotal' => $this->decimal($subtotal),
            'discount_amount' => $this->decimal($discount),
            'tax_amount' => $this->decimal($tax),
            'shipping_amount' => $this->decimal($shippingAmount),
            'other_charges' => $this->decimal($otherCharges),
            'total_amount' => $this->decimal($total),
            'total_cost' => $this->decimal($cost),
        ];
    }

    /**
     * @param array{
     *     lines: list<array<string, mixed>>,
     *     allocations: array<int, list<array<string, mixed>>>
     * } $built
     */
    private function replaceLinesAndAllocations(
        SalesInvoice $salesInvoice,
        array $built,
    ): void {
        SalesInvoiceLine::query()
            ->where('sales_invoice_id', $salesInvoice->getKey())
            ->lockForUpdate()
            ->get()
            ->each(
                static function (SalesInvoiceLine $line): void {
                    $line->delete();
                },
            );

        foreach ($built['lines'] as $lineData) {
            $line = $salesInvoice->lines()->create($lineData);

            foreach (
                $built['allocations'][(int) $lineData['line_number']] ?? []
                as $allocation
            ) {
                $line->dispatchAllocations()->create($allocation);
            }
        }
    }

    /**
     * @param EloquentCollection<int, SalesInvoiceLine> $invoiceLines
     */
    private function applyInvoicedQuantities(
        SalesOrder $salesOrder,
        EloquentCollection $invoiceLines,
        bool $reverse,
    ): void {
        $orderLines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->getKey())
            ->whereIn(
                'id',
                $invoiceLines->pluck('sales_order_line_id')->all(),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($invoiceLines as $invoiceLine) {
            $orderLine = $orderLines->get(
                (int) $invoiceLine->sales_order_line_id,
            );

            if (!$orderLine instanceof SalesOrderLine) {
                throw new LogicException(
                    'A Sales Order line required for invoice posting was not found.',
                );
            }

            $current = BigDecimal::of(
                (string) $orderLine->invoiced_quantity,
            );

            $quantity = BigDecimal::of(
                (string) $invoiceLine->invoiced_quantity,
            );

            if ($reverse) {
                if ($current->isLessThan($quantity)) {
                    throw new LogicException(
                        'Sales Order invoiced quantity is lower than the invoice reversal quantity.',
                    );
                }

                $next = $current->minus($quantity);
            } else {
                $maximum = BigDecimal::of(
                    (string) $orderLine->dispatched_quantity,
                );

                if ($current->plus($quantity)->isGreaterThan($maximum)) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            "Invoice quantity for {$orderLine->product_name} exceeds its posted dispatch quantity.",
                        ],
                    ]);
                }

                $next = $current->plus($quantity);
            }

            $orderLine->invoiced_quantity = $this->decimal($next);
            $orderLine->save();
        }
    }

    private function synchronizeSalesOrderStatus(
        SalesOrder $salesOrder,
    ): void {
        $lines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->getKey())
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        $anyInvoiced = false;
        $allInvoiced = !$lines->isEmpty();
        $anyDispatched = false;
        $allDispatched = !$lines->isEmpty();
        $anyAllocated = false;
        $allAllocated = !$lines->isEmpty();

        foreach ($lines as $line) {
            $ordered = BigDecimal::of((string) $line->ordered_quantity);
            $allocated = BigDecimal::of((string) $line->allocated_quantity);
            $dispatched = BigDecimal::of((string) $line->dispatched_quantity);
            $invoiced = BigDecimal::of((string) $line->invoiced_quantity);

            $anyInvoiced = $anyInvoiced
                || $invoiced->isGreaterThan(
                    BigDecimal::zero(),
                );

            $allInvoiced = $allInvoiced
                && !$invoiced->isLessThan($ordered);

            $anyDispatched = $anyDispatched
                || $dispatched->isGreaterThan(
                    BigDecimal::zero(),
                );

            $allDispatched = $allDispatched
                && !$dispatched->isLessThan($ordered);

            $anyAllocated = $anyAllocated
                || $allocated->isGreaterThan(
                    BigDecimal::zero(),
                );

            $allAllocated = $allAllocated
                && !$allocated->isLessThan($ordered);
        }

        $salesOrder->status = $allInvoiced
            ? 'invoiced'
            : (
                $anyInvoiced
                    ? 'partially_invoiced'
                    : (
                        $allDispatched
                            ? 'dispatched'
                            : (
                                $anyDispatched
                                    ? 'partially_dispatched'
                                    : (
                                        $allAllocated
                                            ? 'allocated'
                                            : (
                                                $anyAllocated
                                                    ? 'partially_allocated'
                                                    : 'approved'
                                            )
                                    )
                            )
                    )
            );

        $salesOrder->save();
    }

    private function lockSalesOrder(
        int $salesOrderId,
        User $actor,
        bool $requireActiveBranch,
    ): SalesOrder {
        $salesOrder = SalesOrder::query()
            ->whereKey($salesOrderId)
            ->lockForUpdate()
            ->first();

        if (!$salesOrder instanceof SalesOrder) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order is unavailable.',
                ],
            ]);
        }

        $this->authorizeBranch(
            branchId: (int) $salesOrder->branch_id,
            actor: $actor,
            requireActive: $requireActiveBranch,
        );

        if (
            !in_array(
                $salesOrder->status,
                [
                    'partially_dispatched',
                    'dispatched',
                    'partially_invoiced',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order does not have posted dispatch quantity available for invoicing.',
                ],
            ]);
        }

        return $salesOrder;
    }

    private function lockCustomer(
        SalesOrder $salesOrder,
    ): Customer {
        $customer = Customer::query()
            ->whereKey($salesOrder->customer_id)
            ->lockForUpdate()
            ->first();

        if (
            !$customer instanceof Customer
            || !$customer->isActive()
        ) {
            throw ValidationException::withMessages([
                'customer_id' => [
                    'The Sales Order customer is inactive or unavailable.',
                ],
            ]);
        }

        return $customer;
    }

    private function ensureCreditLimit(
        Customer $customer,
        BigDecimal $invoiceBaseAmount,
    ): void {
        $creditLimit = BigDecimal::of(
            (string) $customer->credit_limit,
        );

        if (!$creditLimit->isGreaterThan(BigDecimal::zero())) {
            return;
        }

        $outstanding = BigDecimal::of(
            $this->customerBalanceService->baseOutstanding(
                $customer,
            ),
        );

        $newExposure = $outstanding->plus(
            $invoiceBaseAmount,
        );

        if ($newExposure->isGreaterThan($creditLimit)) {
            throw ValidationException::withMessages([
                'customer_id' => [
                    sprintf(
                        'Posting this invoice would increase customer credit exposure to %s, above the configured limit of %s.',
                        $this->decimal($newExposure),
                        $this->decimal($creditLimit),
                    ),
                ],
            ]);
        }
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

    private function loadInvoice(
        SalesInvoice $salesInvoice,
    ): SalesInvoice {
        return $salesInvoice->load([
            'branch:id,name,code,status',
            'customer:id,name,code,status',
            'salesOrder:id,document_number,status',
            'lines.dispatchAllocations.customerDispatchLine.dispatch:id,dispatch_number,dispatch_date,status',
            'createdBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'openItem',
        ]);
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The selected user does not belong to the active tenant.',
            );
        }
    }

    private function ensureInvoiceTenant(
        SalesInvoice $salesInvoice,
        int $tenantId,
    ): void {
        if ((int) $salesInvoice->tenant_id !== $tenantId) {
            throw new LogicException(
                'The selected Sales Invoice belongs to another tenant.',
            );
        }
    }

    private function ensureEditable(
        SalesInvoice $salesInvoice,
    ): void {
        if (!$salesInvoice->canBeEdited()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only a draft Sales Invoice can be modified or deleted.',
                ],
            ]);
        }
    }

    private function draftKey(
        SalesOrder $salesOrder,
    ): string {
        return sprintf(
            'sales-order:%d:sales-invoice-draft',
            (int) $salesOrder->getKey(),
        );
    }

    private function numberAllocationKey(
        SalesInvoice $salesInvoice,
    ): string {
        return sprintf(
            'sales-invoice:%d:%d',
            (int) $salesInvoice->tenant_id,
            (int) $salesInvoice->getKey(),
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

        return $value;
    }

    private function text(
        mixed $value,
        int $maximum,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'sales_invoice' => [
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
                'sales_invoice' => [
                    "A Sales Invoice text field exceeds {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function quantity(
        mixed $value,
        string $field,
        bool $allowZero,
    ): BigDecimal {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be a valid number.',
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
                    'The value must be a non-negative number.',
                ],
            ]);
        }

        try {
            $quantity = BigDecimal::of($value)
                ->toScale(
                    self::SCALE,
                    RoundingMode::UNNECESSARY,
                );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    'The value may not exceed 6 decimal places.',
                ],
            ]);
        }

        if (
            !$allowZero
            && !$quantity->isGreaterThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be greater than zero.',
                ],
            ]);
        }

        return $quantity;
    }

    private function decimal(
        BigDecimal $value,
    ): string {
        return $value->toScale(
            self::SCALE,
            RoundingMode::HALF_UP,
        )->__toString();
    }
}
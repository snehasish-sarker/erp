<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AllocateSalesOrderRequest;
use App\Http\Requests\Sales\ReleaseSalesOrderAllocationRequest;
use App\Models\InventoryReservation;
use App\Models\SalesOrder;
use App\Models\SalesOrderAllocation;
use App\Models\SalesOrderAllocationLine;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\Sales\SalesOrderAllocationService;
use App\Support\Responses\CommonResponseService;
use App\Support\Sales\SalesOrderStatusRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SalesOrderAllocationController extends Controller
{
    public function __construct(
        private readonly SalesOrderAllocationService $allocationService,
        private readonly InventoryAvailabilityService $availabilityService,
        private readonly SalesOrderStatusRegistry $statusRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function show(
        Request $request,
        SalesOrder $salesOrder,
    ): Response {
        Gate::authorize(
            'viewAllocation',
            $salesOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $salesOrder->load([
            'branch:id,name,code,status',

            'warehouse:id,branch_id,name,code,status',

            'customer:id,name,code,status',

            'lines' =>
                static function ($query): void {
                    $query->orderBy(
                        'line_number',
                    );
                },
        ]);

        $availability = collect(
            $this->availabilityService
                ->forSalesOrder(
                    $salesOrder,
                ),
        )->keyBy(
            'sales_order_line_id',
        );

        $activeAllocation =
            SalesOrderAllocation::query()
                ->where(
                    'sales_order_id',
                    $salesOrder->getKey(),
                )
                ->whereNotNull('active_key')
                ->where('status', 'active')
                ->with([
                    'lines.reservation',
                    'createdBy:id,name',
                    'warehouse:id,name,code',
                ])
                ->first();

        $history =
            SalesOrderAllocation::query()
                ->where(
                    'sales_order_id',
                    $salesOrder->getKey(),
                )
                ->with([
                    'createdBy:id,name',
                    'releasedBy:id,name',
                ])
                ->orderByDesc('revision')
                ->limit(20)
                ->get()
                ->map(
                    fn (
                        SalesOrderAllocation $allocation,
                    ): array =>
                        $this
                            ->allocationHistoryData(
                                $allocation,
                            ),
                )
                ->values()
                ->all();

        return Inertia::render(
            'SalesOrders/Allocation',
            [
                'salesOrder' => [
                    'id' =>
                        (int) $salesOrder
                            ->getKey(),

                    'document_number' =>
                        $salesOrder
                            ->document_number,

                    'status' =>
                        $salesOrder->status,

                    'status_label' =>
                        $this->statusRegistry
                            ->label(
                                $salesOrder
                                    ->status,
                            ),

                    'order_date' =>
                        $salesOrder
                            ->order_date
                            ?->format('Y-m-d'),

                    'requested_delivery_date' =>
                        $salesOrder
                            ->requested_delivery_date
                            ?->format('Y-m-d'),

                    'customer_name' =>
                        $salesOrder
                            ->customer_name,

                    'customer_code' =>
                        $salesOrder
                            ->customer_code,

                    'branch' =>
                        $salesOrder->branch
                            !== null
                                ? [
                                    'id' =>
                                        (int) $salesOrder
                                            ->branch
                                            ->getKey(),

                                    'name' =>
                                        $salesOrder
                                            ->branch
                                            ->name,

                                    'code' =>
                                        $salesOrder
                                            ->branch
                                            ->code,
                                ]
                                : null,

                    'warehouse' =>
                        $salesOrder->warehouse
                            !== null
                                ? [
                                    'id' =>
                                        (int) $salesOrder
                                            ->warehouse
                                            ->getKey(),

                                    'name' =>
                                        $salesOrder
                                            ->warehouse
                                            ->name,

                                    'code' =>
                                        $salesOrder
                                            ->warehouse
                                            ->code,
                                ]
                                : null,

                    'lines' =>
                        $salesOrder->lines
                            ->map(
                                fn (
                                    SalesOrderLine $line,
                                ): array =>
                                    $this->lineData(
                                        line: $line,

                                        availability:
                                            $availability,
                                    ),
                            )
                            ->values()
                            ->all(),

                    'can' => [
                        'allocate' =>
                            $actor->can(
                                'allocate',
                                $salesOrder,
                            ),

                        'release' =>
                            $actor->can(
                                'releaseAllocation',
                                $salesOrder,
                            )
                            && $activeAllocation
                                instanceof SalesOrderAllocation,
                    ],
                ],

                'activeAllocation' =>
                    $activeAllocation
                        instanceof SalesOrderAllocation
                            ? $this
                                ->activeAllocationData(
                                    $activeAllocation,
                                )
                            : null,

                'history' => $history,
            ],
        );
    }

    public function store(
        AllocateSalesOrderRequest $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'allocate',
            $salesOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $allocation =
            $this->allocationService
                ->allocate(
                    salesOrder:
                        $salesOrder,

                    data:
                        $request->validated(),

                    actor:
                        $actor,
                );

        return $this->responseService
            ->success(
                message: sprintf(
                    'Sales Order allocation revision %d saved successfully.',
                    (int) $allocation
                        ->revision,
                ),

                data: [
                    'allocation_id' =>
                        (int) $allocation
                            ->getKey(),

                    'revision' =>
                        (int) $allocation
                            ->revision,

                    'status' =>
                        $allocation->status,
                ],

                redirectTo: route(
                    'sales-orders.allocation.show',
                    $salesOrder,
                ),
            );
    }

    public function release(
        ReleaseSalesOrderAllocationRequest $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'releaseAllocation',
            $salesOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->allocationService
            ->release(
                salesOrder:
                    $salesOrder,

                reason:
                    (string) $request
                        ->validated(
                            'release_reason',
                        ),

                actor:
                    $actor,
            );

        return $this->responseService
            ->success(
                message:
                    'Sales Order allocation released successfully.',

                redirectTo: route(
                    'sales-orders.allocation.show',
                    $salesOrder,
                ),
            );
    }

    /**
     * @param Collection<int, array<string, mixed>> $availability
     *
     * @return array<string, mixed>
     */
    private function lineData(
        SalesOrderLine $line,
        Collection $availability,
    ): array {
        /**
         * @var array<string, mixed>|null $stock
         */
        $stock = $availability->get(
            (int) $line->getKey(),
        );

        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

            'product_id' =>
                (int) $line->product_id,

            'product_name' =>
                $line->product_name,

            'product_sku' =>
                $line->product_sku,

            'product_type' =>
                $line->product_type,

            'unit_name' =>
                $line->unit_name,

            'unit_code' =>
                $line->unit_code,

            'ordered_quantity' =>
                (string) $line
                    ->ordered_quantity,

            'allocated_quantity' =>
                (string) $line
                    ->allocated_quantity,

            'dispatched_quantity' =>
                (string) $line
                    ->dispatched_quantity,

            'invoiced_quantity' =>
                (string) $line
                    ->invoiced_quantity,

            'quantity_on_hand' =>
                (string) (
                    $stock[
                        'quantity_on_hand'
                    ] ?? '0.000000'
                ),

            'quantity_reserved_total' =>
                (string) (
                    $stock[
                        'quantity_reserved_total'
                    ] ?? '0.000000'
                ),

            'quantity_reserved_current_order' =>
                (string) (
                    $stock[
                        'quantity_reserved_current_order'
                    ] ?? '0.000000'
                ),

            'quantity_reserved_other' =>
                (string) (
                    $stock[
                        'quantity_reserved_other'
                    ] ?? '0.000000'
                ),

            'quantity_available_to_order' =>
                (string) (
                    $stock[
                        'quantity_available_to_order'
                    ] ?? '0.000000'
                ),

            'maximum_allocatable_quantity' =>
                (string) (
                    $stock[
                        'maximum_allocatable_quantity'
                    ]
                    ?? $line->ordered_quantity
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeAllocationData(
        SalesOrderAllocation $allocation,
    ): array {
        return [
            'id' =>
                (int) $allocation->getKey(),

            'revision' =>
                (int) $allocation->revision,

            'status' =>
                $allocation->status,

            'notes' =>
                $allocation->notes,

            'created_at' =>
                $allocation
                    ->created_at
                    ?->toIso8601String(),

            'created_by' =>
                $allocation->createdBy
                    !== null
                        ? [
                            'id' =>
                                (int) $allocation
                                    ->createdBy
                                    ->getKey(),

                            'name' =>
                                $allocation
                                    ->createdBy
                                    ->name,
                        ]
                        : null,

            'lines' =>
                $allocation->lines
                    ->map(
                        fn (
                            SalesOrderAllocationLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'sales_order_line_id' =>
                                (int) $line
                                    ->sales_order_line_id,

                            'line_number' =>
                                (int) $line
                                    ->line_number,

                            'product_name' =>
                                $line
                                    ->product_name,

                            'product_sku' =>
                                $line
                                    ->product_sku,

                            'product_type' =>
                                $line
                                    ->product_type,

                            'unit_code' =>
                                $line
                                    ->unit_code,

                            'requested_quantity' =>
                                (string) $line
                                    ->requested_quantity,

                            'allocated_quantity' =>
                                (string) $line
                                    ->allocated_quantity,

                            'quantity_on_hand_snapshot' =>
                                (string) $line
                                    ->quantity_on_hand_snapshot,

                            'quantity_reserved_other_snapshot' =>
                                (string) $line
                                    ->quantity_reserved_other_snapshot,

                            'quantity_available_snapshot' =>
                                (string) $line
                                    ->quantity_available_snapshot,

                            'reservation' =>
                                $line->reservation
                                    instanceof InventoryReservation
                                        ? [
                                            'id' =>
                                                (int) $line
                                                    ->reservation
                                                    ->getKey(),

                                            'status' =>
                                                $line
                                                    ->reservation
                                                    ->status,

                                            'reserved_quantity' =>
                                                (string) $line
                                                    ->reservation
                                                    ->reserved_quantity,

                                            'consumed_quantity' =>
                                                (string) $line
                                                    ->reservation
                                                    ->consumed_quantity,

                                            'released_quantity' =>
                                                (string) $line
                                                    ->reservation
                                                    ->released_quantity,

                                            'outstanding_quantity' =>
                                                $line
                                                    ->reservation
                                                    ->outstandingQuantity(),
                                        ]
                                        : null,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function allocationHistoryData(
        SalesOrderAllocation $allocation,
    ): array {
        return [
            'id' =>
                (int) $allocation->getKey(),

            'revision' =>
                (int) $allocation->revision,

            'status' =>
                $allocation->status,

            'notes' =>
                $allocation->notes,

            'created_at' =>
                $allocation
                    ->created_at
                    ?->toIso8601String(),

            'released_at' =>
                $allocation
                    ->released_at
                    ?->toIso8601String(),

            'release_reason' =>
                $allocation
                    ->release_reason,

            'created_by' =>
                $allocation->createdBy
                    !== null
                        ? [
                            'id' =>
                                (int) $allocation
                                    ->createdBy
                                    ->getKey(),

                            'name' =>
                                $allocation
                                    ->createdBy
                                    ->name,
                        ]
                        : null,

            'released_by' =>
                $allocation->releasedBy
                    !== null
                        ? [
                            'id' =>
                                (int) $allocation
                                    ->releasedBy
                                    ->getKey(),

                            'name' =>
                                $allocation
                                    ->releasedBy
                                    ->name,
                        ]
                        : null,
        ];
    }
}
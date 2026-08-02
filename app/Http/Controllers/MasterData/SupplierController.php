<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexSupplierRequest;
use App\Http\Requests\MasterData\StoreSupplierRequest;
use App\Http\Requests\MasterData\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MasterData\SupplierService;
use App\Support\MasterData\SupplierTypeRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly SupplierTypeRegistry $supplierTypeRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSupplierRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            Supplier::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $supplierType = (string) (
            $validated['supplier_type'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            ($validated['sort'] ?? null)
                ?: 'name'
        );

        $direction = (string) (
            ($validated['direction'] ?? null)
                ?: 'asc'
        );

        $perPage = (int) (
            ($validated['per_page'] ?? null)
                ?: 25
        );

        $suppliers = Supplier::query()
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'contact_person',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'alternate_phone',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'tax_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'registration_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'city',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $supplierType !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'supplier_type',
                    $supplierType,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Suppliers/Index',
            [
                'suppliers' => [
                    'data' => $suppliers
                        ->getCollection()
                        ->map(
                            fn (
                                Supplier $supplier,
                            ): array => $this->supplierData(
                                $supplier,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $suppliers->currentPage(),

                        'last_page' =>
                            $suppliers->lastPage(),

                        'per_page' =>
                            $suppliers->perPage(),

                        'from' =>
                            $suppliers->firstItem(),

                        'to' =>
                            $suppliers->lastItem(),

                        'total' =>
                            $suppliers->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'supplier_type' => $supplierType,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'supplierTypeOptions' =>
                    $this->supplierTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Supplier::class,
        );

        return Inertia::render(
            'Suppliers/Create',
            [
                'supplierTypeOptions' =>
                    $this->supplierTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreSupplierRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            Supplier::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $supplier = $this->supplierService->create(
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Supplier created successfully.',

            data: [
                'id' => (int) $supplier->getKey(),
            ],

            redirectTo: route('suppliers.index'),
        );
    }

    public function edit(
        Supplier $supplier,
    ): Response {
        Gate::authorize(
            'update',
            $supplier,
        );

        return Inertia::render(
            'Suppliers/Edit',
            [
                'supplier' =>
                    $this->supplierData($supplier),

                'supplierTypeOptions' =>
                    $this->supplierTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $supplier,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $supplier = $this->supplierService->update(
            supplier: $supplier,
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Supplier updated successfully.',

            data: [
                'id' => (int) $supplier->getKey(),
            ],

            redirectTo: route('suppliers.index'),
        );
    }

    public function destroy(
        Supplier $supplier,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $supplier,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->supplierService->delete(
            supplier: $supplier,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Supplier deleted successfully.',
            redirectTo: route('suppliers.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     supplier_type: string,
     *     supplier_type_label: string,
     *     contact_person: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     alternate_phone: string|null,
     *     tax_number: string|null,
     *     registration_number: string|null,
     *     address_line_1: string|null,
     *     address_line_2: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     country_code: string|null,
     *     payment_terms_days: int,
     *     notes: string|null,
     *     status: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function supplierData(
        Supplier $supplier,
    ): array {
        return [
            'id' => (int) $supplier->getKey(),
            'name' => $supplier->name,
            'code' => $supplier->code,
            'supplier_type' =>
                $supplier->supplier_type,

            'supplier_type_label' =>
                $this->supplierTypeRegistry->label(
                    $supplier->supplier_type,
                ),

            'contact_person' =>
                $supplier->contact_person,

            'email' => $supplier->email,
            'phone' => $supplier->phone,

            'alternate_phone' =>
                $supplier->alternate_phone,

            'tax_number' =>
                $supplier->tax_number,

            'registration_number' =>
                $supplier->registration_number,

            'address_line_1' =>
                $supplier->address_line_1,

            'address_line_2' =>
                $supplier->address_line_2,

            'city' => $supplier->city,
            'state' => $supplier->state,
            'postal_code' => $supplier->postal_code,

            'country_code' =>
                $supplier->country_code,

            'payment_terms_days' =>
                (int) $supplier->payment_terms_days,

            'notes' => $supplier->notes,
            'status' => $supplier->status,

            'created_at' =>
                $supplier->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $supplier->updated_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function statusOptions(): array
    {
        return [
            [
                'value' => 'active',
                'label' => 'Active',
            ],
            [
                'value' => 'inactive',
                'label' => 'Inactive',
            ],
        ];
    }
}
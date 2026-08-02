<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexCustomerRequest;
use App\Http\Requests\MasterData\StoreCustomerRequest;
use App\Http\Requests\MasterData\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\MasterData\CustomerService;
use App\Support\MasterData\CustomerTypeRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerTypeRegistry $customerTypeRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexCustomerRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            Customer::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $customerType = (string) (
            $validated['customer_type'] ?? ''
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

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $canManageCreditLimit = $actor->can(
            'customers.override_credit_limit',
        );

        $customers = Customer::query()
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
                                    'billing_city',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'billing_state',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'shipping_city',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'shipping_state',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $customerType !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'customer_type',
                    $customerType,
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
            'Customers/Index',
            [
                'customers' => [
                    'data' => $customers
                        ->getCollection()
                        ->map(
                            fn (
                                Customer $customer,
                            ): array => $this->customerData(
                                customer: $customer,
                                canManageCreditLimit:
                                    $canManageCreditLimit,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $customers->currentPage(),

                        'last_page' =>
                            $customers->lastPage(),

                        'per_page' =>
                            $customers->perPage(),

                        'from' =>
                            $customers->firstItem(),

                        'to' =>
                            $customers->lastItem(),

                        'total' =>
                            $customers->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'customer_type' => $customerType,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'customerTypeOptions' =>
                    $this->customerTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),

                'canManageCreditLimit' =>
                    $canManageCreditLimit,
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Customer::class,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        return Inertia::render(
            'Customers/Create',
            [
                'customerTypeOptions' =>
                    $this->customerTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),

                'canManageCreditLimit' =>
                    $actor->can(
                        'customers.override_credit_limit',
                    ),
            ],
        );
    }

    public function store(
        StoreCustomerRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            Customer::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $data = $request->validated();

        if (
            !$actor->can(
                'customers.override_credit_limit',
            )
        ) {
            $data['credit_limit'] = '0';
        }

        $customer = $this->customerService->create(
            data: $data,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Customer created successfully.',

            data: [
                'id' => (int) $customer->getKey(),
            ],

            redirectTo: route('customers.index'),
        );
    }

    public function edit(
        Customer $customer,
    ): Response {
        Gate::authorize(
            'update',
            $customer,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $canManageCreditLimit = $actor->can(
            'customers.override_credit_limit',
        );

        return Inertia::render(
            'Customers/Edit',
            [
                'customer' => $this->customerData(
                    customer: $customer,
                    canManageCreditLimit:
                        $canManageCreditLimit,
                ),

                'customerTypeOptions' =>
                    $this->customerTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),

                'canManageCreditLimit' =>
                    $canManageCreditLimit,
            ],
        );
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $customer,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $data = $request->validated();

        if (
            !$actor->can(
                'customers.override_credit_limit',
            )
        ) {
            $data['credit_limit'] =
                (string) $customer->credit_limit;
        }

        $customer = $this->customerService->update(
            customer: $customer,
            data: $data,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Customer updated successfully.',

            data: [
                'id' => (int) $customer->getKey(),
            ],

            redirectTo: route('customers.index'),
        );
    }

    public function destroy(
        Customer $customer,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $customer,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->customerService->delete(
            customer: $customer,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Customer deleted successfully.',
            redirectTo: route('customers.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     customer_type: string,
     *     customer_type_label: string,
     *     contact_person: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     alternate_phone: string|null,
     *     tax_number: string|null,
     *     registration_number: string|null,
     *     billing_address_line_1: string|null,
     *     billing_address_line_2: string|null,
     *     billing_city: string|null,
     *     billing_state: string|null,
     *     billing_postal_code: string|null,
     *     billing_country_code: string|null,
     *     shipping_address_line_1: string|null,
     *     shipping_address_line_2: string|null,
     *     shipping_city: string|null,
     *     shipping_state: string|null,
     *     shipping_postal_code: string|null,
     *     shipping_country_code: string|null,
     *     payment_terms_days: int,
     *     credit_limit: string|null,
     *     notes: string|null,
     *     status: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function customerData(
        Customer $customer,
        bool $canManageCreditLimit,
    ): array {
        return [
            'id' => (int) $customer->getKey(),
            'name' => $customer->name,
            'code' => $customer->code,

            'customer_type' =>
                $customer->customer_type,

            'customer_type_label' =>
                $this->customerTypeRegistry->label(
                    $customer->customer_type,
                ),

            'contact_person' =>
                $customer->contact_person,

            'email' => $customer->email,
            'phone' => $customer->phone,

            'alternate_phone' =>
                $customer->alternate_phone,

            'tax_number' =>
                $customer->tax_number,

            'registration_number' =>
                $customer->registration_number,

            'billing_address_line_1' =>
                $customer->billing_address_line_1,

            'billing_address_line_2' =>
                $customer->billing_address_line_2,

            'billing_city' =>
                $customer->billing_city,

            'billing_state' =>
                $customer->billing_state,

            'billing_postal_code' =>
                $customer->billing_postal_code,

            'billing_country_code' =>
                $customer->billing_country_code,

            'shipping_address_line_1' =>
                $customer->shipping_address_line_1,

            'shipping_address_line_2' =>
                $customer->shipping_address_line_2,

            'shipping_city' =>
                $customer->shipping_city,

            'shipping_state' =>
                $customer->shipping_state,

            'shipping_postal_code' =>
                $customer->shipping_postal_code,

            'shipping_country_code' =>
                $customer->shipping_country_code,

            'payment_terms_days' =>
                (int) $customer->payment_terms_days,

            'credit_limit' =>
                $canManageCreditLimit
                    ? (string) $customer->credit_limit
                    : null,

            'notes' => $customer->notes,
            'status' => $customer->status,

            'created_at' =>
                $customer->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $customer->updated_at
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
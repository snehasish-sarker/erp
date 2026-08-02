<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexBrandRequest;
use App\Http\Requests\MasterData\StoreBrandRequest;
use App\Http\Requests\MasterData\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\User;
use App\Services\MasterData\BrandService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexBrandRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            Brand::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'sort_order'
        );

        $direction = (string) (
            $validated['direction'] ?? 'asc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $brands = Brand::query()
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
                                    'slug',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'website_url',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
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
            'Brands/Index',
            [
                'brands' => [
                    'data' => $brands
                        ->getCollection()
                        ->map(
                            fn (
                                Brand $brand,
                            ): array => $this->brandData(
                                $brand,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $brands->currentPage(),

                        'last_page' =>
                            $brands->lastPage(),

                        'per_page' =>
                            $brands->perPage(),

                        'from' =>
                            $brands->firstItem(),

                        'to' =>
                            $brands->lastItem(),

                        'total' =>
                            $brands->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Brand::class,
        );

        return Inertia::render(
            'Brands/Create',
            [
                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreBrandRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            Brand::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $brand = $this->brandService->create(
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Brand created successfully.',

            data: [
                'id' => (int) $brand->getKey(),
            ],

            redirectTo: route('brands.index'),
        );
    }

    public function edit(
        Brand $brand,
    ): Response {
        Gate::authorize(
            'update',
            $brand,
        );

        return Inertia::render(
            'Brands/Edit',
            [
                'brand' => $this->brandData(
                    $brand,
                ),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateBrandRequest $request,
        Brand $brand,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $brand,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $brand = $this->brandService->update(
            brand: $brand,
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Brand updated successfully.',

            data: [
                'id' => (int) $brand->getKey(),
            ],

            redirectTo: route('brands.index'),
        );
    }

    public function destroy(
        Brand $brand,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $brand,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->brandService->delete(
            brand: $brand,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Brand deleted successfully.',
            redirectTo: route('brands.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     slug: string,
     *     website_url: string|null,
     *     description: string|null,
     *     sort_order: int,
     *     status: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function brandData(
        Brand $brand,
    ): array {
        return [
            'id' => (int) $brand->getKey(),
            'name' => $brand->name,
            'code' => $brand->code,
            'slug' => $brand->slug,
            'website_url' => $brand->website_url,
            'description' => $brand->description,
            'sort_order' => (int) $brand->sort_order,
            'status' => $brand->status,

            'created_at' =>
                $brand->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $brand->updated_at
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
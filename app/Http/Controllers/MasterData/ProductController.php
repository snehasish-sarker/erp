<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexProductRequest;
use App\Http\Requests\MasterData\StoreProductRequest;
use App\Http\Requests\MasterData\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\MasterData\ProductService;
use App\Support\MasterData\ProductTypeRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductTypeRegistry $productTypeRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexProductRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            Product::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $categoryId = isset(
            $validated['product_category_id'],
        )
            ? (int) $validated['product_category_id']
            : null;

        $brandId = isset($validated['brand_id'])
            ? (int) $validated['brand_id']
            : null;

        $productType = (string) (
            $validated['product_type'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'name'
        );

        $direction = (string) (
            $validated['direction'] ?? 'asc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $canViewCost = $request->user()?->can(
            'products.view_cost',
        ) === true;

        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'baseUnit:id,name,code,symbol',
            ])
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
                                    'sku',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'slug',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'barcode',
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
                $categoryId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'product_category_id',
                    $categoryId,
                ),
            )
            ->when(
                $brandId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'brand_id',
                    $brandId,
                ),
            )
            ->when(
                $productType !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'product_type',
                    $productType,
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
            'Products/Index',
            [
                'products' => [
                    'data' => $products
                        ->getCollection()
                        ->map(
                            fn (
                                Product $product,
                            ): array => $this->productData(
                                product: $product,
                                canViewCost: $canViewCost,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $products->currentPage(),

                        'last_page' =>
                            $products->lastPage(),

                        'per_page' =>
                            $products->perPage(),

                        'from' =>
                            $products->firstItem(),

                        'to' =>
                            $products->lastItem(),

                        'total' =>
                            $products->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'product_category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'product_type' => $productType,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'categoryOptions' =>
                    $this->categoryOptions(),

                'brandOptions' =>
                    $this->brandOptions(),

                'productTypeOptions' =>
                    $this->productTypeRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),

                'canViewCost' => $canViewCost,
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Product::class,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        return Inertia::render(
            'Products/Create',
            [
                ...$this->formOptions(),

                'canViewCost' => $actor->can(
                    'products.view_cost',
                ),
            ],
        );
    }

    public function store(
        StoreProductRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            Product::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $data = $request->validated();

        if (!$actor->can('products.view_cost')) {
            $data['cost_price'] = '0';
        }

        $product = $this->productService->create(
            data: $data,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Product created successfully.',

            data: [
                'id' => (int) $product->getKey(),
            ],

            redirectTo: route('products.index'),
        );
    }

    public function edit(
        Product $product,
    ): Response {
        Gate::authorize(
            'update',
            $product,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $canViewCost = $actor->can(
            'products.view_cost',
        );

        $product->load([
            'category:id,name',
            'brand:id,name',
            'baseUnit:id,name,code,symbol',
        ]);

        return Inertia::render(
            'Products/Edit',
            [
                'product' => $this->productData(
                    product: $product,
                    canViewCost: $canViewCost,
                ),

                ...$this->formOptions(),

                'canViewCost' => $canViewCost,
            ],
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $product,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $data = $request->validated();

        if (!$actor->can('products.view_cost')) {
            $data['cost_price'] =
                (string) $product->cost_price;
        }

        $product = $this->productService->update(
            product: $product,
            data: $data,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Product updated successfully.',

            data: [
                'id' => (int) $product->getKey(),
            ],

            redirectTo: route('products.index'),
        );
    }

    public function destroy(
        Product $product,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $product,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->productService->delete(
            product: $product,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Product deleted successfully.',
            redirectTo: route('products.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     product_category_id: int,
     *     category_name: string,
     *     brand_id: int|null,
     *     brand_name: string|null,
     *     base_unit_id: int,
     *     base_unit_name: string,
     *     base_unit_symbol: string|null,
     *     name: string,
     *     sku: string,
     *     slug: string,
     *     barcode: string|null,
     *     product_type: string,
     *     product_type_label: string,
     *     description: string|null,
     *     cost_price: string|null,
     *     selling_price: string,
     *     is_purchasable: bool,
     *     is_sellable: bool,
     *     status: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function productData(
        Product $product,
        bool $canViewCost,
    ): array {
        return [
            'id' => (int) $product->getKey(),

            'product_category_id' =>
                (int) $product->product_category_id,

            'category_name' =>
                $product->category?->name
                    ?? 'Unavailable',

            'brand_id' => $product->brand_id !== null
                ? (int) $product->brand_id
                : null,

            'brand_name' => $product->brand?->name,

            'base_unit_id' =>
                (int) $product->base_unit_id,

            'base_unit_name' =>
                $product->baseUnit?->name
                    ?? 'Unavailable',

            'base_unit_symbol' =>
                $product->baseUnit?->symbol,

            'name' => $product->name,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'barcode' => $product->barcode,
            'product_type' => $product->product_type,

            'product_type_label' =>
                $this->productTypeRegistry->label(
                    $product->product_type,
                ),

            'description' => $product->description,

            'cost_price' => $canViewCost
                ? (string) $product->cost_price
                : null,

            'selling_price' =>
                (string) $product->selling_price,

            'is_purchasable' =>
                (bool) $product->is_purchasable,

            'is_sellable' =>
                (bool) $product->is_sellable,

            'status' => $product->status,

            'created_at' =>
                $product->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $product->updated_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     categoryOptions: list<array{
     *         id: int,
     *         label: string,
     *         status: string
     *     }>,
     *     brandOptions: list<array{
     *         id: int,
     *         label: string,
     *         status: string
     *     }>,
     *     unitOptions: list<array{
     *         id: int,
     *         label: string,
     *         status: string
     *     }>,
     *     productTypeOptions: list<array{
     *         value: string,
     *         label: string
     *     }>,
     *     statusOptions: list<array{
     *         value: string,
     *         label: string
     *     }>
     * }
     */
    private function formOptions(): array
    {
        return [
            'categoryOptions' =>
                $this->categoryOptions(),

            'brandOptions' =>
                $this->brandOptions(),

            'unitOptions' =>
                $this->unitOptions(),

            'productTypeOptions' =>
                $this->productTypeRegistry
                    ->options(),

            'statusOptions' =>
                $this->statusOptions(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     status: string
     * }>
     */
    private function categoryOptions(): array
    {
        $categories = ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'parent_id',
                'name',
                'code',
                'status',
            ]);

        /** @var array<int, list<ProductCategory>> $childrenByParent */
        $childrenByParent = [];

        foreach ($categories as $category) {
            $parentId = $category->parent_id !== null
                ? (int) $category->parent_id
                : 0;

            $childrenByParent[$parentId][] =
                $category;
        }

        $options = [];
        $visited = [];

        $this->appendCategoryOptions(
            parentId: 0,
            childrenByParent: $childrenByParent,
            options: $options,
            visited: $visited,
            path: '',
        );

        foreach ($categories as $category) {
            $categoryId = (int) $category->getKey();

            if (isset($visited[$categoryId])) {
                continue;
            }

            $options[] = [
                'id' => $categoryId,
                'label' => sprintf(
                    '%s (%s)',
                    $category->name,
                    $category->code,
                ),
                'status' => $category->status,
            ];
        }

        return $options;
    }

    /**
     * @param array<int, list<ProductCategory>> $childrenByParent
     * @param list<array{
     *     id: int,
     *     label: string,
     *     status: string
     * }> $options
     * @param array<int, true> $visited
     */
    private function appendCategoryOptions(
        int $parentId,
        array $childrenByParent,
        array &$options,
        array &$visited,
        string $path,
    ): void {
        foreach (
            $childrenByParent[$parentId] ?? []
            as $category
        ) {
            $categoryId = (int) $category->getKey();

            if (isset($visited[$categoryId])) {
                continue;
            }

            $visited[$categoryId] = true;

            $categoryPath = $path === ''
                ? $category->name
                : "{$path} › {$category->name}";

            $options[] = [
                'id' => $categoryId,
                'label' => sprintf(
                    '%s (%s)',
                    $categoryPath,
                    $category->code,
                ),
                'status' => $category->status,
            ];

            $this->appendCategoryOptions(
                parentId: $categoryId,
                childrenByParent: $childrenByParent,
                options: $options,
                visited: $visited,
                path: $categoryPath,
            );
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     status: string
     * }>
     */
    private function brandOptions(): array
    {
        return Brand::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ])
            ->map(
                static fn (Brand $brand): array => [
                    'id' => (int) $brand->getKey(),

                    'label' => sprintf(
                        '%s (%s)',
                        $brand->name,
                        $brand->code,
                    ),

                    'status' => $brand->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     status: string
     * }>
     */
    private function unitOptions(): array
    {
        return Unit::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'symbol',
                'status',
            ])
            ->map(
                static function (Unit $unit): array {
                    $label = sprintf(
                        '%s (%s)',
                        $unit->name,
                        $unit->code,
                    );

                    if ($unit->symbol !== null) {
                        $label .= " — {$unit->symbol}";
                    }

                    return [
                        'id' => (int) $unit->getKey(),
                        'label' => $label,
                        'status' => $unit->status,
                    ];
                },
            )
            ->values()
            ->all();
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
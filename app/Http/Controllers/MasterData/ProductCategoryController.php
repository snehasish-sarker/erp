<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexProductCategoryRequest;
use App\Http\Requests\MasterData\StoreProductCategoryRequest;
use App\Http\Requests\MasterData\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\MasterData\ProductCategoryService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $productCategoryService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexProductCategoryRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            ProductCategory::class,
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

        $categories = ProductCategory::query()
            ->with('parent:id,name')
            ->withCount('children')
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
            'ProductCategories/Index',
            [
                'categories' => [
                    'data' => $categories
                        ->getCollection()
                        ->map(
                            fn (
                                ProductCategory $category,
                            ): array => $this->categoryData(
                                $category,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $categories->currentPage(),

                        'last_page' =>
                            $categories->lastPage(),

                        'per_page' =>
                            $categories->perPage(),

                        'from' =>
                            $categories->firstItem(),

                        'to' =>
                            $categories->lastItem(),

                        'total' =>
                            $categories->total(),
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
            ProductCategory::class,
        );

        return Inertia::render(
            'ProductCategories/Create',
            [
                'parentOptions' =>
                    $this->parentOptions(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreProductCategoryRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            ProductCategory::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $category =
            $this->productCategoryService->create(
                data: $request->validated(),
                actor: $actor,
            );

        return $this->responseService->success(
            message: 'Product category created successfully.',

            data: [
                'id' => (int) $category->getKey(),
            ],

            redirectTo: route(
                'product-categories.index',
            ),
        );
    }

    public function edit(
        ProductCategory $productCategory,
    ): Response {
        Gate::authorize(
            'update',
            $productCategory,
        );

        $productCategory
            ->load('parent:id,name')
            ->loadCount('children');

        return Inertia::render(
            'ProductCategories/Edit',
            [
                'category' => $this->categoryData(
                    $productCategory,
                ),

                'parentOptions' =>
                    $this->parentOptions(
                        excludedIds:
                            $this->categoryAndDescendantIds(
                                $productCategory,
                            ),
                    ),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $productCategory,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $productCategory,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $category =
            $this->productCategoryService->update(
                productCategory: $productCategory,
                data: $request->validated(),
                actor: $actor,
            );

        return $this->responseService->success(
            message: 'Product category updated successfully.',

            data: [
                'id' => (int) $category->getKey(),
            ],

            redirectTo: route(
                'product-categories.index',
            ),
        );
    }

    public function destroy(
        ProductCategory $productCategory,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $productCategory,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->productCategoryService->delete(
            productCategory: $productCategory,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Product category deleted successfully.',
            redirectTo: route(
                'product-categories.index',
            ),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     parent_id: int|null,
     *     parent_name: string|null,
     *     name: string,
     *     code: string,
     *     slug: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string,
     *     children_count: int,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function categoryData(
        ProductCategory $category,
    ): array {
        return [
            'id' => (int) $category->getKey(),

            'parent_id' => $category->parent_id !== null
                ? (int) $category->parent_id
                : null,

            'parent_name' =>
                $category->parent?->name,

            'name' => $category->name,
            'code' => $category->code,
            'slug' => $category->slug,
            'description' => $category->description,

            'sort_order' =>
                (int) $category->sort_order,

            'status' => $category->status,

            'children_count' =>
                (int) ($category->children_count ?? 0),

            'created_at' =>
                $category->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $category->updated_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @param list<int> $excludedIds
     *
     * @return list<array{
     *     id: int,
     *     label: string,
     *     status: string,
     *     depth: int
     * }>
     */
    private function parentOptions(
        array $excludedIds = [],
    ): array {
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

        $excludedLookup = array_fill_keys(
            $excludedIds,
            true,
        );

        /** @var array<int, list<ProductCategory>> $childrenByParent */
        $childrenByParent = [];

        foreach ($categories as $category) {
            $categoryId = (int) $category->getKey();

            if (isset($excludedLookup[$categoryId])) {
                continue;
            }

            $parentId = $category->parent_id !== null
                ? (int) $category->parent_id
                : 0;

            $childrenByParent[$parentId][] =
                $category;
        }

        $options = [];
        $visited = [];

        $this->appendParentOptions(
            parentId: 0,
            childrenByParent: $childrenByParent,
            options: $options,
            visited: $visited,
            depth: 0,
            path: '',
        );

        foreach ($categories as $category) {
            $categoryId = (int) $category->getKey();

            if (
                isset($excludedLookup[$categoryId])
                || isset($visited[$categoryId])
            ) {
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
                'depth' => 0,
            ];
        }

        return $options;
    }

    /**
     * @param array<int, list<ProductCategory>> $childrenByParent
     * @param list<array{
     *     id: int,
     *     label: string,
     *     status: string,
     *     depth: int
     * }> $options
     * @param array<int, true> $visited
     */
    private function appendParentOptions(
        int $parentId,
        array $childrenByParent,
        array &$options,
        array &$visited,
        int $depth,
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
                'depth' => $depth,
            ];

            $this->appendParentOptions(
                parentId: $categoryId,
                childrenByParent: $childrenByParent,
                options: $options,
                visited: $visited,
                depth: $depth + 1,
                path: $categoryPath,
            );
        }
    }

    /**
     * @return list<int>
     */
    private function categoryAndDescendantIds(
        ProductCategory $productCategory,
    ): array {
        $categories = ProductCategory::query()->get([
            'id',
            'parent_id',
        ]);

        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                continue;
            }

            $childrenByParent[
                (int) $category->parent_id
            ][] = (int) $category->getKey();
        }

        $queue = [
            (int) $productCategory->getKey(),
        ];

        $visited = [];

        while ($queue !== []) {
            $categoryId = array_shift($queue);

            if (
                $categoryId === null
                || isset($visited[$categoryId])
            ) {
                continue;
            }

            $visited[$categoryId] = true;

            foreach (
                $childrenByParent[$categoryId] ?? []
                as $childId
            ) {
                $queue[] = $childId;
            }
        }

        return array_map(
            static fn (int|string $id): int =>
                (int) $id,
            array_keys($visited),
        );
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
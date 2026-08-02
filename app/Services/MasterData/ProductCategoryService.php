<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ProductCategoryService
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): ProductCategory {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $normalized,
            ): ProductCategory {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $parent = $this->resolveParent(
                    parentId: $normalized['parent_id'],
                    status: $normalized['status'],
                );

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                );

                return ProductCategory::query()->create([
                    ...$normalized,

                    'parent_id' => $parent?->getKey(),
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        ProductCategory $productCategory,
        array $data,
        User $actor,
    ): ProductCategory {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureCategoryBelongsToTenant(
            productCategory: $productCategory,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $productCategory,
                $normalized,
            ): ProductCategory {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCategory = ProductCategory::query()
                    ->whereKey(
                        $productCategory->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $parent = $this->resolveParent(
                    parentId: $normalized['parent_id'],
                    status: $normalized['status'],
                    currentCategory: $lockedCategory,
                );

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptCategoryId:
                        (int) $lockedCategory->getKey(),
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                    exceptCategoryId:
                        (int) $lockedCategory->getKey(),
                );

                $this->ensureStatusCanBeChanged(
                    productCategory: $lockedCategory,
                    newStatus: $normalized['status'],
                );

                $lockedCategory->fill([
                    ...$normalized,

                    'parent_id' => $parent?->getKey(),
                ]);

                $lockedCategory->save();

                return $lockedCategory->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        ProductCategory $productCategory,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureCategoryBelongsToTenant(
            productCategory: $productCategory,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $productCategory,
            ): void {
                $lockedCategory = ProductCategory::query()
                    ->whereKey(
                        $productCategory->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasChildren = ProductCategory::query()
                    ->where(
                        'parent_id',
                        $lockedCategory->getKey(),
                    )
                    ->exists();

                if ($hasChildren) {
                    throw ValidationException::withMessages([
                        'category' => [
                            'A category with child categories cannot be deleted.',
                        ],
                    ]);
                }

                $isUsedByProduct = Product::query()
                    ->withTrashed()
                    ->where(
                        'product_category_id',
                        $lockedCategory->getKey(),
                    )
                    ->exists();

                if ($isUsedByProduct) {
                    throw ValidationException::withMessages([
                        'category' => [
                            'A category assigned to a product cannot be deleted.',
                        ],
                    ]);
                }

                $lockedCategory->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     parent_id: int|null,
     *     name: string,
     *     code: string,
     *     slug: string,
     *     description: string|null,
     *     sort_order: int,
     *     status: string
     * }
     */
    private function normalize(array $data): array
    {
        $name = trim(
            (string) ($data['name'] ?? ''),
        );

        $code = mb_strtoupper(
            trim(
                (string) ($data['code'] ?? ''),
            ),
        );

        $providedSlug = trim(
            (string) ($data['slug'] ?? ''),
        );

        $slug = Str::slug(
            $providedSlug !== ''
                ? $providedSlug
                : $name,
        );

        $description = $this->nullableTrimmedString(
            $data['description'] ?? null,
        );

        $status = mb_strtolower(
            trim(
                (string) (
                    $data['status'] ?? 'active'
                ),
            ),
        );

        $parentId = $this->normalizeNullableId(
            $data['parent_id'] ?? null,
        );

        $sortOrder = (int) (
            $data['sort_order'] ?? 0
        );

        $errors = [];

        if (
            $name === ''
            || mb_strlen($name) > 120
        ) {
            $errors['name'] = [
                'The category name is required and may not exceed 120 characters.',
            ];
        }

        if (
            $code === ''
            || mb_strlen($code) > 40
            || preg_match(
                '/^[A-Z0-9_-]+$/',
                $code,
            ) !== 1
        ) {
            $errors['code'] = [
                'The category code may contain uppercase letters, numbers, underscores, and hyphens only.',
            ];
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 160
            || preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug,
            ) !== 1
        ) {
            $errors['slug'] = [
                'The category slug must contain lowercase letters, numbers, and hyphens only.',
            ];
        }

        if (
            $description !== null
            && mb_strlen($description) > 2000
        ) {
            $errors['description'] = [
                'The category description may not exceed 2,000 characters.',
            ];
        }

        if (
            !in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            $errors['status'] = [
                'The selected category status is invalid.',
            ];
        }

        if (
            $sortOrder < 0
            || $sortOrder > 4294967295
        ) {
            $errors['sort_order'] = [
                'The category sort order must be zero or greater.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors,
            );
        }

        return [
            'parent_id' => $parentId,
            'name' => $name,
            'code' => $code,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
    }

    private function resolveParent(
        ?int $parentId,
        string $status,
        ?ProductCategory $currentCategory = null,
    ): ?ProductCategory {
        if ($parentId === null) {
            return null;
        }

        if (
            $currentCategory !== null
            && $parentId
                === (int) $currentCategory->getKey()
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'A category cannot be its own parent.',
                ],
            ]);
        }

        $parent = ProductCategory::query()
            ->whereKey($parentId)
            ->lockForUpdate()
            ->first();

        if (!$parent instanceof ProductCategory) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'The selected parent category is invalid.',
                ],
            ]);
        }

        if (
            $status === 'active'
            && !$parent->isActive()
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'An active category cannot be placed under an inactive parent category.',
                ],
            ]);
        }

        if ($currentCategory !== null) {
            $this->ensureParentDoesNotCreateCycle(
                parent: $parent,
                currentCategory: $currentCategory,
            );
        }

        return $parent;
    }

    private function ensureParentDoesNotCreateCycle(
        ProductCategory $parent,
        ProductCategory $currentCategory,
    ): void {
        $visitedIds = [];

        $currentParent = $parent;

        while (
            $currentParent
                instanceof ProductCategory
        ) {
            $currentParentId =
                (int) $currentParent->getKey();

            if (
                $currentParentId
                === (int) $currentCategory->getKey()
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => [
                        'The selected parent category would create a circular hierarchy.',
                    ],
                ]);
            }

            if (
                in_array(
                    $currentParentId,
                    $visitedIds,
                    true,
                )
            ) {
                throw new LogicException(
                    'An existing circular product category hierarchy was detected.',
                );
            }

            $visitedIds[] = $currentParentId;

            if ($currentParent->parent_id === null) {
                break;
            }

            $currentParent = ProductCategory::query()
                ->whereKey(
                    $currentParent->parent_id,
                )
                ->first();
        }
    }

    private function ensureStatusCanBeChanged(
        ProductCategory $productCategory,
        string $newStatus,
    ): void {
        if (
            $newStatus !== 'inactive'
            || $productCategory->status
                === 'inactive'
        ) {
            return;
        }

        $hasActiveChildren = ProductCategory::query()
            ->where(
                'parent_id',
                $productCategory->getKey(),
            )
            ->where(
                'status',
                'active',
            )
            ->exists();

        if (!$hasActiveChildren) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'A category with active child categories cannot be made inactive.',
            ],
        ]);
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptCategoryId = null,
    ): void {
        $exists = ProductCategory::query()
            ->withTrashed()
            ->where('code', $code)
            ->when(
                $exceptCategoryId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptCategoryId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [
                'The category code has already been used.',
            ],
        ]);
    }

    private function ensureSlugIsAvailable(
        string $slug,
        ?int $exceptCategoryId = null,
    ): void {
        $exists = ProductCategory::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when(
                $exceptCategoryId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptCategoryId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => [
                'The category slug has already been used.',
            ],
        ]);
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

    private function ensureCategoryBelongsToTenant(
        ProductCategory $productCategory,
        int $tenantId,
    ): void {
        if (
            (int) $productCategory->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The selected category belongs to another tenant.',
        );
    }

    private function normalizeNullableId(
        mixed $value,
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            !is_int($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'The selected parent category is invalid.',
                ],
            ]);
        }

        if (
            !is_numeric($value)
            || (int) $value < 1
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'The selected parent category is invalid.',
                ],
            ]);
        }

        return (int) $value;
    }

    private function nullableTrimmedString(
        mixed $value,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
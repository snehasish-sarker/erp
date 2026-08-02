<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductCategory;
use App\Models\ProductWarehouseSetting;
use App\Models\PurchaseOrderLine;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\MasterData\ProductTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ProductService
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
        private readonly ProductTypeRegistry $productTypeRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): Product {
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
            ): Product {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $category = $this->resolveCategory(
                    categoryId:
                        $normalized['product_category_id'],
                    productStatus:
                        $normalized['status'],
                );

                $brand = $this->resolveBrand(
                    brandId: $normalized['brand_id'],
                    productStatus:
                        $normalized['status'],
                );

                $baseUnit = $this->resolveBaseUnit(
                    unitId: $normalized['base_unit_id'],
                    productStatus:
                        $normalized['status'],
                );

                $this->ensureSkuIsAvailable(
                    sku: $normalized['sku'],
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                );

                $this->ensureBarcodeIsAvailable(
                    barcode: $normalized['barcode'],
                );

                return Product::query()->create([
                    ...$normalized,

                    'product_category_id' =>
                        $category->getKey(),

                    'brand_id' => $brand?->getKey(),

                    'base_unit_id' =>
                        $baseUnit->getKey(),
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        Product $product,
        array $data,
        User $actor,
    ): Product {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureProductBelongsToTenant(
            product: $product,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $product,
                $normalized,
            ): Product {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureLocationConfigurationCompatibility(
                    product: $lockedProduct,
                    normalized: $normalized,
                );

                $category = $this->resolveCategory(
                    categoryId:
                        $normalized['product_category_id'],
                    productStatus:
                        $normalized['status'],
                );

                $brand = $this->resolveBrand(
                    brandId: $normalized['brand_id'],
                    productStatus:
                        $normalized['status'],
                );

                $baseUnit = $this->resolveBaseUnit(
                    unitId: $normalized['base_unit_id'],
                    productStatus:
                        $normalized['status'],
                );

                $productId =
                    (int) $lockedProduct->getKey();

                $this->ensureSkuIsAvailable(
                    sku: $normalized['sku'],
                    exceptProductId: $productId,
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                    exceptProductId: $productId,
                );

                $this->ensureBarcodeIsAvailable(
                    barcode: $normalized['barcode'],
                    exceptProductId: $productId,
                );

                $lockedProduct->fill([
                    ...$normalized,

                    'product_category_id' =>
                        $category->getKey(),

                    'brand_id' => $brand?->getKey(),

                    'base_unit_id' =>
                        $baseUnit->getKey(),
                ]);

                $lockedProduct->save();

                return $lockedProduct->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Product $product,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureProductBelongsToTenant(
            product: $product,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use ($product): void {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasBranchSettings =
                    ProductBranchSetting::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey(),
                        )
                        ->exists();

                if ($hasBranchSettings) {
                    throw ValidationException::withMessages([
                        'product' => [
                            'Remove the product branch configurations before deleting this product.',
                        ],
                    ]);
                }

                $hasWarehouseSettings =
                    ProductWarehouseSetting::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey(),
                        )
                        ->exists();

                if ($hasWarehouseSettings) {
                    throw ValidationException::withMessages([
                        'product' => [
                            'Remove the product warehouse configurations before deleting this product.',
                        ],
                    ]);
                }

                $hasPurchaseOrderLines =
                    PurchaseOrderLine::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey(),
                        )
                        ->exists();

                if ($hasPurchaseOrderLines) {
                    throw ValidationException::withMessages([
                        'product' => [
                            'The product cannot be deleted because it is referenced by one or more purchase orders.',
                        ],
                    ]);
                }

                /*
                 * Add goods-receipt, supplier-invoice, inventory-ledger,
                 * sales-document, and accounting usage checks as those
                 * modules are introduced.
                 */
                $lockedProduct->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     product_category_id: int,
     *     brand_id: int|null,
     *     base_unit_id: int,
     *     name: string,
     *     sku: string,
     *     slug: string,
     *     barcode: string|null,
     *     product_type: string,
     *     description: string|null,
     *     cost_price: string,
     *     selling_price: string,
     *     is_purchasable: bool,
     *     is_sellable: bool,
     *     status: string
     * }
     */
    private function normalize(array $data): array
    {
        $categoryId = $this->normalizeRequiredId(
            value:
                $data['product_category_id']
                    ?? null,
            field: 'product_category_id',
            message:
                'The selected product category is invalid.',
        );

        $brandId = $this->normalizeNullableId(
            value: $data['brand_id'] ?? null,
            field: 'brand_id',
            message: 'The selected brand is invalid.',
        );

        $baseUnitId = $this->normalizeRequiredId(
            value: $data['base_unit_id'] ?? null,
            field: 'base_unit_id',
            message: 'The selected base unit is invalid.',
        );

        $name = trim(
            (string) ($data['name'] ?? ''),
        );

        $sku = mb_strtoupper(
            trim(
                (string) ($data['sku'] ?? ''),
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

        $barcode = $this->nullableTrimmedString(
            $data['barcode'] ?? null,
        );

        $productType = mb_strtolower(
            trim(
                (string) (
                    $data['product_type']
                        ?? 'stock'
                ),
            ),
        );

        $description =
            $this->nullableTrimmedString(
                $data['description'] ?? null,
            );

        $costPrice = $this->normalizeDecimal(
            value: $data['cost_price'] ?? 0,
            field: 'cost_price',
            label: 'cost price',
        );

        $sellingPrice = $this->normalizeDecimal(
            value: $data['selling_price'] ?? 0,
            field: 'selling_price',
            label: 'selling price',
        );

        $isPurchasable = $this->normalizeBoolean(
            $data['is_purchasable'] ?? true,
        );

        $isSellable = $this->normalizeBoolean(
            $data['is_sellable'] ?? true,
        );

        $status = mb_strtolower(
            trim(
                (string) (
                    $data['status'] ?? 'active'
                ),
            ),
        );

        if (
            $name === ''
            || mb_strlen($name) > 160
        ) {
            throw ValidationException::withMessages([
                'name' => [
                    'The product name is required and may not exceed 160 characters.',
                ],
            ]);
        }

        if (
            $sku === ''
            || mb_strlen($sku) > 80
            || preg_match(
                '/^[A-Z0-9][A-Z0-9._\/-]*$/',
                $sku,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'sku' => [
                    'The SKU may contain uppercase letters, numbers, periods, underscores, slashes, and hyphens only.',
                ],
            ]);
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 180
            || preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'The product slug must contain lowercase letters, numbers, and hyphens only.',
                ],
            ]);
        }

        if (
            $barcode !== null
            && (
                mb_strlen($barcode) > 120
                || preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $barcode,
                ) === 1
            )
        ) {
            throw ValidationException::withMessages([
                'barcode' => [
                    'The barcode may not exceed 120 characters or contain control characters.',
                ],
            ]);
        }

        if (
            !$this->productTypeRegistry
                ->exists($productType)
        ) {
            throw ValidationException::withMessages([
                'product_type' => [
                    'The selected product type is invalid.',
                ],
            ]);
        }

        if (
            $description !== null
            && mb_strlen($description) > 4000
        ) {
            throw ValidationException::withMessages([
                'description' => [
                    'The product description may not exceed 4,000 characters.',
                ],
            ]);
        }

        if (
            !in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'The selected product status is invalid.',
                ],
            ]);
        }

        return [
            'product_category_id' => $categoryId,
            'brand_id' => $brandId,
            'base_unit_id' => $baseUnitId,
            'name' => $name,
            'sku' => $sku,
            'slug' => $slug,
            'barcode' => $barcode,
            'product_type' => $productType,
            'description' => $description,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'is_purchasable' => $isPurchasable,
            'is_sellable' => $isSellable,
            'status' => $status,
        ];
    }

    private function resolveCategory(
        int $categoryId,
        string $productStatus,
    ): ProductCategory {
        $category = ProductCategory::query()
            ->whereKey($categoryId)
            ->lockForUpdate()
            ->first();

        if (!$category instanceof ProductCategory) {
            throw ValidationException::withMessages([
                'product_category_id' => [
                    'The selected product category is unavailable.',
                ],
            ]);
        }

        if (
            $productStatus === 'active'
            && !$category->isActive()
        ) {
            throw ValidationException::withMessages([
                'product_category_id' => [
                    'An active product must use an active category.',
                ],
            ]);
        }

        return $category;
    }

    private function resolveBrand(
        ?int $brandId,
        string $productStatus,
    ): ?Brand {
        if ($brandId === null) {
            return null;
        }

        $brand = Brand::query()
            ->whereKey($brandId)
            ->lockForUpdate()
            ->first();

        if (!$brand instanceof Brand) {
            throw ValidationException::withMessages([
                'brand_id' => [
                    'The selected brand is unavailable.',
                ],
            ]);
        }

        if (
            $productStatus === 'active'
            && !$brand->isActive()
        ) {
            throw ValidationException::withMessages([
                'brand_id' => [
                    'An active product cannot use an inactive brand.',
                ],
            ]);
        }

        return $brand;
    }

    private function resolveBaseUnit(
        int $unitId,
        string $productStatus,
    ): Unit {
        $unit = Unit::query()
            ->whereKey($unitId)
            ->lockForUpdate()
            ->first();

        if (!$unit instanceof Unit) {
            throw ValidationException::withMessages([
                'base_unit_id' => [
                    'The selected base unit is unavailable.',
                ],
            ]);
        }

        if (
            $productStatus === 'active'
            && !$unit->isActive()
        ) {
            throw ValidationException::withMessages([
                'base_unit_id' => [
                    'An active product must use an active base unit.',
                ],
            ]);
        }

        return $unit;
    }

    /**
     * @param array{
     *     product_category_id: int,
     *     brand_id: int|null,
     *     base_unit_id: int,
     *     name: string,
     *     sku: string,
     *     slug: string,
     *     barcode: string|null,
     *     product_type: string,
     *     description: string|null,
     *     cost_price: string,
     *     selling_price: string,
     *     is_purchasable: bool,
     *     is_sellable: bool,
     *     status: string
     * } $normalized
     */
    private function ensureLocationConfigurationCompatibility(
        Product $product,
        array $normalized,
    ): void {
        if ($normalized['status'] === 'inactive') {
            $hasActiveBranchSettings =
                ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where('status', 'active')
                    ->exists();

            $hasActiveWarehouseSettings =
                ProductWarehouseSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where('status', 'active')
                    ->exists();

            if (
                $hasActiveBranchSettings
                || $hasActiveWarehouseSettings
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Deactivate all product branch and warehouse configurations before making the product inactive.',
                    ],
                ]);
            }
        }

        if (!$normalized['is_purchasable']) {
            $hasPurchasableBranch =
                ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where('status', 'active')
                    ->where('is_purchasable', true)
                    ->exists();

            if ($hasPurchasableBranch) {
                throw ValidationException::withMessages([
                    'is_purchasable' => [
                        'Disable purchasing in every active branch configuration first.',
                    ],
                ]);
            }
        }

        if (!$normalized['is_sellable']) {
            $hasSellableBranch =
                ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    )
                    ->where('status', 'active')
                    ->where('is_sellable', true)
                    ->exists();

            if ($hasSellableBranch) {
                throw ValidationException::withMessages([
                    'is_sellable' => [
                        'Disable selling in every active branch configuration first.',
                    ],
                ]);
            }
        }

        if ($normalized['product_type'] === 'stock') {
            return;
        }

        $hasWarehouseSettings =
            ProductWarehouseSetting::query()
                ->where(
                    'product_id',
                    $product->getKey(),
                )
                ->exists();

        if (!$hasWarehouseSettings) {
            return;
        }

        throw ValidationException::withMessages([
            'product_type' => [
                'Remove all warehouse configurations before changing this product from a stock item.',
            ],
        ]);
    }

    private function ensureSkuIsAvailable(
        string $sku,
        ?int $exceptProductId = null,
    ): void {
        $exists = Product::query()
            ->withTrashed()
            ->where('sku', $sku)
            ->when(
                $exceptProductId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptProductId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'sku' => [
                'The SKU has already been used.',
            ],
        ]);
    }

    private function ensureSlugIsAvailable(
        string $slug,
        ?int $exceptProductId = null,
    ): void {
        $exists = Product::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when(
                $exceptProductId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptProductId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => [
                'The product slug has already been used.',
            ],
        ]);
    }

    private function ensureBarcodeIsAvailable(
        ?string $barcode,
        ?int $exceptProductId = null,
    ): void {
        if ($barcode === null) {
            return;
        }

        $exists = Product::query()
            ->withTrashed()
            ->where('barcode', $barcode)
            ->when(
                $exceptProductId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptProductId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'barcode' => [
                'The barcode has already been used.',
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

    private function ensureProductBelongsToTenant(
        Product $product,
        int $tenantId,
    ): void {
        if ((int) $product->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected product belongs to another tenant.',
        );
    }

    private function normalizeRequiredId(
        mixed $value,
        string $field,
        string $message,
    ): int {
        $normalized = $this->normalizeNullableId(
            value: $value,
            field: $field,
            message: $message,
        );

        if ($normalized !== null) {
            return $normalized;
        }

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }

    private function normalizeNullableId(
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
            !is_string($value)
            || preg_match(
                '/^[1-9]\d*$/',
                trim($value),
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [$message],
            ]);
        }

        return (int) trim($value);
    }

    private function normalizeDecimal(
        mixed $value,
        string $field,
        string $label,
    ): string {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (
            is_float($value)
            && is_finite($value)
        ) {
            $value = number_format(
                $value,
                6,
                '.',
                '',
            );
        } elseif (is_string($value)) {
            $value = trim($value);
        } else {
            $value = '';
        }

        if (
            preg_match(
                '/^\d+(?:\.\d{1,6})?$/',
                $value,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a non-negative amount with no more than 6 decimal places.",
                ],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        $whole = ltrim($whole, '0');

        $whole = $whole === ''
            ? '0'
            : $whole;

        if (mb_strlen($whole) > 14) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not exceed 99,999,999,999,999.999999.",
                ],
            ]);
        }

        return $whole . '.' . str_pad(
            $fraction,
            6,
            '0',
        );
    }

    private function normalizeBoolean(
        mixed $value,
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value)
            && in_array(
                mb_strtolower(trim($value)),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                ],
                true,
            );
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
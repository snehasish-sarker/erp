<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ProductLocationConfigurationService
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
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveBranchSetting(
        Product $product,
        array $data,
        User $actor,
    ): ProductBranchSetting {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureProductBelongsToTenant(
            product: $product,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeBranchSetting(
            $data,
        );

        return DB::transaction(
            function () use (
                $product,
                $actor,
                $normalized,
            ): ProductBranchSetting {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = $this->resolveAccessibleBranch(
                    actor: $actor,
                    branchId: $normalized['branch_id'],
                );

                $this->ensureBranchSettingIsCompatible(
                    product: $lockedProduct,
                    branch: $branch,
                    status: $normalized['status'],
                    isPurchasable:
                        $normalized['is_purchasable'],
                    isSellable:
                        $normalized['is_sellable'],
                );

                $this->ensureBranchSettingCanBeChanged(
                    product: $lockedProduct,
                    branch: $branch,
                    newStatus: $normalized['status'],
                );

                $setting = ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey(),
                    )
                    ->where(
                        'branch_id',
                        $branch->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

                $attributes = [
                    'product_id' =>
                        $lockedProduct->getKey(),

                    'branch_id' => $branch->getKey(),
                    'status' => $normalized['status'],

                    'is_purchasable' =>
                        $normalized['is_purchasable'],

                    'is_sellable' =>
                        $normalized['is_sellable'],

                    'selling_price' =>
                        $normalized['selling_price'],
                ];

                if (
                    !$setting
                        instanceof ProductBranchSetting
                ) {
                    return ProductBranchSetting::query()
                        ->create($attributes);
                }

                $setting->fill($attributes);
                $setting->save();

                return $setting->refresh();
            },
            attempts: 5,
        );
    }

    public function deleteBranchSetting(
        ProductBranchSetting $setting,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureBranchSettingBelongsToTenant(
            setting: $setting,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $setting,
                $actor,
            ): void {
                $lockedSetting = ProductBranchSetting::query()
                    ->whereKey($setting->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = Branch::query()
                    ->whereKey($lockedSetting->branch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->branchAccessService->authorizeBranch(
                    user: $actor,
                    branch: $branch,
                    requireActive: false,
                );

                $hasWarehouseSettings =
                    ProductWarehouseSetting::query()
                        ->where(
                            'product_id',
                            $lockedSetting->product_id,
                        )
                        ->where(
                            'branch_id',
                            $lockedSetting->branch_id,
                        )
                        ->exists();

                if ($hasWarehouseSettings) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'Remove the product warehouse settings for this branch before removing its branch configuration.',
                        ],
                    ]);
                }

                $lockedSetting->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveWarehouseSetting(
        Product $product,
        array $data,
        User $actor,
    ): ProductWarehouseSetting {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureProductBelongsToTenant(
            product: $product,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeWarehouseSetting(
            $data,
        );

        return DB::transaction(
            function () use (
                $product,
                $actor,
                $normalized,
            ): ProductWarehouseSetting {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$lockedProduct->isStockItem()) {
                    throw ValidationException::withMessages([
                        'product' => [
                            'Warehouse configuration is only available for stock products.',
                        ],
                    ]);
                }

                $branch = $this->resolveAccessibleBranch(
                    actor: $actor,
                    branchId: $normalized['branch_id'],
                );

                $warehouse = $this->resolveWarehouse(
                    warehouseId:
                        $normalized['warehouse_id'],
                    branch: $branch,
                );

                $branchSetting =
                    ProductBranchSetting::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey(),
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
                ) {
                    throw ValidationException::withMessages([
                        'branch_id' => [
                            'Configure the product for the selected branch before adding warehouse settings.',
                        ],
                    ]);
                }

                $this->ensureWarehouseSettingIsCompatible(
                    product: $lockedProduct,
                    branch: $branch,
                    warehouse: $warehouse,
                    branchSetting: $branchSetting,
                    status: $normalized['status'],
                );

                $setting = ProductWarehouseSetting::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey(),
                    )
                    ->where(
                        'warehouse_id',
                        $warehouse->getKey(),
                    )
                    ->lockForUpdate()
                    ->first();

                $attributes = [
                    'product_id' =>
                        $lockedProduct->getKey(),

                    'branch_id' => $branch->getKey(),

                    'warehouse_id' =>
                        $warehouse->getKey(),

                    'status' => $normalized['status'],

                    'minimum_stock' =>
                        $normalized['minimum_stock'],

                    'reorder_level' =>
                        $normalized['reorder_level'],

                    'maximum_stock' =>
                        $normalized['maximum_stock'],

                    'bin_location' =>
                        $normalized['bin_location'],

                    'allow_negative_stock' =>
                        $normalized['allow_negative_stock'],
                ];

                if (
                    !$setting
                        instanceof ProductWarehouseSetting
                ) {
                    return ProductWarehouseSetting::query()
                        ->create($attributes);
                }

                $setting->fill($attributes);
                $setting->save();

                return $setting->refresh();
            },
            attempts: 5,
        );
    }

    public function deleteWarehouseSetting(
        ProductWarehouseSetting $setting,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureWarehouseSettingBelongsToTenant(
            setting: $setting,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $setting,
                $actor,
            ): void {
                $lockedSetting = ProductWarehouseSetting::query()
                    ->whereKey($setting->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $branch = Branch::query()
                    ->whereKey($lockedSetting->branch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->branchAccessService->authorizeBranch(
                    user: $actor,
                    branch: $branch,
                    requireActive: false,
                );

                /*
                 * Inventory-balance and transaction-reference checks belong
                 * here when the inventory ledger is introduced.
                 */
                $lockedSetting->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     branch_id: int,
     *     status: string,
     *     is_purchasable: bool,
     *     is_sellable: bool,
     *     selling_price: string|null
     * }
     */
    private function normalizeBranchSetting(
        array $data,
    ): array {
        $branchId = $this->normalizeRequiredId(
            value: $data['branch_id'] ?? null,
            field: 'branch_id',
            message: 'The selected branch is invalid.',
        );

        $status = $this->normalizeStatus(
            $data['status'] ?? 'active',
        );

        return [
            'branch_id' => $branchId,
            'status' => $status,

            'is_purchasable' =>
                $this->normalizeBoolean(
                    $data['is_purchasable'] ?? true,
                ),

            'is_sellable' =>
                $this->normalizeBoolean(
                    $data['is_sellable'] ?? true,
                ),

            'selling_price' =>
                $this->normalizeNullableDecimal(
                    value:
                        $data['selling_price'] ?? null,
                    field: 'selling_price',
                    label: 'branch selling price',
                ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     branch_id: int,
     *     warehouse_id: int,
     *     status: string,
     *     minimum_stock: string,
     *     reorder_level: string,
     *     maximum_stock: string|null,
     *     bin_location: string|null,
     *     allow_negative_stock: bool
     * }
     */
    private function normalizeWarehouseSetting(
        array $data,
    ): array {
        $branchId = $this->normalizeRequiredId(
            value: $data['branch_id'] ?? null,
            field: 'branch_id',
            message: 'The selected branch is invalid.',
        );

        $warehouseId = $this->normalizeRequiredId(
            value: $data['warehouse_id'] ?? null,
            field: 'warehouse_id',
            message: 'The selected warehouse is invalid.',
        );

        $minimumStock = $this->normalizeDecimal(
            value: $data['minimum_stock'] ?? 0,
            field: 'minimum_stock',
            label: 'minimum stock',
        );

        $reorderLevel = $this->normalizeDecimal(
            value: $data['reorder_level'] ?? 0,
            field: 'reorder_level',
            label: 'reorder level',
        );

        $maximumStock = $this->normalizeNullableDecimal(
            value: $data['maximum_stock'] ?? null,
            field: 'maximum_stock',
            label: 'maximum stock',
        );

        if (
            $this->compareDecimals(
                $reorderLevel,
                $minimumStock,
            ) < 0
        ) {
            throw ValidationException::withMessages([
                'reorder_level' => [
                    'The reorder level must be greater than or equal to the minimum stock.',
                ],
            ]);
        }

        if (
            $maximumStock !== null
            && $this->compareDecimals(
                $maximumStock,
                $reorderLevel,
            ) < 0
        ) {
            throw ValidationException::withMessages([
                'maximum_stock' => [
                    'The maximum stock must be greater than or equal to the reorder level.',
                ],
            ]);
        }

        $binLocation = $this->nullableTrimmedString(
            $data['bin_location'] ?? null,
        );

        if (
            $binLocation !== null
            && mb_strlen($binLocation) > 120
        ) {
            throw ValidationException::withMessages([
                'bin_location' => [
                    'The bin location may not exceed 120 characters.',
                ],
            ]);
        }

        return [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,

            'status' => $this->normalizeStatus(
                $data['status'] ?? 'active',
            ),

            'minimum_stock' => $minimumStock,
            'reorder_level' => $reorderLevel,
            'maximum_stock' => $maximumStock,
            'bin_location' => $binLocation,

            'allow_negative_stock' =>
                $this->normalizeBoolean(
                    $data['allow_negative_stock']
                        ?? false,
                ),
        ];
    }

    private function resolveAccessibleBranch(
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
            requireActive: false,
        );

        return $branch;
    }

    private function resolveWarehouse(
        int $warehouseId,
        Branch $branch,
    ): Warehouse {
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

        return $warehouse;
    }

    private function ensureBranchSettingIsCompatible(
        Product $product,
        Branch $branch,
        string $status,
        bool $isPurchasable,
        bool $isSellable,
    ): void {
        if (
            $status === 'active'
            && $product->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'An inactive product cannot have an active branch configuration.',
                ],
            ]);
        }

        if (
            $status === 'active'
            && $branch->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'An active product configuration requires an active branch.',
                ],
            ]);
        }

        if (
            $isPurchasable
            && !$product->is_purchasable
        ) {
            throw ValidationException::withMessages([
                'is_purchasable' => [
                    'Purchasing is disabled on the product master record.',
                ],
            ]);
        }

        if (
            $isSellable
            && !$product->is_sellable
        ) {
            throw ValidationException::withMessages([
                'is_sellable' => [
                    'Selling is disabled on the product master record.',
                ],
            ]);
        }
    }

    private function ensureBranchSettingCanBeChanged(
        Product $product,
        Branch $branch,
        string $newStatus,
    ): void {
        if ($newStatus !== 'inactive') {
            return;
        }

        $hasActiveWarehouseSettings =
            ProductWarehouseSetting::query()
                ->where(
                    'product_id',
                    $product->getKey(),
                )
                ->where(
                    'branch_id',
                    $branch->getKey(),
                )
                ->where('status', 'active')
                ->exists();

        if (!$hasActiveWarehouseSettings) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Deactivate the product warehouse configurations for this branch first.',
            ],
        ]);
    }

    private function ensureWarehouseSettingIsCompatible(
        Product $product,
        Branch $branch,
        Warehouse $warehouse,
        ProductBranchSetting $branchSetting,
        string $status,
    ): void {
        if ($status !== 'active') {
            return;
        }

        if ($product->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => [
                    'An inactive product cannot have an active warehouse configuration.',
                ],
            ]);
        }

        if (!$branchSetting->isActive()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Activate the product branch configuration before activating a warehouse configuration.',
                ],
            ]);
        }

        if ($branch->status !== 'active') {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'An active warehouse configuration requires an active branch.',
                ],
            ]);
        }

        if ($warehouse->status !== 'active') {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'An active product configuration requires an active warehouse.',
                ],
            ]);
        }
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

    private function ensureBranchSettingBelongsToTenant(
        ProductBranchSetting $setting,
        int $tenantId,
    ): void {
        if ((int) $setting->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected product branch setting belongs to another tenant.',
        );
    }

    private function ensureWarehouseSettingBelongsToTenant(
        ProductWarehouseSetting $setting,
        int $tenantId,
    ): void {
        if ((int) $setting->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected product warehouse setting belongs to another tenant.',
        );
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = mb_strtolower(
            trim((string) $value),
        );

        if (
            in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            return $status;
        }

        throw ValidationException::withMessages([
            'status' => [
                'The selected configuration status is invalid.',
            ],
        ]);
    }

    private function normalizeRequiredId(
        mixed $value,
        string $field,
        string $message,
    ): int {
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

    private function normalizeDecimal(
        mixed $value,
        string $field,
        string $label,
    ): string {
        $normalized = $this->normalizeNullableDecimal(
            value: $value,
            field: $field,
            label: $label,
        );

        if ($normalized !== null) {
            return $normalized;
        }

        throw ValidationException::withMessages([
            $field => [
                "The {$label} is required.",
            ],
        ]);
    }

    private function normalizeNullableDecimal(
        mixed $value,
        string $field,
        string $label,
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

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
                    "The {$label} must be a non-negative number with no more than 6 decimal places.",
                ],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

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

    private function compareDecimals(
        string $left,
        string $right,
    ): int {
        [$leftWhole, $leftFraction] = explode(
            '.',
            $left,
            2,
        );

        [$rightWhole, $rightFraction] = explode(
            '.',
            $right,
            2,
        );

        $leftWhole = ltrim($leftWhole, '0');
        $rightWhole = ltrim($rightWhole, '0');

        $leftWhole = $leftWhole === ''
            ? '0'
            : $leftWhole;

        $rightWhole = $rightWhole === ''
            ? '0'
            : $rightWhole;

        $lengthComparison = strlen($leftWhole)
            <=> strlen($rightWhole);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        $wholeComparison = strcmp(
            $leftWhole,
            $rightWhole,
        );

        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }

        return strcmp(
            str_pad($leftFraction, 6, '0'),
            str_pad($rightFraction, 6, '0'),
        ) <=> 0;
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
<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreProductBranchSettingRequest;
use App\Http\Requests\MasterData\StoreProductWarehouseSettingRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MasterData\ProductLocationConfigurationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductLocationConfigurationController extends Controller
{
    public function __construct(
        private readonly ProductLocationConfigurationService $configurationService,
        private readonly BranchAccessService $branchAccessService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function show(Product $product): Response
    {
        Gate::authorize('update', $product);

        $actor = request()->user();

        abort_unless($actor instanceof User, 401);

        $branches = $this->branchAccessService
            ->accessibleBranches(
                user: $actor,
                activeOnly: false,
            );

        $branchIds = $branches->modelKeys();

        $warehouses = Warehouse::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('branch_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
            ]);

        $branchSettings = $this->branchAccessService
            ->scopeQuery(
                query: ProductBranchSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    ),
                user: $actor,
                branchColumn: 'branch_id',
            )
            ->with('branch:id,name,code,status')
            ->orderBy('branch_id')
            ->get();

        $warehouseSettings = $this->branchAccessService
            ->scopeQuery(
                query: ProductWarehouseSetting::query()
                    ->where(
                        'product_id',
                        $product->getKey(),
                    ),
                user: $actor,
                branchColumn: 'branch_id',
            )
            ->with([
                'branch:id,name,code,status',
                'warehouse:id,branch_id,name,code,status',
            ])
            ->orderBy('branch_id')
            ->orderBy('warehouse_id')
            ->get();

        return Inertia::render(
            'Products/Locations',
            [
                'product' => [
                    'id' => (int) $product->getKey(),
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'product_type' => $product->product_type,
                    'status' => $product->status,

                    'is_purchasable' =>
                        (bool) $product->is_purchasable,

                    'is_sellable' =>
                        (bool) $product->is_sellable,

                    'selling_price' =>
                        (string) $product->selling_price,
                ],

                'branches' => $branches
                    ->map(
                        static fn (Branch $branch): array => [
                            'id' => (int) $branch->getKey(),
                            'name' => $branch->name,
                            'code' => $branch->code,
                            'status' => $branch->status,
                        ],
                    )
                    ->values()
                    ->all(),

                'warehouses' => $warehouses
                    ->map(
                        static fn (
                            Warehouse $warehouse,
                        ): array => [
                            'id' => (int) $warehouse->getKey(),

                            'branch_id' =>
                                (int) $warehouse->branch_id,

                            'name' => $warehouse->name,
                            'code' => $warehouse->code,
                            'status' => $warehouse->status,
                        ],
                    )
                    ->values()
                    ->all(),

                'branchSettings' => $branchSettings
                    ->map(
                        fn (
                            ProductBranchSetting $setting,
                        ): array => [
                            'id' => (int) $setting->getKey(),

                            'branch_id' =>
                                (int) $setting->branch_id,

                            'branch_name' =>
                                $setting->branch?->name
                                    ?? 'Unavailable',

                            'branch_code' =>
                                $setting->branch?->code
                                    ?? '—',

                            'branch_status' =>
                                $setting->branch?->status
                                    ?? 'inactive',

                            'status' => $setting->status,

                            'is_purchasable' =>
                                (bool) $setting->is_purchasable,

                            'is_sellable' =>
                                (bool) $setting->is_sellable,

                            'selling_price' =>
                                $setting->selling_price !== null
                                    ? (string) $setting->selling_price
                                    : null,

                            'effective_selling_price' =>
                                $setting->effectiveSellingPrice(
                                    $product,
                                ),
                        ],
                    )
                    ->values()
                    ->all(),

                'warehouseSettings' => $warehouseSettings
                    ->map(
                        static fn (
                            ProductWarehouseSetting $setting,
                        ): array => [
                            'id' => (int) $setting->getKey(),

                            'branch_id' =>
                                (int) $setting->branch_id,

                            'branch_name' =>
                                $setting->branch?->name
                                    ?? 'Unavailable',

                            'warehouse_id' =>
                                (int) $setting->warehouse_id,

                            'warehouse_name' =>
                                $setting->warehouse?->name
                                    ?? 'Unavailable',

                            'warehouse_code' =>
                                $setting->warehouse?->code
                                    ?? '—',

                            'warehouse_status' =>
                                $setting->warehouse?->status
                                    ?? 'inactive',

                            'status' => $setting->status,

                            'minimum_stock' =>
                                (string) $setting->minimum_stock,

                            'reorder_level' =>
                                (string) $setting->reorder_level,

                            'maximum_stock' =>
                                $setting->maximum_stock !== null
                                    ? (string) $setting->maximum_stock
                                    : null,

                            'bin_location' =>
                                $setting->bin_location,

                            'allow_negative_stock' =>
                                (bool) $setting
                                    ->allow_negative_stock,
                        ],
                    )
                    ->values()
                    ->all(),

                'statusOptions' => [
                    [
                        'value' => 'active',
                        'label' => 'Active',
                    ],
                    [
                        'value' => 'inactive',
                        'label' => 'Inactive',
                    ],
                ],
            ],
        );
    }

    public function storeBranchSetting(
        StoreProductBranchSettingRequest $request,
        Product $product,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $product);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $setting = $this->configurationService
            ->saveBranchSetting(
                product: $product,
                data: $request->validated(),
                actor: $actor,
            );

        return $this->responseService->success(
            message:
                'Product branch configuration saved successfully.',

            data: [
                'id' => (int) $setting->getKey(),
            ],

            redirectTo: route(
                'products.locations.show',
                $product,
            ),
        );
    }

    public function destroyBranchSetting(
        Product $product,
        ProductBranchSetting $productBranchSetting,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $product);

        abort_unless(
            (int) $productBranchSetting->product_id
                === (int) $product->getKey(),
            404,
        );

        $actor = request()->user();

        abort_unless($actor instanceof User, 401);

        $this->configurationService
            ->deleteBranchSetting(
                setting: $productBranchSetting,
                actor: $actor,
            );

        return $this->responseService->success(
            message:
                'Product branch configuration removed successfully.',

            redirectTo: route(
                'products.locations.show',
                $product,
            ),
        );
    }

    public function storeWarehouseSetting(
        StoreProductWarehouseSettingRequest $request,
        Product $product,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $product);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $setting = $this->configurationService
            ->saveWarehouseSetting(
                product: $product,
                data: $request->validated(),
                actor: $actor,
            );

        return $this->responseService->success(
            message:
                'Product warehouse configuration saved successfully.',

            data: [
                'id' => (int) $setting->getKey(),
            ],

            redirectTo: route(
                'products.locations.show',
                $product,
            ),
        );
    }

    public function destroyWarehouseSetting(
        Product $product,
        ProductWarehouseSetting $productWarehouseSetting,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $product);

        abort_unless(
            (int) $productWarehouseSetting->product_id
                === (int) $product->getKey(),
            404,
        );

        $actor = request()->user();

        abort_unless($actor instanceof User, 401);

        $this->configurationService
            ->deleteWarehouseSetting(
                setting: $productWarehouseSetting,
                actor: $actor,
            );

        return $this->responseService->success(
            message:
                'Product warehouse configuration removed successfully.',

            redirectTo: route(
                'products.locations.show',
                $product,
            ),
        );
    }
}
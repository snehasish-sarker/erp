<?php

declare(strict_types=1);

namespace App\Services\Organisation;

use App\Models\Branch;
use App\Models\ProductBranchSetting;
use App\Models\PurchaseOrder;
use App\Services\Saas\SaasUsageLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BranchService
{
    public function __construct(
        private readonly SaasUsageLimitService $saasUsageLimitService,
    ) {
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     status: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * } $attributes
     */
    public function create(array $attributes): Branch
    {
        return DB::transaction(
            function () use ($attributes): Branch {
                $this->saasUsageLimitService
                    ->assertCanCreateBranch();

                return Branch::query()->create(
                    $attributes,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     status: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * } $attributes
     */
    public function update(
        Branch $branch,
        array $attributes,
    ): Branch {
        return DB::transaction(
            function () use (
                $branch,
                $attributes,
            ): Branch {
                $lockedBranch = Branch::query()
                    ->whereKey($branch->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureStatusCanChange(
                    branch: $lockedBranch,
                    nextStatus: $attributes['status'],
                );

                $lockedBranch->fill($attributes);
                $lockedBranch->save();

                return $lockedBranch->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(Branch $branch): void
    {
        DB::transaction(
            function () use ($branch): void {
                $lockedBranch = Branch::query()
                    ->whereKey($branch->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedBranch->warehouses()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'The branch cannot be deleted while it has warehouses.',
                        ],
                    ]);
                }

                if (
                    $lockedBranch->users()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'The branch cannot be deleted while users are assigned to it.',
                        ],
                    ]);
                }

                if (
                    $lockedBranch->documentSequences()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'The branch cannot be deleted while document number sequences are assigned to it.',
                        ],
                    ]);
                }

                $hasProductSettings =
                    ProductBranchSetting::query()
                        ->where(
                            'branch_id',
                            $lockedBranch->getKey(),
                        )
                        ->exists();

                if ($hasProductSettings) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'The branch cannot be deleted while product configurations are assigned to it.',
                        ],
                    ]);
                }

                $hasPurchaseOrders =
                    PurchaseOrder::query()
                        ->withTrashed()
                        ->where(
                            'branch_id',
                            $lockedBranch->getKey(),
                        )
                        ->exists();

                if ($hasPurchaseOrders) {
                    throw ValidationException::withMessages([
                        'branch' => [
                            'The branch cannot be deleted because it is referenced by one or more purchase orders.',
                        ],
                    ]);
                }

                /*
                 * Add goods-receipt, supplier-invoice, inventory-ledger,
                 * sales-document, payment, and accounting usage checks as
                 * those modules are introduced.
                 */
                $lockedBranch->delete();
            },
            attempts: 5,
        );
    }

    private function ensureStatusCanChange(
        Branch $branch,
        string $nextStatus,
    ): void {
        if (
            $nextStatus === 'active'
            || $branch->status === $nextStatus
        ) {
            return;
        }

        $hasActiveProductSettings =
            ProductBranchSetting::query()
                ->where(
                    'branch_id',
                    $branch->getKey(),
                )
                ->where('status', 'active')
                ->exists();

        if ($hasActiveProductSettings) {
            throw ValidationException::withMessages([
                'status' => [
                    'Deactivate all product branch configurations before making this branch inactive or archived.',
                ],
            ]);
        }

        $hasUnfinishedPurchaseOrders =
            PurchaseOrder::query()
                ->where(
                    'branch_id',
                    $branch->getKey(),
                )
                ->whereIn(
                    'status',
                    [
                        'draft',
                        'submitted',
                        'approved',
                        'partially_received',
                        'received',
                    ],
                )
                ->exists();

        if (!$hasUnfinishedPurchaseOrders) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'The branch cannot be made inactive or archived while it has unfinished purchase orders.',
            ],
        ]);
    }
}
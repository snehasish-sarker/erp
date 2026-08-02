<?php

declare(strict_types=1);

namespace App\Services\Organisation;

use App\Models\Branch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class BranchAccessService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function hasCompanyWideAccess(
        User $user,
    ): bool {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        return $user->branch_id === null
            || $user->can('branches.access_all');
    }

    public function canAccessBranch(
        User $user,
        Branch $branch,
        bool $requireActive = false,
    ): bool {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        if (
            (int) $branch->tenant_id
            !== (int) $user->tenant_id
        ) {
            return false;
        }

        if (
            $requireActive
            && $branch->status !== 'active'
        ) {
            return false;
        }

        if ($this->hasCompanyWideAccess($user)) {
            return true;
        }

        return $user->branch_id !== null
            && (int) $user->branch_id
                === (int) $branch->getKey();
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeBranch(
        User $user,
        Branch $branch,
        bool $requireActive = false,
    ): void {
        if (
            $this->canAccessBranch(
                user: $user,
                branch: $branch,
                requireActive: $requireActive,
            )
        ) {
            return;
        }

        throw new AuthorizationException(
            'You are not authorized to access the selected branch.',
        );
    }

    public function findAccessibleBranch(
        User $user,
        int $branchId,
        bool $requireActive = true,
    ): ?Branch {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        if ($branchId < 1) {
            return null;
        }

        $branch = Branch::query()
            ->whereKey($branchId)
            ->when(
                $requireActive,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'status',
                    'active',
                ),
            )
            ->first();

        if (!$branch instanceof Branch) {
            return null;
        }

        return $this->canAccessBranch(
            user: $user,
            branch: $branch,
            requireActive: $requireActive,
        )
            ? $branch
            : null;
    }

    /**
     * Restrict a query containing a branch ownership column.
     *
     * Examples:
     *
     * $service->scopeQuery(
     *     PurchaseOrder::query(),
     *     $user,
     * );
     *
     * $service->scopeQuery(
     *     $query,
     *     $user,
     *     'purchase_orders.branch_id',
     * );
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public function scopeQuery(
        Builder $query,
        User $user,
        string $branchColumn = 'branch_id',
    ): Builder {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        $this->ensureValidColumnName(
            $branchColumn,
        );

        if ($this->hasCompanyWideAccess($user)) {
            return $query;
        }

        if ($user->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        $qualifiedColumn = str_contains(
            $branchColumn,
            '.',
        )
            ? $branchColumn
            : $query
                ->getModel()
                ->qualifyColumn($branchColumn);

        return $query->where(
            $qualifiedColumn,
            $user->branch_id,
        );
    }

    /**
     * Scope the Branch model itself.
     *
     * @param Builder<Branch> $query
     * @return Builder<Branch>
     */
    public function scopeBranchQuery(
        Builder $query,
        User $user,
    ): Builder {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        if ($this->hasCompanyWideAccess($user)) {
            return $query;
        }

        if ($user->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey(
            $user->branch_id,
        );
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(
        User $user,
        bool $activeOnly = true,
    ): Collection {
        $query = $this->scopeBranchQuery(
            query: Branch::query(),
            user: $user,
        );

        return $query
            ->when(
                $activeOnly,
                static fn (
                    Builder $branchQuery,
                ): Builder => $branchQuery->where(
                    'status',
                    'active',
                ),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function assignedBranch(
        User $user,
    ): ?Branch {
        $this->ensureUserBelongsToActiveTenant(
            $user,
        );

        if ($user->branch_id === null) {
            return null;
        }

        return Branch::query()
            ->whereKey($user->branch_id)
            ->first();
    }

    private function ensureUserBelongsToActiveTenant(
        User $user,
    ): void {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw new LogicException(
                'Tenant context has not been initialized.',
            );
        }

        if ((int) $user->tenant_id !== $tenantId) {
            throw new LogicException(
                'The user does not belong to the active tenant.',
            );
        }
    }

    private function ensureValidColumnName(
        string $column,
    ): void {
        if (
            preg_match(
                '/^[A-Za-z_][A-Za-z0-9_.]*$/',
                $column,
            ) === 1
        ) {
            return;
        }

        throw new LogicException(
            'The branch query column is invalid.',
        );
    }
}
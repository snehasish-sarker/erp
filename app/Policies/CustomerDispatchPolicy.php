<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerDispatch;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class CustomerDispatchPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(
        User $user,
    ): bool {
        return $user->can(
            'dispatches.view',
        );
    }

    public function view(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->canAccess(
            user: $user,
            dispatch: $dispatch,
        )
            && $user->can(
                'dispatches.view',
            );
    }

    public function create(
        User $user,
    ): bool {
        return $user->can(
            'dispatches.create',
        );
    }

    public function update(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->canAccess(
            user: $user,
            dispatch: $dispatch,
        )
            && $dispatch->canBeEdited()
            && $user->can(
                'dispatches.create',
            );
    }

    public function delete(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->canAccess(
            user: $user,
            dispatch: $dispatch,
        )
            && $dispatch->canBeDeleted()
            && $user->can(
                'dispatches.create',
            );
    }

    public function post(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->canAccess(
            user: $user,
            dispatch: $dispatch,
        )
            && $dispatch->canBePosted()
            && $user->can(
                'dispatches.post',
            );
    }

    public function reverse(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->canAccess(
            user: $user,
            dispatch: $dispatch,
        )
            && $dispatch->canBeReversed()
            && $user->can(
                'dispatches.reverse',
            );
    }

    public function print(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return $this->view(
            user: $user,
            dispatch: $dispatch,
        );
    }

    private function canAccess(
        User $user,
        CustomerDispatch $dispatch,
    ): bool {
        return (int) $user->tenant_id
            === (int) $dispatch->tenant_id
            && $this->branchAccessService
                ->accessibleBranches(
                    user: $user,
                    activeOnly: false,
                )
                ->contains(
                    'id',
                    (int) $dispatch->branch_id,
                );
    }
}
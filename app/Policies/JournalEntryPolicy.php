<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\GeneralLedgerRegistry;

final class JournalEntryPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly GeneralLedgerRegistry $registry,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('journals.view');
    }

    public function view(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        ) && $user->can('journals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create');
    }

    public function update(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $journalEntry->canBeEdited()
            && $user->can('journals.create');
    }

    public function delete(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $journalEntry->canBeDeleted()
            && $user->can('journals.create');
    }

    public function approve(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $this->registry->canApprove(
                $journalEntry->status,
            )
            && $user->can('journals.approve');
    }

    public function returnToDraft(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $this->registry->canReturnToDraft(
                $journalEntry->status,
            )
            && $user->can('journals.approve');
    }

    public function post(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $this->registry->canPost(
                $journalEntry->status,
            )
            && $user->can('journals.post');
    }

    public function reverse(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $this->registry->canReverse(
                $journalEntry->status,
            )
            && $user->can('journals.reverse');
    }

    public function cancel(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        return $this->canAccess(
            user: $user,
            journalEntry: $journalEntry,
        )
            && $this->registry->canCancel(
                $journalEntry->status,
            )
            && $user->can('journals.create');
    }

    private function canAccess(
        User $user,
        JournalEntry $journalEntry,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $journalEntry->tenant_id
        ) {
            return false;
        }

        return $this->branchAccessService
            ->findAccessibleBranch(
                user: $user,
                branchId: (int) $journalEntry->branch_id,
                requireActive: false,
            ) !== null;
    }
}
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierDebitNote;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Purchasing\SupplierDebitNoteStatusRegistry;

final class SupplierDebitNotePolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly SupplierDebitNoteStatusRegistry $statusRegistry,
    ) {
    }

    public function viewAny(
        User $user,
    ): bool {
        return $user->can(
            'supplier_debit_notes.view',
        );
    }

    public function view(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $user->can(
                'supplier_debit_notes.view',
            );
    }

    public function create(
        User $user,
    ): bool {
        return $user->can(
            'supplier_debit_notes.create',
        );
    }

    public function update(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->isEditable(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.update',
            );
    }

    public function delete(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $supplierDebitNote
                ->canBeDeleted()
            && $user->can(
                'supplier_debit_notes.delete',
            );
    }

    public function submit(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canSubmit(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.submit',
            );
    }

    public function returnToDraft(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canReturnToDraft(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.submit',
            );
    }

    public function approve(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canApprove(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.approve',
            );
    }

    public function post(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canPost(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.post',
            );
    }

    public function reverse(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canReverse(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.reverse',
            );
    }

    public function cancel(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierDebitNote:
                $supplierDebitNote,
        )
            && $this->statusRegistry
                ->canCancel(
                    $supplierDebitNote
                        ->status,
                )
            && $user->can(
                'supplier_debit_notes.cancel',
            );
    }

    private function canAccess(
        User $user,
        SupplierDebitNote $supplierDebitNote,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $supplierDebitNote
                ->tenant_id
        ) {
            return false;
        }

        return $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->contains(
                'id',
                (int) $supplierDebitNote
                    ->branch_id,
            );
    }
}
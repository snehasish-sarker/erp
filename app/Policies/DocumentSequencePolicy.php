<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentSequence;
use App\Models\User;

final class DocumentSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('document_numbering.view');
    }

    public function view(
        User $user,
        DocumentSequence $documentSequence,
    ): bool {
        return $user->tenant_id
                === $documentSequence->tenant_id
            && $user->can('document_numbering.view');
    }

    public function create(User $user): bool
    {
        return $user->can(
            'document_numbering.create',
        );
    }

    public function update(
        User $user,
        DocumentSequence $documentSequence,
    ): bool {
        return $user->tenant_id
                === $documentSequence->tenant_id
            && $user->can(
                'document_numbering.update',
            );
    }

    public function delete(
        User $user,
        DocumentSequence $documentSequence,
    ): bool {
        return $user->tenant_id
                === $documentSequence->tenant_id
            && $user->can(
                'document_numbering.delete',
            );
    }
}
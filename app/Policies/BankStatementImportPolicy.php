<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankStatementImport;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class BankStatementImportPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('bank_statements.view');
    }

    public function view(User $user, BankStatementImport $statement): bool
    {
        return $user->can('bank_statements.view') && $this->canAccess($user, $statement);
    }

    public function import(User $user): bool
    {
        return $user->can('bank_statements.import');
    }

    public function delete(User $user, BankStatementImport $statement): bool
    {
        return $user->can('bank_statements.delete')
            && $statement->status === 'imported'
            && $statement->reconciliations()->doesntExist()
            && $this->canAccess($user, $statement);
    }

    private function canAccess(User $user, BankStatementImport $statement): bool
    {
        return (int) $user->tenant_id === (int) $statement->tenant_id
            && $this->branchAccessService
                ->accessibleBranches($user, false)
                ->contains('id', (int) $statement->branch_id);
    }
}

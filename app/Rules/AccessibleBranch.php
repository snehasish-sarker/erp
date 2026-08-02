<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class AccessibleBranch implements ValidationRule
{
    public function __construct(
        private User $user,
        private BranchAccessService $branchAccessService,
        private bool $requireActive = true,
    ) {
    }

    /**
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (
            !is_int($value)
            && !is_string($value)
        ) {
            $fail(
                'The selected branch is invalid.',
            );

            return;
        }

        if (
            !is_numeric($value)
            || (int) $value < 1
        ) {
            $fail(
                'The selected branch is invalid.',
            );

            return;
        }

        $branch = $this
            ->branchAccessService
            ->findAccessibleBranch(
                user: $this->user,
                branchId: (int) $value,
                requireActive:
                    $this->requireActive,
            );

        if ($branch !== null) {
            return;
        }

        $fail(
            $this->requireActive
                ? 'The selected branch is unavailable or outside your access.'
                : 'The selected branch is outside your access.',
        );
    }
}
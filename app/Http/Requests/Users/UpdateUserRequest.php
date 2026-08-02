<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('managedUser');

        return $managedUser instanceof User
            && $this->user()?->can(
                'update',
                $managedUser,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $managedUser = $this->route('managedUser');

        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        $currentBranchId = $managedUser instanceof User
            ? $managedUser->branch_id
            : null;

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static function (
                            Builder $query,
                        ) use (
                            $tenantId,
                            $currentBranchId,
                        ): Builder {
                            return $query
                                ->where('tenant_id', $tenantId)
                                ->whereNull('deleted_at')
                                ->where(
                                    static function (
                                        Builder $branchQuery,
                                    ) use ($currentBranchId): void {
                                        $branchQuery->where(
                                            'status',
                                            '!=',
                                            'archived',
                                        );

                                        if ($currentBranchId !== null) {
                                            $branchQuery->orWhere(
                                                'id',
                                                $currentBranchId,
                                            );
                                        }
                                    },
                                );
                        },
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query->where(
                                'tenant_id',
                                $tenantId,
                            ),
                    )
                    ->ignore(
                        $managedUser instanceof User
                            ? $managedUser->getKey()
                            : null,
                    ),
            ],
            'role_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where('tenant_id', $tenantId)
                                ->where('guard_name', 'web'),
                    ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id')
                ? $this->integer('branch_id')
                : null,
            'name' => trim(
                $this->string('name')->toString(),
            ),
            'email' => Str::lower(
                trim(
                    $this->string('email')->toString(),
                ),
            ),
        ]);
    }
}
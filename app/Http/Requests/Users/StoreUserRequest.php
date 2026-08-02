<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where('tenant_id', $tenantId)
                                ->whereNull('deleted_at')
                                ->where('status', '!=', 'archived'),
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
                    ),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers(),
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
            'status' => trim(
                $this->string('status')->toString(),
            ),
        ]);
    }
}
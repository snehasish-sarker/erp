<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.create') === true;
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('branches', 'code')
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
                    'archived',
                ]),
            ],
            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                $this->string('name')->toString(),
            ),
            'code' => Str::upper(
                trim(
                    $this->string('code')->toString(),
                ),
            ),
            'status' => trim(
                $this->string('status')->toString(),
            ),
            'email' => $this->nullableTrimmedString('email'),
            'phone' => $this->nullableTrimmedString('phone'),
            'address' => $this->nullableTrimmedString('address'),
        ]);
    }

    private function nullableTrimmedString(
        string $key,
    ): ?string {
        $value = trim(
            $this->string($key)->toString(),
        );

        return $value === '' ? null : $value;
    }
}
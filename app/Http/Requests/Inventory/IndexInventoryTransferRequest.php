<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'inventory.transfer',
        ) === true;
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
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static fn (
                            Builder $query,
                        ): Builder => $query
                            ->where('tenant_id', $tenantId)
                            ->whereNull('deleted_at'),
                    ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'draft',
                    'posted',
                    'cancelled',
                ]),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'transfer_date',
                    'transfer_number',
                    'status',
                    'created_at',
                ]),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'required',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableString('search'),
            'branch_id' => $this->filled('branch_id')
                ? $this->input('branch_id')
                : null,
            'status' => $this->nullableString(
                'status',
                lowercase: true,
            ),
            'sort' => $this->filled('sort')
                ? trim((string) $this->input('sort'))
                : 'transfer_date',
            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim((string) $this->input('direction')),
                )
                : 'desc',
            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(
        string $field,
        bool $lowercase = false,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim((string) $this->input($field));

        if ($value === '') {
            return null;
        }

        return $lowercase
            ? mb_strtolower($value)
            : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['draft', 'posted', 'cancelled']),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'adjustment_date',
                    'adjustment_number',
                    'status',
                    'created_at',
                ]),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'required',
                'integer',
                Rule::in([10, 25, 50, 100]),
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
            'warehouse_id' => $this->filled('warehouse_id')
                ? $this->input('warehouse_id')
                : null,
            'status' => $this->nullableString('status', true),
            'sort' => $this->filled('sort')
                ? trim((string) $this->input('sort'))
                : 'adjustment_date',
            'direction' => $this->filled('direction')
                ? mb_strtolower(trim((string) $this->input('direction')))
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

        return $lowercase ? mb_strtolower($value) : $value;
    }
}

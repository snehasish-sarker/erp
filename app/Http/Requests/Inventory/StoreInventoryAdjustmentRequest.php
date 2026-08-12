<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'adjustment_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
            'lines.*.adjustment_type' => [
                'required',
                'string',
                Rule::in(['increase', 'decrease']),
            ],
            'lines.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->map(
                static function (mixed $line): mixed {
                    if (!is_array($line)) {
                        return $line;
                    }

                    $line['adjustment_type'] = mb_strtolower(
                        trim((string) ($line['adjustment_type'] ?? '')),
                    );

                    return $line;
                },
            )
            ->values()
            ->all();

        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
            'lines' => $lines,
        ]);
    }
}

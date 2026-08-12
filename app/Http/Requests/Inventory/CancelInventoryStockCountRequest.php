<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class CancelInventoryStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.count') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
        ]);
    }
}

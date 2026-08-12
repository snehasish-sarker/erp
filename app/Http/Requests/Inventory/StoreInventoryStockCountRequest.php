<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.count') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenant()->getKey();

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(
                    static fn (Builder $query): Builder => $query
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'active')
                        ->whereNull('deleted_at'),
                ),
            ],
            'count_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(
                    static fn (Builder $query): Builder => $query
                        ->where('tenant_id', $tenantId)
                        ->where('product_type', 'stock')
                        ->where('status', 'active')
                        ->whereNull('deleted_at'),
                ),
            ],
            'lines.*.counted_quantity' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'warehouse_id' => $this->filled('warehouse_id')
                ? $this->input('warehouse_id')
                : null,
            'count_date' => trim((string) $this->input('count_date')),
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }
}

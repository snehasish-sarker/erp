<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryTransferRequest extends FormRequest
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

        $warehouseExists = static fn () => Rule::exists(
            'warehouses',
            'id',
        )->where(
            static fn (Builder $query): Builder => $query
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNull('deleted_at'),
        );

        return [
            'source_warehouse_id' => [
                'required',
                'integer',
                $warehouseExists(),
                'different:destination_warehouse_id',
            ],
            'destination_warehouse_id' => [
                'required',
                'integer',
                $warehouseExists(),
                'different:source_warehouse_id',
            ],
            'transfer_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where(
                        static fn (
                            Builder $query,
                        ): Builder => $query
                            ->where('tenant_id', $tenantId)
                            ->where('product_type', 'stock')
                            ->where('status', 'active')
                            ->whereNull('deleted_at'),
                    ),
            ],
            'lines.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
                'max:99999999999999.999999',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_warehouse_id' => $this->filled(
                'source_warehouse_id',
            )
                ? $this->input('source_warehouse_id')
                : null,
            'destination_warehouse_id' => $this->filled(
                'destination_warehouse_id',
            )
                ? $this->input('destination_warehouse_id')
                : null,
            'transfer_date' => trim(
                (string) $this->input('transfer_date'),
            ),
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }
}

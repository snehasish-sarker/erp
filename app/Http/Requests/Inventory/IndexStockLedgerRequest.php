<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexStockLedgerRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const MOVEMENT_TYPES = [
        'goods_receipt',
        'goods_receipt_reversal',
        'purchase_return',
        'purchase_return_reversal',
        'dispatch',
        'dispatch_reversal',
        'sales_return',
        'sales_return_reversal',
        'transfer_in',
        'transfer_out',
        'adjustment_in',
        'adjustment_out',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'inventory.view_ledger',
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

        $sortOptions = [
            'occurred_at',
            'document_number',
            'quantity_in',
            'quantity_out',
            'balance_quantity',
        ];

        if (
            $this->user()?->can(
                'inventory.view_cost',
            ) === true
        ) {
            $sortOptions[] = 'unit_cost';
            $sortOptions[] = 'total_cost';
            $sortOptions[] = 'balance_value';
        }

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
                            ->where(
                                'tenant_id',
                                $tenantId,
                            )
                            ->whereNull('deleted_at'),
                    ),
            ],

            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where(
                        static fn (
                            Builder $query,
                        ): Builder => $query
                            ->where(
                                'tenant_id',
                                $tenantId,
                            )
                            ->whereNull('deleted_at'),
                    ),
            ],

            'movement_type' => [
                'nullable',
                'string',
                Rule::in(self::MOVEMENT_TYPES),
            ],

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'sort' => [
                'required',
                'string',
                Rule::in($sortOptions),
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
            'search' => $this->nullableString(
                'search',
            ),

            'branch_id' => $this->filled('branch_id')
                ? $this->input('branch_id')
                : null,

            'warehouse_id' => $this->filled('warehouse_id')
                ? $this->input('warehouse_id')
                : null,

            'movement_type' => $this->nullableString(
                'movement_type',
                lowercase: true,
            ),

            'date_from' => $this->nullableString(
                'date_from',
            ),

            'date_to' => $this->nullableString(
                'date_to',
            ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'occurred_at',

            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'direction',
                        ),
                    ),
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

        $value = trim(
            (string) $this->input($field),
        );

        if ($value === '') {
            return null;
        }

        return $lowercase
            ? mb_strtolower($value)
            : $value;
    }
}
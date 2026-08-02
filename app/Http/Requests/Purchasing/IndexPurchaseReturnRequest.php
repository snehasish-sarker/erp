<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPurchaseReturnRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'draft',
        'submitted',
        'approved',
        'posted',
        'reversed',
        'cancelled',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'return_number',
        'return_date',
        'posting_date',
        'purchase_order_number',
        'goods_receipt_number',
        'supplier_name',
        'total_return_quantity',
        'total_supplier_value',
        'total_inventory_value',
        'status',
        'created_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            PurchaseReturn::class,
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],

            'branch_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'warehouse_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'supplier_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'purchase_order_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'goods_receipt_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'supplier_invoice_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(self::STATUSES),
            ],

            'return_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'return_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:return_date_from',
            ],

            'sort' => [
                'required',
                'string',
                Rule::in(self::SORTS),
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
                    15,
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
            'search' =>
                $this->nullableString(
                    'search',
                ),

            'branch_id' =>
                $this->nullableId(
                    'branch_id',
                ),

            'warehouse_id' =>
                $this->nullableId(
                    'warehouse_id',
                ),

            'supplier_id' =>
                $this->nullableId(
                    'supplier_id',
                ),

            'purchase_order_id' =>
                $this->nullableId(
                    'purchase_order_id',
                ),

            'goods_receipt_id' =>
                $this->nullableId(
                    'goods_receipt_id',
                ),

            'supplier_invoice_id' =>
                $this->nullableId(
                    'supplier_invoice_id',
                ),

            'status' =>
                $this->nullableLowercaseString(
                    'status',
                ),

            'return_date_from' =>
                $this->nullableString(
                    'return_date_from',
                ),

            'return_date_to' =>
                $this->nullableString(
                    'return_date_to',
                ),

            'sort' => $this->filled('sort')
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'sort',
                        ),
                    ),
                )
                : 'created_at',

            'direction' =>
                $this->filled('direction')
                    ? mb_strtolower(
                        trim(
                            (string) $this->input(
                                'direction',
                            ),
                        ),
                    )
                    : 'desc',

            'per_page' =>
                $this->filled('per_page')
                    ? $this->input(
                        'per_page',
                    )
                    : 15,
        ]);
    }

    private function nullableString(
        string $field,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input(
                $field,
            ),
        );

        return $value === ''
            ? null
            : $value;
    }

    private function nullableLowercaseString(
        string $field,
    ): ?string {
        $value = $this->nullableString(
            $field,
        );

        return $value === null
            ? null
            : mb_strtolower($value);
    }

    private function nullableId(
        string $field,
    ): int|string|null {
        if (!$this->filled($field)) {
            return null;
        }

        $value = $this->input($field);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value)
            ? trim($value)
            : $value;
    }
}
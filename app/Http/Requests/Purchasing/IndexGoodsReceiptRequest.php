<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexGoodsReceiptRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'draft',
        'posted',
        'reversed',
    ];

    /**
     * @var list<string>
     */
    private const INSPECTION_STATUSES = [
        'not_required',
        'pending',
        'passed',
        'partial',
        'failed',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'receipt_number',
        'receipt_date',
        'purchase_order_number',
        'supplier_name',
        'total_inventory_value',
        'status',
        'inspection_status',
        'created_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            GoodsReceipt::class,
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

            'status' => [
                'nullable',
                'string',
                Rule::in(self::STATUSES),
            ],

            'inspection_status' => [
                'nullable',
                'string',
                Rule::in(
                    self::INSPECTION_STATUSES,
                ),
            ],

            'receipt_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'receipt_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:receipt_date_from',
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

            'status' =>
                $this->nullableLowercaseString(
                    'status',
                ),

            'inspection_status' =>
                $this->nullableLowercaseString(
                    'inspection_status',
                ),

            'receipt_date_from' =>
                $this->nullableString(
                    'receipt_date_from',
                ),

            'receipt_date_to' =>
                $this->nullableString(
                    'receipt_date_to',
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

            'direction' => $this->filled(
                'direction',
            )
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'direction',
                        ),
                    ),
                )
                : 'desc',

            'per_page' => $this->filled(
                'per_page',
            )
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

        return $value !== null
            ? mb_strtolower($value)
            : null;
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
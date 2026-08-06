<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSalesOrderRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'draft',
        'submitted',
        'approved',
        'partially_allocated',
        'allocated',
        'partially_dispatched',
        'dispatched',
        'partially_invoiced',
        'invoiced',
        'closed',
        'cancelled',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'document_number',
        'order_date',
        'requested_delivery_date',
        'customer_name',
        'total_amount',
        'status',
        'created_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'sales_orders.view',
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

            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(self::STATUSES),
            ],

            'order_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'order_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:order_date_from',
            ],

            'requested_delivery_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'requested_delivery_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:requested_delivery_from',
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'warehouse_id' => 'warehouse',
            'customer_id' => 'customer',
            'order_date_from' => 'order date from',
            'order_date_to' => 'order date to',

            'requested_delivery_from' =>
                'requested delivery date from',

            'requested_delivery_to' =>
                'requested delivery date to',

            'per_page' => 'records per page',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableString(
                'search',
            ),

            'branch_id' => $this->nullableId(
                'branch_id',
            ),

            'warehouse_id' => $this->nullableId(
                'warehouse_id',
            ),

            'customer_id' => $this->nullableId(
                'customer_id',
            ),

            'status' => $this->nullableLowercaseString(
                'status',
            ),

            'order_date_from' =>
                $this->nullableString(
                    'order_date_from',
                ),

            'order_date_to' =>
                $this->nullableString(
                    'order_date_to',
                ),

            'requested_delivery_from' =>
                $this->nullableString(
                    'requested_delivery_from',
                ),

            'requested_delivery_to' =>
                $this->nullableString(
                    'requested_delivery_to',
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
                ? $this->input('per_page')
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
            (string) $this->input($field),
        );

        return $value === ''
            ? null
            : $value;
    }

    private function nullableLowercaseString(
        string $field,
    ): ?string {
        $value = $this->nullableString($field);

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

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }
}
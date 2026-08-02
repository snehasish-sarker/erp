<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSupplierInvoiceRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'draft',
        'validated',
        'approved',
        'posted',
        'disputed',
        'reversed',
        'cancelled',
    ];

    /**
     * @var list<string>
     */
    private const MATCH_STATUSES = [
        'unmatched',
        'matched',
        'variance',
        'blocked',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'document_number',
        'supplier_invoice_number',
        'invoice_date',
        'posting_date',
        'due_date',
        'purchase_order_number',
        'supplier_name',
        'total_amount',
        'status',
        'match_status',
        'created_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            SupplierInvoice::class,
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

            'match_status' => [
                'nullable',
                'string',
                Rule::in(
                    self::MATCH_STATUSES,
                ),
            ],

            'invoice_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'invoice_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:invoice_date_from',
            ],

            'due_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'due_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:due_date_from',
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

            'match_status' =>
                $this->nullableLowercaseString(
                    'match_status',
                ),

            'invoice_date_from' =>
                $this->nullableString(
                    'invoice_date_from',
                ),

            'invoice_date_to' =>
                $this->nullableString(
                    'invoice_date_to',
                ),

            'due_date_from' =>
                $this->nullableString(
                    'due_date_from',
                ),

            'due_date_to' =>
                $this->nullableString(
                    'due_date_to',
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
            (string) $this->input($field),
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
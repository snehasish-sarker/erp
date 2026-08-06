<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCustomerReceiptRequest extends FormRequest
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
    private const RECEIPT_METHODS = [
        'cash',
        'bank_transfer',
        'cheque',
        'mobile_financial_service',
        'other',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'receipt_number',
        'receipt_date',
        'posting_date',
        'customer_name',
        'receipt_account_name',
        'receipt_method',
        'currency_code',
        'total_amount',
        'allocated_amount',
        'unallocated_amount',
        'status',
        'created_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            CustomerReceipt::class,
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

            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'receipt_account_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(self::STATUSES),
            ],

            'receipt_method' => [
                'nullable',
                'string',
                Rule::in(self::RECEIPT_METHODS),
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
            'search' => $this->nullableString('search'),

            'branch_id' => $this->nullableId(
                'branch_id',
            ),

            'customer_id' => $this->nullableId(
                'customer_id',
            ),

            'receipt_account_id' => $this->nullableId(
                'receipt_account_id',
            ),

            'status' => $this->nullableLowercaseString(
                'status',
            ),

            'receipt_method' => $this->nullableLowercaseString(
                'receipt_method',
            ),

            'receipt_date_from' => $this->nullableString(
                'receipt_date_from',
            ),

            'receipt_date_to' => $this->nullableString(
                'receipt_date_to',
            ),

            'sort' => $this->filled('sort')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('sort'),
                    ),
                )
                : 'created_at',

            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('direction'),
                    ),
                )
                : 'desc',

            'per_page' => $this->filled('per_page')
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
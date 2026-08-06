<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            SalesInvoice::class,
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

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'posted',
                    'reversed',
                ]),
            ],

            'posting_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'posting_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:posting_date_from',
            ],

            'sort' => [
                'required',
                Rule::in([
                    'invoice_number',
                    'invoice_date',
                    'posting_date',
                    'due_date',
                    'customer_name',
                    'total_amount',
                    'status',
                    'created_at',
                ]),
            ],

            'direction' => [
                'required',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'required',
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
            'search' => $this->nullableString(
                'search',
            ),

            'branch_id' => $this->filled(
                'branch_id',
            )
                ? $this->input('branch_id')
                : null,

            'customer_id' => $this->filled(
                'customer_id',
            )
                ? $this->input('customer_id')
                : null,

            'status' => $this->nullableString(
                'status',
            ),

            'posting_date_from' =>
                $this->nullableString(
                    'posting_date_from',
                ),

            'posting_date_to' =>
                $this->nullableString(
                    'posting_date_to',
                ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'created_at',

            'direction' => $this->filled(
                'direction',
            )
                ? strtolower(
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
}
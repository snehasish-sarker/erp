<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerDispatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCustomerDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'viewAny',
            CustomerDispatch::class,
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

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'posted',
                    'reversed',
                ]),
            ],

            'dispatch_date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'dispatch_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:dispatch_date_from',
            ],

            'sort' => [
                'required',
                Rule::in([
                    'dispatch_number',
                    'dispatch_date',
                    'customer_name',
                    'sales_order_number',
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

            'status' => $this->nullableString(
                'status',
            ),

            'dispatch_date_from' =>
                $this->nullableString(
                    'dispatch_date_from',
                ),

            'dispatch_date_to' =>
                $this->nullableString(
                    'dispatch_date_to',
                ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input(
                        'sort',
                    ),
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
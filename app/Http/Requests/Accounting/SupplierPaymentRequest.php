<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SupplierPaymentRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const PAYMENT_METHODS = [
        'cash',
        'bank_transfer',
        'cheque',
        'mobile_financial_service',
        'other',
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'supplier_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'payment_account_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'payment_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:payment_date',
            ],

            'currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            'exchange_rate' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99999999',
                'decimal:0,8',
            ],

            'payment_method' => [
                'required',
                'string',
                Rule::in(self::PAYMENT_METHODS),
            ],

            'payment_reference' => [
                'nullable',
                'string',
                'max:160',
            ],

            'cheque_number' => [
                'nullable',
                'string',
                'max:100',
                'required_if:payment_method,cheque',
            ],

            'cheque_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_if:payment_method,cheque',
            ],

            'total_amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'allocations' => [
                'nullable',
                'array',
                'max:500',
            ],

            'allocations.*' => [
                'required',
                'array',
            ],

            'allocations.*.supplier_open_item_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],

            'allocations.*.amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
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
            'supplier_id' => 'supplier',
            'payment_account_id' => 'payment account',
            'payment_date' => 'payment date',
            'posting_date' => 'posting date',
            'currency_code' => 'currency code',
            'exchange_rate' => 'exchange rate',
            'payment_method' => 'payment method',
            'payment_reference' => 'payment reference',
            'cheque_number' => 'cheque number',
            'cheque_date' => 'cheque date',
            'total_amount' => 'payment amount',

            'allocations.*.supplier_open_item_id' =>
                'Supplier Invoice open item',

            'allocations.*.amount' =>
                'allocation amount',
        ];
    }

    protected function prepareForValidation(): void
    {
        $allocations = $this->input(
            'allocations',
        );

        $this->merge([
            'branch_id' => $this->input(
                'branch_id',
            ),

            'supplier_id' => $this->input(
                'supplier_id',
            ),

            'payment_account_id' => $this->input(
                'payment_account_id',
            ),

            'payment_date' => $this->trimmedInput(
                'payment_date',
            ),

            'posting_date' => $this->filled('posting_date')
                ? $this->trimmedInput(
                    'posting_date',
                )
                : $this->trimmedInput(
                    'payment_date',
                ),

            'currency_code' => mb_strtoupper(
                trim(
                    (string) $this->input(
                        'currency_code',
                    ),
                ),
            ),

            'exchange_rate' => $this->trimmedInput(
                'exchange_rate',
            ),

            'payment_method' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'payment_method',
                    ),
                ),
            ),

            'payment_reference' => $this->nullableInput(
                'payment_reference',
            ),

            'cheque_number' => $this->nullableInput(
                'cheque_number',
            ),

            'cheque_date' => $this->nullableInput(
                'cheque_date',
            ),

            'total_amount' => $this->trimmedInput(
                'total_amount',
            ),

            'notes' => $this->nullableInput(
                'notes',
            ),

            'allocations' => is_array($allocations)
                ? $this->normalizeAllocations(
                    $allocations,
                )
                : $allocations,
        ]);
    }

    /**
     * @param array<mixed> $allocations
     * @return list<mixed>
     */
    private function normalizeAllocations(
        array $allocations,
    ): array {
        $normalized = [];

        foreach (
            array_values($allocations)
            as $allocation
        ) {
            if (!is_array($allocation)) {
                $normalized[] = $allocation;

                continue;
            }

            $normalized[] = [
                ...$allocation,

                'supplier_open_item_id' =>
                    $allocation[
                        'supplier_open_item_id'
                    ] ?? null,

                'amount' => $this->trimScalar(
                    $allocation['amount'] ?? null,
                ),
            ];
        }

        return $normalized;
    }

    private function trimmedInput(
        string $field,
    ): mixed {
        return $this->trimScalar(
            $this->input($field),
        );
    }

    private function nullableInput(
        string $field,
    ): mixed {
        return $this->nullableScalar(
            $this->input($field),
        );
    }

    private function trimScalar(
        mixed $value,
    ): mixed {
        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function nullableScalar(
        mixed $value,
    ): mixed {
        $value = $this->trimScalar($value);

        return $value === ''
            ? null
            : $value;
    }
}
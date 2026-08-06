<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

abstract class SalesOrderRequest extends FormRequest
{
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

            'warehouse_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'customer_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'order_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'requested_delivery_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:order_date',
            ],

            'customer_reference' => [
                'nullable',
                'string',
                'max:120',
            ],

            'currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Za-z]{3}$/',
            ],

            'exchange_rate' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99999999',
                'decimal:0,8',
            ],

            'billing_address' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'shipping_address' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'payment_terms_days' => [
                'nullable',
                'integer',
                'between:0,3650',
            ],

            'shipping_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'other_charges' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'delivery_instructions' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'terms_and_conditions' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'lines' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'lines.*' => [
                'required',
                'array',
            ],

            'lines.*.product_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'lines.*.unit_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'lines.*.description' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'lines.*.ordered_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.discount_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.tax_rate' => [
                'required',
                'numeric',
                'between:0,100',
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
            'warehouse_id' => 'fulfillment warehouse',
            'customer_id' => 'customer',
            'order_date' => 'order date',

            'requested_delivery_date' =>
                'requested delivery date',

            'customer_reference' =>
                'customer reference',

            'currency_code' => 'currency',
            'exchange_rate' => 'exchange rate',
            'billing_address' => 'billing address',
            'shipping_address' => 'shipping address',

            'payment_terms_days' =>
                'payment terms',

            'shipping_amount' => 'shipping amount',
            'other_charges' => 'other charges',

            'delivery_instructions' =>
                'delivery instructions',

            'terms_and_conditions' =>
                'terms and conditions',

            'lines.*.product_id' => 'product',
            'lines.*.unit_id' => 'unit',

            'lines.*.description' =>
                'line description',

            'lines.*.ordered_quantity' =>
                'ordered quantity',

            'lines.*.unit_price' =>
                'unit price',

            'lines.*.discount_amount' =>
                'line discount',

            'lines.*.tax_rate' => 'tax rate',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'branch_id' => $this->input(
                'branch_id',
            ),

            'warehouse_id' => $this->filled(
                'warehouse_id',
            )
                ? $this->input('warehouse_id')
                : null,

            'customer_id' => $this->input(
                'customer_id',
            ),

            'order_date' => $this->trimmedInput(
                'order_date',
            ),

            'requested_delivery_date' =>
                $this->nullableInput(
                    'requested_delivery_date',
                ),

            'customer_reference' =>
                $this->nullableInput(
                    'customer_reference',
                ),

            'currency_code' => mb_strtoupper(
                (string) $this->trimmedInput(
                    'currency_code',
                ),
            ),

            'exchange_rate' => $this->filled(
                'exchange_rate',
            )
                ? $this->trimmedInput(
                    'exchange_rate',
                )
                : '1',

            'billing_address' =>
                $this->nullableInput(
                    'billing_address',
                ),

            'shipping_address' =>
                $this->nullableInput(
                    'shipping_address',
                ),

            'payment_terms_days' => $this->filled(
                'payment_terms_days',
            )
                ? $this->input(
                    'payment_terms_days',
                )
                : null,

            'shipping_amount' => $this->filled(
                'shipping_amount',
            )
                ? $this->trimmedInput(
                    'shipping_amount',
                )
                : '0',

            'other_charges' => $this->filled(
                'other_charges',
            )
                ? $this->trimmedInput(
                    'other_charges',
                )
                : '0',

            'delivery_instructions' =>
                $this->nullableInput(
                    'delivery_instructions',
                ),

            'terms_and_conditions' =>
                $this->nullableInput(
                    'terms_and_conditions',
                ),

            'notes' => $this->nullableInput(
                'notes',
            ),

            'lines' => is_array($lines)
                ? $this->normalizeLines($lines)
                : $lines,
        ]);
    }

    /**
     * @param array<array-key, mixed> $lines
     *
     * @return list<mixed>
     */
    private function normalizeLines(
        array $lines,
    ): array {
        $normalized = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                $normalized[] = $line;

                continue;
            }

            $normalized[] = [
                'product_id' =>
                    $line['product_id'] ?? null,

                'unit_id' =>
                    $line['unit_id'] ?? null,

                'description' =>
                    $this->nullableLineString(
                        $line['description'] ?? null,
                    ),

                'ordered_quantity' => trim(
                    (string) (
                        $line['ordered_quantity']
                            ?? ''
                    ),
                ),

                'unit_price' => trim(
                    (string) (
                        $line['unit_price']
                            ?? '0'
                    ),
                ),

                'discount_amount' => trim(
                    (string) (
                        $line['discount_amount']
                            ?? '0'
                    ),
                ),

                'tax_rate' => trim(
                    (string) (
                        $line['tax_rate']
                            ?? '0'
                    ),
                ),
            ];
        }

        return array_values($normalized);
    }

    private function trimmedInput(
        string $field,
    ): mixed {
        $value = $this->input($field);

        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function nullableInput(
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

    private function nullableLineString(
        mixed $value,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
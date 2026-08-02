<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route(
            'purchaseOrder',
        );

        return $purchaseOrder instanceof PurchaseOrder
            && $this->user()?->can(
                'update',
                $purchaseOrder,
            ) === true;
    }

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

            'supplier_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'order_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'expected_delivery_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:order_date',
            ],

            'supplier_reference' => [
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

            'delivery_address' => [
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
            'warehouse_id' => 'receiving warehouse',
            'supplier_id' => 'supplier',
            'order_date' => 'order date',

            'expected_delivery_date' =>
                'expected delivery date',

            'supplier_reference' =>
                'supplier reference',

            'currency_code' => 'currency',
            'exchange_rate' => 'exchange rate',
            'delivery_address' => 'delivery address',

            'payment_terms_days' =>
                'payment terms',

            'shipping_amount' => 'shipping amount',
            'other_charges' => 'other charges',

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

            'supplier_id' => $this->input(
                'supplier_id',
            ),

            'order_date' => trim(
                (string) $this->input(
                    'order_date',
                ),
            ),

            'expected_delivery_date' =>
                $this->nullableInput(
                    'expected_delivery_date',
                ),

            'supplier_reference' =>
                $this->nullableInput(
                    'supplier_reference',
                ),

            'currency_code' => mb_strtoupper(
                trim(
                    (string) $this->input(
                        'currency_code',
                    ),
                ),
            ),

            'exchange_rate' => trim(
                (string) $this->input(
                    'exchange_rate',
                    '1',
                ),
            ),

            'delivery_address' =>
                $this->nullableInput(
                    'delivery_address',
                ),

            'payment_terms_days' => $this->filled(
                'payment_terms_days',
            )
                ? $this->input(
                    'payment_terms_days',
                )
                : null,

            'shipping_amount' => trim(
                (string) $this->input(
                    'shipping_amount',
                    '0',
                ),
            ),

            'other_charges' => trim(
                (string) $this->input(
                    'other_charges',
                    '0',
                ),
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
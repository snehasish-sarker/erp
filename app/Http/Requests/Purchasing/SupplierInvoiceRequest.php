<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

abstract class SupplierInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'supplier_invoice_number' => [
                'required',
                'string',
                'max:160',
            ],

            'invoice_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:invoice_date',
            ],

            'due_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:invoice_date',
            ],

            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Za-z]{3}$/',
            ],

            'exchange_rate' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:999999999999.99999999',
                'decimal:0,8',
            ],

            'other_charges' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'rounding_adjustment' => [
                'required',
                'numeric',
                'between:-99999999999999.999999,99999999999999.999999',
                'decimal:0,6',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'matching_notes' => [
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

            'lines.*.purchase_order_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],

            'lines.*.invoiced_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.invoice_unit_price' => [
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

            'lines.*.variance_reason' => [
                'nullable',
                'string',
                'max:500',
            ],

            'lines.*.matches' => [
                'nullable',
                'array',
                'max:1000',
            ],

            'lines.*.matches.*' => [
                'required',
                'array',
            ],

            'lines.*.matches.*.goods_receipt_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],

            'lines.*.matches.*.matched_quantity' => [
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
            'purchase_order_id' =>
                'Purchase Order',

            'supplier_invoice_number' =>
                'supplier invoice number',

            'invoice_date' =>
                'invoice date',

            'posting_date' =>
                'posting date',

            'due_date' =>
                'due date',

            'currency_code' =>
                'currency',

            'exchange_rate' =>
                'exchange rate',

            'other_charges' =>
                'other charges',

            'rounding_adjustment' =>
                'rounding adjustment',

            'matching_notes' =>
                'matching notes',

            'lines.*.purchase_order_line_id' =>
                'Purchase Order line',

            'lines.*.invoiced_quantity' =>
                'invoiced quantity',

            'lines.*.invoice_unit_price' =>
                'invoice unit price',

            'lines.*.discount_amount' =>
                'line discount',

            'lines.*.tax_rate' =>
                'tax rate',

            'lines.*.variance_reason' =>
                'variance reason',

            'lines.*.matches' =>
                'Goods Receipt matches',

            'lines.*.matches.*.goods_receipt_line_id' =>
                'Goods Receipt line',

            'lines.*.matches.*.matched_quantity' =>
                'matched quantity',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'purchase_order_id' =>
                $this->input(
                    'purchase_order_id',
                ),

            'supplier_invoice_number' =>
                $this->trimmedInput(
                    'supplier_invoice_number',
                ),

            'invoice_date' =>
                $this->trimmedInput(
                    'invoice_date',
                ),

            'posting_date' =>
                $this->filled('posting_date')
                    ? $this->trimmedInput(
                        'posting_date',
                    )
                    : $this->trimmedInput(
                        'invoice_date',
                    ),

            'due_date' =>
                $this->nullableInput(
                    'due_date',
                ),

            'currency_code' =>
                $this->filled('currency_code')
                    ? mb_strtoupper(
                        (string) $this->trimmedInput(
                            'currency_code',
                        ),
                    )
                    : null,

            'exchange_rate' =>
                $this->filled('exchange_rate')
                    ? $this->trimmedInput(
                        'exchange_rate',
                    )
                    : null,

            'other_charges' =>
                $this->filled('other_charges')
                    ? $this->trimmedInput(
                        'other_charges',
                    )
                    : '0',

            'rounding_adjustment' =>
                $this->filled(
                    'rounding_adjustment',
                )
                    ? $this->trimmedInput(
                        'rounding_adjustment',
                    )
                    : '0',

            'notes' =>
                $this->nullableInput(
                    'notes',
                ),

            'matching_notes' =>
                $this->nullableInput(
                    'matching_notes',
                ),

            'lines' => is_array($lines)
                ? $this->normalizeLines(
                    $lines,
                )
                : $lines,
        ]);
    }

    /**
     * @param array<mixed> $lines
     * @return list<mixed>
     */
    private function normalizeLines(
        array $lines,
    ): array {
        $normalized = [];

        foreach (array_values($lines) as $line) {
            if (!is_array($line)) {
                $normalized[] = $line;

                continue;
            }

            $matches = $line['matches'] ?? null;

            $normalized[] = [
                ...$line,

                'purchase_order_line_id' =>
                    $line[
                        'purchase_order_line_id'
                    ] ?? null,

                'invoiced_quantity' =>
                    $this->trimScalar(
                        $line[
                            'invoiced_quantity'
                        ] ?? null,
                    ),

                'invoice_unit_price' =>
                    $this->trimScalar(
                        $line[
                            'invoice_unit_price'
                        ] ?? null,
                    ),

                'discount_amount' =>
                    $this->trimScalar(
                        $line[
                            'discount_amount'
                        ] ?? '0',
                    ),

                'tax_rate' =>
                    $this->trimScalar(
                        $line['tax_rate'] ?? '0',
                    ),

                'variance_reason' =>
                    $this->nullableScalar(
                        $line[
                            'variance_reason'
                        ] ?? null,
                    ),

                'matches' => is_array($matches)
                    ? $this->normalizeMatches(
                        $matches,
                    )
                    : $matches,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $matches
     * @return list<mixed>
     */
    private function normalizeMatches(
        array $matches,
    ): array {
        $normalized = [];

        foreach (array_values($matches) as $match) {
            if (!is_array($match)) {
                $normalized[] = $match;

                continue;
            }

            $normalized[] = [
                ...$match,

                'goods_receipt_line_id' =>
                    $match[
                        'goods_receipt_line_id'
                    ] ?? null,

                'matched_quantity' =>
                    $this->trimScalar(
                        $match[
                            'matched_quantity'
                        ] ?? null,
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
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
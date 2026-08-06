<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

abstract class SalesInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sales_order_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'invoice_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:invoice_date',
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

            'lines.*.sales_order_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],

            'lines.*.invoiced_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.description' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'sales_order_id' => $this->input(
                'sales_order_id',
            ),

            'invoice_date' => $this->trimmed(
                'invoice_date',
            ),

            'posting_date' => $this->trimmed(
                'posting_date',
            ),

            'due_date' => $this->trimmed(
                'due_date',
            ),

            'billing_address' => $this->nullableText(
                'billing_address',
            ),

            'shipping_address' => $this->nullableText(
                'shipping_address',
            ),

            'shipping_amount' => $this->decimalText(
                'shipping_amount',
                '0',
            ),

            'other_charges' => $this->decimalText(
                'other_charges',
                '0',
            ),

            'notes' => $this->nullableText(
                'notes',
            ),

            'lines' => is_array($lines)
                ? array_values(
                    array_map(
                        fn (mixed $line): mixed =>
                            $this->normalizeLine($line),
                        $lines,
                    ),
                )
                : $lines,
        ]);
    }

    private function normalizeLine(
        mixed $line,
    ): mixed {
        if (!is_array($line)) {
            return $line;
        }

        $description = $line['description']
            ?? null;

        return [
            'sales_order_line_id' =>
                $line['sales_order_line_id']
                    ?? null,

            'invoiced_quantity' => trim(
                (string) (
                    $line['invoiced_quantity']
                        ?? '0'
                ),
            ),

            'description' => is_string($description)
                ? (
                    trim($description) === ''
                        ? null
                        : trim($description)
                )
                : null,
        ];
    }

    private function trimmed(
        string $field,
    ): mixed {
        $value = $this->input($field);

        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function nullableText(
        string $field,
    ): ?string {
        $value = $this->input($field);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function decimalText(
        string $field,
        string $default,
    ): string {
        $value = $this->input(
            $field,
            $default,
        );

        return trim((string) $value);
    }
}
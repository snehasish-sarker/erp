<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CustomerCreditNoteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sales_invoice_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'credit_note_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'return_address' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
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

            'lines.*.sales_invoice_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],

            'lines.*.line_type' => [
                'required',
                Rule::in(['quantity', 'amount']),
            ],

            'lines.*.credit_quantity' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.credit_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.return_to_stock' => [
                'required',
                'boolean',
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
            'sales_invoice_id' => $this->input('sales_invoice_id'),
            'credit_note_date' => $this->trimmed('credit_note_date'),
            'posting_date' => $this->trimmed('posting_date'),
            'return_address' => $this->nullableText('return_address'),
            'reason' => $this->trimmed('reason'),
            'notes' => $this->nullableText('notes'),
            'lines' => is_array($lines)
                ? array_values(
                    array_map(
                        fn (mixed $line): mixed => $this->normalizeLine($line),
                        $lines,
                    ),
                )
                : $lines,
        ]);
    }

    private function normalizeLine(mixed $line): mixed
    {
        if (!is_array($line)) {
            return $line;
        }

        $description = $line['description'] ?? null;

        return [
            'sales_invoice_line_id' => $line['sales_invoice_line_id'] ?? null,
            'line_type' => is_string($line['line_type'] ?? null)
                ? trim((string) $line['line_type'])
                : $line['line_type'] ?? null,
            'credit_quantity' => trim(
                (string) ($line['credit_quantity'] ?? '0'),
            ),
            'credit_amount' => trim(
                (string) ($line['credit_amount'] ?? '0'),
            ),
            'return_to_stock' => filter_var(
                $line['return_to_stock'] ?? false,
                FILTER_VALIDATE_BOOL,
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

    private function trimmed(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value)
            ? trim($value)
            : $value;
    }

    private function nullableText(string $field): ?string
    {
        $value = $this->input($field);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
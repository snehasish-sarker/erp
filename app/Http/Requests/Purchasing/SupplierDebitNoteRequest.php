<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

abstract class SupplierDebitNoteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_return_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'supplier_invoice_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'debit_note_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:debit_note_date',
            ],

            'supplier_reference' => [
                'nullable',
                'string',
                'max:160',
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

            'lines.*' => [
                'required',
                'array',
            ],

            'lines.*.purchase_return_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],

            'lines.*.supplier_invoice_line_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'lines.*.return_quantity' => [
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

            'lines.*.discount_per_unit' => [
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

            'lines.*.description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'lines.*.notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_return_id' =>
                'Purchase Return',

            'supplier_invoice_id' =>
                'Supplier Invoice',

            'debit_note_date' =>
                'Debit Note date',

            'posting_date' =>
                'posting date',

            'supplier_reference' =>
                'supplier reference',

            'lines.*.purchase_return_line_id' =>
                'Purchase Return line',

            'lines.*.supplier_invoice_line_id' =>
                'Supplier Invoice line',

            'lines.*.return_quantity' =>
                'return quantity',

            'lines.*.unit_price' =>
                'unit price',

            'lines.*.discount_per_unit' =>
                'discount per unit',

            'lines.*.tax_rate' =>
                'tax rate',

            'lines.*.description' =>
                'line description',

            'lines.*.notes' =>
                'line notes',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'purchase_return_id' =>
                $this->input(
                    'purchase_return_id',
                ),

            'supplier_invoice_id' =>
                $this->nullableId(
                    'supplier_invoice_id',
                ),

            'debit_note_date' =>
                $this->trimmedInput(
                    'debit_note_date',
                ),

            'posting_date' =>
                $this->filled('posting_date')
                    ? $this->trimmedInput(
                        'posting_date',
                    )
                    : $this->trimmedInput(
                        'debit_note_date',
                    ),

            'supplier_reference' =>
                $this->nullableInput(
                    'supplier_reference',
                ),

            'reason' =>
                $this->trimmedInput(
                    'reason',
                ),

            'notes' =>
                $this->nullableInput(
                    'notes',
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

        foreach (
            array_values($lines)
            as $line
        ) {
            if (!is_array($line)) {
                $normalized[] = $line;

                continue;
            }

            $normalized[] = [
                ...$line,

                'purchase_return_line_id' =>
                    $line[
                        'purchase_return_line_id'
                    ] ?? null,

                'supplier_invoice_line_id' =>
                    $this->nullableScalar(
                        $line[
                            'supplier_invoice_line_id'
                        ] ?? null,
                    ),

                'return_quantity' =>
                    $this->trimScalar(
                        $line[
                            'return_quantity'
                        ] ?? null,
                    ),

                'unit_price' =>
                    $this->trimScalar(
                        $line['unit_price']
                            ?? null,
                    ),

                'discount_per_unit' =>
                    $this->trimScalar(
                        $line[
                            'discount_per_unit'
                        ] ?? '0',
                    ),

                'tax_rate' =>
                    $this->trimScalar(
                        $line['tax_rate']
                            ?? '0',
                    ),

                'description' =>
                    $this->nullableScalar(
                        $line['description']
                            ?? null,
                    ),

                'notes' =>
                    $this->nullableScalar(
                        $line['notes']
                            ?? null,
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
<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

abstract class PurchaseReturnRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'goods_receipt_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'supplier_invoice_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'return_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'posting_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:return_date',
            ],

            'supplier_reference' => [
                'nullable',
                'string',
                'max:160',
            ],

            'return_reason' => [
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

            'lines.*.goods_receipt_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],

            'lines.*.return_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.return_reason' => [
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
            'goods_receipt_id' =>
                'Goods Receipt',

            'supplier_invoice_id' =>
                'Supplier Invoice',

            'return_date' =>
                'return date',

            'posting_date' =>
                'posting date',

            'supplier_reference' =>
                'supplier reference',

            'return_reason' =>
                'return reason',

            'lines.*.goods_receipt_line_id' =>
                'Goods Receipt line',

            'lines.*.return_quantity' =>
                'return quantity',

            'lines.*.return_reason' =>
                'line return reason',

            'lines.*.notes' =>
                'line notes',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'goods_receipt_id' =>
                $this->input(
                    'goods_receipt_id',
                ),

            'supplier_invoice_id' =>
                $this->nullableId(
                    'supplier_invoice_id',
                ),

            'return_date' =>
                $this->trimmedInput(
                    'return_date',
                ),

            'posting_date' =>
                $this->filled('posting_date')
                    ? $this->trimmedInput(
                        'posting_date',
                    )
                    : $this->trimmedInput(
                        'return_date',
                    ),

            'supplier_reference' =>
                $this->nullableInput(
                    'supplier_reference',
                ),

            'return_reason' =>
                $this->trimmedInput(
                    'return_reason',
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

                'goods_receipt_line_id' =>
                    $line[
                        'goods_receipt_line_id'
                    ] ?? null,

                'return_quantity' =>
                    $this->trimScalar(
                        $line[
                            'return_quantity'
                        ] ?? null,
                    ),

                'return_reason' =>
                    $this->nullableScalar(
                        $line[
                            'return_reason'
                        ] ?? null,
                    ),

                'notes' =>
                    $this->nullableScalar(
                        $line['notes'] ?? null,
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
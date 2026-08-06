<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

abstract class CustomerDispatchRequest extends FormRequest
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

            'dispatch_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'shipping_address' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'delivery_instructions' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'carrier_name' => [
                'nullable',
                'string',
                'max:160',
            ],

            'vehicle_number' => [
                'nullable',
                'string',
                'max:80',
            ],

            'tracking_number' => [
                'nullable',
                'string',
                'max:120',
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

            'lines.*.dispatched_quantity' => [
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

            'dispatch_date' => $this->trimmed(
                'dispatch_date',
            ),

            'shipping_address' => $this->nullableText(
                'shipping_address',
            ),

            'delivery_instructions' => $this->nullableText(
                'delivery_instructions',
            ),

            'carrier_name' => $this->nullableText(
                'carrier_name',
            ),

            'vehicle_number' => $this->nullableText(
                'vehicle_number',
            ),

            'tracking_number' => $this->nullableText(
                'tracking_number',
            ),

            'notes' => $this->nullableText(
                'notes',
            ),

            'lines' => is_array($lines)
                ? array_values(
                    array_map(
                        fn (mixed $line): mixed =>
                            $this->normalizeLine(
                                $line,
                            ),
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

        return [
            'sales_order_line_id' =>
                $line['sales_order_line_id']
                    ?? null,

            'dispatched_quantity' => trim(
                (string) (
                    $line['dispatched_quantity']
                        ?? ''
                ),
            ),

            'description' =>
                $this->nullableLineText(
                    $line['description']
                        ?? null,
                ),
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

    private function nullableLineText(
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
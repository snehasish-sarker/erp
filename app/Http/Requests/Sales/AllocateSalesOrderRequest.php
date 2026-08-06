<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;

final class AllocateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $salesOrder = $this->route(
            'salesOrder',
        );

        return $salesOrder instanceof SalesOrder
            && $this->user()?->can(
                'allocate',
                $salesOrder,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
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

            'lines.*.allocated_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        $notes = $this->input('notes');

        $this->merge([
            'notes' => is_string($notes)
                ? (
                    trim($notes) === ''
                        ? null
                        : trim($notes)
                )
                : $notes,

            'lines' => is_array($lines)
                ? array_values(
                    array_map(
                        static function (
                            mixed $line,
                        ): mixed {
                            if (!is_array($line)) {
                                return $line;
                            }

                            return [
                                'sales_order_line_id' =>
                                    $line[
                                        'sales_order_line_id'
                                    ] ?? null,

                                'allocated_quantity' =>
                                    trim(
                                        (string) (
                                            $line[
                                                'allocated_quantity'
                                            ] ?? '0'
                                        ),
                                    ),
                            ];
                        },
                        $lines,
                    ),
                )
                : $lines,
        ]);
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class GoodsReceiptRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const INSPECTION_STATUSES = [
        'not_required',
        'pending',
        'passed',
        'partial',
        'failed',
    ];

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

            'receipt_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'supplier_delivery_note' => [
                'nullable',
                'string',
                'max:160',
            ],

            'inspection_status' => [
                'required',
                'string',
                Rule::in(
                    self::INSPECTION_STATUSES,
                ),
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

            'lines.*.purchase_order_line_id' => [
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],

            'lines.*.receipt_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.accepted_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.rejected_quantity' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'lines.*.batch_number' => [
                'nullable',
                'string',
                'max:120',
            ],

            'lines.*.manufacturing_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'lines.*.expiry_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:lines.*.manufacturing_date',
            ],

            'lines.*.serial_numbers' => [
                'nullable',
                'array',
                'max:1000',
            ],

            'lines.*.serial_numbers.*' => [
                'required',
                'string',
                'max:190',
                'distinct:strict',
            ],

            'lines.*.storage_location' => [
                'nullable',
                'string',
                'max:160',
            ],

            'lines.*.variance_reason' => [
                'nullable',
                'string',
                'max:500',
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

            'receipt_date' =>
                'receipt date',

            'supplier_delivery_note' =>
                'supplier delivery note',

            'inspection_status' =>
                'inspection status',

            'lines.*.purchase_order_line_id' =>
                'Purchase Order line',

            'lines.*.receipt_quantity' =>
                'receipt quantity',

            'lines.*.accepted_quantity' =>
                'accepted quantity',

            'lines.*.rejected_quantity' =>
                'rejected quantity',

            'lines.*.batch_number' =>
                'batch number',

            'lines.*.manufacturing_date' =>
                'manufacturing date',

            'lines.*.expiry_date' =>
                'expiry date',

            'lines.*.serial_numbers' =>
                'serial numbers',

            'lines.*.serial_numbers.*' =>
                'serial number',

            'lines.*.storage_location' =>
                'storage location',

            'lines.*.variance_reason' =>
                'variance reason',
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

            'receipt_date' => trim(
                (string) $this->input(
                    'receipt_date',
                ),
            ),

            'supplier_delivery_note' =>
                $this->nullableInput(
                    'supplier_delivery_note',
                ),

            'inspection_status' =>
                mb_strtolower(
                    trim(
                        (string) $this->input(
                            'inspection_status',
                            'not_required',
                        ),
                    ),
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

    public function withValidator(
        Validator $validator,
    ): void {
        $validator->after(
            function (
                Validator $validator,
            ): void {
                $lines = $this->input(
                    'lines',
                    [],
                );

                if (!is_array($lines)) {
                    return;
                }

                foreach (
                    array_values($lines)
                    as $index => $line
                ) {
                    if (!is_array($line)) {
                        continue;
                    }

                    $rejectedQuantity =
                        $line[
                            'rejected_quantity'
                        ] ?? null;

                    /*
                     * The standard validation rules handle malformed
                     * or out-of-range rejected quantity values.
                     */
                    if (
                        !is_numeric(
                            $rejectedQuantity,
                        )
                        || (float) $rejectedQuantity
                            <= 0
                    ) {
                        continue;
                    }

                    $varianceReason =
                        $line[
                            'variance_reason'
                        ] ?? null;

                    if (
                        is_string($varianceReason)
                        && trim($varianceReason)
                            !== ''
                    ) {
                        continue;
                    }

                    $validator
                        ->errors()
                        ->add(
                            "lines.{$index}.variance_reason",
                            'A variance reason is required when the rejected quantity is greater than zero.',
                        );
                }
            },
        );
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
                'purchase_order_line_id' =>
                    $line[
                        'purchase_order_line_id'
                    ] ?? null,

                'receipt_quantity' => trim(
                    (string) (
                        $line[
                            'receipt_quantity'
                        ] ?? ''
                    ),
                ),

                'accepted_quantity' => trim(
                    (string) (
                        $line[
                            'accepted_quantity'
                        ] ?? '0'
                    ),
                ),

                'rejected_quantity' => trim(
                    (string) (
                        $line[
                            'rejected_quantity'
                        ] ?? '0'
                    ),
                ),

                'batch_number' =>
                    $this->nullableLineString(
                        $line[
                            'batch_number'
                        ] ?? null,
                    ),

                'manufacturing_date' =>
                    $this->nullableLineString(
                        $line[
                            'manufacturing_date'
                        ] ?? null,
                    ),

                'expiry_date' =>
                    $this->nullableLineString(
                        $line[
                            'expiry_date'
                        ] ?? null,
                    ),

                'serial_numbers' =>
                    $this->normalizeSerialNumbers(
                        $line[
                            'serial_numbers'
                        ] ?? null,
                    ),

                'storage_location' =>
                    $this->nullableLineString(
                        $line[
                            'storage_location'
                        ] ?? null,
                    ),

                'variance_reason' =>
                    $this->nullableLineString(
                        $line[
                            'variance_reason'
                        ] ?? null,
                    ),
            ];
        }

        return array_values($normalized);
    }

    private function normalizeSerialNumbers(
        mixed $value,
    ): mixed {
        if (
            $value === null
            || $value === ''
            || $value === []
        ) {
            return null;
        }

        if (is_string($value)) {
            $value = preg_split(
                '/\r\n|\r|\n/',
                $value,
            );
        }

        /*
         * Preserve malformed non-array values so Laravel's
         * normal validation rules return a validation error.
         */
        if (!is_array($value)) {
            return $value;
        }

        $serialNumbers = [];

        foreach ($value as $serialNumber) {
            if (!is_string($serialNumber)) {
                $serialNumbers[] =
                    $serialNumber;

                continue;
            }

            $serialNumber = trim(
                $serialNumber,
            );

            if ($serialNumber !== '') {
                $serialNumbers[] =
                    $serialNumber;
            }
        }

        return $serialNumbers === []
            ? null
            : array_values(
                $serialNumbers,
            );
    }

    private function nullableInput(
        string $field,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input(
                $field,
            ),
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
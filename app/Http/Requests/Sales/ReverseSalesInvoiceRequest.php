<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $salesInvoice = $this->route(
            'salesInvoice',
        );

        return $salesInvoice instanceof SalesInvoice
            && $this->user()?->can(
                'reverse',
                $salesInvoice,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reversal_posting_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'reversal_reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reversal_posting_date' => trim(
                (string) $this->input(
                    'reversal_posting_date',
                    '',
                ),
            ),

            'reversal_reason' => trim(
                (string) $this->input(
                    'reversal_reason',
                    '',
                ),
            ),
        ]);
    }
}
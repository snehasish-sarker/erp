<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierInvoice = $this->route(
            'supplierInvoice',
        );

        return $supplierInvoice
                instanceof SupplierInvoice
            && $this->user()?->can(
                'reverse',
                $supplierInvoice,
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
        $date = $this->input(
            'reversal_posting_date',
        );

        $reason = $this->input(
            'reversal_reason',
        );

        $this->merge([
            'reversal_posting_date' =>
                is_string($date)
                    ? trim($date)
                    : $date,

            'reversal_reason' =>
                is_string($reason)
                    ? trim($reason)
                    : $reason,
        ]);
    }
}
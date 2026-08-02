<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

final class CancelSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierInvoice = $this->route(
            'supplierInvoice',
        );

        return $supplierInvoice
                instanceof SupplierInvoice
            && $this->user()?->can(
                'cancel',
                $supplierInvoice,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input(
            'cancellation_reason',
        );

        $this->merge([
            'cancellation_reason' =>
                is_string($reason)
                    ? trim($reason)
                    : $reason,
        ]);
    }
}
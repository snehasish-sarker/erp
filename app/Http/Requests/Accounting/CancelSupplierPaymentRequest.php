<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;

final class CancelSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierPayment = $this->route(
            'supplierPayment',
        );

        return $supplierPayment
                instanceof SupplierPayment
            && $this->user()?->can(
                'cancel',
                $supplierPayment,
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
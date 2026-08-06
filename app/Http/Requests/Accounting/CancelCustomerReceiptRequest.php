<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerReceipt;
use Illuminate\Foundation\Http\FormRequest;

final class CancelCustomerReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerReceipt = $this->route(
            'customerReceipt',
        );

        return $customerReceipt
                instanceof CustomerReceipt
            && $this->user()?->can(
                'cancel',
                $customerReceipt,
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
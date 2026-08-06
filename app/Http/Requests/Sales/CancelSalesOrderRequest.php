<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;

final class CancelSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $salesOrder = $this->route(
            'salesOrder',
        );

        return $salesOrder instanceof SalesOrder
            && $this->user()?->can(
                'cancel',
                $salesOrder,
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cancellation_reason' =>
                'cancellation reason',
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
<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerDispatch;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseCustomerDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dispatch = $this->route(
            'customerDispatch',
        );

        return $dispatch instanceof CustomerDispatch
            && $this->user()?->can(
                'reverse',
                $dispatch,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reversal_reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input(
            'reversal_reason',
        );

        $this->merge([
            'reversal_reason' =>
                is_string($reason)
                    ? trim($reason)
                    : $reason,
        ]);
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerCreditNote;
use Illuminate\Foundation\Http\FormRequest;

final class CancelCustomerCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $creditNote = $this->route('customerCreditNote');

        return $creditNote instanceof CustomerCreditNote
            && $this->user()?->can('cancel', $creditNote) === true;
    }

    /** @return array<string, list<mixed>> */
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
        $this->merge([
            'cancellation_reason' => trim(
                (string) $this->input('cancellation_reason', ''),
            ),
        ]);
    }
}
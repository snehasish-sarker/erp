<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerCreditNote;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseCustomerCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $creditNote = $this->route('customerCreditNote');

        return $creditNote instanceof CustomerCreditNote
            && $this->user()?->can('reverse', $creditNote) === true;
    }

    /** @return array<string, list<mixed>> */
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
                (string) $this->input('reversal_posting_date', ''),
            ),
            'reversal_reason' => trim(
                (string) $this->input('reversal_reason', ''),
            ),
        ]);
    }
}
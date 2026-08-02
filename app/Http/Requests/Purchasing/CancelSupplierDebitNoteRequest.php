<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierDebitNote;
use Illuminate\Foundation\Http\FormRequest;

final class CancelSupplierDebitNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierDebitNote = $this->route(
            'supplierDebitNote',
        );

        return $supplierDebitNote
                instanceof SupplierDebitNote
            && $this->user()?->can(
                'cancel',
                $supplierDebitNote,
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
<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankReconciliation::class) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'bank_statement_import_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }
}

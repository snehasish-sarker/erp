<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\BankStatementImport;
use Illuminate\Foundation\Http\FormRequest;

final class BankStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', BankStatementImport::class) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'bank_account_id' => ['required', 'integer', 'min:1'],
            'statement_reference' => ['nullable', 'string', 'max:160'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'opening_balance' => ['required', 'numeric', 'max:99999999999999.999999', 'decimal:0,6'],
            'closing_balance' => ['required', 'numeric', 'max:99999999999999.999999', 'decimal:0,6'],
            'statement_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'statement_reference' => $this->filled('statement_reference')
                ? trim((string) $this->input('statement_reference'))
                : null,
            'period_start' => trim((string) $this->input('period_start', '')),
            'period_end' => trim((string) $this->input('period_end', '')),
            'currency_code' => strtoupper(trim((string) $this->input('currency_code', ''))),
            'opening_balance' => trim((string) $this->input('opening_balance', '')),
            'closing_balance' => trim((string) $this->input('closing_balance', '')),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

final class BankReconciliationMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reconciliation = $this->route('bankReconciliation');

        return $reconciliation instanceof BankReconciliation
            && $this->user()?->can('match', $reconciliation) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'bank_statement_line_id' => ['required', 'integer', 'min:1'],
            'journal_entry_line_id' => ['required', 'integer', 'min:1'],
            'matched_amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'],
        ];
    }
}

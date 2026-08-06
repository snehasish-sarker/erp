<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerCreditNote;

final class UpdateCustomerCreditNoteRequest extends CustomerCreditNoteRequest
{
    public function authorize(): bool
    {
        $creditNote = $this->route('customerCreditNote');

        return $creditNote instanceof CustomerCreditNote
            && $this->user()?->can('update', $creditNote) === true;
    }
}
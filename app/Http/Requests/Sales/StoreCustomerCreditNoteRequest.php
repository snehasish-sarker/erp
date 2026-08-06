<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerCreditNote;

final class StoreCustomerCreditNoteRequest extends CustomerCreditNoteRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CustomerCreditNote::class,
        ) === true;
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierDebitNote;

final class StoreSupplierDebitNoteRequest extends SupplierDebitNoteRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SupplierDebitNote::class,
        ) === true;
    }
}
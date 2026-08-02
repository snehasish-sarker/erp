<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierDebitNote;

final class UpdateSupplierDebitNoteRequest extends SupplierDebitNoteRequest
{
    public function authorize(): bool
    {
        $supplierDebitNote = $this->route(
            'supplierDebitNote',
        );

        return $supplierDebitNote
                instanceof SupplierDebitNote
            && $this->user()?->can(
                'update',
                $supplierDebitNote,
            ) === true;
    }
}
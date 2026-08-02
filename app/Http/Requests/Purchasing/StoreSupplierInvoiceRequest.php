<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierInvoice;

final class StoreSupplierInvoiceRequest extends SupplierInvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SupplierInvoice::class,
        ) === true;
    }
}
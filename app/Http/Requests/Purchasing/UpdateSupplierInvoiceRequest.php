<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\SupplierInvoice;

final class UpdateSupplierInvoiceRequest extends SupplierInvoiceRequest
{
    public function authorize(): bool
    {
        $supplierInvoice = $this->route(
            'supplierInvoice',
        );

        return $supplierInvoice
                instanceof SupplierInvoice
            && $this->user()?->can(
                'update',
                $supplierInvoice,
            ) === true;
    }
}
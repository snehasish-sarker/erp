<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesInvoice;

final class UpdateSalesInvoiceRequest extends SalesInvoiceRequest
{
    public function authorize(): bool
    {
        $salesInvoice = $this->route(
            'salesInvoice',
        );

        return $salesInvoice instanceof SalesInvoice
            && $this->user()?->can(
                'update',
                $salesInvoice,
            ) === true;
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesInvoice;

final class StoreSalesInvoiceRequest extends SalesInvoiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SalesInvoice::class,
        ) === true;
    }
}
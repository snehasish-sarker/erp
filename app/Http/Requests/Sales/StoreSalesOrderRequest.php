<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;

final class StoreSalesOrderRequest extends SalesOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SalesOrder::class,
        ) === true;
    }
}
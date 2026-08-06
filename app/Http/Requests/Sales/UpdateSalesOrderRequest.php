<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;

final class UpdateSalesOrderRequest extends SalesOrderRequest
{
    public function authorize(): bool
    {
        $salesOrder = $this->route(
            'salesOrder',
        );

        return $salesOrder instanceof SalesOrder
            && $this->user()?->can(
                'update',
                $salesOrder,
            ) === true;
    }
}
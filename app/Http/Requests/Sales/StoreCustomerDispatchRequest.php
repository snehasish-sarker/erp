<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerDispatch;

final class StoreCustomerDispatchRequest extends CustomerDispatchRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CustomerDispatch::class,
        ) === true;
    }
}
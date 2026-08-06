<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\CustomerDispatch;

final class UpdateCustomerDispatchRequest extends CustomerDispatchRequest
{
    public function authorize(): bool
    {
        $dispatch = $this->route(
            'customerDispatch',
        );

        return $dispatch instanceof CustomerDispatch
            && $this->user()?->can(
                'update',
                $dispatch,
            ) === true;
    }
}
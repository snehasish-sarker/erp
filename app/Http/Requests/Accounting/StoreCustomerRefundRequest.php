<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerRefund;

final class StoreCustomerRefundRequest extends CustomerRefundRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerRefund::class) === true;
    }
}

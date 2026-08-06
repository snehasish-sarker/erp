<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerReceipt;

final class StoreCustomerReceiptRequest extends CustomerReceiptRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            CustomerReceipt::class,
        ) === true;
    }
}
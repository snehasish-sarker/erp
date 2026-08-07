<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerCreditApplication;

final class StoreCustomerCreditApplicationRequest extends CustomerCreditApplicationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerCreditApplication::class) === true;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerArAdjustment;

final class StoreCustomerArAdjustmentRequest extends CustomerArAdjustmentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerArAdjustment::class) === true;
    }
}

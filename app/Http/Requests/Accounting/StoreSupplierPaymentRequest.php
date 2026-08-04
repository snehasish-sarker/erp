<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\SupplierPayment;

final class StoreSupplierPaymentRequest extends SupplierPaymentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SupplierPayment::class,
        ) === true;
    }
}
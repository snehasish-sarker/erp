<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\SupplierPayment;

final class UpdateSupplierPaymentRequest extends SupplierPaymentRequest
{
    public function authorize(): bool
    {
        $supplierPayment = $this->route(
            'supplierPayment',
        );

        return $supplierPayment
                instanceof SupplierPayment
            && $this->user()?->can(
                'update',
                $supplierPayment,
            ) === true;
    }
}
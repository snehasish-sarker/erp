<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerReceipt;

final class UpdateCustomerReceiptRequest extends CustomerReceiptRequest
{
    public function authorize(): bool
    {
        $customerReceipt = $this->route(
            'customerReceipt',
        );

        return $customerReceipt
                instanceof CustomerReceipt
            && $this->user()?->can(
                'update',
                $customerReceipt,
            ) === true;
    }
}
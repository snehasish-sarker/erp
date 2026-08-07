<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerRefund;

final class UpdateCustomerRefundRequest extends CustomerRefundRequest
{
    public function authorize(): bool
    {
        $document = $this->route('customerRefund');
        return $document instanceof CustomerRefund && $this->user()?->can('update', $document) === true;
    }
}

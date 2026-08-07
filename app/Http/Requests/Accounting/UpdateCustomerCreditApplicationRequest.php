<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerCreditApplication;

final class UpdateCustomerCreditApplicationRequest extends CustomerCreditApplicationRequest
{
    public function authorize(): bool
    {
        $document = $this->route('customerCreditApplication');
        return $document instanceof CustomerCreditApplication && $this->user()?->can('update', $document) === true;
    }
}

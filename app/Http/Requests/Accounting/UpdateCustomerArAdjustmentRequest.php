<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\CustomerArAdjustment;

final class UpdateCustomerArAdjustmentRequest extends CustomerArAdjustmentRequest
{
    public function authorize(): bool
    {
        $document = $this->route('customerArAdjustment');
        return $document instanceof CustomerArAdjustment && $this->user()?->can('update', $document) === true;
    }
}

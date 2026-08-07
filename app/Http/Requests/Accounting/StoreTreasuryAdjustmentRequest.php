<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\TreasuryAdjustment;

final class StoreTreasuryAdjustmentRequest extends TreasuryAdjustmentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TreasuryAdjustment::class) === true;
    }
}

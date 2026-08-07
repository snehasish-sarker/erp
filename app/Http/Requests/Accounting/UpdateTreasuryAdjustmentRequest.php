<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\TreasuryAdjustment;

final class UpdateTreasuryAdjustmentRequest extends TreasuryAdjustmentRequest
{
    public function authorize(): bool
    {
        $adjustment = $this->route('treasuryAdjustment');

        return $adjustment instanceof TreasuryAdjustment
            && $this->user()?->can('update', $adjustment) === true;
    }
}

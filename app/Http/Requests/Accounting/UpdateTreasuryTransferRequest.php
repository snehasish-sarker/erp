<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\TreasuryTransfer;

final class UpdateTreasuryTransferRequest extends TreasuryTransferRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('treasuryTransfer');

        return $transfer instanceof TreasuryTransfer
            && $this->user()?->can('update', $transfer) === true;
    }
}

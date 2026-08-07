<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\TreasuryTransfer;

final class StoreTreasuryTransferRequest extends TreasuryTransferRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TreasuryTransfer::class) === true;
    }
}

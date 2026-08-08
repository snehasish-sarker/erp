<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ManagementBudgetLine extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'management_budget_id',
        'account_id',
        'month_number',
        'amount',
        'notes',
    ];

    /** @return BelongsTo<ManagementBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(ManagementBudget::class, 'management_budget_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'management_budget_id' => 'integer',
            'account_id' => 'integer',
            'month_number' => 'integer',
            'amount' => 'decimal:6',
        ];
    }
}

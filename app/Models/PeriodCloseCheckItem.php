<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PeriodCloseCheckItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'period_close_run_id',
        'check_key',
        'category',
        'label',
        'status',
        'is_blocking',
        'issue_count',
        'difference_amount',
        'message',
        'details',
        'checked_at',
    ];

    public function closeRun(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseRun::class, 'period_close_run_id');
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'period_close_run_id' => 'integer',
            'is_blocking' => 'boolean',
            'issue_count' => 'integer',
            'difference_amount' => 'decimal:6',
            'details' => 'array',
            'checked_at' => 'immutable_datetime',
        ];
    }
}

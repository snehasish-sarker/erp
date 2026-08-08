<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ManagementReportSchedule extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'created_by_user_id',
        'name',
        'report_type',
        'format',
        'frequency',
        'run_day',
        'run_time',
        'filters',
        'status',
        'next_run_at',
        'last_run_at',
        'last_status',
        'last_error',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'created_by_user_id' => 'integer',
            'run_day' => 'integer',
            'filters' => 'array',
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
        ];
    }
}
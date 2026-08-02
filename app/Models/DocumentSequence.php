<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentSequence extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'scope_key',
        'name',
        'document_type',
        'prefix',
        'suffix',
        'current_number',
        'number_padding',
        'reset_policy',
        'fiscal_year_start_month',
        'last_reset_key',
        'status',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<DocumentNumberAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            DocumentNumberAllocation::class,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'current_number' => 'integer',
            'number_padding' => 'integer',
            'fiscal_year_start_month' => 'integer',
        ];
    }
}
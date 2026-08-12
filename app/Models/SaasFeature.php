<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SaasFeature extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'value_type',
        'unit',
        'status',
        'sort_order',
    ];

    /**
     * @return HasMany<SaasPlanFeature, $this>
     */
    public function planEntitlements(): HasMany
    {
        return $this->hasMany(SaasPlanFeature::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}

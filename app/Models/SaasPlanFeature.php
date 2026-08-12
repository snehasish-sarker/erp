<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaasPlanFeature extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'saas_plan_id',
        'saas_feature_id',
        'enabled',
        'limit_value',
    ];

    /**
     * @return BelongsTo<SaasPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /**
     * @return BelongsTo<SaasFeature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(SaasFeature::class, 'saas_feature_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }
}

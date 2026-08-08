<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductionAcceptanceCheckItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'production_acceptance_run_id',
        'sequence',
        'category',
        'check_key',
        'label',
        'status',
        'blocking',
        'message',
        'context',
    ];

    /** @return BelongsTo<ProductionAcceptanceRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ProductionAcceptanceRun::class, 'production_acceptance_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'production_acceptance_run_id' => 'integer',
            'sequence' => 'integer',
            'blocking' => 'boolean',
            'context' => 'array',
        ];
    }
}

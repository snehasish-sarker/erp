<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @mixin Model
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(
            'tenant',
            static function (Builder $builder): void {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId === null) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->where(
                    $builder->getModel()->qualifyColumn('tenant_id'),
                    $tenantId,
                );
            },
        );

        static::creating(
            static function (Model $model): void {
                if ($model->getAttribute('tenant_id') !== null) {
                    return;
                }

                $tenantId = app(TenantContext::class)->id();

                if ($tenantId === null) {
                    throw new LogicException(
                        'A tenant context is required to create this record.',
                    );
                }

                $model->setAttribute('tenant_id', $tenantId);
            },
        );
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        forward_static_call(
            [static::class, 'addGlobalScope'],
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

        forward_static_call(
            [static::class, 'creating'],
            static function (Model $model): void {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId === null) {
                    throw new LogicException(
                        'A tenant-scoped model cannot be created without an active tenant context.',
                    );
                }

                $existingTenantId = $model->getAttribute(
                    'tenant_id',
                );

                if (
                    $existingTenantId !== null
                    && (int) $existingTenantId !== $tenantId
                ) {
                    throw new LogicException(
                        'A tenant-scoped model cannot be created for a different tenant.',
                    );
                }

                $model->setAttribute(
                    'tenant_id',
                    $tenantId,
                );
            },
        );
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class,
            'tenant_id',
        );
    }
}
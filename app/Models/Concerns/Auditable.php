<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Auditing\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @phpstan-require-extends Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(
            static function (Model $model): void {
                app(AuditLogService::class)
                    ->recordCreated($model);
            },
        );

        static::updated(
            static function (Model $model): void {
                app(AuditLogService::class)
                    ->recordUpdated($model);
            },
        );

        static::deleted(
            static function (Model $model): void {
                app(AuditLogService::class)
                    ->recordDeleted($model);
            },
        );

        static::restored(
            static function (Model $model): void {
                app(AuditLogService::class)
                    ->recordRestored($model);
            },
        );
    }

    /**
     * Models may override this method to hide additional attributes.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [];
    }
}
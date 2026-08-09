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

        /*
         * restored() is provided by Laravel's SoftDeletes trait.
         *
         * Many auditable ERP models are not soft-deletable, so calling
         * static::restored() unconditionally causes Eloquent to fall
         * through to Model::__callStatic(), which constructs the model
         * again while that same model is still booting.
         */
        if (method_exists(static::class, 'restored')) {
            forward_static_call(
                [static::class, 'restored'],
                static function (Model $model): void {
                    app(AuditLogService::class)
                        ->recordRestored($model);
                },
            );
        }
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
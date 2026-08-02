<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\UserNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

final class PruneUserNotifications extends Command
{
    /**
     * @var string
     */
    protected $signature =
        'notifications:prune
        {--days= : Delete read notifications older than this number of days}
        {--limit=5000 : Maximum notifications to delete per tenant}';

    /**
     * @var string
     */
    protected $description =
        'Delete old read tenant notifications after their retention period.';

    public function handle(
        TenantContext $tenantContext,
    ): int {
        $configuredDays = max(
            1,
            (int) config(
                'erp-notifications.retention_days',
                90,
            ),
        );

        $daysOption = $this->option('days');

        $retentionDays =
            is_numeric($daysOption)
                ? max(1, (int) $daysOption)
                : $configuredDays;

        $limit = max(
            1,
            (int) $this->option('limit'),
        );

        $deletedCount = 0;
        $failedTenantCount = 0;

        Tenant::query()
            ->select([
                'id',
                'name',
                'code',
                'status',
                'timezone',
            ])
            ->orderBy('id')
            ->chunkById(
                50,
                function ($tenants) use (
                    $tenantContext,
                    $retentionDays,
                    $limit,
                    &$deletedCount,
                    &$failedTenantCount,
                ): void {
                    foreach ($tenants as $tenant) {
                        if (!$tenant instanceof Tenant) {
                            continue;
                        }

                        $tenantContext->set($tenant);

                        try {
                            $notificationIds =
                                UserNotification::query()
                                    ->whereNotNull('read_at')
                                    ->where(
                                        'created_at',
                                        '<=',
                                        now()->subDays(
                                            $retentionDays,
                                        ),
                                    )
                                    ->orderBy('id')
                                    ->limit($limit)
                                    ->pluck('id');

                            if (
                                $notificationIds
                                    ->isEmpty()
                            ) {
                                continue;
                            }

                            $deletedCount +=
                                UserNotification::query()
                                    ->whereIn(
                                        'id',
                                        $notificationIds,
                                    )
                                    ->delete();
                        } catch (
                            Throwable $exception
                        ) {
                            $failedTenantCount++;
                            report($exception);

                            $this->error(
                                sprintf(
                                    'Failed to prune notifications for tenant %s (%d).',
                                    $tenant->code,
                                    $tenant->getKey(),
                                ),
                            );
                        } finally {
                            $tenantContext->clear();
                        }
                    }
                },
            );

        $this->info(
            sprintf(
                'Deleted %d old read notification(s).',
                $deletedCount,
            ),
        );

        return $failedTenantCount === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
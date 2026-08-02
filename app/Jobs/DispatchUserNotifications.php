<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Notifications\NotificationMessageData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use LogicException;

final class DispatchUserNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param list<int> $recipientUserIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly array $recipientUserIds,
        public readonly NotificationMessageData $notification,
        public readonly ?int $actorUserId = null,
        public readonly ?string $actorName = null,
        public readonly ?string $actorEmail = null,
    ) {
        $queue = config(
            'erp-notifications.queue',
            'notifications',
        );

        if (
            is_string($queue)
            && $queue !== ''
        ) {
            $this->onQueue($queue);
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            30,
            120,
        ];
    }

    /**
     * @throws JsonException
     */
    public function handle(
        TenantContext $tenantContext,
    ): void {
        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->firstOrFail();

        if ($tenant->status !== 'active') {
            throw new LogicException(
                'Notifications cannot be dispatched for an inactive tenant.',
            );
        }

        $tenantContext->set($tenant);

        try {
            $encodedData =
                $this->notification->data === []
                    ? null
                    : json_encode(
                        $this->notification->data,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE,
                    );

            User::query()
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'active')
                ->whereIn(
                    'id',
                    $this->recipientUserIds,
                )
                ->orderBy('id')
                ->chunkById(
                    500,
                    function ($users) use (
                        $encodedData,
                    ): void {
                        $timestamp = now();
                        $rows = [];

                        foreach ($users as $user) {
                            if (!$user instanceof User) {
                                continue;
                            }

                            $rows[] = [
                                'tenant_id' =>
                                    $this->tenantId,

                                'recipient_user_id' =>
                                    $user->getKey(),

                                'actor_user_id' =>
                                    $this->actorUserId,

                                'notification_key' =>
                                    Str::uuid()->toString(),

                                'idempotency_key' =>
                                    $this->notification
                                        ->idempotencyKey,

                                'category' =>
                                    $this->notification
                                        ->category,

                                'type' =>
                                    $this->notification
                                        ->type,

                                'severity' =>
                                    $this->notification
                                        ->severity,

                                'title' =>
                                    $this->notification
                                        ->title,

                                'message' =>
                                    $this->notification
                                        ->message,

                                'action_url' =>
                                    $this->notification
                                        ->actionUrl,

                                'action_label' =>
                                    $this->notification
                                        ->actionLabel,

                                'source_type' =>
                                    $this->notification
                                        ->sourceType,

                                'source_id' =>
                                    $this->notification
                                        ->sourceId,

                                'actor_name' =>
                                    $this->actorName,

                                'actor_email' =>
                                    $this->actorEmail,

                                'data' => $encodedData,
                                'read_at' => null,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ];
                        }

                        if ($rows === []) {
                            return;
                        }

                        /*
                         * A retried job cannot create duplicate recipient
                         * notifications because of the tenant-recipient-
                         * idempotency unique constraint.
                         */
                        UserNotification::query()
                            ->insertOrIgnore($rows);
                    },
                );
        } finally {
            $tenantContext->clear();
        }
    }
}
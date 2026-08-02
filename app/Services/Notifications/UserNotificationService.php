<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\DispatchUserNotifications;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Notifications\NotificationCategoryRegistry;
use App\Support\Notifications\NotificationMessageData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

final class UserNotificationService
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'secret',
        'token',
        'otp',
        'private_key',
        'recovery_code',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationCategoryRegistry $categoryRegistry,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    public function queueForUser(
        User $recipient,
        NotificationMessageData $notification,
        ?User $actor = null,
    ): int {
        return $this->queueForUsers(
            recipientUserIds: [
                (int) $recipient->getKey(),
            ],
            notification: $notification,
            actor: $actor,
        );
    }

    /**
     * @param list<int> $recipientUserIds
     */
    public function queueForUsers(
        array $recipientUserIds,
        NotificationMessageData $notification,
        ?User $actor = null,
    ): int {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $notification = $this->normalizeAndValidate(
            $notification,
        );

        if ($actor !== null) {
            $this->ensureUserBelongsToTenant(
                user: $actor,
                tenantId: $tenantId,
                field: 'actor',
            );
        }

        $recipientUserIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (
                            int $recipientUserId,
                        ): int => $recipientUserId,
                        $recipientUserIds,
                    ),
                    static fn (
                        int $recipientUserId,
                    ): bool => $recipientUserId > 0,
                ),
            ),
        );

        if ($recipientUserIds === []) {
            return 0;
        }

        $activeRecipientIds = User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereIn('id', $recipientUserIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(
                static fn (mixed $id): int =>
                    (int) $id,
            )
            ->all();

        if ($activeRecipientIds === []) {
            return 0;
        }

        $batchSize = max(
            1,
            (int) config(
                'erp-notifications.dispatch_batch_size',
                500,
            ),
        );

        foreach (
            array_chunk(
                $activeRecipientIds,
                $batchSize,
            )
            as $recipientBatch
        ) {
            DispatchUserNotifications::dispatch(
                tenantId: $tenantId,
                recipientUserIds: $recipientBatch,
                notification: $notification,
                actorUserId: $actor === null
                    ? null
                    : (int) $actor->getKey(),
                actorName: $actor?->name,
                actorEmail: $actor?->email,
            )->afterCommit();
        }

        return count($activeRecipientIds);
    }

    public function queueForPermission(
        string $permission,
        NotificationMessageData $notification,
        ?User $actor = null,
        ?int $branchId = null,
    ): int {
        $permission = trim($permission);

        if ($permission === '') {
            throw new LogicException(
                'A permission name is required to target notification recipients.',
            );
        }

        $tenantId = (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();

        /*
         * Spatie Permission must use the same tenant team ID as the active
         * tenant before applying the permission scope.
         */
        $this->permissionRegistrar
            ->setPermissionsTeamId($tenantId);

        $recipientIds = User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->when(
                $branchId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->permission(
                $permission,
                'web',
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(
                static fn (mixed $id): int =>
                    (int) $id,
            )
            ->all();

        return $this->queueForUsers(
            recipientUserIds: $recipientIds,
            notification: $notification,
            actor: $actor,
        );
    }

    public function markAsRead(
        UserNotification $userNotification,
        User $actor,
    ): UserNotification {
        return DB::transaction(
            function () use (
                $userNotification,
                $actor,
            ): UserNotification {
                $lockedNotification =
                    UserNotification::query()
                        ->whereKey(
                            $userNotification->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->ensureNotificationBelongsToUser(
                    userNotification:
                        $lockedNotification,
                    user: $actor,
                );

                if ($lockedNotification->isUnread()) {
                    $lockedNotification->read_at =
                        now();

                    $lockedNotification->saveQuietly();
                }

                return $lockedNotification->refresh();
            },
            attempts: 5,
        );
    }

    public function markAllAsRead(User $actor): int
    {
        $tenantId = (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();

        $this->ensureUserBelongsToTenant(
            user: $actor,
            tenantId: $tenantId,
            field: 'user',
        );

        return UserNotification::query()
            ->where(
                'recipient_user_id',
                $actor->getKey(),
            )
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function unreadCount(User $user): int
    {
        $tenantId = (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();

        $this->ensureUserBelongsToTenant(
            user: $user,
            tenantId: $tenantId,
            field: 'user',
        );

        return UserNotification::query()
            ->where(
                'recipient_user_id',
                $user->getKey(),
            )
            ->whereNull('read_at')
            ->count();
    }

    private function normalizeAndValidate(
        NotificationMessageData $notification,
    ): NotificationMessageData {
        $idempotencyKey = trim(
            $notification->idempotencyKey,
        );

        $category = trim(
            $notification->category,
        );

        $type = trim(
            $notification->type,
        );

        $severity = trim(
            $notification->severity,
        );

        $title = trim(
            $notification->title,
        );

        $message = trim(
            $notification->message,
        );

        $actionUrl = $this->nullableTrimmedString(
            $notification->actionUrl,
        );

        $actionLabel = $this->nullableTrimmedString(
            $notification->actionLabel,
        );

        $sourceType = $this->nullableTrimmedString(
            $notification->sourceType,
        );

        $sourceId = $this->nullableTrimmedString(
            $notification->sourceId,
        );

        $errors = [];

        if (
            $idempotencyKey === ''
            || mb_strlen($idempotencyKey) > 160
        ) {
            $errors['idempotency_key'] = [
                'The notification idempotency key is required and may not exceed 160 characters.',
            ];
        }

        if (
            !$this->categoryRegistry
                ->categoryExists($category)
        ) {
            $errors['category'] = [
                'The selected notification category is invalid.',
            ];
        }

        if (
            $type === ''
            || mb_strlen($type) > 100
            || preg_match(
                '/^[a-z0-9_.-]+$/',
                $type,
            ) !== 1
        ) {
            $errors['type'] = [
                'The notification type may contain lowercase letters, numbers, dots, hyphens, and underscores only.',
            ];
        }

        if (
            !$this->categoryRegistry
                ->severityExists($severity)
        ) {
            $errors['severity'] = [
                'The selected notification severity is invalid.',
            ];
        }

        if (
            $title === ''
            || mb_strlen($title) > 160
        ) {
            $errors['title'] = [
                'The notification title is required and may not exceed 160 characters.',
            ];
        }

        if (
            $message === ''
            || mb_strlen($message) > 2000
        ) {
            $errors['message'] = [
                'The notification message is required and may not exceed 2,000 characters.',
            ];
        }

        if (
            $actionUrl !== null
            && (
                mb_strlen($actionUrl) > 500
                || !str_starts_with(
                    $actionUrl,
                    '/',
                )
                || str_starts_with(
                    $actionUrl,
                    '//',
                )
            )
        ) {
            $errors['action_url'] = [
                'The notification action URL must be an internal application path.',
            ];
        }

        if (
            $actionLabel !== null
            && $actionUrl === null
        ) {
            $errors['action_label'] = [
                'An action label requires an action URL.',
            ];
        }

        if (
            $actionLabel !== null
            && mb_strlen($actionLabel) > 80
        ) {
            $errors['action_label'] = [
                'The notification action label may not exceed 80 characters.',
            ];
        }

        if (
            $sourceType !== null
            && mb_strlen($sourceType) > 150
        ) {
            $errors['source_type'] = [
                'The notification source type may not exceed 150 characters.',
            ];
        }

        if (
            $sourceId !== null
            && mb_strlen($sourceId) > 100
        ) {
            $errors['source_id'] = [
                'The notification source ID may not exceed 100 characters.',
            ];
        }

        $data = $this->sanitizeData(
            $notification->data,
        );

        try {
            $encodedData = json_encode(
                $data,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            $encodedData = false;
        }

        if (
            !is_string($encodedData)
            || strlen($encodedData) > 65535
        ) {
            $errors['data'] = [
                'The notification metadata is invalid or too large.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors,
            );
        }

        return new NotificationMessageData(
            idempotencyKey: $idempotencyKey,
            category: $category,
            type: $type,
            severity: $severity,
            title: $title,
            message: $message,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            sourceType: $sourceType,
            sourceId: $sourceId,
            data: $data,
        );
    }

    private function ensureNotificationBelongsToUser(
        UserNotification $userNotification,
        User $user,
    ): void {
        if (
            (int) $userNotification->tenant_id
                !== (int) $user->tenant_id
            || (int) $userNotification
                ->recipient_user_id
                !== (int) $user->getKey()
        ) {
            throw new LogicException(
                'The notification does not belong to the selected user.',
            );
        }
    }

    private function ensureUserBelongsToTenant(
        User $user,
        int $tenantId,
        string $field,
    ): void {
        if ((int) $user->tenant_id !== $tenantId) {
            throw new LogicException(
                "The {$field} belongs to another tenant.",
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = (string) $key;

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$normalizedKey] =
                    $this->sanitizeData($value);

                continue;
            }

            if (
                is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
                || $value === null
            ) {
                $sanitized[$normalizedKey] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach (
            self::SENSITIVE_KEY_PARTS
            as $sensitivePart
        ) {
            if (
                str_contains(
                    $key,
                    $sensitivePart,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function nullableTrimmedString(
        ?string $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\SaasPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaasSubscriptionHistoryService
{
    /** @var list<string> */
    private const EVENTS = [
        'saas_plan_assigned',
        'saas_subscription_manually_updated',
        'saas_subscription_manually_activated',
        'saas_subscription_manually_suspended',
        'saas_subscription_quick_action_applied',
        'saas_trial_extended',
        'saas_subscription_past_due',
        'saas_subscription_suspended',
    ];

    /**
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'saas_plan_assigned' => 'Package Assigned',
        'saas_subscription_manually_updated' => 'Manual Package Update',
        'saas_subscription_manually_activated' => 'Manually Activated',
        'saas_subscription_manually_suspended' => 'Manually Suspended',
        'saas_subscription_quick_action_applied' => 'Quick Action',
        'saas_trial_extended' => 'Trial Extended',
        'saas_subscription_past_due' => 'Moved to Past Due',
        'saas_subscription_suspended' => 'Lifecycle Suspended',
    ];

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, int|string|null>
     * }
     */
    public function paginate(array $filters): array
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);
        $this->applySort(
            query: $query,
            sort: (string) $filters['sort'],
            direction: (string) $filters['direction'],
        );

        /** @var LengthAwarePaginator<int, AuditLog> $paginator */
        $paginator = $query
            ->paginate((int) $filters['per_page'])
            ->withQueryString();

        $planMap = $this->planMapForLogs(
            $paginator->getCollection(),
        );

        return [
            'data' => $paginator->getCollection()
                ->map(
                    fn (AuditLog $log): array => $this->presentLog(
                        log: $log,
                        planMap: $planMap,
                    ),
                )
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'previous_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * @return array{
     *     total_events: int,
     *     manual_actions: int,
     *     lifecycle_actions: int,
     *     trial_extensions: int,
     *     last_30_days: int
     * }
     */
    public function metrics(): array
    {
        $row = DB::table('audit_logs')
            ->whereIn('event', self::EVENTS)
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw(
                "SUM(CASE WHEN event IN ('saas_plan_assigned', 'saas_subscription_manually_updated', 'saas_subscription_manually_activated', 'saas_subscription_manually_suspended', 'saas_subscription_quick_action_applied') THEN 1 ELSE 0 END) as manual_actions",
            )
            ->selectRaw(
                "SUM(CASE WHEN event IN ('saas_subscription_past_due', 'saas_subscription_suspended') THEN 1 ELSE 0 END) as lifecycle_actions",
            )
            ->selectRaw(
                "SUM(CASE WHEN event = 'saas_trial_extended' THEN 1 ELSE 0 END) as trial_extensions",
            )
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_30_days',
                [now()->subDays(30)],
            )
            ->first();

        return [
            'total_events' => (int) ($row?->total_events ?? 0),
            'manual_actions' => (int) ($row?->manual_actions ?? 0),
            'lifecycle_actions' => (int) ($row?->lifecycle_actions ?? 0),
            'trial_extensions' => (int) ($row?->trial_extensions ?? 0),
            'last_30_days' => (int) ($row?->last_30_days ?? 0),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function eventOptions(): array
    {
        return collect(self::EVENTS)
            ->map(
                fn (string $event): array => [
                    'value' => $event,
                    'label' => self::EVENT_LABELS[$event],
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    public function selectedTenant(?int $tenantId): ?array
    {
        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::withTrashed()->find($tenantId);

        if (!$tenant instanceof Tenant) {
            return null;
        }

        return [
            'id' => (int) $tenant->getKey(),
            'name' => (string) $tenant->name,
            'code' => (string) $tenant->code,
        ];
    }

    /** @return Builder<AuditLog> */
    private function baseQuery(): Builder
    {
        return AuditLog::withoutGlobalScope('tenant')
            ->leftJoin(
                'tenants',
                static function (JoinClause $join): void {
                    $join->on(
                        'tenants.id',
                        '=',
                        'audit_logs.tenant_id',
                    );
                },
            )
            ->whereIn('audit_logs.event', self::EVENTS)
            ->select([
                'audit_logs.*',
                'tenants.name as history_tenant_name',
                'tenants.code as history_tenant_code',
            ]);
    }

    /**
     * @param Builder<AuditLog> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
    ): void {
        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(
                static function (Builder $nested) use ($search): void {
                    $like = '%' . addcslashes($search, '%_\\') . '%';

                    $nested
                        ->where('tenants.name', 'like', $like)
                        ->orWhere('tenants.code', 'like', $like)
                        ->orWhere('audit_logs.actor_name', 'like', $like)
                        ->orWhere('audit_logs.actor_email', 'like', $like)
                        ->orWhere('audit_logs.subject_label', 'like', $like)
                        ->orWhere('audit_logs.event', 'like', $like);
                },
            );
        }

        $tenantId = $filters['tenant_id'] ?? null;

        if (is_int($tenantId)) {
            $query->where('audit_logs.tenant_id', $tenantId);
        }

        $event = $filters['event'] ?? null;

        if (is_string($event) && $event !== '') {
            $query->where('audit_logs.event', $event);
        }

        $actorType = $filters['actor_type'] ?? null;

        if ($actorType === 'platform_admin') {
            $query->where(
                static function (Builder $nested): void {
                    $nested
                        ->where(
                            'audit_logs.metadata->actor_type',
                            'platform_admin',
                        )
                        ->orWhereNotNull('audit_logs.actor_name');
                },
            );
        } elseif ($actorType === 'system') {
            $query->where(
                static function (Builder $nested): void {
                    $nested
                        ->where(
                            'audit_logs.metadata->actor_type',
                            'system',
                        )
                        ->orWhere(
                            static function (Builder $systemActor): void {
                                $systemActor
                                    ->whereNull('audit_logs.actor_name')
                                    ->whereNull('audit_logs.actor_email');
                            },
                        );
                },
            );
        }

        $dateFrom = $filters['date_from'] ?? null;

        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->whereDate(
                'audit_logs.created_at',
                '>=',
                $dateFrom,
            );
        }

        $dateTo = $filters['date_to'] ?? null;

        if (is_string($dateTo) && $dateTo !== '') {
            $query->whereDate(
                'audit_logs.created_at',
                '<=',
                $dateTo,
            );
        }
    }

    /** @param Builder<AuditLog> $query */
    private function applySort(
        Builder $query,
        string $sort,
        string $direction,
    ): void {
        $column = match ($sort) {
            'tenant' => 'tenants.name',
            'event' => 'audit_logs.event',
            'actor' => 'audit_logs.actor_name',
            default => 'audit_logs.created_at',
        };

        $query
            ->orderBy($column, $direction)
            ->orderBy('audit_logs.id', $direction);
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array<int, array{name: string, code: string}>
     */
    private function planMapForLogs(Collection $logs): array
    {
        $planIds = $logs
            ->flatMap(
                static function (AuditLog $log): array {
                    $oldValues = is_array($log->old_values)
                        ? $log->old_values
                        : [];
                    $newValues = is_array($log->new_values)
                        ? $log->new_values
                        : [];

                    return [
                        $oldValues['saas_plan_id'] ?? null,
                        $newValues['saas_plan_id'] ?? null,
                    ];
                },
            )
            ->filter(
                static fn (mixed $value): bool => is_numeric($value),
            )
            ->map(
                static fn (mixed $value): int => (int) $value,
            )
            ->unique()
            ->values();

        if ($planIds->isEmpty()) {
            return [];
        }

        return SaasPlan::withTrashed()
            ->whereIn('id', $planIds->all())
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(
                static fn (SaasPlan $plan): array => [
                    (int) $plan->getKey() => [
                        'name' => (string) $plan->name,
                        'code' => (string) $plan->code,
                    ],
                ],
            )
            ->all();
    }

    /**
     * @param array<int, array{name: string, code: string}> $planMap
     * @return array<string, mixed>
     */
    private function presentLog(
        AuditLog $log,
        array $planMap,
    ): array {
        $oldValues = is_array($log->old_values)
            ? $log->old_values
            : [];
        $newValues = is_array($log->new_values)
            ? $log->new_values
            : [];
        $metadata = is_array($log->metadata)
            ? $log->metadata
            : [];

        $tenantName = $log->getAttribute('history_tenant_name');
        $tenantCode = $log->getAttribute('history_tenant_code');

        return [
            'id' => (int) $log->getKey(),
            'event' => (string) $log->event,
            'event_label' => self::EVENT_LABELS[(string) $log->event]
                ?? Str::headline((string) $log->event),
            'tenant' => [
                'id' => (int) $log->tenant_id,
                'name' => is_string($tenantName)
                    ? $tenantName
                    : ((string) ($log->subject_label ?? 'Tenant')),
                'code' => is_string($tenantCode)
                    ? $tenantCode
                    : '—',
            ],
            'actor' => [
                'type' => $this->actorType($log, $metadata),
                'name' => $log->actor_name,
                'email' => $log->actor_email,
            ],
            'reason' => $this->reasonLabel(
                event: (string) $log->event,
                metadata: $metadata,
            ),
            'changes' => $this->presentChanges(
                oldValues: $oldValues,
                newValues: $newValues,
                planMap: $planMap,
            ),
            'metadata' => $this->presentMetadata($metadata),
            'request_id' => $log->request_id,
            'route_name' => $log->route_name,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function actorType(
        AuditLog $log,
        array $metadata,
    ): string {
        $actorType = $metadata['actor_type'] ?? null;

        if (in_array($actorType, ['platform_admin', 'system'], true)) {
            return (string) $actorType;
        }

        if ($log->actor_name !== null || $log->actor_email !== null) {
            return 'platform_admin';
        }

        return 'system';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function reasonLabel(
        string $event,
        array $metadata,
    ): ?string {
        $daysAdded = $metadata['days_added'] ?? null;

        if ($event === 'saas_trial_extended' && is_numeric($daysAdded)) {
            $days = (int) $daysAdded;

            return sprintf(
                'Trial extended by %d %s.',
                $days,
                $days === 1 ? 'day' : 'days',
            );
        }

        $quickActionLabel = $metadata['quick_action_label'] ?? null;

        if (
            $event === 'saas_subscription_quick_action_applied'
            && is_string($quickActionLabel)
            && $quickActionLabel !== ''
        ) {
            return $quickActionLabel . '.';
        }

        $pastDueReason = $metadata['past_due_reason'] ?? null;

        if (is_string($pastDueReason) && $pastDueReason !== '') {
            return match ($pastDueReason) {
                'trial_expired' => 'Trial period expired.',
                'period_expired' => 'Configured subscription period expired.',
                'manual' => 'Marked past due manually.',
                default => Str::headline($pastDueReason) . '.',
            };
        }

        return match ($event) {
            'saas_plan_assigned' => 'Package assigned to the tenant.',
            'saas_subscription_manually_updated' => 'Package and lifecycle values changed manually by Super Admin.',
            'saas_subscription_manually_activated' => 'Tenant subscription access activated manually.',
            'saas_subscription_manually_suspended' => 'Tenant subscription access suspended manually.',
            'saas_subscription_suspended' => 'Grace period ended and the lifecycle processor suspended access.',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<int, array{name: string, code: string}> $planMap
     * @return list<array{
     *     field: string,
     *     label: string,
     *     old_value: string|null,
     *     new_value: string|null
     * }>
     */
    private function presentChanges(
        array $oldValues,
        array $newValues,
        array $planMap,
    ): array {
        $definitions = [
            'package' => [
                'label' => 'Package',
                'keys' => ['saas_plan_id'],
            ],
            'subscription_status' => [
                'label' => 'Subscription status',
                'keys' => ['status', 'subscription_status'],
            ],
            'tenant_status' => [
                'label' => 'Tenant status',
                'keys' => ['tenant_status'],
            ],
            'billing_cycle' => [
                'label' => 'Billing cycle',
                'keys' => ['billing_cycle'],
            ],
            'billing_currency_code' => [
                'label' => 'Billing currency',
                'keys' => ['billing_currency_code'],
            ],
            'starts_at' => [
                'label' => 'Subscription starts',
                'keys' => ['starts_at'],
            ],
            'trial_ends_at' => [
                'label' => 'Trial ends',
                'keys' => ['trial_ends_at'],
            ],
            'current_period_starts_at' => [
                'label' => 'Current period starts',
                'keys' => ['current_period_starts_at'],
            ],
            'current_period_ends_at' => [
                'label' => 'Current period ends',
                'keys' => ['current_period_ends_at'],
            ],
            'past_due_at' => [
                'label' => 'Past due at',
                'keys' => ['past_due_at'],
            ],
            'grace_ends_at' => [
                'label' => 'Grace ends',
                'keys' => ['grace_ends_at'],
            ],
            'suspended_at' => [
                'label' => 'Suspended at',
                'keys' => ['suspended_at'],
            ],
            'cancelled_at' => [
                'label' => 'Cancelled at',
                'keys' => ['cancelled_at'],
            ],
            'ends_at' => [
                'label' => 'Subscription ends',
                'keys' => ['ends_at'],
            ],
        ];

        $changes = [];

        foreach ($definitions as $field => $definition) {
            [$oldExists, $oldValue] = $this->firstValue(
                $oldValues,
                $definition['keys'],
            );
            [$newExists, $newValue] = $this->firstValue(
                $newValues,
                $definition['keys'],
            );

            if (!$oldExists && !$newExists) {
                continue;
            }

            $oldDisplay = $field === 'package'
                ? $this->planLabel($oldValue, $planMap)
                : $this->scalarLabel($oldValue);
            $newDisplay = $field === 'package'
                ? $this->planLabel($newValue, $planMap)
                : $this->scalarLabel($newValue);

            if ($oldDisplay === $newDisplay) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $definition['label'],
                'old_value' => $oldDisplay,
                'new_value' => $newDisplay,
            ];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $keys
     * @return array{0: bool, 1: mixed}
     */
    private function firstValue(
        array $values,
        array $keys,
    ): array {
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                return [true, $values[$key]];
            }
        }

        return [false, null];
    }

    /**
     * @param array<int, array{name: string, code: string}> $planMap
     */
    private function planLabel(
        mixed $value,
        array $planMap,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return $this->scalarLabel($value);
        }

        $planId = (int) $value;
        $plan = $planMap[$planId] ?? null;

        if ($plan === null) {
            return "Package #{$planId}";
        }

        return sprintf(
            '%s (%s)',
            $plan['name'],
            $plan['code'],
        );
    }

    private function scalarLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) ?: null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<array{label: string, value: string}>
     */
    private function presentMetadata(array $metadata): array
    {
        $definitions = [
            'tenant_subscription_id' => 'Subscription ID',
            'quick_action' => 'Quick action',
            'quick_action_label' => 'Action',
            'days_added' => 'Days added',
            'past_due_reason' => 'Past-due reason',
            'expired_at' => 'Expired at',
            'grace_ends_at' => 'Grace ends',
            'source' => 'Source',
        ];

        $items = [];

        foreach ($definitions as $key => $label) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $this->scalarLabel($metadata[$key]);

            if ($value === null) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $items;
    }
}

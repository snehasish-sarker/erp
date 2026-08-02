<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auditing;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(
        IndexAuditLogRequest $request,
    ): Response {
        Gate::authorize('viewAny', AuditLog::class);

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $event = (string) ($validated['event'] ?? '');

        $subjectType = (string) (
            $validated['subject_type'] ?? ''
        );

        $actor = (string) ($validated['actor'] ?? '');

        $dateFrom = (string) (
            $validated['date_from'] ?? ''
        );

        $dateTo = (string) (
            $validated['date_to'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'created_at'
        );

        $direction = (string) (
            $validated['direction'] ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $tenantTimezone = $this->tenantContext
            ->tenant()
            ->timezone;

        $dateFromUtc = $dateFrom === ''
            ? null
            : CarbonImmutable::parse(
                $dateFrom,
                $tenantTimezone,
            )->startOfDay()->utc();

        $dateToUtc = $dateTo === ''
            ? null
            : CarbonImmutable::parse(
                $dateTo,
                $tenantTimezone,
            )->endOfDay()->utc();

        $auditLogs = AuditLog::query()
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'actor_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'actor_email',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'subject_label',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'subject_type',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'request_id',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'route_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'ip_address',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $event !== '',
                static fn (Builder $query): Builder =>
                    $query->where('event', $event),
            )
            ->when(
                $subjectType !== '',
                static fn (Builder $query): Builder =>
                    $query->where(
                        'subject_type',
                        $subjectType,
                    ),
            )
            ->when(
                $actor === 'system',
                static fn (Builder $query): Builder =>
                    $query->whereNull('actor_user_id'),
            )
            ->when(
                $actor !== ''
                && $actor !== 'system',
                static fn (Builder $query): Builder =>
                    $query->where(
                        'actor_user_id',
                        (int) $actor,
                    ),
            )
            ->when(
                $dateFromUtc !== null,
                static fn (Builder $query): Builder =>
                    $query->where(
                        'created_at',
                        '>=',
                        $dateFromUtc,
                    ),
            )
            ->when(
                $dateToUtc !== null,
                static fn (Builder $query): Builder =>
                    $query->where(
                        'created_at',
                        '<=',
                        $dateToUtc,
                    ),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'AuditLogs/Index',
            [
                'auditLogs' => [
                    'data' => $auditLogs
                        ->getCollection()
                        ->map(
                            fn (AuditLog $auditLog): array =>
                                $this->auditLogListData(
                                    $auditLog,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $auditLogs->currentPage(),

                        'last_page' =>
                            $auditLogs->lastPage(),

                        'per_page' =>
                            $auditLogs->perPage(),

                        'from' =>
                            $auditLogs->firstItem(),

                        'to' =>
                            $auditLogs->lastItem(),

                        'total' =>
                            $auditLogs->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'event' => $event,
                    'subject_type' => $subjectType,
                    'actor' => $actor,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'eventOptions' =>
                    $this->eventOptions(),

                'subjectTypeOptions' =>
                    $this->subjectTypeOptions(),

                'actorOptions' =>
                    $this->actorOptions(),

                'tenantTimezone' =>
                    $tenantTimezone,
            ],
        );
    }

    public function show(
        AuditLog $auditLog,
    ): Response {
        Gate::authorize('view', $auditLog);

        return Inertia::render(
            'AuditLogs/Show',
            [
                'auditLog' =>
                    $this->auditLogDetailData(
                        $auditLog,
                    ),

                'tenantTimezone' =>
                    $this->tenantContext
                        ->tenant()
                        ->timezone,
            ],
        );
    }

    /**
     * @return array{
     *     id: int,
     *     event: string,
     *     actor_user_id: int|null,
     *     actor_name: string|null,
     *     actor_email: string|null,
     *     subject_type: string,
     *     subject_type_label: string,
     *     subject_id: int|null,
     *     subject_label: string|null,
     *     changes_count: int,
     *     request_id: string|null,
     *     route_name: string|null,
     *     http_method: string|null,
     *     ip_address: string|null,
     *     created_at: string|null
     * }
     */
    private function auditLogListData(
        AuditLog $auditLog,
    ): array {
        $oldValues = is_array(
            $auditLog->old_values,
        )
            ? $auditLog->old_values
            : [];

        $newValues = is_array(
            $auditLog->new_values,
        )
            ? $auditLog->new_values
            : [];

        return [
            'id' => (int) $auditLog->getKey(),
            'event' => $auditLog->event,

            'actor_user_id' =>
                $auditLog->actor_user_id === null
                    ? null
                    : (int) $auditLog->actor_user_id,

            'actor_name' => $auditLog->actor_name,
            'actor_email' => $auditLog->actor_email,
            'subject_type' => $auditLog->subject_type,

            'subject_type_label' =>
                $this->subjectTypeLabel(
                    $auditLog->subject_type,
                ),

            'subject_id' =>
                $auditLog->subject_id === null
                    ? null
                    : (int) $auditLog->subject_id,

            'subject_label' => $auditLog->subject_label,

            'changes_count' => count(
                array_unique([
                    ...array_keys($oldValues),
                    ...array_keys($newValues),
                ]),
            ),

            'request_id' => $auditLog->request_id,
            'route_name' => $auditLog->route_name,
            'http_method' => $auditLog->http_method,
            'ip_address' => $auditLog->ip_address,

            'created_at' =>
                $auditLog->created_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditLogDetailData(
        AuditLog $auditLog,
    ): array {
        return [
            ...$this->auditLogListData(
                $auditLog,
            ),

            'old_values' => $auditLog->old_values,
            'new_values' => $auditLog->new_values,
            'metadata' => $auditLog->metadata,
            'url' => $auditLog->url,
            'user_agent' => $auditLog->user_agent,
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function eventOptions(): array
    {
        return AuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->map(
                static fn (string $event): array => [
                    'value' => $event,

                    'label' => Str::headline(
                        str_replace(
                            '.',
                            ' ',
                            $event,
                        ),
                    ),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function subjectTypeOptions(): array
    {
        return AuditLog::query()
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(
                fn (string $subjectType): array => [
                    'value' => $subjectType,

                    'label' =>
                        $this->subjectTypeLabel(
                            $subjectType,
                        ),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     value: string,
     *     name: string,
     *     email: string|null
     * }>
     */
    private function actorOptions(): array
    {
        return AuditLog::query()
            ->whereNotNull('actor_user_id')
            ->select('actor_user_id')
            ->selectRaw(
                'MAX(actor_name) AS actor_name',
            )
            ->selectRaw(
                'MAX(actor_email) AS actor_email',
            )
            ->groupBy('actor_user_id')
            ->orderBy('actor_name')
            ->get()
            ->map(
                static fn (
                    AuditLog $auditLog,
                ): array => [
                    'value' => (string) (
                        $auditLog->actor_user_id
                    ),

                    'name' =>
                        $auditLog->actor_name
                        ?? 'Unknown user',

                    'email' =>
                        $auditLog->actor_email,
                ],
            )
            ->values()
            ->all();
    }

    private function subjectTypeLabel(
        string $subjectType,
    ): string {
        return Str::headline(
            class_basename($subjectType),
        );
    }
}
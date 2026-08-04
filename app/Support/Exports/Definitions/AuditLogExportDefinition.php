<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Exports\ExportDefinition;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as LaravelValidator;
use LogicException;

final class AuditLogExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function key(): string
    {
        return 'audit_logs';
    }

    public function label(): string
    {
        return 'Audit Logs';
    }

    public function requiredPermission(): string
    {
        return 'audit_logs.view';
    }

    public function isSelectableFromExportCenter(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Occurred At',
            'Actor Name',
            'Actor Email',
            'Event',
            'Subject Type',
            'Subject ID',
            'Subject Label',
            'Old Values',
            'New Values',
            'Metadata',
            'Request ID',
            'Route Name',
            'HTTP Method',
            'IP Address',
            'URL',
            'User Agent',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validateFilters(
        array $filters,
        User $requester,
    ): array {
        $validator = Validator::make(
            $filters,
            [
                'search' => [
                    'nullable',
                    'string',
                    'max:150',
                ],
                'event' => [
                    'nullable',
                    'string',
                    'max:50',
                    'regex:/^[a-z0-9_.-]+$/',
                ],
                'subject_type' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'actor' => [
                    'nullable',
                    'string',
                    'regex:/^(system|[1-9][0-9]*)$/',
                ],
                'date_from' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'date_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'direction' => [
                    'nullable',
                    'string',
                    Rule::in([
                        'asc',
                        'desc',
                    ]),
                ],
            ],
        );

        $validator->after(
            static function (
                LaravelValidator $validator,
            ) use ($filters): void {
                $dateFrom = trim(
                    (string) (
                        $filters['date_from'] ?? ''
                    ),
                );

                $dateTo = trim(
                    (string) (
                        $filters['date_to'] ?? ''
                    ),
                );

                if (
                    $dateFrom !== ''
                    && $dateTo !== ''
                    && $dateTo < $dateFrom
                ) {
                    $validator->errors()->add(
                        'date_to',
                        'The end date must be on or after the start date.',
                    );
                }
            },
        );

        if ($validator->fails()) {
            throw new ValidationException(
                $validator,
            );
        }

        $validated = $validator->validated();

        return [
            'search' => $this->nullableTrimmedString(
                $validated['search'] ?? null,
            ),

            'event' => $this->nullableTrimmedString(
                $validated['event'] ?? null,
            ),

            'subject_type' =>
                $this->nullableTrimmedString(
                    $validated['subject_type']
                        ?? null,
                ),

            'actor' => $this->nullableTrimmedString(
                $validated['actor'] ?? null,
            ),

            'date_from' =>
                $this->nullableTrimmedString(
                    $validated['date_from']
                        ?? null,
                ),

            'date_to' =>
                $this->nullableTrimmedString(
                    $validated['date_to']
                        ?? null,
                ),

            'direction' => (string) (
                $validated['direction']
                    ?? 'desc'
            ),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return (clone $this->query($filters))->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, AuditLog>
     */
    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        $chunkSize = max(
            100,
            (int) config(
                'exports.chunk_size',
                500,
            ),
        );

        $query = $this->query($filters);

        return (
            $filters['direction'] ?? 'desc'
        ) === 'asc'
            ? $query->lazyById(
                $chunkSize,
                'id',
            )
            : $query->lazyByIdDesc(
                $chunkSize,
                'id',
            );
    }

    /**
     * @return list<string|int|float|null>
     */
    public function mapRow(mixed $model): array
    {
        if (!$model instanceof AuditLog) {
            throw new LogicException(
                'The audit-log exporter received an unsupported model.',
            );
        }

        $timezone = $this->tenantContext
            ->tenant()
            ->timezone;

        return [
            (int) $model->getKey(),

            $model->created_at === null
                ? null
                : CarbonImmutable::instance(
                    $model->created_at,
                )
                    ->setTimezone($timezone)
                    ->format('Y-m-d H:i:s'),

            $model->actor_name,
            $model->actor_email,
            $model->event,

            Str::afterLast(
                $model->subject_type,
                '\\',
            ),

            $model->subject_id,
            $model->subject_label,

            $this->encodeJson(
                $model->old_values,
            ),

            $this->encodeJson(
                $model->new_values,
            ),

            $this->encodeJson(
                $model->metadata,
            ),

            $model->request_id,
            $model->route_name,
            $model->http_method,
            $model->ip_address,
            $model->url,
            $model->user_agent,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters): Builder
    {
        $search = (string) (
            $filters['search'] ?? ''
        );

        $event = (string) (
            $filters['event'] ?? ''
        );

        $subjectType = (string) (
            $filters['subject_type'] ?? ''
        );

        $actor = (string) (
            $filters['actor'] ?? ''
        );

        $dateFrom = (string) (
            $filters['date_from'] ?? ''
        );

        $dateTo = (string) (
            $filters['date_to'] ?? ''
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

        return AuditLog::query()
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
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'event',
                    $event,
                ),
            )
            ->when(
                $subjectType !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'subject_type',
                    $subjectType,
                ),
            )
            ->when(
                $actor === 'system',
                static fn (
                    Builder $query,
                ): Builder => $query->whereNull(
                    'actor_user_id',
                ),
            )
            ->when(
                $actor !== ''
                && $actor !== 'system',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'actor_user_id',
                    (int) $actor,
                ),
            )
            ->when(
                $dateFromUtc !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'created_at',
                    '>=',
                    $dateFromUtc,
                ),
            )
            ->when(
                $dateToUtc !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'created_at',
                    '<=',
                    $dateToUtc,
                ),
            );
    }

    private function nullableTrimmedString(
        mixed $value,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @param array<string, mixed>|null $value
     */
    private function encodeJson(?array $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return is_string($encoded)
            ? $encoded
            : '';
    }
}
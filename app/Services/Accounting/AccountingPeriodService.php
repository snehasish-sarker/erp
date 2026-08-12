<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountingPeriodService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function generateFiscalYear(
        string $name,
        string $code,
        DateTimeInterface $startDate,
    ): FiscalYear {
        $name = trim($name);
        $code = mb_strtoupper(trim($code));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [
                    'The fiscal year name is required.',
                ],
            ]);
        }

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => [
                    'The fiscal year code is required.',
                ],
            ]);
        }

        $startDate = CarbonImmutable::instance(
            $startDate,
        )->startOfDay();

        if ($startDate->day !== 1) {
            throw ValidationException::withMessages([
                'start_date' => [
                    'A fiscal year must begin on the first day of a month.',
                ],
            ]);
        }

        $endDate = $startDate
            ->addMonthsNoOverflow(12)
            ->subDay();

        return DB::transaction(
            function () use (
                $name,
                $code,
                $startDate,
                $endDate,
            ): FiscalYear {
                $tenant = $this->lockTenant();

                $this->ensureCodeIsAvailable($code);

                $this->ensureFiscalYearDoesNotOverlap(
                    startDate: $startDate,
                    endDate: $endDate,
                );

                $fiscalYear = FiscalYear::query()->create([
                    'name' => $name,
                    'code' => $code,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => 'active',
                ]);

                for ($offset = 0; $offset < 12; $offset++) {
                    $periodStart = $startDate
                        ->addMonthsNoOverflow($offset)
                        ->startOfMonth();

                    $periodEnd = $periodStart->endOfMonth();

                    $periodNumber = $offset + 1;

                    AccountingPeriod::query()->create([
                        'fiscal_year_id' =>
                            $fiscalYear->getKey(),

                        'period_number' =>
                            $periodNumber,

                        'name' => $periodStart->format(
                            'F Y',
                        ),

                        'code' => sprintf(
                            '%s-P%02d',
                            $code,
                            $periodNumber,
                        ),

                        'start_date' =>
                            $periodStart->toDateString(),

                        'end_date' =>
                            $periodEnd->toDateString(),

                        'status' => 'open',
                        'closed_at' => null,
                        'closed_by_user_id' => null,
                    ]);
                }

                $fiscalYear->load([
                    'periods' => static function (
                        HasMany $relation,
                    ): void {
                        $relation->orderBy('period_number');
                    },
                ]);

                $this->auditLogService->recordCustomEvent(
                    subject: $fiscalYear,
                    event: 'periods_generated',
                    metadata: [
                        'period_count' => 12,
                        'tenant_code' => $tenant->code,
                        'start_date' =>
                            $startDate->toDateString(),

                        'end_date' =>
                            $endDate->toDateString(),
                    ],
                );

                return $fiscalYear;
            },
            attempts: 5,
        );
    }

    public function close(
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): AccountingPeriod {
        return DB::transaction(
            function () use (
                $accountingPeriod,
                $actor,
            ): AccountingPeriod {
                $this->lockTenant();

                $period = AccountingPeriod::query()
                    ->with('fiscalYear')
                    ->whereKey(
                        $accountingPeriod->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureActorBelongsToPeriodTenant(
                    actor: $actor,
                    accountingPeriod: $period,
                );

                if ($period->isClosed()) {
                    return $period;
                }

                $hasEarlierOpenPeriod =
                    AccountingPeriod::query()
                        ->where(
                            'fiscal_year_id',
                            $period->fiscal_year_id,
                        )
                        ->where(
                            'period_number',
                            '<',
                            $period->period_number,
                        )
                        ->where('status', 'open')
                        ->exists();

                if ($hasEarlierOpenPeriod) {
                    throw ValidationException::withMessages([
                        'accounting_period' => [
                            'Earlier accounting periods must be closed first.',
                        ],
                    ]);
                }

                $oldValues = [
                    'status' => $period->status,
                    'closed_at' => $period->closed_at,
                    'closed_by_user_id' =>
                        $period->closed_by_user_id,
                ];

                $period->status = 'closed';
                $period->closed_at = now();
                $period->closed_by_user_id =
                    $actor->getKey();

                $period->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $period,
                    event: 'period_closed',
                    oldValues: $oldValues,
                    newValues: [
                        'status' => $period->status,
                        'closed_at' => $period->closed_at,
                        'closed_by_user_id' =>
                            $period->closed_by_user_id,
                    ],
                    metadata: [
                        'fiscal_year_id' =>
                            $period->fiscal_year_id,

                        'fiscal_year_code' =>
                            $period->fiscalYear->code,

                        'period_number' =>
                            $period->period_number,
                    ],
                );

                $this->closeFiscalYearWhenComplete(
                    $period->fiscalYear,
                );

                return $period->refresh()->load([
                    'fiscalYear',
                    'closedBy',
                ]);
            },
            attempts: 5,
        );
    }

    public function reopen(
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): AccountingPeriod {
        return DB::transaction(
            function () use (
                $accountingPeriod,
                $actor,
            ): AccountingPeriod {
                $this->lockTenant();

                $period = AccountingPeriod::query()
                    ->with('fiscalYear')
                    ->whereKey(
                        $accountingPeriod->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureActorBelongsToPeriodTenant(
                    actor: $actor,
                    accountingPeriod: $period,
                );

                if ($period->isOpen()) {
                    return $period;
                }

                $hasLaterClosedPeriod =
                    AccountingPeriod::query()
                        ->where(
                            'fiscal_year_id',
                            $period->fiscal_year_id,
                        )
                        ->where(
                            'period_number',
                            '>',
                            $period->period_number,
                        )
                        ->where('status', 'closed')
                        ->exists();

                if ($hasLaterClosedPeriod) {
                    throw ValidationException::withMessages([
                        'accounting_period' => [
                            'Later closed periods must be reopened first.',
                        ],
                    ]);
                }

                $oldValues = [
                    'status' => $period->status,
                    'closed_at' => $period->closed_at,
                    'closed_by_user_id' =>
                        $period->closed_by_user_id,
                ];

                $period->status = 'open';
                $period->closed_at = null;
                $period->closed_by_user_id = null;

                $period->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $period,
                    event: 'period_reopened',
                    oldValues: $oldValues,
                    newValues: [
                        'status' => $period->status,
                        'closed_at' => null,
                        'closed_by_user_id' => null,
                    ],
                    metadata: [
                        'fiscal_year_id' =>
                            $period->fiscal_year_id,

                        'fiscal_year_code' =>
                            $period->fiscalYear->code,

                        'period_number' =>
                            $period->period_number,

                        'reopened_by_user_id' =>
                            $actor->getKey(),
                    ],
                );

                $this->reopenFiscalYearWhenRequired(
                    $period->fiscalYear,
                    $actor,
                );

                return $period->refresh()->load([
                    'fiscalYear',
                    'closedBy',
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * Resolve and lock the accounting period for a posting date.
     *
     * Posting services should call this method inside the same database
     * transaction that creates the journal, stock, payment, or document
     * posting records.
     */
    public function lockOpenPeriod(
        DateTimeInterface $postingDate,
    ): AccountingPeriod {
        $tenant = $this->tenantContext->tenant();

        $date = CarbonImmutable::instance(
            $postingDate,
        )
            ->setTimezone($tenant->timezone)
            ->toDateString();

        $period = AccountingPeriod::query()
            ->with('fiscalYear')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->lockForUpdate()
            ->first();

        if (!$period instanceof AccountingPeriod) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'No accounting period is configured for the selected posting date.',
                ],
            ]);
        }

        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    "The accounting period {$period->code} is closed.",
                ],
            ]);
        }

        return $period;
    }

    private function closeFiscalYearWhenComplete(
        FiscalYear $fiscalYear,
    ): void {
        $lockedFiscalYear = FiscalYear::query()
            ->whereKey($fiscalYear->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $hasOpenPeriod = AccountingPeriod::query()
            ->where(
                'fiscal_year_id',
                $lockedFiscalYear->getKey(),
            )
            ->where('status', 'open')
            ->exists();

        if (
            $hasOpenPeriod
            || $lockedFiscalYear->status === 'closed'
        ) {
            return;
        }

        $oldStatus = $lockedFiscalYear->status;

        $lockedFiscalYear->status = 'closed';
        $lockedFiscalYear->saveQuietly();

        $this->auditLogService->recordCustomEvent(
            subject: $lockedFiscalYear,
            event: 'fiscal_year_closed',
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => 'closed',
            ],
        );
    }

    private function reopenFiscalYearWhenRequired(
        FiscalYear $fiscalYear,
        User $actor,
    ): void {
        $lockedFiscalYear = FiscalYear::query()
            ->whereKey($fiscalYear->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedFiscalYear->status === 'active') {
            return;
        }

        $oldStatus = $lockedFiscalYear->status;

        $lockedFiscalYear->status = 'active';
        $lockedFiscalYear->saveQuietly();

        $this->auditLogService->recordCustomEvent(
            subject: $lockedFiscalYear,
            event: 'fiscal_year_reopened',
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => 'active',
            ],
            metadata: [
                'reopened_by_user_id' =>
                    $actor->getKey(),
            ],
        );
    }

    private function ensureCodeIsAvailable(
        string $code,
    ): void {
        $exists = FiscalYear::query()
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [
                    'The fiscal year code is already in use.',
                ],
            ]);
        }
    }

    private function ensureFiscalYearDoesNotOverlap(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): void {
        $overlaps = FiscalYear::query()
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => [
                    'The fiscal year overlaps an existing fiscal year.',
                ],
            ]);
        }
    }

    private function ensureActorBelongsToPeriodTenant(
        User $actor,
        AccountingPeriod $accountingPeriod,
    ): void {
        if (
            (int) $actor->tenant_id
            !== (int) $accountingPeriod->tenant_id
        ) {
            throw new LogicException(
                'The accounting period does not belong to the actor tenant.',
            );
        }
    }

    private function lockTenant(): Tenant
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw new LogicException(
                'Tenant context has not been initialized.',
            );
        }

        return Tenant::query()
            ->whereKey($tenantId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
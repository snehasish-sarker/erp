<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\PeriodCloseRun;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PeriodCloseService
{
    public function __construct(
        private readonly PeriodCloseChecklistService $checklistService,
        private readonly YearEndClosingService $yearEndClosingService,
        private readonly AuditLogService $auditLogService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function prepare(AccountingPeriod $period, User $actor): PeriodCloseRun
    {
        $this->ensureActor($actor);

        return DB::transaction(function () use ($period, $actor): PeriodCloseRun {
            $locked = $this->lockPeriod($period);
            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'accounting_period' => ['A closed period cannot be prepared again until it is reopened.'],
                ]);
            }

            return $this->createRun($locked, $actor);
        }, attempts: 5);
    }

    public function close(AccountingPeriod $period, User $actor, string $reason): PeriodCloseRun
    {
        $this->ensureActor($actor);
        $reason = $this->reason($reason);

        $run = DB::transaction(function () use ($period, $actor, $reason): PeriodCloseRun {
            $locked = $this->lockPeriod($period);
            if ($locked->isClosed()) {
                $existing = $locked->closeRuns()
                    ->where('status', 'closed')
                    ->latest('id')
                    ->first();

                if ($existing instanceof PeriodCloseRun) {
                    return $existing->load('checks');
                }

                throw ValidationException::withMessages([
                    'accounting_period' => [
                        'The accounting period is closed, but its close-run record could not be found.',
                    ],
                ]);
            }

            $hasEarlierOpen = AccountingPeriod::query()
                ->whereDate(
                    'end_date',
                    '<',
                    $locked->start_date->toDateString(),
                )
                ->where('status', 'open')
                ->exists();

            if ($hasEarlierOpen) {
                throw ValidationException::withMessages([
                    'accounting_period' => [
                        'Earlier accounting periods must be closed first.',
                    ],
                ]);
            }

            $run = $this->createRun($locked, $actor);

            if ($run->isBlocked()) {
                return $run;
            }

            $journalIds = $this->yearEndClosingService->post(
                $run,
                $locked,
                $actor,
            );

            $locked->status = 'closed';
            $locked->closed_at = now();
            $locked->closed_by_user_id = $actor->getKey();
            $locked->saveQuietly();

            $run->status = 'closed';
            $run->closing_journal_ids = $journalIds;
            $run->close_reason = $reason;
            $run->closed_by_user_id = $actor->getKey();
            $run->closed_at = now();
            $run->save();

            $this->closeFiscalYearIfComplete(
                $locked->fiscalYear,
                $actor,
            );
            $this->auditLogService->recordCustomEvent(
                subject: $locked,
                event: 'period_closed_with_controls',
                newValues: [
                    'status' => 'closed',
                    'closed_by_user_id' => $actor->getKey(),
                    'period_close_run_id' => $run->getKey(),
                    'closing_journal_ids' => $journalIds,
                ],
                metadata: ['reason' => $reason],
            );

            return $run->refresh()->load([
                'checks',
                'accountingPeriod.fiscalYear',
                'closedBy',
            ]);
        }, attempts: 5);

        return $run;
    }

    public function reopen(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod
    {
        $this->ensureActor($actor);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($period, $actor, $reason): AccountingPeriod {
            $locked = $this->lockPeriod($period);
            if ($locked->isOpen()) {
                return $locked;
            }

            $hasLaterClosed = AccountingPeriod::query()
                ->whereDate(
                    'start_date',
                    '>',
                    $locked->end_date->toDateString(),
                )
                ->where('status', 'closed')
                ->exists();
            if ($hasLaterClosed) {
                throw ValidationException::withMessages([
                    'accounting_period' => ['Later closed periods must be reopened first.'],
                ]);
            }

            $run = $locked->closeRuns()->where('status', 'closed')->latest('id')->lockForUpdate()->first();
            $locked->status = 'open';
            $locked->closed_at = null;
            $locked->closed_by_user_id = null;
            $locked->saveQuietly();

            if ($run instanceof PeriodCloseRun) {
                $this->yearEndClosingService->reverse($run, $locked, $reason, $actor);
                $run->status = 'reopened';
                $run->reopen_reason = $reason;
                $run->reopened_by_user_id = $actor->getKey();
                $run->reopened_at = now();
                $run->save();
            }

            $fiscalYear = FiscalYear::query()->whereKey($locked->fiscal_year_id)->lockForUpdate()->firstOrFail();
            if ($fiscalYear->status !== 'active') {
                $oldFiscalYearStatus = $fiscalYear->status;
                $fiscalYear->status = 'active';
                $fiscalYear->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $fiscalYear,
                    event: 'fiscal_year_reopened',
                    oldValues: ['status' => $oldFiscalYearStatus],
                    newValues: ['status' => 'active'],
                    metadata: [
                        'reopened_by_user_id' => $actor->getKey(),
                        'accounting_period_id' => $locked->getKey(),
                    ],
                );
            }

            $this->auditLogService->recordCustomEvent(
                subject: $locked,
                event: 'period_reopened_with_controls',
                oldValues: ['status' => 'closed'],
                newValues: ['status' => 'open'],
                metadata: [
                    'reason' => $reason,
                    'period_close_run_id' => $run?->getKey(),
                    'reopened_by_user_id' => $actor->getKey(),
                ],
            );

            return $locked->refresh()->load(['fiscalYear', 'closedBy', 'closeRuns.checks']);
        }, attempts: 5);
    }

    private function createRun(AccountingPeriod $period, User $actor): PeriodCloseRun
    {
        $checks = $this->checklistService->run($period, $actor);
        $failed = collect($checks)->where('status', 'failed')->count();
        $warnings = collect($checks)->where('status', 'warning')->count();
        $passed = collect($checks)->where('status', 'passed')->count();
        $difference = collect($checks)->reduce(
            static fn (BigDecimal $carry, array $check): BigDecimal => $carry->plus(BigDecimal::of((string) $check['difference_amount'])->abs()),
            BigDecimal::zero(),
        );
        $runNumber = ((int) PeriodCloseRun::query()
            ->where('accounting_period_id', $period->getKey())
            ->lockForUpdate()
            ->max('run_number')) + 1;
        $run = PeriodCloseRun::query()->create([
            'accounting_period_id' => $period->getKey(),
            'run_number' => $runNumber,
            'status' => $failed > 0 ? 'blocked' : 'ready',
            'total_checks' => count($checks),
            'passed_checks' => $passed,
            'warning_checks' => $warnings,
            'failed_checks' => $failed,
            'total_reconciliation_difference' => $difference->toScale(6, RoundingMode::HalfUp)->__toString(),
            'closing_journal_ids' => null,
            'prepared_by_user_id' => $actor->getKey(),
            'prepared_at' => now(),
        ]);

        foreach ($checks as $check) {
            $run->checks()->create($check);
        }

        $this->auditLogService->recordCustomEvent(
            subject: $run,
            event: 'period_close_prepared',
            newValues: [
                'status' => $run->status,
                'total_checks' => $run->total_checks,
                'failed_checks' => $run->failed_checks,
                'warning_checks' => $run->warning_checks,
            ],
        );

        return $run->load(['checks', 'accountingPeriod.fiscalYear', 'preparedBy']);
    }

    private function lockPeriod(AccountingPeriod $period): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->with(['fiscalYear.periods', 'closeRuns.checks'])
            ->whereKey($period->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function closeFiscalYearIfComplete(
        FiscalYear $fiscalYear,
        User $actor,
    ): void {
        $locked = FiscalYear::query()
            ->whereKey($fiscalYear->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $hasOpen = AccountingPeriod::query()
            ->where('fiscal_year_id', $locked->getKey())
            ->where('status', 'open')
            ->exists();

        if ($hasOpen || $locked->status === 'closed') {
            return;
        }

        $oldStatus = $locked->status;
        $locked->status = 'closed';
        $locked->saveQuietly();

        $this->auditLogService->recordCustomEvent(
            subject: $locked,
            event: 'fiscal_year_closed',
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'closed'],
            metadata: [
                'closed_by_user_id' => $actor->getKey(),
            ],
        );
    }

    private function ensureActor(User $actor): void
    {
        if ((int) $actor->tenant_id !== (int) $this->tenantContext->id()) {
            throw new LogicException('The period-close actor does not belong to the active tenant.');
        }
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => ['A reason of at least 10 characters is required.'],
            ]);
        }

        return mb_substr($reason, 0, 500);
    }
}

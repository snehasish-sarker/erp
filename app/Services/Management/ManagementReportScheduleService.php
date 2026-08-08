<?php

declare(strict_types=1);

namespace App\Services\Management;

use App\Models\Branch;
use App\Models\ManagementBudget;
use App\Models\ManagementReportSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ManagementReportScheduleService
{
    /** @var array<string, true> */
    private const REPORT_TYPES = [
        'management_branch_profitability' => true,
        'management_budget_vs_actual' => true,
        'management_product_profitability' => true,
        'management_customer_profitability' => true,
        'management_supplier_spend' => true,
        'management_gross_margin' => true,
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly ExportRequestService $exportRequestService,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ManagementReportSchedule
    {
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId === null && !$this->branchAccessService->hasCompanyWideAccess($actor)) {
            $branchId = $actor->branch_id === null ? null : (int) $actor->branch_id;
        }

        if ($branchId !== null) {
            $branch = $this->branchAccessService->findAccessibleBranch($actor, $branchId, true);
            if (!$branch instanceof Branch) {
                throw ValidationException::withMessages(['branch_id' => ['The selected branch is unavailable or outside your access.']]);
            }
        }

        $reportType = (string) $data['report_type'];
        $budgetId = isset($data['budget_id']) ? (int) $data['budget_id'] : null;
        if ($reportType === 'management_budget_vs_actual') {
            $budget = $this->validateBudget($budgetId, $actor);
            $branchId = (int) $budget->branch_id;
        }

        $frequency = (string) $data['frequency'];
        $runDay = isset($data['run_day']) ? (int) $data['run_day'] : null;
        $this->validateRunDay($frequency, $runDay);
        if ($frequency === 'daily') {
            $runDay = null;
        }

        $filters = [
            'branch_id' => $branchId,
            'budget_id' => $budgetId,
            'limit' => max(10, min(500, (int) ($data['limit'] ?? 100))),
            'date_window_days' => max(1, min(366, (int) ($data['date_window_days'] ?? 30))),
        ];

        $schedule = ManagementReportSchedule::query()->create([
            'branch_id' => $branchId,
            'created_by_user_id' => $actor->getKey(),
            'name' => trim((string) $data['name']),
            'report_type' => $reportType,
            'format' => (string) $data['format'],
            'frequency' => $frequency,
            'run_day' => $runDay,
            'run_time' => (string) $data['run_time'].':00',
            'filters' => $filters,
            'status' => 'active',
            'next_run_at' => $this->nextRun($frequency, $runDay, (string) $data['run_time']),
        ]);

        return $this->load($schedule);
    }

    public function toggle(ManagementReportSchedule $schedule, User $actor): ManagementReportSchedule
    {
        $this->authorizeSchedule($schedule, $actor);
        $schedule->status = $schedule->isActive() ? 'inactive' : 'active';
        $schedule->next_run_at = $schedule->status === 'active'
            ? $this->nextRun($schedule->frequency, $schedule->run_day, substr((string) $schedule->run_time, 0, 5))
            : null;
        $schedule->save();

        return $this->load($schedule->refresh());
    }

    public function delete(ManagementReportSchedule $schedule, User $actor): void
    {
        $this->authorizeSchedule($schedule, $actor);
        $schedule->delete();
    }

    public function load(ManagementReportSchedule $schedule): ManagementReportSchedule
    {
        return $schedule->load(['branch:id,code,name', 'createdBy:id,name,email,status']);
    }

    /** @return array{processed:int, queued:int, failed:int, skipped:int} */
    public function dispatchDue(int $limit = 100): array
    {
        $ids = DB::table('management_report_schedules')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(max(1, min(500, $limit)))
            ->get(['id', 'tenant_id']);

        $result = ['processed' => 0, 'queued' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($ids as $row) {
            $result['processed']++;
            $tenant = Tenant::query()->whereKey((int) $row->tenant_id)->where('status', 'active')->first();
            if (!$tenant instanceof Tenant) {
                $result['skipped']++;
                continue;
            }

            $this->tenantContext->set($tenant);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            try {
                $schedule = ManagementReportSchedule::query()->whereKey((int) $row->id)->first();
                if (!$schedule instanceof ManagementReportSchedule || !$schedule->isActive()) {
                    $result['skipped']++;
                    continue;
                }

                $requester = User::query()->whereKey($schedule->created_by_user_id)->where('status', 'active')->first();
                if (!$requester instanceof User) {
                    $this->mark($schedule, 'skipped', 'The schedule creator is unavailable or inactive.');
                    $result['skipped']++;
                    continue;
                }

                $this->exportRequestService->request(
                    requester: $requester,
                    exportType: $schedule->report_type,
                    filters: $this->runtimeFilters($schedule),
                    format: $schedule->format,
                );

                $this->mark($schedule, 'queued', null);
                $result['queued']++;
            } catch (Throwable $exception) {
                $schedule = ManagementReportSchedule::query()->whereKey((int) $row->id)->first();
                if ($schedule instanceof ManagementReportSchedule) {
                    $this->mark($schedule, 'failed', mb_substr($exception->getMessage(), 0, 500));
                }
                $result['failed']++;
            } finally {
                $this->permissionRegistrar->setPermissionsTeamId(null);
                $this->tenantContext->clear();
            }
        }

        return $result;
    }

    private function mark(ManagementReportSchedule $schedule, string $status, ?string $error): void
    {
        $schedule->last_run_at = now();
        $schedule->last_status = $status;
        $schedule->last_error = $error;
        $schedule->next_run_at = $this->nextRun(
            $schedule->frequency,
            $schedule->run_day,
            substr((string) $schedule->run_time, 0, 5),
            CarbonImmutable::now($this->tenantContext->tenant()->timezone)->addMinute(),
        );
        $schedule->save();
    }

    /** @return array<string, mixed> */
    private function runtimeFilters(ManagementReportSchedule $schedule): array
    {
        $stored = is_array($schedule->filters) ? $schedule->filters : [];
        $filters = [
            'branch_id' => $stored['branch_id'] ?? null,
            'budget_id' => $stored['budget_id'] ?? null,
            'limit' => $stored['limit'] ?? 100,
        ];

        if ($schedule->report_type !== 'management_budget_vs_actual') {
            $days = max(1, min(366, (int) ($stored['date_window_days'] ?? 30)));
            $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->startOfDay();
            $filters['date_to'] = $today->toDateString();
            $filters['date_from'] = $today->subDays($days - 1)->toDateString();
        }

        return array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function nextRun(string $frequency, ?int $runDay, string $runTime, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = $this->tenantContext->tenant()->timezone;
        $cursor = ($from ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        [$hour, $minute] = array_map('intval', explode(':', $runTime));
        $candidate = $cursor->setTime($hour, $minute, 0);

        if ($frequency === 'daily') {
            if ($candidate->lessThanOrEqualTo($cursor)) {
                $candidate = $candidate->addDay();
            }
        } elseif ($frequency === 'weekly') {
            $target = max(1, min(7, (int) $runDay));
            while ($candidate->dayOfWeekIso !== $target || $candidate->lessThanOrEqualTo($cursor)) {
                $candidate = $candidate->addDay()->setTime($hour, $minute, 0);
            }
        } else {
            $target = max(1, min(28, (int) $runDay));
            $candidate = $candidate->setDate($candidate->year, $candidate->month, $target);
            if ($candidate->lessThanOrEqualTo($cursor)) {
                $candidate = $candidate->addMonthNoOverflow();
                $candidate = $candidate->setDate($candidate->year, $candidate->month, $target)->setTime($hour, $minute, 0);
            }
        }

        return $candidate->utc();
    }

    private function validateRunDay(string $frequency, ?int $runDay): void
    {
        if ($frequency === 'daily') {
            return;
        }
        $maximum = $frequency === 'weekly' ? 7 : 28;
        if ($runDay === null || $runDay < 1 || $runDay > $maximum) {
            throw ValidationException::withMessages(['run_day' => [
                $frequency === 'weekly' ? 'Weekly schedules require an ISO weekday from 1 to 7.' : 'Monthly schedules require a day from 1 to 28.',
            ]]);
        }
    }

    private function validateBudget(?int $budgetId, User $actor): ManagementBudget
    {
        if ($budgetId === null) {
            throw ValidationException::withMessages(['budget_id' => ['Budget vs Actual schedules require a budget.']]);
        }
        $budget = ManagementBudget::query()->whereKey($budgetId)->first();
        if (
            !$budget instanceof ManagementBudget
            || !$budget->isApproved()
            || $this->branchAccessService->findAccessibleBranch($actor, (int) $budget->branch_id, false) === null
        ) {
            throw ValidationException::withMessages(['budget_id' => ['Select an approved budget within your branch access.']]);
        }

        return $budget;
    }

    private function authorizeSchedule(ManagementReportSchedule $schedule, User $actor): void
    {
        if ((int) $schedule->tenant_id !== (int) $actor->tenant_id) {
            abort(404);
        }
        if ($schedule->branch_id !== null && $this->branchAccessService->findAccessibleBranch($actor, (int) $schedule->branch_id, false) === null) {
            abort(403);
        }
    }
}
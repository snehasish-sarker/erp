<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ClosePeriodControlRequest;
use App\Http\Requests\Accounting\PreparePeriodCloseRequest;
use App\Http\Requests\Accounting\ReopenPeriodControlRequest;
use App\Models\AccountingPeriod;
use App\Models\PeriodCloseRun;
use App\Models\User;
use App\Services\Accounting\PeriodCloseService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PeriodCloseController extends Controller
{
    public function __construct(
        private readonly PeriodCloseService $service,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function show(Request $request, AccountingPeriod $accountingPeriod): Response
    {
        $accountingPeriod->load([
            'fiscalYear.periods',
            'closedBy',
            'closeRuns' => static fn ($query) => $query->latest('id'),
            'closeRuns.checks',
            'closeRuns.preparedBy',
            'closeRuns.closedBy',
            'closeRuns.reopenedBy',
        ]);

        return Inertia::render('PeriodClose/Show', [
            'period' => $this->periodData($accountingPeriod),
            'runs' => $accountingPeriod->closeRuns
                ->map(fn (PeriodCloseRun $run): array => $this->runData($run))
                ->values()
                ->all(),
        ]);
    }

    public function prepare(PreparePeriodCloseRequest $request, AccountingPeriod $accountingPeriod): JsonResponse|RedirectResponse
    {
        $run = $this->service->prepare($accountingPeriod, $this->actor($request));

        return $this->responseService->success(
            message: $run->isBlocked()
                ? 'Period-close checklist completed with blocking failures.'
                : 'Period-close checklist completed successfully.',
            data: ['period_close_run_id' => (int) $run->getKey(), 'status' => $run->status],
            redirectTo: route('financial-control.period-close.show', $accountingPeriod),
        );
    }

    public function close(ClosePeriodControlRequest $request, AccountingPeriod $accountingPeriod): JsonResponse|RedirectResponse
    {
        $run = $this->service->close(
            period: $accountingPeriod,
            actor: $this->actor($request),
            reason: (string) $request->validated('reason'),
        );

        if ($run->isBlocked()) {
            return $this->responseService->error(
                message: 'The period cannot be closed because the latest financial-control checklist contains blocking failures.',
                errors: [
                    'period_close' => [
                        'Review the saved checklist, resolve every blocking control, and try again.',
                    ],
                ],
                code: 'PERIOD_CLOSE_BLOCKED',
                redirectTo: route(
                    'financial-control.period-close.show',
                    $accountingPeriod,
                ),
            );
        }

        return $this->responseService->success(
            message: 'Accounting period closed successfully after all financial controls passed.',
            data: [
                'period_close_run_id' => (int) $run->getKey(),
                'status' => $run->status,
            ],
            redirectTo: route(
                'financial-control.period-close.show',
                $accountingPeriod,
            ),
        );
    }

    public function reopen(ReopenPeriodControlRequest $request, AccountingPeriod $accountingPeriod): JsonResponse|RedirectResponse
    {
        $period = $this->service->reopen(
            period: $accountingPeriod,
            actor: $this->actor($request),
            reason: (string) $request->validated('reason'),
        );

        return $this->responseService->success(
            message: 'Accounting period reopened and year-end closing journals reversed where applicable.',
            data: ['accounting_period_id' => (int) $period->getKey(), 'status' => $period->status],
            redirectTo: route('financial-control.period-close.show', $period),
        );
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function periodData(AccountingPeriod $period): array
    {
        return [
            'id' => (int) $period->getKey(),
            'code' => $period->code,
            'name' => $period->name,
            'period_number' => (int) $period->period_number,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $period->status,
            'closed_at' => $period->closed_at?->toIso8601String(),
            'closed_by' => $period->closedBy?->name,
            'fiscal_year' => [
                'id' => (int) $period->fiscalYear->getKey(),
                'code' => $period->fiscalYear->code,
                'name' => $period->fiscalYear->name,
                'status' => $period->fiscalYear->status,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function runData(PeriodCloseRun $run): array
    {
        return [
            'id' => (int) $run->getKey(),
            'run_number' => (int) $run->run_number,
            'status' => $run->status,
            'total_checks' => (int) $run->total_checks,
            'passed_checks' => (int) $run->passed_checks,
            'warning_checks' => (int) $run->warning_checks,
            'failed_checks' => (int) $run->failed_checks,
            'total_reconciliation_difference' => (string) $run->total_reconciliation_difference,
            'closing_journal_ids' => $run->closing_journal_ids ?? [],
            'close_reason' => $run->close_reason,
            'reopen_reason' => $run->reopen_reason,
            'prepared_at' => $run->prepared_at?->toIso8601String(),
            'prepared_by' => $run->preparedBy?->name,
            'closed_at' => $run->closed_at?->toIso8601String(),
            'closed_by' => $run->closedBy?->name,
            'reopened_at' => $run->reopened_at?->toIso8601String(),
            'reopened_by' => $run->reopenedBy?->name,
            'checks' => $run->checks->map(static fn ($check): array => [
                'id' => (int) $check->getKey(),
                'check_key' => $check->check_key,
                'category' => $check->category,
                'label' => $check->label,
                'status' => $check->status,
                'is_blocking' => (bool) $check->is_blocking,
                'issue_count' => (int) $check->issue_count,
                'difference_amount' => (string) $check->difference_amount,
                'message' => $check->message,
                'details' => $check->details,
            ])->values()->all(),
        ];
    }
}

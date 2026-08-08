<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingPeriods\CloseAccountingPeriodRequest;
use App\Http\Requests\AccountingPeriods\ReopenAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Accounting\PeriodCloseService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly PeriodCloseService $periodCloseService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function close(
        CloseAccountingPeriodRequest $request,
        AccountingPeriod $accountingPeriod,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'close',
            $accountingPeriod,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $run = $this->periodCloseService->close(
            period: $accountingPeriod,
            actor: $actor,
            reason: (string) $request->validated('reason'),
        );

        $accountingPeriod = $run->accountingPeriod()->firstOrFail();

        if ($run->isBlocked()) {
            $redirectTo = $actor->can('financial_control.view')
                ? route(
                    'financial-control.period-close.show',
                    $accountingPeriod,
                )
                : route(
                    'accounting-periods.show',
                    $accountingPeriod->fiscal_year_id,
                );

            return $this->responseService->error(
                message: 'The accounting period cannot be closed because financial controls failed.',
                errors: [
                    'period_close' => [
                        'Review the saved period-close checklist and resolve every blocking control.',
                    ],
                ],
                code: 'PERIOD_CLOSE_BLOCKED',
                redirectTo: $redirectTo,
            );
        }

        return $this->responseService->success(
            message: 'Accounting period closed successfully.',

            data: [
                'id' => (int) (
                    $accountingPeriod->getKey()
                ),

                'status' =>
                    $accountingPeriod->status,
            ],

            redirectTo: route(
                'accounting-periods.show',
                $accountingPeriod
                    ->fiscal_year_id,
            ),
        );
    }

    public function reopen(
        ReopenAccountingPeriodRequest $request,
        AccountingPeriod $accountingPeriod,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reopen',
            $accountingPeriod,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $accountingPeriod = $this->periodCloseService->reopen(
            period: $accountingPeriod,
            actor: $actor,
            reason: (string) $request->validated('reason'),
        );

        return $this->responseService->success(
            message: 'Accounting period reopened successfully.',

            data: [
                'id' => (int) (
                    $accountingPeriod->getKey()
                ),

                'status' =>
                    $accountingPeriod->status,
            ],

            redirectTo: route(
                'accounting-periods.show',
                $accountingPeriod
                    ->fiscal_year_id,
            ),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingPeriods\CloseAccountingPeriodRequest;
use App\Http\Requests\AccountingPeriods\ReopenAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly AccountingPeriodService $accountingPeriodService,
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

        $accountingPeriod = $this
            ->accountingPeriodService
            ->close(
                accountingPeriod:
                    $accountingPeriod,

                actor: $actor,
            );

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

        $accountingPeriod = $this
            ->accountingPeriodService
            ->reopen(
                accountingPeriod:
                    $accountingPeriod,

                actor: $actor,
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
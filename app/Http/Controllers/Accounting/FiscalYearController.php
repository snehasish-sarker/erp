<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingPeriods\IndexFiscalYearRequest;
use App\Http\Requests\AccountingPeriods\StoreFiscalYearRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FiscalYearController extends Controller
{
    public function __construct(
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly CommonResponseService $responseService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(
        IndexFiscalYearRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            FiscalYear::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'start_date'
        );

        $direction = (string) (
            $validated['direction'] ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $today = CarbonImmutable::now(
            $this->tenantContext
                ->tenant()
                ->timezone,
        )->toDateString();

        $fiscalYears = FiscalYear::query()
            ->withCount([
                'periods',

                'periods as open_periods_count' =>
                    static fn (
                        Builder $query,
                    ): Builder => $query->where(
                        'status',
                        'open',
                    ),

                'periods as closed_periods_count' =>
                    static fn (
                        Builder $query,
                    ): Builder => $query->where(
                        'status',
                        'closed',
                    ),
            ])
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
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'AccountingPeriods/Index',
            [
                'fiscalYears' => [
                    'data' => $fiscalYears
                        ->getCollection()
                        ->map(
                            fn (
                                FiscalYear $fiscalYear,
                            ): array =>
                                $this->fiscalYearData(
                                    fiscalYear:
                                        $fiscalYear,

                                    today: $today,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $fiscalYears
                                ->currentPage(),

                        'last_page' =>
                            $fiscalYears
                                ->lastPage(),

                        'per_page' =>
                            $fiscalYears->perPage(),

                        'from' =>
                            $fiscalYears->firstItem(),

                        'to' =>
                            $fiscalYears->lastItem(),

                        'total' =>
                            $fiscalYears->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            FiscalYear::class,
        );

        $tenant = $this->tenantContext->tenant();

        $today = CarbonImmutable::now(
            $tenant->timezone,
        );

        return Inertia::render(
            'AccountingPeriods/Create',
            [
                'timezone' => $tenant->timezone,

                'suggestedStartDate' => $today
                    ->startOfMonth()
                    ->toDateString(),
            ],
        );
    }

    public function store(
        StoreFiscalYearRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            FiscalYear::class,
        );

        $validated = $request->validated();

        $fiscalYear = $this
            ->accountingPeriodService
            ->generateFiscalYear(
                name: $validated['name'],
                code: $validated['code'],

                startDate: CarbonImmutable::parse(
                    $validated['start_date'],
                ),
            );

        return $this->responseService->success(
            message: 'Fiscal year and accounting periods generated successfully.',

            data: [
                'id' => (int) $fiscalYear->getKey(),
                'code' => $fiscalYear->code,
            ],

            redirectTo: route(
                'accounting-periods.show',
                $fiscalYear,
            ),
        );
    }

    public function show(
        FiscalYear $fiscalYear,
    ): Response {
        Gate::authorize(
            'view',
            $fiscalYear,
        );

        $fiscalYear->load([
            'periods.closedBy:id,name,email',
        ]);

        $today = CarbonImmutable::now(
            $this->tenantContext
                ->tenant()
                ->timezone,
        )->toDateString();

        $periods = $fiscalYear
            ->periods
            ->sortBy('period_number')
            ->values();

        $firstOpenPeriodNumber = $periods
            ->where('status', 'open')
            ->min('period_number');

        $lastClosedPeriodNumber = $periods
            ->where('status', 'closed')
            ->max('period_number');

        return Inertia::render(
            'AccountingPeriods/Show',
            [
                'fiscalYear' =>
                    $this->fiscalYearData(
                        fiscalYear: $fiscalYear,
                        today: $today,
                    ),

                'periods' => $periods
                    ->map(
                        fn (
                            AccountingPeriod $period,
                        ): array =>
                            $this->periodData(
                                accountingPeriod:
                                    $period,

                                today: $today,

                                firstOpenPeriodNumber:
                                    $firstOpenPeriodNumber
                                        === null
                                        ? null
                                        : (int) $firstOpenPeriodNumber,

                                lastClosedPeriodNumber:
                                    $lastClosedPeriodNumber
                                        === null
                                        ? null
                                        : (int) $lastClosedPeriodNumber,
                            ),
                    )
                    ->all(),
            ],
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     start_date: string,
     *     end_date: string,
     *     status: string,
     *     periods_count: int,
     *     open_periods_count: int,
     *     closed_periods_count: int,
     *     is_current: bool,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function fiscalYearData(
        FiscalYear $fiscalYear,
        string $today,
    ): array {
        $periodsCount = (int) (
            $fiscalYear->periods_count
            ?? $fiscalYear->periods->count()
        );

        $openPeriodsCount = (int) (
            $fiscalYear->open_periods_count
            ?? $fiscalYear
                ->periods
                ->where('status', 'open')
                ->count()
        );

        $closedPeriodsCount = (int) (
            $fiscalYear->closed_periods_count
            ?? $fiscalYear
                ->periods
                ->where('status', 'closed')
                ->count()
        );

        return [
            'id' => (int) $fiscalYear->getKey(),
            'name' => $fiscalYear->name,
            'code' => $fiscalYear->code,

            'start_date' =>
                $fiscalYear
                    ->start_date
                    ->toDateString(),

            'end_date' =>
                $fiscalYear
                    ->end_date
                    ->toDateString(),

            'status' => $fiscalYear->status,
            'periods_count' => $periodsCount,

            'open_periods_count' =>
                $openPeriodsCount,

            'closed_periods_count' =>
                $closedPeriodsCount,

            'is_current' =>
                $today
                    >= $fiscalYear
                        ->start_date
                        ->toDateString()
                && $today
                    <= $fiscalYear
                        ->end_date
                        ->toDateString(),

            'created_at' =>
                $fiscalYear
                    ->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $fiscalYear
                    ->updated_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     fiscal_year_id: int,
     *     period_number: int,
     *     name: string,
     *     code: string,
     *     start_date: string,
     *     end_date: string,
     *     status: string,
     *     closed_at: string|null,
     *     closed_by: array{
     *         id: int,
     *         name: string,
     *         email: string
     *     }|null,
     *     is_current: bool,
     *     can_close: bool,
     *     can_reopen: bool
     * }
     */
    private function periodData(
        AccountingPeriod $accountingPeriod,
        string $today,
        ?int $firstOpenPeriodNumber,
        ?int $lastClosedPeriodNumber,
    ): array {
        $closedBy = $accountingPeriod->closedBy;

        return [
            'id' => (int) (
                $accountingPeriod->getKey()
            ),

            'fiscal_year_id' => (int) (
                $accountingPeriod
                    ->fiscal_year_id
            ),

            'period_number' => (int) (
                $accountingPeriod
                    ->period_number
            ),

            'name' => $accountingPeriod->name,
            'code' => $accountingPeriod->code,

            'start_date' =>
                $accountingPeriod
                    ->start_date
                    ->toDateString(),

            'end_date' =>
                $accountingPeriod
                    ->end_date
                    ->toDateString(),

            'status' => $accountingPeriod->status,

            'closed_at' =>
                $accountingPeriod
                    ->closed_at
                    ?->toIso8601String(),

            'closed_by' => $closedBy === null
                ? null
                : [
                    'id' => (int) $closedBy->getKey(),
                    'name' => $closedBy->name,
                    'email' => $closedBy->email,
                ],

            'is_current' =>
                $today
                    >= $accountingPeriod
                        ->start_date
                        ->toDateString()
                && $today
                    <= $accountingPeriod
                        ->end_date
                        ->toDateString(),

            'can_close' =>
                $accountingPeriod->status
                    === 'open'
                && $firstOpenPeriodNumber
                    === (int) $accountingPeriod
                        ->period_number,

            'can_reopen' =>
                $accountingPeriod->status
                    === 'closed'
                && $lastClosedPeriodNumber
                    === (int) $accountingPeriod
                        ->period_number,
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function statusOptions(): array
    {
        return [
            [
                'value' => 'active',
                'label' => 'Active',
            ],
            [
                'value' => 'closed',
                'label' => 'Closed',
            ],
        ];
    }
}
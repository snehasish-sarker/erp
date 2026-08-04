<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountsPayableAgingRequest;
use App\Http\Requests\Accounting\SupplierStatementRequest;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Accounting\AccountsPayableAgingService;
use App\Services\Accounting\SupplierStatementService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AccountsPayableReportController extends Controller
{
    public function __construct(
        private readonly AccountsPayableAgingService $agingService,
        private readonly SupplierStatementService $statementService,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function aging(
        AccountsPayableAgingRequest $request,
    ): Response {
        $actor = $this->actor($request);

        return Inertia::render(
            'Reports/AccountsPayable/Aging',
            [
                'report' =>
                    $this->agingService
                        ->buildSummary(
                            filters:
                                $request->validated(),

                            actor:
                                $actor,
                        ),

                ...$this->reportOptions(
                    $actor,
                ),
            ],
        );
    }

    public function supplierAging(
        AccountsPayableAgingRequest $request,
        string $supplierId,
    ): Response {
        $actor = $this->actor($request);

        $supplier = $this->supplierFromRoute(
            $supplierId,
        );

        return Inertia::render(
            'Reports/AccountsPayable/SupplierAging',
            [
                'report' =>
                    $this->agingService
                        ->buildSupplierDetail(
                            supplier:
                                $supplier,

                            filters:
                                $request->validated(),

                            actor:
                                $actor,
                        ),

                ...$this->reportOptions(
                    $actor,
                ),
            ],
        );
    }

    public function supplierStatement(
        SupplierStatementRequest $request,
    ): Response {
        $actor = $this->actor($request);
        $validated = $request->validated();

        $supplierId = isset(
            $validated['supplier_id'],
        )
            ? (int) $validated['supplier_id']
            : null;

        $report = null;

        if (
            $supplierId !== null
            && $supplierId > 0
        ) {
            $supplier = Supplier::withTrashed()
                ->whereKey($supplierId)
                ->firstOrFail();

            $report =
                $this->statementService
                    ->build(
                        supplier:
                            $supplier,

                        actor:
                            $actor,

                        filters:
                            $validated,
                    );
        }

        return Inertia::render(
            'Reports/AccountsPayable/SupplierStatement',
            [
                'report' => $report,

                'filters' => $report['filters']
                    ?? $this->statementDefaults(
                        $validated,
                    ),

                ...$this->reportOptions(
                    $actor,
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportOptions(
        User $actor,
    ): array {
        $branches =
            $this->branchAccessService
                ->accessibleBranches(
                    user: $actor,
                    activeOnly: false,
                );

        $ledgerSupplierQuery =
            SupplierLedgerEntry::query()
                ->select('supplier_id');

        $this->branchAccessService
            ->scopeQuery(
                query:
                    $ledgerSupplierQuery,

                user:
                    $actor,

                branchColumn:
                    'supplier_ledger_entries.branch_id',
            );

        $suppliers = Supplier::withTrashed()
            ->whereIn(
                'id',
                $ledgerSupplierQuery,
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'status',
                'deleted_at',
            ]);

        $currencyQuery =
            SupplierLedgerEntry::query()
                ->select('currency_code')
                ->distinct();

        $this->branchAccessService
            ->scopeQuery(
                query:
                    $currencyQuery,

                user:
                    $actor,

                branchColumn:
                    'supplier_ledger_entries.branch_id',
            );

        $currencies = $currencyQuery
            ->orderBy('currency_code')
            ->pluck('currency_code')
            ->map(
                static fn (
                    mixed $currency,
                ): string => mb_strtoupper(
                    (string) $currency,
                ),
            )
            ->values()
            ->all();

        return [
            'branches' => $branches
                ->map(
                    static fn (
                        Branch $branch,
                    ): array => [
                        'id' =>
                            (int) $branch->getKey(),

                        'code' =>
                            $branch->code,

                        'name' =>
                            $branch->name,

                        'status' =>
                            $branch->status,
                    ],
                )
                ->values()
                ->all(),

            'suppliers' => $suppliers
                ->map(
                    static fn (
                        Supplier $supplier,
                    ): array => [
                        'id' =>
                            (int) $supplier->getKey(),

                        'code' =>
                            $supplier->code,

                        'name' =>
                            $supplier->name,

                        'status' =>
                            $supplier->status,

                        'deleted' =>
                            $supplier->trashed(),
                    ],
                )
                ->values()
                ->all(),

            'currencies' =>
                $currencies,

            'baseCurrencyCode' =>
                mb_strtoupper(
                    (string) $this
                        ->tenantContext
                        ->tenant()
                        ->currency_code,
                ),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function statementDefaults(
        array $validated,
    ): array {
        $tenant =
            $this->tenantContext
                ->tenant();

        $today = CarbonImmutable::now(
            $tenant->timezone,
        );

        $dateTo = isset(
            $validated['date_to'],
        )
            && is_string(
                $validated['date_to'],
            )
            && $validated['date_to'] !== ''
                ? $validated['date_to']
                : $today->toDateString();

        $dateToValue =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $dateTo,
                $tenant->timezone,
            );

        if (
            !$dateToValue
                instanceof CarbonImmutable
        ) {
            throw new LogicException(
                'The Supplier Statement ending date is invalid.',
            );
        }

        return [
            'supplier_id' => isset(
                $validated['supplier_id'],
            )
                ? (int) $validated[
                    'supplier_id'
                ]
                : null,

            'branch_id' => isset(
                $validated['branch_id'],
            )
                ? (int) $validated[
                    'branch_id'
                ]
                : null,

            'currency_code' => isset(
                $validated['currency_code'],
            )
                ? (string) $validated[
                    'currency_code'
                ]
                : null,

            'date_from' => isset(
                $validated['date_from'],
            )
                && is_string(
                    $validated['date_from'],
                )
                && $validated['date_from'] !== ''
                    ? $validated['date_from']
                    : $dateToValue
                        ->startOfMonth()
                        ->toDateString(),

            'date_to' =>
                $dateTo,

            'per_page' => isset(
                $validated['per_page'],
            )
                ? (int) $validated[
                    'per_page'
                ]
                : 25,
        ];
    }

    private function supplierFromRoute(
        string $supplierId,
    ): Supplier {
        $id = filter_var(
            $supplierId,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        abort_if(
            $id === false,
            404,
        );

        return Supplier::withTrashed()
            ->whereKey((int) $id)
            ->firstOrFail();
    }

    private function actor(
        Request $request,
    ): User {
        $actor = $request->user();

        if (!$actor instanceof User) {
            throw new LogicException(
                'An authenticated user is required.',
            );
        }

        return $actor;
    }
}
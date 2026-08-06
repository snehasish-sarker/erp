<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountsReceivableAgingRequest;
use App\Http\Requests\Accounting\CustomerStatementRequest;
use App\Http\Requests\Accounting\OpenInvoiceReportRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use App\Services\Accounting\AccountsReceivableAgingService;
use App\Services\Accounting\CustomerStatementService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AccountsReceivableReportController extends Controller
{
    public function __construct(
        private readonly AccountsReceivableAgingService $agingService,
        private readonly CustomerStatementService $statementService,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function aging(
        AccountsReceivableAgingRequest $request,
    ): Response {
        $actor = $this->actor($request);

        return Inertia::render(
            'Reports/AccountsReceivable/Aging',
            [
                'report' => $this->agingService->buildSummary(
                    filters: $filters,
                    actor: $actor,
                ),
                ...$this->reportOptions($actor),
            ],
        );
    }

    public function customerAging(
        AccountsReceivableAgingRequest $request,
        string $customerId,
    ): Response {
        $actor = $this->actor($request);
        $customer = $this->customerFromRoute($customerId);

        return Inertia::render(
            'Reports/AccountsReceivable/CustomerAging',
            [
                'report' => $this->agingService->buildCustomerDetail(
                    customer: $customer,
                    filters: $filters,
                    actor: $actor,
                ),
                ...$this->reportOptions($actor),
            ],
        );
    }

    public function customerStatement(
        CustomerStatementRequest $request,
    ): Response {
        $actor = $this->actor($request);
        $validated = $request->validated();
        $customerId = isset($validated['customer_id'])
            ? (int) $validated['customer_id']
            : null;
        $report = null;

        if ($customerId !== null && $customerId > 0) {
            $customer = Customer::withTrashed()
                ->whereKey($customerId)
                ->firstOrFail();

            $report = $this->statementService->build(
                customer: $customer,
                actor: $actor,
                filters: $validated,
            );
        }

        return Inertia::render(
            'Reports/AccountsReceivable/CustomerStatement',
            [
                'report' => $report,
                'filters' => $report['filters']
                    ?? $this->statementDefaults($validated),
                ...$this->reportOptions($actor),
            ],
        );
    }

    public function openInvoices(
        OpenInvoiceReportRequest $request,
    ): Response {
        return $this->openInvoiceResponse(
            request: $request,
            overdueOnly: false,
        );
    }

    public function overdueInvoices(
        OpenInvoiceReportRequest $request,
    ): Response {
        return $this->openInvoiceResponse(
            request: $request,
            overdueOnly: true,
        );
    }

    public function printAging(
        AccountsReceivableAgingRequest $request,
    ): Response {
        $actor = $this->actor($request);
        $filters = $request->validated();

        $this->ensurePrintableRowCount(
            $this->agingService->exportSummaryTotalRows(
                filters: $filters,
                actor: $actor,
            ),
        );

        return Inertia::render(
            'Reports/AccountsReceivable/Print/Aging',
            [
                'report' => $this->agingService->buildPrintableSummary(
                    filters: $filters,
                    actor: $actor,
                ),
                'company' => $this->company(),
            ],
        );
    }

    public function printCustomerAging(
        AccountsReceivableAgingRequest $request,
        string $customerId,
    ): Response {
        $actor = $this->actor($request);
        $customer = $this->customerFromRoute($customerId);
        $filters = $request->validated();

        $this->ensurePrintableRowCount(
            $this->agingService->exportCustomerDetailTotalRows(
                customer: $customer,
                filters: $filters,
                actor: $actor,
            ),
        );

        return Inertia::render(
            'Reports/AccountsReceivable/Print/CustomerAging',
            [
                'report' => $this->agingService->buildPrintableCustomerDetail(
                    customer: $customer,
                    filters: $filters,
                    actor: $actor,
                ),
                'company' => $this->company(),
            ],
        );
    }

    public function printCustomerStatement(
        CustomerStatementRequest $request,
    ): Response {
        $actor = $this->actor($request);
        $validated = $request->validated();
        $customerId = isset($validated['customer_id'])
            ? (int) $validated['customer_id']
            : 0;

        abort_if($customerId < 1, 404);

        $customer = Customer::withTrashed()
            ->whereKey($customerId)
            ->firstOrFail();

        $this->ensurePrintableRowCount(
            $this->statementService->exportTotalRows(
                customer: $customer,
                actor: $actor,
                filters: $validated,
            ),
        );

        return Inertia::render(
            'Reports/AccountsReceivable/Print/CustomerStatement',
            [
                'report' => $this->statementService->buildPrintable(
                    customer: $customer,
                    actor: $actor,
                    filters: $validated,
                ),
                'company' => $this->company(),
            ],
        );
    }

    public function printOpenInvoices(
        OpenInvoiceReportRequest $request,
    ): Response {
        return $this->openInvoicePrintResponse(
            request: $request,
            overdueOnly: false,
        );
    }

    public function printOverdueInvoices(
        OpenInvoiceReportRequest $request,
    ): Response {
        return $this->openInvoicePrintResponse(
            request: $request,
            overdueOnly: true,
        );
    }

    private function openInvoiceResponse(
        OpenInvoiceReportRequest $request,
        bool $overdueOnly,
    ): Response {
        $actor = $this->actor($request);

        return Inertia::render(
            'Reports/AccountsReceivable/OpenInvoices',
            [
                'report' => $this->agingService->buildOpenInvoices(
                    filters: $request->validated(),
                    actor: $actor,
                    overdueOnly: $overdueOnly,
                ),
                ...$this->reportOptions($actor),
            ],
        );
    }

    private function openInvoicePrintResponse(
        OpenInvoiceReportRequest $request,
        bool $overdueOnly,
    ): Response {
        $actor = $this->actor($request);
        $filters = $request->validated();

        $this->ensurePrintableRowCount(
            $this->agingService->exportOpenInvoiceTotalRows(
                filters: $filters,
                actor: $actor,
                overdueOnly: $overdueOnly,
            ),
        );

        return Inertia::render(
            'Reports/AccountsReceivable/Print/OpenInvoices',
            [
                'report' => $this->agingService->buildPrintableOpenInvoices(
                    filters: $filters,
                    actor: $actor,
                    overdueOnly: $overdueOnly,
                ),
                'company' => $this->company(),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function reportOptions(User $actor): array
    {
        $branches = $this->branchAccessService->accessibleBranches(
            user: $actor,
            activeOnly: false,
        );

        $ledgerCustomerQuery = CustomerLedgerEntry::query()
            ->select('customer_id')
            ->distinct();

        $this->branchAccessService->scopeQuery(
            query: $ledgerCustomerQuery,
            user: $actor,
            branchColumn: 'customer_ledger_entries.branch_id',
        );

        $customers = Customer::withTrashed()
            ->whereIn('id', $ledgerCustomerQuery)
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'customer_type',
                'status',
                'deleted_at',
            ]);

        $currencyQuery = CustomerLedgerEntry::query()
            ->select('currency_code')
            ->distinct();

        $this->branchAccessService->scopeQuery(
            query: $currencyQuery,
            user: $actor,
            branchColumn: 'customer_ledger_entries.branch_id',
        );

        return [
            'branches' => $branches
                ->map(
                    static fn (Branch $branch): array => [
                        'id' => (int) $branch->getKey(),
                        'code' => $branch->code,
                        'name' => $branch->name,
                        'status' => $branch->status,
                    ],
                )
                ->values()
                ->all(),
            'customers' => $customers
                ->map(
                    static fn (Customer $customer): array => [
                        'id' => (int) $customer->getKey(),
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'customer_type' => $customer->customer_type,
                        'status' => $customer->status,
                        'deleted' => $customer->trashed(),
                    ],
                )
                ->values()
                ->all(),
            'currencies' => $currencyQuery
                ->orderBy('currency_code')
                ->pluck('currency_code')
                ->map(
                    static fn (mixed $currency): string =>
                        mb_strtoupper((string) $currency),
                )
                ->values()
                ->all(),
            'baseCurrencyCode' => mb_strtoupper(
                (string) $this->tenantContext
                    ->tenant()
                    ->currency_code,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function statementDefaults(array $validated): array
    {
        $tenant = $this->tenantContext->tenant();
        $today = CarbonImmutable::now($tenant->timezone);
        $dateTo = isset($validated['date_to'])
            && is_string($validated['date_to'])
            && $validated['date_to'] !== ''
                ? $validated['date_to']
                : $today->toDateString();
        $dateToValue = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $dateTo,
            $tenant->timezone,
        );

        if (!$dateToValue instanceof CarbonImmutable) {
            throw new LogicException(
                'The Customer Statement ending date is invalid.',
            );
        }

        return [
            'customer_id' => isset($validated['customer_id'])
                ? (int) $validated['customer_id']
                : null,
            'branch_id' => isset($validated['branch_id'])
                ? (int) $validated['branch_id']
                : null,
            'currency_code' => isset($validated['currency_code'])
                ? (string) $validated['currency_code']
                : null,
            'date_from' => isset($validated['date_from'])
                && is_string($validated['date_from'])
                && $validated['date_from'] !== ''
                    ? $validated['date_from']
                    : $dateToValue->startOfMonth()->toDateString(),
            'date_to' => $dateTo,
            'per_page' => isset($validated['per_page'])
                ? (int) $validated['per_page']
                : 25,
        ];
    }

    private function customerFromRoute(string $customerId): Customer
    {
        $id = filter_var(
            $customerId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        abort_if($id === false, 404);

        return Customer::withTrashed()
            ->whereKey((int) $id)
            ->firstOrFail();
    }

    private function ensurePrintableRowCount(int $rowCount): void
    {
        $maximumRows = max(
            1,
            (int) config(
                'exports.print_max_rows',
                5000,
            ),
        );

        if ($rowCount > $maximumRows) {
            throw ValidationException::withMessages([
                'report' => [
                    sprintf(
                        'This report contains %d rows and exceeds the printable limit of %d rows. Narrow the filters or request a CSV or Excel export instead.',
                        $rowCount,
                        $maximumRows,
                    ),
                ],
            ]);
        }
    }

    /** @return array<string, string|null> */
    private function company(): array
    {
        $tenant = $this->tenantContext->tenant();

        return [
            'name' => $tenant->name,
            'code' => $tenant->code,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (!$actor instanceof User) {
            throw new LogicException(
                'An authenticated user is required.',
            );
        }

        return $actor;
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountsPayableAgingRequest;
use App\Http\Requests\Accounting\SupplierStatementRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountsPayableAgingService;
use App\Services\Accounting\SupplierStatementService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AccountsPayablePrintController extends Controller
{
    public function __construct(
        private readonly AccountsPayableAgingService $agingService,
        private readonly SupplierStatementService $statementService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function aging(
        AccountsPayableAgingRequest $request,
    ): Response {
        $actor = $this->actor($request);
        $filters = $request->validated();

        $this->ensurePrintableRowCount(
            $this->agingService
                ->exportSummaryTotalRows(
                    filters: $filters,
                    actor: $actor,
                ),
        );

        return Inertia::render(
            'Reports/AccountsPayable/Print/Aging',
            [
                ...$this->documentContext(
                    request: $request,
                    actor: $actor,
                    title: 'Accounts Payable Aging',
                ),

                'report' => $this->agingService
                    ->buildPrintableSummary(
                        filters: $filters,
                        actor: $actor,
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

        $filters = $request->validated();

        $this->ensurePrintableRowCount(
            $this->agingService
                ->exportSupplierDetailTotalRows(
                    supplier: $supplier,
                    filters: $filters,
                    actor: $actor,
                ),
        );

        return Inertia::render(
            'Reports/AccountsPayable/Print/SupplierAging',
            [
                ...$this->documentContext(
                    request: $request,
                    actor: $actor,
                    title: sprintf(
                        'Supplier Aging — %s',
                        $supplier->name,
                    ),
                ),

                'report' => $this->agingService
                    ->buildPrintableSupplierDetail(
                        supplier: $supplier,
                        filters: $filters,
                        actor: $actor,
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
            : 0;

        if ($supplierId < 1) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'A supplier is required to print a Supplier Statement.',
                ],
            ]);
        }

        $supplier = Supplier::withTrashed()
            ->whereKey($supplierId)
            ->firstOrFail();

        $this->ensurePrintableRowCount(
            $this->statementService
                ->exportTotalRows(
                    supplier: $supplier,
                    actor: $actor,
                    filters: $validated,
                ),
        );

        return Inertia::render(
            'Reports/AccountsPayable/Print/SupplierStatement',
            [
                ...$this->documentContext(
                    request: $request,
                    actor: $actor,
                    title: sprintf(
                        'Supplier Statement — %s',
                        $supplier->name,
                    ),
                ),

                'report' => $this->statementService
                    ->buildPrintable(
                        supplier: $supplier,
                        actor: $actor,
                        filters: $validated,
                    ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentContext(
        Request $request,
        User $actor,
        string $title,
    ): array {
        $tenant = $this->tenantContext->tenant();

        return [
            'title' => $title,

            'tenant' => [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,

                'currency_code' => mb_strtoupper(
                    (string) $tenant->currency_code,
                ),

                'timezone' => $tenant->timezone,
            ],

            'generatedBy' => [
                'id' => (int) $actor->getKey(),
                'name' => $actor->name,
                'email' => $actor->email,
            ],

            'generatedAt' => CarbonImmutable::now(
                $tenant->timezone,
            )->format('Y-m-d H:i:s T'),

            'autoprint' => $request->boolean(
                'autoprint',
            ),
        ];
    }

    private function ensurePrintableRowCount(
        int $rowCount,
    ): void {
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
                        'This report contains %d rows and exceeds the printable limit of %d rows. Narrow the filters or request the CSV export instead.',
                        $rowCount,
                        $maximumRows,
                    ),
                ],
            ]);
        }
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
<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Supplier;
use App\Models\SupplierOpenItem;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierBalanceService
{
    private const MONEY_SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    /**
     * @return list<array{
     *     currency_code: string,
     *     payable_outstanding: string,
     *     overdue_payable: string,
     *     open_credit: string,
     *     net_outstanding: string,
     *     base_payable_outstanding: string,
     *     base_overdue_payable: string,
     *     base_open_credit: string,
     *     base_net_outstanding: string
     * }>
     */
    public function summaries(
        Supplier $supplier,
        User $actor,
        ?int $branchId = null,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if (
            (int) $supplier->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The supplier balance context contains records from different tenants.',
            );
        }

        $query = SupplierOpenItem::query()
            ->where(
                'supplier_id',
                $supplier->getKey(),
            )
            ->whereIn(
                'status',
                [
                    'open',
                    'partially_settled',
                ],
            );

        $query = $this->applyBranchScope(
            query: $query,
            actor: $actor,
            branchId: $branchId,
        );

        $today = CarbonImmutable::now(
            $tenant->timezone,
        )->toDateString();

        $rows = $query
            ->select('currency_code')
            ->selectRaw(
                "SUM(CASE WHEN item_type = 'invoice' THEN outstanding_amount ELSE 0 END) AS payable_outstanding",
            )
            ->selectRaw(
                "SUM(CASE WHEN item_type = 'invoice' AND due_date IS NOT NULL AND due_date < ? THEN outstanding_amount ELSE 0 END) AS overdue_payable",
                [$today],
            )
            ->selectRaw(
                "SUM(CASE WHEN item_type IN ('credit', 'payment') THEN outstanding_amount ELSE 0 END) AS open_credit",
            )
            ->selectRaw(
                "SUM(CASE WHEN item_type = 'invoice' THEN base_outstanding_amount ELSE 0 END) AS base_payable_outstanding",
            )
            ->selectRaw(
                "SUM(CASE WHEN item_type = 'invoice' AND due_date IS NOT NULL AND due_date < ? THEN base_outstanding_amount ELSE 0 END) AS base_overdue_payable",
                [$today],
            )
            ->selectRaw(
                "SUM(CASE WHEN item_type IN ('credit', 'payment') THEN base_outstanding_amount ELSE 0 END) AS base_open_credit",
            )
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();

        $summaries = [];

        foreach ($rows as $row) {
            $payable = $this->money(
                $row->getAttribute(
                    'payable_outstanding',
                ) ?? '0',
            );

            $overdue = $this->money(
                $row->getAttribute(
                    'overdue_payable',
                ) ?? '0',
            );

            $credit = $this->money(
                $row->getAttribute(
                    'open_credit',
                ) ?? '0',
            );

            $basePayable = $this->money(
                $row->getAttribute(
                    'base_payable_outstanding',
                ) ?? '0',
            );

            $baseOverdue = $this->money(
                $row->getAttribute(
                    'base_overdue_payable',
                ) ?? '0',
            );

            $baseCredit = $this->money(
                $row->getAttribute(
                    'base_open_credit',
                ) ?? '0',
            );

            $summaries[] = [
                'currency_code' => (string) $row
                    ->getAttribute('currency_code'),

                'payable_outstanding' =>
                    (string) $payable,

                'overdue_payable' =>
                    (string) $overdue,

                'open_credit' =>
                    (string) $credit,

                'net_outstanding' =>
                    (string) $payable
                        ->minus($credit),

                'base_payable_outstanding' =>
                    (string) $basePayable,

                'base_overdue_payable' =>
                    (string) $baseOverdue,

                'base_open_credit' =>
                    (string) $baseCredit,

                'base_net_outstanding' =>
                    (string) $basePayable
                        ->minus($baseCredit),
            ];
        }

        return $summaries;
    }

    /**
     * @param Builder<SupplierOpenItem> $query
     * @return Builder<SupplierOpenItem>
     */
    private function applyBranchScope(
        Builder $query,
        User $actor,
        ?int $branchId,
    ): Builder {
        if ($branchId === null) {
            return $this->branchAccessService
                ->scopeQuery(
                    query: $query,
                    user: $actor,
                );
        }

        $branch = $this->branchAccessService
            ->findAccessibleBranch(
                user: $actor,
                branchId: $branchId,
                requireActive: false,
            );

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is unavailable or outside your access scope.',
                ],
            ]);
        }

        return $query->where(
            'branch_id',
            $branch->getKey(),
        );
    }

    private function money(mixed $value): BigDecimal
    {
        return BigDecimal::of(
            (string) $value,
        )->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );
    }
}
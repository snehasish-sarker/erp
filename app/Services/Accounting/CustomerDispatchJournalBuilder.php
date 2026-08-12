<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\CustomerDispatch;
use App\Models\CustomerDispatchLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CustomerDispatchJournalBuilder
{
    private const SCALE = 6;

    public function __construct(
        private readonly SalesAccountingAccountService $accountService,
    ) {
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>,
     *     total_cost: string
     * }
     */
    public function buildPosting(
        CustomerDispatch $customerDispatch,
    ): array {
        $this->ensureInsideTransaction();

        $customerDispatch->loadMissing('lines');

        $totalCost = BigDecimal::zero();

        foreach ($customerDispatch->lines as $line) {
            if ($line->isStockItem()) {
                $totalCost = $totalCost->plus(
                    BigDecimal::of(
                        (string) $line->total_cost,
                    ),
                );
            }
        }

        if (!$totalCost->isGreaterThan(BigDecimal::zero())) {
            throw new LogicException(
                'A stock dispatch accounting journal requires a positive issue cost.',
            );
        }

        $accounts = $this->accountService
            ->dispatchAccounts();

        $reference = (string) $customerDispatch
            ->dispatch_number;

        $amount = $totalCost
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            )
            ->__toString();

        return [
            'posting_key' => $this->postingKey(
                $customerDispatch,
            ),
            'description' => mb_substr(
                "Customer Dispatch {$reference} — Cost of Goods Sold",
                0,
                500,
            ),
            'total_cost' => $amount,
            'lines' => [
                [
                    'account_id' => $accounts[
                        'cost_of_goods_sold'
                    ]->getKey(),
                    'branch_id' => $customerDispatch->branch_id,
                    'supplier_id' => null,
                    'customer_id' => null,
                    'reference' => $reference,
                    'description' => "Cost of Goods Sold for Dispatch {$reference}",
                    'due_date' => null,
                    'debit_amount' => $amount,
                    'credit_amount' => '0.000000',
                ],
                [
                    'account_id' => $accounts[
                        'inventory_asset'
                    ]->getKey(),
                    'branch_id' => $customerDispatch->branch_id,
                    'supplier_id' => null,
                    'customer_id' => null,
                    'reference' => $reference,
                    'description' => "Inventory issued for Dispatch {$reference}",
                    'due_date' => null,
                    'debit_amount' => '0.000000',
                    'credit_amount' => $amount,
                ],
            ],
        ];
    }

    public function hasStockCost(
        CustomerDispatch $customerDispatch,
    ): bool {
        return CustomerDispatchLine::query()
            ->where(
                'customer_dispatch_id',
                $customerDispatch->getKey(),
            )
            ->where('product_type', 'stock')
            ->where('total_cost', '>', 0)
            ->exists();
    }

    public function postingKey(
        CustomerDispatch $customerDispatch,
    ): string {
        return sprintf(
            'customer_dispatch:%d:journal:post',
            (int) $customerDispatch->getKey(),
        );
    }

    public function reversalPostingKey(
        CustomerDispatch $customerDispatch,
    ): string {
        return sprintf(
            'customer_dispatch:%d:journal:reverse',
            (int) $customerDispatch->getKey(),
        );
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Dispatch journal building must run inside a database transaction.',
            );
        }
    }
}
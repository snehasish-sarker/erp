<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

final class SalesAccountingAccountService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    /**
     * @return array{
     *     accounts_receivable_control: Account,
     *     output_tax_payable: Account,
     *     sales_revenue: Account,
     *     service_revenue: Account
     * }
     */
    public function salesInvoiceAccounts(): array
    {
        return [
            'accounts_receivable_control' =>
                $this->accountService->findSystemAccount(
                    'accounts_receivable_control',
                    true,
                ),

            'output_tax_payable' =>
                $this->accountService->findSystemAccount(
                    'output_tax_payable',
                    true,
                ),

            'sales_revenue' =>
                $this->postingAccountBySubtype(
                    'sales_revenue',
                ),

            'service_revenue' =>
                $this->postingAccountBySubtype(
                    'service_revenue',
                ),
        ];
    }

    /**
     * @return array{
     *     inventory_asset: Account,
     *     cost_of_goods_sold: Account
     * }
     */
    public function dispatchAccounts(): array
    {
        return [
            'inventory_asset' =>
                $this->accountService->findSystemAccount(
                    'inventory_asset',
                    true,
                ),

            'cost_of_goods_sold' =>
                $this->postingAccountBySubtype(
                    'cost_of_sales',
                ),
        ];
    }

    private function postingAccountBySubtype(
        string $subtype,
    ): Account {
        $account = Account::query()
            ->where(
                'account_subtype',
                $subtype,
            )
            ->where('status', 'active')
            ->where('is_group', false)
            ->orderBy('code')
            ->lockForUpdate()
            ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'accounting' => [
                    "An active posting account with subtype [{$subtype}] is required.",
                ],
            ]);
        }

        return $account;
    }
}
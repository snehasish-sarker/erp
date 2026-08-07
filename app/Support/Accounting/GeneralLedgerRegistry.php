<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class GeneralLedgerRegistry
{
    /**
     * @var array<string, string>
     */
    private const ACCOUNT_TYPES = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'expense' => 'Expense',
    ];

    /**
     * @var array<string, array{
     *     label: string,
     *     account_type: string,
     *     normal_balance: string
     * }>
     */
    private const ACCOUNT_SUBTYPES = [
        'cash' => [
            'label' => 'Cash',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'bank' => [
            'label' => 'Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'cash_in_transit' => [
            'label' => 'Cash in Transit',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'accounts_receivable' => [
            'label' => 'Accounts Receivable',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'supplier_advances' => [
            'label' => 'Supplier Advances',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'customer_advances' => [
            'label' => 'Customer Advances',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'inventory' => [
            'label' => 'Inventory',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'input_tax' => [
            'label' => 'Input Tax Receivable',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'prepaid_expense' => [
            'label' => 'Prepaid Expense',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'fixed_asset' => [
            'label' => 'Fixed Asset',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'accumulated_depreciation' => [
            'label' => 'Accumulated Depreciation',
            'account_type' => 'asset',
            'normal_balance' => 'credit',
        ],
        'other_asset' => [
            'label' => 'Other Asset',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ],
        'accounts_payable' => [
            'label' => 'Accounts Payable',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'goods_received_not_invoiced' => [
            'label' => 'Goods Received Not Invoiced',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'output_tax' => [
            'label' => 'Output Tax Payable',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'accrued_liability' => [
            'label' => 'Accrued Liability',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'loan' => [
            'label' => 'Loan',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'other_liability' => [
            'label' => 'Other Liability',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
        ],
        'share_capital' => [
            'label' => 'Share Capital',
            'account_type' => 'equity',
            'normal_balance' => 'credit',
        ],
        'retained_earnings' => [
            'label' => 'Retained Earnings',
            'account_type' => 'equity',
            'normal_balance' => 'credit',
        ],
        'reserves' => [
            'label' => 'Reserves',
            'account_type' => 'equity',
            'normal_balance' => 'credit',
        ],
        'other_equity' => [
            'label' => 'Other Equity',
            'account_type' => 'equity',
            'normal_balance' => 'credit',
        ],
        'sales_revenue' => [
            'label' => 'Sales Revenue',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
        ],
        'service_revenue' => [
            'label' => 'Service Revenue',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
        ],
        'sales_returns' => [
            'label' => 'Sales Returns',
            'account_type' => 'revenue',
            'normal_balance' => 'debit',
        ],
        'other_income' => [
            'label' => 'Other Income',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
        ],
        'cost_of_sales' => [
            'label' => 'Cost of Sales',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'purchase_returns' => [
            'label' => 'Purchase Return Recovery',
            'account_type' => 'expense',
            'normal_balance' => 'credit',
        ],
        'purchase_price_variance' => [
            'label' => 'Purchase Price Variance',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'non_stock_purchase' => [
            'label' => 'Non-stock Purchases and Services',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'operating_expense' => [
            'label' => 'Operating Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'depreciation_expense' => [
            'label' => 'Depreciation Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'finance_cost' => [
            'label' => 'Finance Cost',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'exchange_loss' => [
            'label' => 'Exchange Loss',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'tax_expense' => [
            'label' => 'Tax Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
        'other_expense' => [
            'label' => 'Other Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const CONTROL_TYPES = [
        'accounts_payable' => 'Accounts Payable',
        'accounts_receivable' => 'Accounts Receivable',
        'inventory' => 'Inventory',
        'tax' => 'Tax',
        'cash' => 'Cash',
        'bank' => 'Bank',
    ];

    /**
     * @var array<string, array{
     *     label: string,
     *     account_type: string,
     *     account_subtype: string,
     *     control_type: string|null
     * }>
     */
    private const SYSTEM_ACCOUNTS = [
        'accounts_payable_control' => [
            'label' => 'Accounts Payable Control',
            'account_type' => 'liability',
            'account_subtype' => 'accounts_payable',
            'control_type' => 'accounts_payable',
        ],
        'accounts_receivable_control' => [
            'label' => 'Accounts Receivable Control',
            'account_type' => 'asset',
            'account_subtype' => 'accounts_receivable',
            'control_type' => 'accounts_receivable',
        ],
        'supplier_advances' => [
            'label' => 'Supplier Advances',
            'account_type' => 'asset',
            'account_subtype' => 'supplier_advances',
            'control_type' => 'accounts_payable',
        ],
        'customer_advances' => [
            'label' => 'Customer Advances',
            'account_type' => 'liability',
            'account_subtype' => 'customer_advances',
            'control_type' => 'accounts_receivable',
        ],
        'inventory_asset' => [
            'label' => 'Inventory Asset',
            'account_type' => 'asset',
            'account_subtype' => 'inventory',
            'control_type' => 'inventory',
        ],
        'goods_received_not_invoiced' => [
            'label' => 'Goods Received Not Invoiced',
            'account_type' => 'liability',
            'account_subtype' => 'goods_received_not_invoiced',
            'control_type' => null,
        ],
        'input_tax_receivable' => [
            'label' => 'Input Tax Receivable',
            'account_type' => 'asset',
            'account_subtype' => 'input_tax',
            'control_type' => 'tax',
        ],
        'output_tax_payable' => [
            'label' => 'Output Tax Payable',
            'account_type' => 'liability',
            'account_subtype' => 'output_tax',
            'control_type' => 'tax',
        ],
        'purchase_return_recovery' => [
            'label' => 'Purchase Return Recovery',
            'account_type' => 'expense',
            'account_subtype' => 'purchase_returns',
            'control_type' => null,
        ],
        'purchase_price_variance' => [
            'label' => 'Purchase Price Variance',
            'account_type' => 'expense',
            'account_subtype' => 'purchase_price_variance',
            'control_type' => null,
        ],
        'non_stock_purchase_expense' => [
            'label' => 'Non-stock Purchases and Services Expense',
            'account_type' => 'expense',
            'account_subtype' => 'non_stock_purchase',
            'control_type' => null,
        ],
        'realized_exchange_gain' => [
            'label' => 'Realized Exchange Gain',
            'account_type' => 'revenue',
            'account_subtype' => 'other_income',
            'control_type' => null,
        ],
        'realized_exchange_loss' => [
            'label' => 'Realized Exchange Loss',
            'account_type' => 'expense',
            'account_subtype' => 'exchange_loss',
            'control_type' => null,
        ],
        'rounding_difference' => [
            'label' => 'Rounding Difference',
            'account_type' => 'expense',
            'account_subtype' => 'other_expense',
            'control_type' => null,
        ],
        'cash_control' => [
            'label' => 'Cash Control',
            'account_type' => 'asset',
            'account_subtype' => 'cash',
            'control_type' => 'cash',
        ],
        'bank_control' => [
            'label' => 'Bank Control',
            'account_type' => 'asset',
            'account_subtype' => 'bank',
            'control_type' => 'bank',
        ],
        'treasury_clearing' => [
            'label' => 'Treasury Clearing',
            'account_type' => 'asset',
            'account_subtype' => 'cash_in_transit',
            'control_type' => null,
        ],
        'bank_charges' => [
            'label' => 'Bank Charges',
            'account_type' => 'expense',
            'account_subtype' => 'finance_cost',
            'control_type' => null,
        ],
        'bank_interest_income' => [
            'label' => 'Bank Interest Income',
            'account_type' => 'revenue',
            'account_subtype' => 'other_income',
            'control_type' => null,
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const JOURNAL_TYPES = [
        'manual' => 'Manual Journal',
        'supplier_invoice' => 'Supplier Invoice',
        'supplier_invoice_reversal' => 'Supplier Invoice Reversal',
        'supplier_debit_note' => 'Supplier Debit Note',
        'supplier_debit_note_reversal' => 'Supplier Debit Note Reversal',
        'supplier_payment' => 'Supplier Payment',
        'supplier_payment_reversal' => 'Supplier Payment Reversal',
        'customer_receipt' => 'Customer Receipt',
        'customer_receipt_reversal' => 'Customer Receipt Reversal',
        'customer_credit_application' => 'Customer Credit Application',
        'customer_credit_application_reversal' => 'Customer Credit Application Reversal',
        'customer_refund' => 'Customer Refund',
        'customer_refund_reversal' => 'Customer Refund Reversal',
        'customer_ar_adjustment' => 'Customer AR Adjustment',
        'customer_ar_adjustment_reversal' => 'Customer AR Adjustment Reversal',
        'treasury_transfer' => 'Treasury Transfer',
        'treasury_transfer_reversal' => 'Treasury Transfer Reversal',
        'treasury_adjustment' => 'Treasury Adjustment',
        'treasury_adjustment_reversal' => 'Treasury Adjustment Reversal',
        'customer_dispatch' => 'Customer Dispatch',
        'customer_dispatch_reversal' => 'Customer Dispatch Reversal',
        'sales_invoice' => 'Sales Invoice',
        'sales_invoice_reversal' => 'Sales Invoice Reversal',
        'customer_credit_note' => 'Customer Credit Note',
        'customer_credit_note_reversal' => 'Customer Credit Note Reversal',
        'sales_return' => 'Sales Return Inventory',
        'sales_return_reversal' => 'Sales Return Inventory Reversal',
        'inventory' => 'Inventory Posting',
        'inventory_reversal' => 'Inventory Reversal',
        'opening_balance' => 'Opening Balance',
        'closing' => 'Closing Journal',
        'adjustment' => 'Adjustment',
        'adjustment_reversal' => 'Adjustment Reversal',
    ];

    /**
     * @var array<string, string>
     */
    private const JOURNAL_STATUSES = [
        'draft' => 'Draft',
        'approved' => 'Approved',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * @return list<string>
     */
    public function accountTypes(): array
    {
        return array_keys(self::ACCOUNT_TYPES);
    }

    /**
     * @return list<string>
     */
    public function accountSubtypes(): array
    {
        return array_keys(self::ACCOUNT_SUBTYPES);
    }

    /**
     * @return list<string>
     */
    public function controlTypes(): array
    {
        return array_keys(self::CONTROL_TYPES);
    }

    /**
     * @return list<string>
     */
    public function systemAccountKeys(): array
    {
        return array_keys(self::SYSTEM_ACCOUNTS);
    }

    /**
     * @return list<string>
     */
    public function journalTypes(): array
    {
        return array_keys(self::JOURNAL_TYPES);
    }

    /**
     * @return list<string>
     */
    public function journalStatuses(): array
    {
        return array_keys(self::JOURNAL_STATUSES);
    }

    public function isAccountType(string $accountType): bool
    {
        return array_key_exists(
            $accountType,
            self::ACCOUNT_TYPES,
        );
    }

    public function isAccountSubtype(string $accountSubtype): bool
    {
        return array_key_exists(
            $accountSubtype,
            self::ACCOUNT_SUBTYPES,
        );
    }

    public function isControlType(string $controlType): bool
    {
        return array_key_exists(
            $controlType,
            self::CONTROL_TYPES,
        );
    }

    public function isSystemAccountKey(string $systemKey): bool
    {
        return array_key_exists(
            $systemKey,
            self::SYSTEM_ACCOUNTS,
        );
    }

    public function isJournalType(string $journalType): bool
    {
        return array_key_exists(
            $journalType,
            self::JOURNAL_TYPES,
        );
    }

    public function isJournalStatus(string $status): bool
    {
        return array_key_exists(
            $status,
            self::JOURNAL_STATUSES,
        );
    }

    public function subtypeBelongsToType(
        string $accountSubtype,
        string $accountType,
    ): bool {
        return isset(
            self::ACCOUNT_SUBTYPES[$accountSubtype],
        ) && self::ACCOUNT_SUBTYPES[$accountSubtype]['account_type']
            === $accountType;
    }

    public function normalBalance(
        string $accountType,
        ?string $accountSubtype = null,
    ): string {
        if ($accountSubtype !== null) {
            $definition = self::ACCOUNT_SUBTYPES[$accountSubtype]
                ?? null;

            if ($definition === null) {
                throw new LogicException(
                    "Unsupported account subtype [{$accountSubtype}].",
                );
            }

            if ($definition['account_type'] !== $accountType) {
                throw new LogicException(
                    "The account subtype [{$accountSubtype}] does not belong to account type [{$accountType}].",
                );
            }

            return $definition['normal_balance'];
        }

        return match ($accountType) {
            'asset', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            default => throw new LogicException(
                "Unsupported account type [{$accountType}].",
            ),
        };
    }

    /**
     * @return array{
     *     label: string,
     *     account_type: string,
     *     account_subtype: string,
     *     control_type: string|null
     * }
     */
    public function systemAccountDefinition(
        string $systemKey,
    ): array {
        $definition = self::SYSTEM_ACCOUNTS[$systemKey]
            ?? null;

        if ($definition === null) {
            throw new LogicException(
                "Unsupported system account key [{$systemKey}].",
            );
        }

        return $definition;
    }

    public function accountTypeLabel(string $accountType): string
    {
        return self::ACCOUNT_TYPES[$accountType]
            ?? $accountType;
    }

    public function accountSubtypeLabel(string $accountSubtype): string
    {
        return self::ACCOUNT_SUBTYPES[$accountSubtype]['label']
            ?? $accountSubtype;
    }

    public function controlTypeLabel(string $controlType): string
    {
        return self::CONTROL_TYPES[$controlType]
            ?? $controlType;
    }

    public function systemAccountLabel(string $systemKey): string
    {
        return self::SYSTEM_ACCOUNTS[$systemKey]['label']
            ?? $systemKey;
    }

    public function journalTypeLabel(string $journalType): string
    {
        return self::JOURNAL_TYPES[$journalType]
            ?? $journalType;
    }

    public function journalStatusLabel(string $status): string
    {
        return self::JOURNAL_STATUSES[$status]
            ?? $status;
    }

    public function canApprove(string $status): bool
    {
        return $status === 'draft';
    }

    public function canReturnToDraft(string $status): bool
    {
        return $status === 'approved';
    }

    public function canPost(string $status): bool
    {
        return $status === 'approved';
    }

    public function canReverse(string $status): bool
    {
        return $status === 'posted';
    }

    public function canCancel(string $status): bool
    {
        return in_array(
            $status,
            [
                'draft',
                'approved',
            ],
            true,
        );
    }
}
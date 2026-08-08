export interface FinancialBranchOption {
    id: number;
    code: string;
    name: string;
}

export interface FinancialStatementFilters {
    date_from?: string;
    date_to?: string;
    as_of_date?: string;
    branch_id?: number | null;
    comparison?: 'none' | 'previous_period' | 'previous_year';
    method?: 'direct' | 'indirect';
}

export interface TrialBalanceRow {
    account_id: number;
    code: string;
    name: string;
    account_type: string;
    account_subtype: string | null;
    opening_debit: string;
    opening_credit: string;
    period_debit: string;
    period_credit: string;
    closing_debit: string;
    closing_credit: string;
}

export interface FinancialLine {
    account_id: number | null;
    code: string;
    name: string;
    account_subtype: string | null;
    amount: string;
}

export interface FinancialCashFlowLine {
    label: string;
    amount: string;
}

export interface TrialBalanceReport {
    statement: 'trial_balance';
    title: string;
    filters: FinancialStatementFilters;
    currency_code: string;
    rows: TrialBalanceRow[];
    totals: Record<string, string>;
    generated_at: string;
}

export interface ProfitAndLossData {
    sections: Record<string, FinancialLine[]>;
    totals: Record<string, string>;
}

export interface ProfitAndLossReport extends ProfitAndLossData {
    statement: 'profit_and_loss';
    title: string;
    filters: FinancialStatementFilters;
    comparison_range: null | {
        date_from: string;
        date_to: string;
    };
    comparison: ProfitAndLossData | null;
    comparison_variance: Record<string, string> | null;
    currency_code: string;
    generated_at: string;
}

export interface BalanceSheetReport {
    statement: 'balance_sheet';
    title: string;
    filters: FinancialStatementFilters;
    currency_code: string;
    sections: Record<string, FinancialLine[]>;
    totals: Record<string, string>;
    generated_at: string;
}

export interface CashFlowData {
    sections: Record<string, FinancialCashFlowLine[]>;
    totals: Record<string, string>;
}

export interface IndirectCashFlowData {
    rows: FinancialCashFlowLine[];
    totals: Record<string, string>;
}

export interface CashFlowReport {
    statement: 'cash_flow';
    title: string;
    filters: FinancialStatementFilters;
    currency_code: string;
    method: 'direct' | 'indirect';
    direct: CashFlowData;
    indirect: IndirectCashFlowData;
    generated_at: string;
}

export type FinancialStatementReport =
    | TrialBalanceReport
    | ProfitAndLossReport
    | BalanceSheetReport
    | CashFlowReport;

export interface ReconciliationLine {
    general_ledger: string;
    subledger: string;
    difference: string;
    status: string;
}

export interface TreasuryClearingReconciliation {
    ledger_balance: string;
    difference: string;
    status: string;
}

export interface BankReconciliationSummary {
    account_id: number;
    account_code: string;
    account_name: string;
    branch_id: number;
    branch_code: string;
    branch_name: string;
    book_balance: string;
    last_reconciliation_date: string | null;
    last_reconciliation_number: string | null;
    difference_since_reconciliation: string;
    status: string;
}

export interface FinancialReconciliationReport {
    as_of_date: string;
    branch_id: number | null;
    currency_code: string;
    accounts_receivable: ReconciliationLine;
    accounts_payable: ReconciliationLine;
    inventory: ReconciliationLine;
    treasury_clearing: TreasuryClearingReconciliation;
    bank_accounts: BankReconciliationSummary[];
    summary: {
        total_absolute_difference: string;
        unreconciled_bank_accounts: number;
        status: string;
    };
    generated_at: string;
}

export interface FinancialControlPeriod {
    id: number;
    code: string;
    name: string;
    status: string;
    start_date: string;
    end_date: string;
}

export interface FinancialControlDashboard {
    as_of_date: string;
    currency_code: string;
    period: FinancialControlPeriod | null;
    metrics: {
        net_profit: string;
        total_assets: string;
        cash_and_bank: string;
        working_capital: string;
        current_ratio: string | null;
        reconciliation_difference: string;
        unreconciled_bank_accounts: number;
        unposted_journals: number;
    };
    reconciliation: FinancialReconciliationReport;
    generated_at: string;
}

export interface PeriodCloseCheck {
    id: number;
    check_key: string;
    category: string;
    label: string;
    status: 'passed' | 'warning' | 'failed';
    is_blocking: boolean;
    issue_count: number;
    difference_amount: string;
    message: string | null;
    details: unknown;
}

export interface PeriodCloseRun {
    id: number;
    run_number: number;
    status: string;
    total_checks: number;
    passed_checks: number;
    warning_checks: number;
    failed_checks: number;
    total_reconciliation_difference: string;
    closing_journal_ids: number[];
    close_reason: string | null;
    reopen_reason: string | null;
    prepared_at: string | null;
    prepared_by: string | null;
    closed_at: string | null;
    closed_by: string | null;
    reopened_at: string | null;
    reopened_by: string | null;
    checks: PeriodCloseCheck[];
}

export interface PeriodClosePeriod {
    id: number;
    code: string;
    name: string;
    period_number: number;
    start_date: string;
    end_date: string;
    status: string;
    closed_at: string | null;
    closed_by: string | null;
    fiscal_year: {
        id: number;
        code: string;
        name: string;
        status: string;
    };
}

export interface FinancialCompany {
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    currency_code: string;
    timezone: string;
}
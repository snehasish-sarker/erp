export interface ManagementBranch {
    id: number;
    code: string;
    name: string;
}

export interface ManagementFiscalYear {
    id: number;
    name: string;
    code?: string;
    start_date?: string | null;
    end_date?: string | null;
    status?: string;
}

export interface ManagementAccount {
    id: number;
    code: string;
    name: string;
    account_type: 'revenue' | 'expense';
}

export interface ManagementBudgetLineForm {
    account_id: number | null;
    month_number: number;
    amount: string;
    notes: string;
}

export interface ManagementBudgetFormData {
    id?: number;
    branch_id: number | null;
    fiscal_year_id: number | null;
    name: string;
    notes: string;
    lines: ManagementBudgetLineForm[];
}

export interface ManagementBudgetPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    approve: boolean;
    reopen: boolean;
}

export interface ManagementBudgetSummary {
    id: number;
    name: string;
    currency_code: string;
    status: 'draft' | 'approved';
    status_label: string;
    branch: ManagementBranch | null;
    fiscal_year: ManagementFiscalYear | null;
    can: ManagementBudgetPermissions;
}

export interface ManagementBudgetLineDetail {
    id: number;
    account_id: number;
    account_code: string;
    account_name: string;
    account_type: string;
    month_number: number;
    amount: string;
    notes: string | null;
}

export interface ManagementBudgetDetail extends ManagementBudgetSummary {
    notes: string | null;
    total_amount: string;
    approved_at: string | null;
    approved_by: { id: number; name: string } | null;
    lines: ManagementBudgetLineDetail[];
}

export interface PaginatedManagementBudgets {
    data: ManagementBudgetSummary[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
    };
}

export interface ManagementContext {
    date_from: string;
    date_to: string;
    branch_id: number | null;
    budget_id: number | null;
    limit: number;
}

export interface BranchProfitabilityRow {
    branch_id: number;
    branch_code: string;
    branch_name: string;
    revenue: string;
    expenses: string;
    profit: string;
    margin_percent: string;
}

export interface GrossMarginRow {
    period: string;
    revenue: string;
    cost: string;
    gross_profit: string;
    margin_percent: string;
}

export interface ManagementDashboard {
    filters: ManagementContext;
    currency_code: string;
    kpis: {
        revenue: string;
        revenue_change_percent: string;
        gross_profit: string;
        gross_margin_percent: string;
        net_profit: string;
        net_profit_change_percent: string;
        net_margin_percent: string;
        total_assets: string;
        total_liabilities: string;
        closing_cash: string;
    };
    branch_profitability: BranchProfitabilityRow[];
    gross_margin_trend: GrossMarginRow[];
    generated_at: string;
}

export interface BudgetVsActualReport {
    budget: {
        id: number;
        name: string;
        status: string;
        branch: ManagementBranch | null;
        fiscal_year: (ManagementFiscalYear & { start_date: string; end_date: string }) | null;
    };
    currency_code: string;
    rows: Array<{
        account_id: number;
        account_code: string;
        account_name: string;
        account_type: string;
        month_number: number;
        budget_amount: string;
        actual_amount: string;
        variance_amount: string;
        variance_percent: string;
    }>;
    totals: { budget: string; actual: string; difference: string };
}

export interface ManagementSchedule {
    id: number;
    name: string;
    report_type: string;
    format: 'csv' | 'xlsx';
    frequency: 'daily' | 'weekly' | 'monthly';
    run_day: number | null;
    run_time: string;
    status: 'active' | 'inactive';
    filters: Record<string, unknown> | null;
    next_run_at: string | null;
    last_run_at: string | null;
    last_status: string | null;
    last_error: string | null;
    branch: ManagementBranch | null;
    created_by: { id: number; name: string } | null;
}

export interface ProductionReadinessCheck {
    key: string;
    label: string;
    status: 'passed' | 'failed';
    blocking: boolean;
    message: string;
}

export interface ProductionReadinessReport {
    environment: string;
    generated_at: string;
    summary: {
        checks: number;
        passed: number;
        blocking_failures: number;
        warnings: number;
        ready: boolean;
    };
    checks: ProductionReadinessCheck[];
    deployment_checklist: string[];
}
export type AccountsPayableAgingSort =
    | 'supplier_name'
    | 'total_payable'
    | 'unapplied_credit'
    | 'net_outstanding'
    | 'current'
    | 'days_1_30'
    | 'days_31_60'
    | 'days_61_90'
    | 'days_91_120'
    | 'days_over_120';

export type SortDirection = 'asc' | 'desc';

export type AccountsPayableAgingBucketKey =
    | 'current'
    | 'days_1_30'
    | 'days_31_60'
    | 'days_61_90'
    | 'days_91_120'
    | 'days_over_120';

export interface AccountsPayableBranchOption {
    id: number;
    code: string;
    name: string;
    status: string;
}

export interface AccountsPayableSupplierOption {
    id: number;
    code: string;
    name: string;
    status: string;
    deleted: boolean;
}

export interface AccountsPayableAgingBucketOption {
    value: AccountsPayableAgingBucketKey;
    label: string;
    minimum_days: number | null;
    maximum_days: number | null;
}

export interface AccountsPayablePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface AccountsPayableAgingFilters {
    as_of_date: string;
    branch_id: number | null;
    supplier_id: number | null;
    currency_code: string | null;
    search: string;
    sort: AccountsPayableAgingSort;
    direction: SortDirection;
    per_page: number;
}

export interface AccountsPayableAgingTotals {
    total_payable: string;
    unapplied_credit: string;
    net_outstanding: string;
    current: string;
    days_1_30: string;
    days_31_60: string;
    days_61_90: string;
    days_91_120: string;
    days_over_120: string;
}

export interface AccountsPayableCurrencyBreakdown {
    currency_code: string;
    total_payable: string;
    unapplied_credit: string;
    net_outstanding: string;
    base_total_payable: string;
    base_unapplied_credit: string;
    base_net_outstanding: string;
}

export interface AccountsPayableAgingSupplierRow {
    supplier: {
        id: number;
        code: string;
        name: string;
        status: string;
    };
    total_payable: string;
    unapplied_credit: string;
    net_outstanding: string;
    buckets: Record<AccountsPayableAgingBucketKey, string>;
    currencies: AccountsPayableCurrencyBreakdown[];
}

export interface AccountsPayableAgingSummaryReport {
    filters: AccountsPayableAgingFilters;
    base_currency_code: string;
    buckets: AccountsPayableAgingBucketOption[];
    totals: AccountsPayableAgingTotals;
    suppliers: {
        data: AccountsPayableAgingSupplierRow[];
        meta: AccountsPayablePaginationMeta;
    };
}

export interface AccountsPayableReportOptions {
    branches: AccountsPayableBranchOption[];
    suppliers: AccountsPayableSupplierOption[];
    currencies: string[];
    baseCurrencyCode: string;
}

export interface AccountsPayableAgingPageProps
    extends AccountsPayableReportOptions {
    report: AccountsPayableAgingSummaryReport;
}

export interface SupplierAgingOpenItem {
    id: number;
    ledger_entry_id: number;
    branch: {
        id: number;
        code: string;
        name: string;
    };
    item_type: string;
    item_type_label: string;
    entry_type: string;
    entry_type_label: string;
    balance_side: 'payable' | 'credit';
    source_type: string;
    source_id: number;
    document_number: string | null;
    document_date: string;
    posting_date: string;
    due_date: string | null;
    currency_code: string;
    exchange_rate: string;
    original_amount: string;
    historical_allocated_amount: string;
    outstanding_amount: string;
    base_original_amount: string;
    historical_base_allocated_amount: string;
    base_outstanding_amount: string;
    days_overdue: number | null;
    bucket_key: AccountsPayableAgingBucketKey | null;
    bucket_label: string;
}

export interface AccountsPayableSupplierReference {
    id: number;
    code: string;
    name: string;
    status: string;
    email: string | null;
    phone: string | null;
    payment_terms_days: number;
}

export interface SupplierAgingDetailReport {
    supplier: AccountsPayableSupplierReference;
    filters: AccountsPayableAgingFilters;
    base_currency_code: string;
    buckets: AccountsPayableAgingBucketOption[];
    summary: AccountsPayableAgingTotals;
    currencies: AccountsPayableCurrencyBreakdown[];
    items: {
        data: SupplierAgingOpenItem[];
        meta: AccountsPayablePaginationMeta;
    };
}

export interface SupplierAgingPageProps
    extends AccountsPayableReportOptions {
    report: SupplierAgingDetailReport;
}

export interface SupplierStatementFilters {
    supplier_id: number | null;
    branch_id: number | null;
    currency_code: string | null;
    date_from: string;
    date_to: string;
    per_page: number;
}

export interface SupplierStatementCurrencySummary {
    currency_code: string;
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

export interface SupplierStatementBaseSummary {
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

export interface SupplierStatementEntry {
    id: number;
    reference: string;
    journal_reference: string;
    entry_type: string;
    entry_type_label: string;
    source_type: string;
    source_id: number;
    source_document_number: string | null;
    document_date: string;
    posting_date: string;
    due_date: string | null;
    branch: {
        id: number;
        code: string | null;
        name: string | null;
    };
    currency_code: string;
    exchange_rate: string;
    debit_amount: string;
    credit_amount: string;
    transaction_change: string;
    currency_running_balance: string;
    base_debit_amount: string;
    base_credit_amount: string;
    base_change: string;
    base_running_balance: string;
    description: string;
    created_by: {
        id: number;
        name: string;
    } | null;
    reversal_of: {
        id: number;
        reference: string;
        entry_type: string;
        source_document_number: string | null;
    } | null;
}

export interface SupplierStatementReport {
    supplier: AccountsPayableSupplierReference;
    filters: SupplierStatementFilters;
    base_currency_code: string;
    summary: {
        base: SupplierStatementBaseSummary;
        currencies: SupplierStatementCurrencySummary[];
    };
    entries: {
        data: SupplierStatementEntry[];
        meta: AccountsPayablePaginationMeta;
    };
}

export interface SupplierStatementPageProps
    extends AccountsPayableReportOptions {
    report: SupplierStatementReport | null;
    filters: SupplierStatementFilters;
}
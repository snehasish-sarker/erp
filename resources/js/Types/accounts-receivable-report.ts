export type AccountsReceivableAgingSort =
    | 'customer_name'
    | 'total_receivable'
    | 'unapplied_credit'
    | 'net_outstanding'
    | 'ledger_balance'
    | 'difference'
    | 'current'
    | 'days_1_30'
    | 'days_31_60'
    | 'days_61_90'
    | 'days_91_120'
    | 'days_over_120';

export type OpenInvoiceSort =
    | 'customer_name'
    | 'document_number'
    | 'document_date'
    | 'due_date'
    | 'original_amount'
    | 'outstanding_amount'
    | 'days_overdue';

export type SortDirection = 'asc' | 'desc';

export type AccountsReceivableAgingBucketKey =
    | 'current'
    | 'days_1_30'
    | 'days_31_60'
    | 'days_61_90'
    | 'days_91_120'
    | 'days_over_120';

export interface AccountsReceivableBranchOption {
    id: number;
    code: string;
    name: string;
    status: string;
}

export interface AccountsReceivableCustomerOption {
    id: number;
    code: string;
    name: string;
    customer_type: string;
    status: string;
    deleted: boolean;
}

export interface AccountsReceivableAgingBucketOption {
    value: AccountsReceivableAgingBucketKey;
    label: string;
    minimum_days: number | null;
    maximum_days: number | null;
}

export interface AccountsReceivablePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface AccountsReceivableAgingFilters {
    as_of_date: string;
    branch_id: number | null;
    customer_id: number | null;
    currency_code: string | null;
    search: string;
    sort: AccountsReceivableAgingSort;
    direction: SortDirection;
    per_page: number;
}

export interface OpenInvoiceFilters {
    as_of_date: string;
    branch_id: number | null;
    customer_id: number | null;
    currency_code: string | null;
    search: string;
    sort: OpenInvoiceSort;
    direction: SortDirection;
    per_page: number;
}

export interface AccountsReceivableAgingTotals {
    total_receivable: string;
    unapplied_credit: string;
    net_outstanding: string;
    ledger_balance?: string;
    difference?: string;
    current: string;
    days_1_30: string;
    days_31_60: string;
    days_61_90: string;
    days_91_120: string;
    days_over_120: string;
}

export interface AccountsReceivableDashboard {
    total_receivable: string;
    unapplied_credit: string;
    net_receivable: string;
    overdue_receivable: string;
    overdue_ratio: string;
    customer_count: number;
    open_invoice_count: number;
}

export interface AccountsReceivableCustomerReference {
    id: number;
    code: string;
    name: string;
    status: string;
    customer_type: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    payment_terms_days?: number;
    credit_limit?: string;
}

export interface AccountsReceivableAgingCustomerRow {
    customer: AccountsReceivableCustomerReference;
    total_receivable: string;
    unapplied_credit: string;
    net_outstanding: string;
    ledger_balance: string;
    difference: string;
    open_invoice_count: number;
    buckets: Record<AccountsReceivableAgingBucketKey, string>;
}

export interface AccountsReceivableAgingSummaryReport {
    filters: AccountsReceivableAgingFilters;
    base_currency_code: string;
    buckets: AccountsReceivableAgingBucketOption[];
    dashboard: AccountsReceivableDashboard;
    totals: AccountsReceivableAgingTotals;
    customers: {
        data: AccountsReceivableAgingCustomerRow[];
        meta: AccountsReceivablePaginationMeta;
    };
}

export interface AccountsReceivableReportOptions {
    branches: AccountsReceivableBranchOption[];
    customers: AccountsReceivableCustomerOption[];
    currencies: string[];
    baseCurrencyCode: string;
}

export interface AccountsReceivableAgingPageProps
    extends AccountsReceivableReportOptions {
    report: AccountsReceivableAgingSummaryReport;
}

export interface CustomerAgingOpenItem {
    id: number;
    ledger_entry_id: number;
    branch: {
        id: number;
        code: string;
        name: string;
    };
    customer: AccountsReceivableCustomerReference;
    item_type: string;
    item_type_label: string;
    entry_type: string;
    entry_type_label: string;
    balance_side: 'receivable' | 'credit';
    source_type: string;
    source_id: number;
    document_number: string | null;
    reference: string;
    journal_reference: string;
    description: string;
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
    bucket_key: AccountsReceivableAgingBucketKey | null;
    bucket_label: string;
}

export interface CustomerAgingDetailReport {
    customer: AccountsReceivableCustomerReference;
    filters: AccountsReceivableAgingFilters;
    base_currency_code: string;
    buckets: AccountsReceivableAgingBucketOption[];
    summary: AccountsReceivableAgingTotals;
    items: {
        data: CustomerAgingOpenItem[];
        meta: AccountsReceivablePaginationMeta;
    };
}

export interface CustomerAgingPageProps
    extends AccountsReceivableReportOptions {
    report: CustomerAgingDetailReport;
}

export interface CustomerStatementFilters {
    customer_id: number | null;
    branch_id: number | null;
    currency_code: string | null;
    date_from: string;
    date_to: string;
    per_page: number;
}

export interface CustomerStatementCurrencySummary {
    currency_code: string;
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

export interface CustomerStatementBaseSummary {
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

export interface CustomerStatementEntry {
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

export interface CustomerStatementReport {
    customer: AccountsReceivableCustomerReference;
    filters: CustomerStatementFilters;
    base_currency_code: string;
    summary: {
        base: CustomerStatementBaseSummary;
        currencies: CustomerStatementCurrencySummary[];
    };
    entries: {
        data: CustomerStatementEntry[];
        meta: AccountsReceivablePaginationMeta;
    };
}

export interface CustomerStatementPageProps
    extends AccountsReceivableReportOptions {
    report: CustomerStatementReport | null;
    filters: CustomerStatementFilters;
}

export interface OpenInvoiceSummary {
    invoice_count: number;
    customer_count: number;
    original_amount: string;
    allocated_amount: string;
    outstanding_amount: string;
    base_original_amount: string;
    base_allocated_amount: string;
    base_outstanding_amount: string;
    overdue_base_amount: string;
}

export interface OpenInvoiceReport {
    mode: 'open' | 'overdue';
    filters: OpenInvoiceFilters;
    base_currency_code: string;
    summary: OpenInvoiceSummary;
    invoices: {
        data: CustomerAgingOpenItem[];
        meta: AccountsReceivablePaginationMeta;
    };
}

export interface OpenInvoicePageProps
    extends AccountsReceivableReportOptions {
    report: OpenInvoiceReport;
}

export interface ReportCompany {
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
}

export interface AccountsReceivableAgingPrintProps {
    report: Omit<AccountsReceivableAgingSummaryReport, 'customers'> & {
        customers: AccountsReceivableAgingCustomerRow[];
    };
    company: ReportCompany;
}

export interface CustomerAgingPrintProps {
    report: Omit<CustomerAgingDetailReport, 'items'> & {
        items: CustomerAgingOpenItem[];
    };
    company: ReportCompany;
}

export interface CustomerStatementPrintProps {
    report: Omit<CustomerStatementReport, 'entries'> & {
        entries: CustomerStatementEntry[];
    };
    company: ReportCompany;
}

export interface OpenInvoicePrintProps {
    report: Omit<OpenInvoiceReport, 'invoices'> & {
        invoices: CustomerAgingOpenItem[];
    };
    company: ReportCompany;
}
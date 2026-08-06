export type CustomerReceiptStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type CustomerReceiptMethod =
    | 'cash'
    | 'bank_transfer'
    | 'cheque'
    | 'mobile_financial_service'
    | 'other';

export type CustomerReceiptAllocationStatus =
    | 'draft'
    | 'applied'
    | 'reversed'
    | 'cancelled';

export type CustomerReceiptSort =
    | 'receipt_number'
    | 'receipt_date'
    | 'posting_date'
    | 'customer_name'
    | 'receipt_account_name'
    | 'receipt_method'
    | 'currency_code'
    | 'total_amount'
    | 'allocated_amount'
    | 'unallocated_amount'
    | 'status'
    | 'created_at';

export type CustomerReceiptSortDirection =
    | 'asc'
    | 'desc';

export interface CustomerReceiptOption<
    TValue extends string = string,
> {
    value: TValue;
    label: string;
}

export interface CustomerReceiptMethodOption
    extends CustomerReceiptOption<CustomerReceiptMethod> {
    account_control_type: 'cash' | 'bank';
    requires_cheque_details: boolean;
}

export interface CustomerReceiptUserReference {
    id: number;
    name: string;
}

export interface CustomerReceiptBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface CustomerReceiptCustomerOption {
    id: number;
    name: string;
    code: string;
    status: string;
    payment_terms_days: number;
}

export interface CustomerReceiptAccountOption {
    id: number;
    code: string;
    name: string;
    account_subtype: string;
    control_type: 'cash' | 'bank';
    status: string;
}

export interface CustomerReceiptOpenItemOption {
    id: number;
    branch_id: number;
    customer_id: number;
    sales_invoice_id: number;
    document_number: string | null;
    sales_invoice_number: string;
    document_date: string;
    due_date: string | null;
    currency_code: string;
    exchange_rate: string;
    original_amount: string;
    allocated_amount: string;
    outstanding_amount: string;
    base_outstanding_amount: string;
    status: string;
    available: boolean;
    selected: boolean;
}

export interface CustomerReceiptDefaults {
    receipt_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    branch_id: number | null;
    customer_id: number | null;
}

export interface CustomerReceiptAllocationPayload {
    customer_open_item_id: number;
    amount: string;
}

export interface CustomerReceiptFormPayload {
    branch_id: number | null;
    customer_id: number | null;
    receipt_account_id: number | null;
    receipt_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    receipt_method: CustomerReceiptMethod;
    receipt_reference: string;
    cheque_number: string;
    cheque_date: string;
    total_amount: string;
    notes: string;
    allocations: CustomerReceiptAllocationPayload[];
}

export type CustomerReceiptFormData =
    CustomerReceiptFormPayload;

export type ExistingCustomerReceiptFormData = Omit<
    CustomerReceiptFormPayload,
    | 'branch_id'
    | 'customer_id'
    | 'receipt_account_id'
    | 'receipt_reference'
    | 'cheque_number'
    | 'cheque_date'
    | 'notes'
> & {
    id: number;
    branch_id: number;
    customer_id: number;
    receipt_account_id: number;
    receipt_number: string | null;
    receipt_reference: string | null;
    cheque_number: string | null;
    cheque_date: string | null;
    notes: string | null;
    status: CustomerReceiptStatus;
    revision: number;
};

export interface CustomerReceiptFormProps {
    branches: CustomerReceiptBranchOption[];
    customers: CustomerReceiptCustomerOption[];
    receiptAccounts: CustomerReceiptAccountOption[];
    openItems: CustomerReceiptOpenItemOption[];
    receiptMethods: CustomerReceiptMethodOption[];
    defaults: CustomerReceiptDefaults;
}

export type CustomerReceiptCreateProps =
    CustomerReceiptFormProps;

export interface CustomerReceiptEditProps
    extends CustomerReceiptFormProps {
    customerReceipt: ExistingCustomerReceiptFormData;
}

export interface CustomerReceiptPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    cancel: boolean;
    post: boolean;
    reverse: boolean;
    print: boolean;
}

export interface CustomerReceiptBranchReference {
    id: number;
    name: string | null;
    code: string | null;
}

export interface CustomerReceiptCustomerReference {
    id: number;
    name: string;
    code: string;
}

export interface CustomerReceiptAccountReference {
    id: number;
    code: string;
    name: string;
}

export interface CustomerReceiptSummary {
    id: number;
    receipt_number: string | null;
    receipt_date: string;
    posting_date: string;
    branch: CustomerReceiptBranchReference;
    customer: CustomerReceiptCustomerReference;
    receipt_account: CustomerReceiptAccountReference;
    receipt_method: CustomerReceiptMethod;
    receipt_method_label: string;
    receipt_reference: string | null;
    currency_code: string;
    total_amount: string;
    allocated_amount: string;
    unallocated_amount: string;
    status: CustomerReceiptStatus;
    status_label: string;
    created_at: string | null;
    can: CustomerReceiptPermissions;
}

export interface CustomerReceiptAllocationDetail {
    id: number;
    line_number: number;
    customer_open_item_id: number;
    sales_invoice_id: number;
    invoice_document_number: string | null;
    invoice_due_date: string | null;
    currency_code: string;
    invoice_exchange_rate: string;
    receipt_exchange_rate: string;
    amount: string;
    receivable_base_amount: string;
    receipt_base_amount: string;
    exchange_difference_amount: string;
    status: CustomerReceiptAllocationStatus;
    applied_at: string | null;
    reversed_at: string | null;
}

export interface CustomerReceiptJournalSummary {
    id: number;
    journal_number: string | null;
    journal_type: string;
    status: string;
    posting_date: string;
    total_debit: string;
    total_credit: string;
    base_total_debit: string;
    base_total_credit: string;
}

export interface CustomerReceiptLedgerSummary {
    id: number;
    reference: string;
    journal_reference: string;
    entry_type: string;
    posting_date: string;
    debit_amount: string;
    credit_amount: string;
    base_debit_amount: string;
    base_credit_amount: string;
}

export interface CustomerReceiptDetail
    extends CustomerReceiptSummary {
    exchange_rate: string;
    cheque_number: string | null;
    cheque_date: string | null;
    base_total_amount: string;
    base_allocated_amount: string;
    base_unallocated_amount: string;
    notes: string | null;
    revision: number;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    reversed_at: string | null;
    cancelled_at: string | null;
    created_by: CustomerReceiptUserReference | null;
    submitted_by: CustomerReceiptUserReference | null;
    approved_by: CustomerReceiptUserReference | null;
    posted_by: CustomerReceiptUserReference | null;
    reversed_by: CustomerReceiptUserReference | null;
    cancelled_by: CustomerReceiptUserReference | null;
    allocations: CustomerReceiptAllocationDetail[];
    journal_entries: CustomerReceiptJournalSummary[];
    customer_ledger_entries: CustomerReceiptLedgerSummary[];
}

export interface CustomerReceiptFilters {
    search: string;
    branch_id: number | null;
    customer_id: number | null;
    receipt_account_id: number | null;
    status: CustomerReceiptStatus | '';
    receipt_method: CustomerReceiptMethod | '';
    receipt_date_from: string;
    receipt_date_to: string;
    sort: CustomerReceiptSort;
    direction: CustomerReceiptSortDirection;
    per_page: number;
}

export interface CustomerReceiptPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface CustomerReceiptPagination {
    data: CustomerReceiptSummary[];
    meta: CustomerReceiptPaginationMeta;
}

export interface CustomerReceiptIndexProps {
    customerReceipts: CustomerReceiptPagination;
    filters: CustomerReceiptFilters;
    branches: CustomerReceiptBranchOption[];
    customers: Omit<
        CustomerReceiptCustomerOption,
        'payment_terms_days'
    >[];
    receiptAccounts: Pick<
        CustomerReceiptAccountOption,
        | 'id'
        | 'code'
        | 'name'
        | 'status'
        | 'control_type'
    >[];
    statuses: CustomerReceiptOption<CustomerReceiptStatus>[];
    receiptMethods: CustomerReceiptMethodOption[];
    can: {
        create: boolean;
    };
}

export interface CustomerReceiptShowProps {
    customerReceipt: CustomerReceiptDetail;
}

export interface CustomerReceiptPrintProps {
    customerReceipt: CustomerReceiptDetail;

    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}
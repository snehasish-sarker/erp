export type SupplierPaymentStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type SupplierPaymentMethod =
    | 'cash'
    | 'bank_transfer'
    | 'cheque'
    | 'mobile_financial_service'
    | 'other';

export type SupplierPaymentAllocationStatus =
    | 'draft'
    | 'applied'
    | 'reversed'
    | 'cancelled';

export type SupplierPaymentSort =
    | 'payment_number'
    | 'payment_date'
    | 'posting_date'
    | 'supplier_name'
    | 'payment_account_name'
    | 'payment_method'
    | 'currency_code'
    | 'total_amount'
    | 'allocated_amount'
    | 'unallocated_amount'
    | 'status'
    | 'created_at';

export type SupplierPaymentSortDirection =
    | 'asc'
    | 'desc';

export interface SupplierPaymentOption<
    TValue extends string = string,
> {
    value: TValue;
    label: string;
}

export interface SupplierPaymentMethodOption
    extends SupplierPaymentOption<SupplierPaymentMethod> {
    account_control_type: 'cash' | 'bank';
    requires_cheque_details: boolean;
}

export interface SupplierPaymentUserReference {
    id: number;
    name: string;
}

export interface SupplierPaymentBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface SupplierPaymentSupplierOption {
    id: number;
    name: string;
    code: string;
    status: string;
    payment_terms_days: number;
}

export interface SupplierPaymentAccountOption {
    id: number;
    code: string;
    name: string;
    account_subtype: string;
    control_type: 'cash' | 'bank';
    status: string;
}

export interface SupplierPaymentOpenItemOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    supplier_invoice_id: number;
    document_number: string | null;
    supplier_invoice_number: string;
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

export interface SupplierPaymentDefaults {
    payment_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    branch_id: number | null;
    supplier_id: number | null;
}

export interface SupplierPaymentAllocationPayload {
    supplier_open_item_id: number;
    amount: string;
}

export interface SupplierPaymentFormPayload {
    branch_id: number | null;
    supplier_id: number | null;
    payment_account_id: number | null;
    payment_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    payment_method: SupplierPaymentMethod;
    payment_reference: string;
    cheque_number: string;
    cheque_date: string;
    total_amount: string;
    notes: string;
    allocations: SupplierPaymentAllocationPayload[];
}

export type SupplierPaymentFormData =
    SupplierPaymentFormPayload;

export type ExistingSupplierPaymentFormData = Omit<
    SupplierPaymentFormPayload,
    | 'branch_id'
    | 'supplier_id'
    | 'payment_account_id'
    | 'payment_reference'
    | 'cheque_number'
    | 'cheque_date'
    | 'notes'
> & {
    id: number;
    branch_id: number;
    supplier_id: number;
    payment_account_id: number;
    payment_number: string | null;
    payment_reference: string | null;
    cheque_number: string | null;
    cheque_date: string | null;
    notes: string | null;
    status: SupplierPaymentStatus;
    revision: number;
};

export interface SupplierPaymentFormProps {
    branches: SupplierPaymentBranchOption[];
    suppliers: SupplierPaymentSupplierOption[];
    paymentAccounts: SupplierPaymentAccountOption[];
    openItems: SupplierPaymentOpenItemOption[];
    paymentMethods: SupplierPaymentMethodOption[];
    defaults: SupplierPaymentDefaults;
}

export type SupplierPaymentCreateProps =
    SupplierPaymentFormProps;

export interface SupplierPaymentEditProps
    extends SupplierPaymentFormProps {
    supplierPayment: ExistingSupplierPaymentFormData;
}

export interface SupplierPaymentPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    cancel: boolean;
    post: boolean;
    reverse: boolean;
}

export interface SupplierPaymentBranchReference {
    id: number;
    name: string | null;
    code: string | null;
}

export interface SupplierPaymentSupplierReference {
    id: number;
    name: string;
    code: string;
}

export interface SupplierPaymentAccountReference {
    id: number;
    code: string;
    name: string;
}

export interface SupplierPaymentSummary {
    id: number;
    payment_number: string | null;
    payment_date: string;
    posting_date: string;
    branch: SupplierPaymentBranchReference;
    supplier: SupplierPaymentSupplierReference;
    payment_account: SupplierPaymentAccountReference;
    payment_method: SupplierPaymentMethod;
    payment_method_label: string;
    payment_reference: string | null;
    currency_code: string;
    total_amount: string;
    allocated_amount: string;
    unallocated_amount: string;
    status: SupplierPaymentStatus;
    status_label: string;
    created_at: string | null;
    can: SupplierPaymentPermissions;
}

export interface SupplierPaymentAllocationDetail {
    id: number;
    line_number: number;
    supplier_open_item_id: number;
    supplier_invoice_id: number;
    invoice_document_number: string | null;
    invoice_due_date: string | null;
    currency_code: string;
    invoice_exchange_rate: string;
    payment_exchange_rate: string;
    amount: string;
    payable_base_amount: string;
    credit_base_amount: string;
    exchange_difference_amount: string;
    status: SupplierPaymentAllocationStatus;
    applied_at: string | null;
    reversed_at: string | null;
}

export interface SupplierPaymentJournalSummary {
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

export interface SupplierPaymentLedgerSummary {
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

export interface SupplierPaymentDetail
    extends SupplierPaymentSummary {
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
    created_by: SupplierPaymentUserReference | null;
    submitted_by: SupplierPaymentUserReference | null;
    approved_by: SupplierPaymentUserReference | null;
    posted_by: SupplierPaymentUserReference | null;
    reversed_by: SupplierPaymentUserReference | null;
    cancelled_by: SupplierPaymentUserReference | null;
    allocations: SupplierPaymentAllocationDetail[];
    journal_entries: SupplierPaymentJournalSummary[];
    supplier_ledger_entries: SupplierPaymentLedgerSummary[];
}

export interface SupplierPaymentFilters {
    search: string;
    branch_id: number | null;
    supplier_id: number | null;
    payment_account_id: number | null;
    status: SupplierPaymentStatus | '';
    payment_method: SupplierPaymentMethod | '';
    payment_date_from: string;
    payment_date_to: string;
    sort: SupplierPaymentSort;
    direction: SupplierPaymentSortDirection;
    per_page: number;
}

export interface SupplierPaymentPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SupplierPaymentPagination {
    data: SupplierPaymentSummary[];
    meta: SupplierPaymentPaginationMeta;
}

export interface SupplierPaymentIndexProps {
    supplierPayments: SupplierPaymentPagination;
    filters: SupplierPaymentFilters;
    branches: SupplierPaymentBranchOption[];
    suppliers: Omit<
        SupplierPaymentSupplierOption,
        'payment_terms_days'
    >[];
    paymentAccounts: Pick<
        SupplierPaymentAccountOption,
        | 'id'
        | 'code'
        | 'name'
        | 'status'
        | 'control_type'
    >[];
    statuses: SupplierPaymentOption<SupplierPaymentStatus>[];
    paymentMethods: SupplierPaymentMethodOption[];
    can: {
        create: boolean;
    };
}

export interface SupplierPaymentShowProps {
    supplierPayment: SupplierPaymentDetail;
}
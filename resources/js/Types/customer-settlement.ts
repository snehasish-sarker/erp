export type CustomerSettlementStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type CustomerArAdjustmentDirection = 'debit' | 'credit';

export interface SettlementReference {
    id: number;
    name: string;
}

export interface SettlementBranch {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface SettlementCustomer {
    id: number;
    name: string;
    code: string;
    credit_limit?: string;
}

export interface SettlementAccount {
    id: number;
    code: string;
    name: string;
    control_type?: string | null;
    account_type?: string;
    account_subtype?: string;
}

export interface SettlementOption<T extends string = string> {
    value: T;
    label: string;
}

export interface SettlementPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    post: boolean;
    cancel: boolean;
    reverse: boolean;
    print?: boolean;
}

export interface SettlementPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface OpenCustomerItem {
    id: number;
    branch_id: number;
    customer_id: number;
    item_type: string;
    document_number: string | null;
    document_date: string | null;
    posting_date: string | null;
    due_date: string | null;
    currency_code: string;
    exchange_rate: string;
    original_amount: string;
    allocated_amount: string;
    outstanding_amount: string;
    base_outstanding_amount: string;
    status: string;
    branch: SettlementBranch | null;
    customer: SettlementCustomer | null;
}

export interface CustomerCreditBalanceItem extends OpenCustomerItem {}

export interface CustomerCreditBalanceProps {
    credits: {
        data: CustomerCreditBalanceItem[];
        meta: SettlementPaginationMeta;
    };
    summary: Array<{
        currency_code: string;
        outstanding_amount: string;
        base_outstanding_amount: string;
        item_count: number;
    }>;
    filters: {
        search: string;
        branch_id: number | null;
        customer_id: number | null;
        currency_code: string;
        item_type: string;
        per_page: number;
    };
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    can: {
        apply: boolean;
        refund: boolean;
    };
}

export interface CustomerCreditApplicationSummary {
    id: number;
    application_number: string | null;
    application_date: string | null;
    posting_date: string | null;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
    status: CustomerSettlementStatus;
    status_label: string;
    branch: SettlementBranch | null;
    can: SettlementPermissions;
}

export interface CustomerCreditApplicationLine {
    id: number;
    line_number: number;
    receivable_open_item_id: number;
    credit_open_item_id: number;
    receivable_document_number: string | null;
    credit_document_number: string | null;
    credit_item_type: string;
    amount: string;
    receivable_base_amount: string;
    credit_base_amount: string;
    exchange_difference_amount: string;
    status: string;
}

export interface CustomerCreditApplicationDetail
    extends CustomerCreditApplicationSummary {
    reason: string;
    notes: string | null;
    revision: number;
    receivable_base_amount: string;
    credit_base_amount: string;
    exchange_difference_amount: string;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    created_by: SettlementReference | null;
    submitted_by: SettlementReference | null;
    approved_by: SettlementReference | null;
    posted_by: SettlementReference | null;
    reversed_by: SettlementReference | null;
    cancelled_by: SettlementReference | null;
    lines: CustomerCreditApplicationLine[];
}

export interface CustomerCreditApplicationFormLine {
    receivable_open_item_id: number | null;
    credit_open_item_id: number | null;
    amount: string;
}

export interface CustomerCreditApplicationFormData {
    id?: number;
    branch_id: number | null;
    customer_id: number | null;
    application_date: string;
    posting_date: string;
    currency_code: string;
    reason: string;
    notes: string;
    revision?: number;
    lines: CustomerCreditApplicationFormLine[];
}

export interface CustomerCreditApplicationFormProps {
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    receivables: OpenCustomerItem[];
    credits: OpenCustomerItem[];
    selection: {
        branch_id: number | null;
        customer_id: number | null;
        currency_code: string;
    };
    defaults: {
        application_date: string;
        posting_date: string;
    };
    document?: CustomerCreditApplicationFormData;
}

export interface CustomerCreditApplicationIndexProps {
    documents: {
        data: CustomerCreditApplicationSummary[];
        meta: SettlementPaginationMeta;
    };
    filters: {
        search: string;
        branch_id: number | null;
        customer_id: number | null;
        status: CustomerSettlementStatus | '';
        per_page: number;
    };
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    statuses: SettlementOption<CustomerSettlementStatus>[];
    can: { create: boolean };
}

export interface CustomerRefundSummary {
    id: number;
    refund_number: string | null;
    refund_date: string | null;
    posting_date: string | null;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
    refund_method: string;
    refund_method_label: string;
    status: CustomerSettlementStatus;
    status_label: string;
    branch: SettlementBranch | null;
    refund_account: SettlementAccount | null;
    can: SettlementPermissions;
}

export interface CustomerRefundAllocation {
    id: number;
    line_number: number;
    credit_open_item_id: number;
    credit_document_number: string | null;
    credit_item_type: string;
    amount: string;
    credit_exchange_rate: string;
    credit_base_amount: string;
    cash_base_amount: string;
    exchange_difference_amount: string;
    status: string;
}

export interface CustomerRefundDetail extends CustomerRefundSummary {
    exchange_rate: string;
    refund_reference: string | null;
    cheque_number: string | null;
    cheque_date: string | null;
    reason: string;
    notes: string | null;
    revision: number;
    base_cash_amount: string;
    base_credit_amount: string;
    exchange_difference_amount: string;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    created_by: SettlementReference | null;
    submitted_by: SettlementReference | null;
    approved_by: SettlementReference | null;
    posted_by: SettlementReference | null;
    reversed_by: SettlementReference | null;
    cancelled_by: SettlementReference | null;
    allocations: CustomerRefundAllocation[];
}

export interface CustomerRefundFormAllocation {
    credit_open_item_id: number | null;
    amount: string;
}

export interface CustomerRefundFormData {
    id?: number;
    branch_id: number | null;
    customer_id: number | null;
    refund_account_id: number | null;
    refund_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    refund_method: string;
    refund_reference: string;
    cheque_number: string;
    cheque_date: string;
    reason: string;
    notes: string;
    revision?: number;
    allocations: CustomerRefundFormAllocation[];
}

export interface CustomerRefundFormProps {
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    accounts: SettlementAccount[];
    credits: OpenCustomerItem[];
    methods: SettlementOption[];
    selection: {
        branch_id: number | null;
        customer_id: number | null;
        currency_code: string;
    };
    defaults: {
        refund_date: string;
        posting_date: string;
        exchange_rate: string;
    };
    document?: CustomerRefundFormData;
}

export interface CustomerRefundIndexProps {
    documents: {
        data: CustomerRefundSummary[];
        meta: SettlementPaginationMeta;
    };
    filters: {
        search: string;
        branch_id: number | null;
        customer_id: number | null;
        status: CustomerSettlementStatus | '';
        refund_method: string;
        per_page: number;
    };
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    statuses: SettlementOption<CustomerSettlementStatus>[];
    methods: SettlementOption[];
    can: { create: boolean };
}

export interface CustomerArAdjustmentSummary {
    id: number;
    adjustment_number: string | null;
    adjustment_date: string | null;
    posting_date: string | null;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    amount: string;
    direction: CustomerArAdjustmentDirection;
    direction_label: string;
    status: CustomerSettlementStatus;
    status_label: string;
    branch: SettlementBranch | null;
    offset_account: SettlementAccount | null;
    can: SettlementPermissions;
}

export interface CustomerArAdjustmentDetail
    extends CustomerArAdjustmentSummary {
    exchange_rate: string;
    base_amount: string;
    reason: string;
    notes: string | null;
    revision: number;
    accounting_posting_reference: string | null;
    accounting_reversal_reference: string | null;
    reversal_posting_date: string | null;
    reversal_reason: string | null;
    cancellation_reason: string | null;
    open_item: {
        id: number;
        item_type: string;
        status: string;
        outstanding_amount: string;
    } | null;
    created_by: SettlementReference | null;
    submitted_by: SettlementReference | null;
    approved_by: SettlementReference | null;
    posted_by: SettlementReference | null;
    reversed_by: SettlementReference | null;
    cancelled_by: SettlementReference | null;
}

export interface CustomerArAdjustmentFormData {
    id?: number;
    branch_id: number | null;
    customer_id: number | null;
    offset_account_id: number | null;
    adjustment_date: string;
    posting_date: string;
    currency_code: string;
    exchange_rate: string;
    direction: CustomerArAdjustmentDirection;
    amount: string;
    reason: string;
    notes: string;
    revision?: number;
}

export interface CustomerArAdjustmentFormProps {
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    accounts: SettlementAccount[];
    directions: SettlementOption<CustomerArAdjustmentDirection>[];
    defaults: {
        adjustment_date: string;
        posting_date: string;
        currency_code: string;
        exchange_rate: string;
    };
    document?: CustomerArAdjustmentFormData;
}

export interface CustomerArAdjustmentIndexProps {
    documents: {
        data: CustomerArAdjustmentSummary[];
        meta: SettlementPaginationMeta;
    };
    filters: {
        search: string;
        branch_id: number | null;
        customer_id: number | null;
        status: CustomerSettlementStatus | '';
        direction: CustomerArAdjustmentDirection | '';
        per_page: number;
    };
    branches: SettlementBranch[];
    customers: SettlementCustomer[];
    statuses: SettlementOption<CustomerSettlementStatus>[];
    directions: SettlementOption<CustomerArAdjustmentDirection>[];
    can: { create: boolean };
}

export interface CustomerRefundPrintProps {
    document: CustomerRefundDetail;
    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}
export type CustomerCreditNoteStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type CustomerCreditLineType =
    | 'quantity'
    | 'amount';

export type CustomerCreditNoteSort =
    | 'credit_note_number'
    | 'credit_note_date'
    | 'posting_date'
    | 'customer_name'
    | 'sales_invoice_number'
    | 'total_amount'
    | 'status'
    | 'created_at';

export type SortDirection = 'asc' | 'desc';

export interface CreditNoteReference {
    id: number;
    name: string;
}

export interface CreditNoteBranch {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface CreditNoteWarehouse {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface CustomerCreditNotePermissions {
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

export interface CustomerCreditNoteSummary {
    id: number;
    credit_note_number: string | null;
    credit_note_date: string | null;
    posting_date: string | null;
    sales_invoice_number: string;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
    returned_quantity: string;
    status: CustomerCreditNoteStatus;
    status_label: string;
    branch: CreditNoteBranch | null;
    warehouse: CreditNoteWarehouse | null;
    created_at: string | null;
    can: CustomerCreditNotePermissions;
}

export interface CreditNoteDispatchAllocation {
    id: number;
    dispatch_number: string | null;
    dispatch_date: string | null;
    allocated_quantity: string;
    unit_cost: string;
    total_cost: string;
}

export interface CustomerCreditNoteLine {
    id: number;
    line_number: number;
    sales_invoice_line_id: number;
    sales_order_line_id: number;
    line_type: CustomerCreditLineType;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    credit_quantity: string;
    return_to_stock: boolean;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    unit_cost: string;
    total_cost: string;
    stock_ledger_entry_id: number | null;
    reversal_stock_ledger_entry_id: number | null;
    dispatch_allocations: CreditNoteDispatchAllocation[];
}

export interface CreditOpenItemSummary {
    id: number;
    status: string;
    original_amount: string;
    allocated_amount: string;
    outstanding_amount: string;
}

export interface CreditAutomaticAllocationSummary {
    id: number;
    status: string;
    amount: string;
    posting_date: string | null;
}

export interface CustomerCreditNoteDetail
    extends CustomerCreditNoteSummary {
    sales_invoice_id: number;
    sales_order_id: number;
    branch_id: number;
    warehouse_id: number | null;
    customer_id: number;
    customer_type: string;
    customer_contact_person: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    customer_tax_number: string | null;
    billing_address: string | null;
    return_address: string | null;
    exchange_rate: string;
    gross_amount: string;
    discount_amount: string;
    subtotal: string;
    tax_amount: string;
    quantity_credit_amount: string;
    amount_only_credit_amount: string;
    inventory_return_value: string;
    reason: string;
    notes: string | null;
    revision: number;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    accounting_posting_reference: string | null;
    inventory_posting_reference: string | null;
    reversal_posting_date: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    accounting_reversal_reference: string | null;
    inventory_reversal_reference: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    created_by: CreditNoteReference | null;
    submitted_by: CreditNoteReference | null;
    approved_by: CreditNoteReference | null;
    posted_by: CreditNoteReference | null;
    reversed_by: CreditNoteReference | null;
    cancelled_by: CreditNoteReference | null;
    customer_open_item: CreditOpenItemSummary | null;
    automatic_allocation: CreditAutomaticAllocationSummary | null;
    lines: CustomerCreditNoteLine[];
}

export interface CreditableSalesInvoiceSummary {
    id: number;
    invoice_number: string | null;
    invoice_date: string | null;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
}

export interface CreditableSalesInvoiceLine {
    id: number;
    line_number: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    invoiced_quantity: string;
    credited_quantity: string;
    remaining_creditable_quantity: string;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    credited_amount: string;
    remaining_creditable_amount: string;
}

export interface CreditableSalesInvoice {
    id: number;
    invoice_number: string | null;
    invoice_date: string | null;
    posting_date: string | null;
    sales_order_number: string;
    status: string;
    customer_name: string;
    customer_code: string;
    customer_contact_person: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    customer_tax_number: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    currency_code: string;
    exchange_rate: string;
    total_amount: string;
    open_item_outstanding: string;
    branch: CreditNoteBranch | null;
    warehouse: CreditNoteWarehouse | null;
    lines: CreditableSalesInvoiceLine[];
}

export interface CustomerCreditNoteFormLine {
    id?: number;
    sales_invoice_line_id: number;
    line_type: CustomerCreditLineType;
    credit_quantity: string;
    credit_amount: string;
    return_to_stock: boolean;
    description: string;
}

export interface CustomerCreditNoteFormData {
    sales_invoice_id: number;
    credit_note_date: string;
    posting_date: string;
    return_address: string;
    reason: string;
    notes: string;
    lines: CustomerCreditNoteFormLine[];
}

export interface ExistingCustomerCreditNoteFormData
    extends CustomerCreditNoteFormData {
    id: number;
    status: CustomerCreditNoteStatus;
    revision: number;
}

export interface CustomerCreditNoteFilters {
    search: string;
    branch_id: number | null;
    customer_id: number | null;
    status: CustomerCreditNoteStatus | '';
    posting_date_from: string;
    posting_date_to: string;
    sort: CustomerCreditNoteSort;
    direction: SortDirection;
    per_page: number;
}

export interface CustomerCreditNotePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface CustomerCreditNotePagination {
    data: CustomerCreditNoteSummary[];
    meta: CustomerCreditNotePaginationMeta;
}

export interface CustomerCreditNoteIndexProps {
    creditNotes: CustomerCreditNotePagination;
    filters: CustomerCreditNoteFilters;
    branches: Array<
        CreditNoteBranch & {
            status: string;
        }
    >;
    customers: Array<{
        id: number;
        name: string;
        code: string;
    }>;
    statuses: Array<{
        value: CustomerCreditNoteStatus;
        label: string;
    }>;
    can: {
        create: boolean;
    };
}

export interface CustomerCreditNoteCreateProps {
    salesInvoices: CreditableSalesInvoiceSummary[];
    selectedSalesInvoice: CreditableSalesInvoice | null;
    defaults: {
        credit_note_date: string;
        posting_date: string;
    };
}

export interface CustomerCreditNoteEditProps {
    creditNote: ExistingCustomerCreditNoteFormData;
    selectedSalesInvoice: CreditableSalesInvoice;
}

export interface CustomerCreditNoteShowProps {
    creditNote: CustomerCreditNoteDetail;
}

export interface CustomerCreditNotePrintProps {
    creditNote: CustomerCreditNoteDetail;
    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}
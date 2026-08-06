export type SalesInvoiceStatus =
    | 'draft'
    | 'posted'
    | 'reversed';

export type SalesInvoiceSort =
    | 'invoice_number'
    | 'invoice_date'
    | 'posting_date'
    | 'due_date'
    | 'customer_name'
    | 'total_amount'
    | 'status'
    | 'created_at';

export type SortDirection = 'asc' | 'desc';

export interface SalesInvoiceReference {
    id: number;
    name: string;
}

export interface SalesInvoiceBranch {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface SalesInvoicePermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    post: boolean;
    reverse: boolean;
    print: boolean;
}

export interface SalesInvoiceSummary {
    id: number;
    invoice_number: string | null;
    invoice_date: string | null;
    posting_date: string | null;
    due_date: string | null;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
    status: SalesInvoiceStatus;
    status_label: string;
    branch: SalesInvoiceBranch | null;
    created_at: string | null;
    can: SalesInvoicePermissions;
}

export interface SalesInvoiceDispatchAllocation {
    id: number;
    dispatch_number: string | null;
    dispatch_date: string | null;
    allocated_quantity: string;
    unit_cost: string;
    total_cost: string;
}

export interface SalesInvoiceLine {
    id: number;
    line_number: number;
    sales_order_line_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    invoiced_quantity: string;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    unit_cost: string;
    total_cost: string;
    dispatch_allocations: SalesInvoiceDispatchAllocation[];
}

export interface CustomerOpenItemSummary {
    id: number;
    status: string;
    original_amount: string;
    allocated_amount: string;
    outstanding_amount: string;
    base_outstanding_amount: string;
}

export interface SalesInvoiceDetail extends SalesInvoiceSummary {
    branch_id: number;
    customer_id: number;
    sales_order_id: number;
    customer_type: string;
    customer_contact_person: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    customer_tax_number: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    payment_terms_days: number;
    credit_limit_snapshot: string;
    exchange_rate: string;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    shipping_amount: string;
    other_charges: string;
    total_cost: string;
    notes: string | null;
    revision: number;
    posted_at: string | null;
    accounting_posting_reference: string | null;
    reversal_posting_date: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    accounting_reversal_reference: string | null;
    created_by: SalesInvoiceReference | null;
    posted_by: SalesInvoiceReference | null;
    reversed_by: SalesInvoiceReference | null;
    open_item: CustomerOpenItemSummary | null;
    lines: SalesInvoiceLine[];
}

export interface InvoiceableSalesOrderSummary {
    id: number;
    document_number: string | null;
    order_date: string | null;
    customer_name: string;
    customer_code: string;
    status: string;
}

export interface InvoiceableSalesOrderLine {
    id: number;
    line_number: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    ordered_quantity: string;
    dispatched_quantity: string;
    already_invoiced_quantity: string;
    remaining_invoiceable_quantity: string;
    unit_price: string;
    discount_amount: string;
    tax_rate: string;
}

export interface InvoiceableSalesOrder {
    id: number;
    document_number: string | null;
    order_date: string | null;
    status: string;
    customer_name: string;
    customer_code: string;
    customer_contact_person: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    payment_terms_days: number;
    credit_limit: string;
    current_base_outstanding: string;
    currency_code: string;
    exchange_rate: string;
    branch: SalesInvoiceBranch | null;
    lines: InvoiceableSalesOrderLine[];
}

export interface SalesInvoiceFormLine {
    id?: number;
    sales_order_line_id: number;
    invoiced_quantity: string;
    description: string;
}

export interface SalesInvoiceFormData {
    sales_order_id: number;
    invoice_date: string;
    posting_date: string;
    due_date: string;
    billing_address: string;
    shipping_address: string;
    shipping_amount: string;
    other_charges: string;
    notes: string;
    lines: SalesInvoiceFormLine[];
}

export interface ExistingSalesInvoiceFormData
    extends SalesInvoiceFormData {
    id: number;
    status: SalesInvoiceStatus;
    revision: number;
}

export interface SalesInvoiceFilters {
    search: string;
    branch_id: number | null;
    customer_id: number | null;
    status: SalesInvoiceStatus | '';
    posting_date_from: string;
    posting_date_to: string;
    sort: SalesInvoiceSort;
    direction: SortDirection;
    per_page: number;
}

export interface SalesInvoicePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SalesInvoicePagination {
    data: SalesInvoiceSummary[];
    meta: SalesInvoicePaginationMeta;
}

export interface SalesInvoiceIndexProps {
    salesInvoices: SalesInvoicePagination;
    filters: SalesInvoiceFilters;
    branches: Array<
        SalesInvoiceBranch & {
            status: string;
        }
    >;

    customers: Array<{
        id: number;
        name: string;
        code: string;
    }>;

    statuses: Array<{
        value: SalesInvoiceStatus;
        label: string;
    }>;

    can: {
        create: boolean;
    };
}

export interface SalesInvoiceCreateProps {
    salesOrders: InvoiceableSalesOrderSummary[];

    selectedSalesOrder:
        InvoiceableSalesOrder | null;

    defaults: {
        invoice_date: string;
        posting_date: string;
        due_date: string;
    };
}

export interface SalesInvoiceEditProps {
    salesInvoice:
        ExistingSalesInvoiceFormData;

    selectedSalesOrder:
        InvoiceableSalesOrder;
}

export interface SalesInvoiceShowProps {
    salesInvoice: SalesInvoiceDetail;
}

export interface SalesInvoicePrintProps {
    salesInvoice: SalesInvoiceDetail;

    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}
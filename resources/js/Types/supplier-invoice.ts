export type SupplierInvoiceStatus =
    | 'draft'
    | 'validated'
    | 'approved'
    | 'posted'
    | 'disputed'
    | 'reversed'
    | 'cancelled';

export type SupplierInvoiceMatchStatus =
    | 'unmatched'
    | 'matched'
    | 'variance'
    | 'blocked';

export type SupplierInvoiceSort =
    | 'document_number'
    | 'supplier_invoice_number'
    | 'invoice_date'
    | 'posting_date'
    | 'due_date'
    | 'purchase_order_number'
    | 'supplier_name'
    | 'total_amount'
    | 'status'
    | 'match_status'
    | 'created_at';

export type SupplierInvoiceSortDirection =
    | 'asc'
    | 'desc';

export interface SupplierInvoiceOption<
    TValue extends string = string,
> {
    value: TValue;
    label: string;
}

export interface SupplierInvoiceReference {
    id: number;
    name: string;
}

export interface SupplierInvoiceBranch {
    id: number;
    name: string;
    code: string;
}

export interface SupplierInvoiceSupplier {
    id: number;
    name: string;
    code: string;
}

export interface SupplierInvoiceBranchOption {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface SupplierInvoiceSupplierOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface SupplierInvoicePurchaseOrderFilterOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    document_number: string | null;
    status: string;
}

export interface SupplierInvoiceGoodsReceiptLineOption {
    goods_receipt_id: number;
    goods_receipt_line_id: number;
    receipt_number: string | null;
    receipt_date: string | null;
    accepted_quantity: string;
    invoiced_quantity: string;
    available_quantity: string;
}

export interface SupplierInvoicePurchaseOrderLineOption {
    id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    allow_decimal: boolean;
    decimal_places: number;
    description: string | null;
    ordered_quantity: string;
    received_quantity: string;
    previously_invoiced_quantity: string;
    available_to_invoice_quantity: string;
    unit_price: string;
    discount_amount: string;
    tax_rate: string;
    goods_receipt_lines:
        SupplierInvoiceGoodsReceiptLineOption[];
}

export interface SupplierInvoicePurchaseOrderOption {
    id: number;
    branch_id: number;
    branch_name: string;
    supplier_id: number;
    supplier_name: string;
    supplier_code: string;
    document_number: string | null;
    order_date: string | null;
    status: string;
    currency_code: string;
    exchange_rate: string;
    payment_terms_days: number;
    lines: SupplierInvoicePurchaseOrderLineOption[];
}

export interface SupplierInvoiceDefaults {
    invoice_date: string;
    posting_date: string;
    other_charges: string;
    rounding_adjustment: string;
}

export interface SupplierInvoicePermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    validate: boolean;
    return_to_draft: boolean;
    approve: boolean;
    dispute: boolean;
    cancel: boolean;
    post: boolean;
    reverse: boolean;
}

export interface SupplierInvoiceSummary {
    id: number;
    document_number: string | null;
    supplier_invoice_number: string;
    invoice_date: string | null;
    posting_date: string | null;
    due_date: string | null;
    purchase_order_number: string | null;
    branch: SupplierInvoiceBranch;
    supplier: SupplierInvoiceSupplier;
    currency_code: string;
    total_amount: string;
    status: SupplierInvoiceStatus;
    status_label: string;
    match_status: SupplierInvoiceMatchStatus;
    match_status_label: string;
    created_at: string | null;
    created_by: SupplierInvoiceReference | null;
    can: SupplierInvoicePermissions;
}

export interface SupplierInvoiceMatch {
    id: number;
    goods_receipt_id: number;
    goods_receipt_line_id: number;
    receipt_number: string | null;
    receipt_date: string | null;
    matched_quantity: string;
    receipt_accepted_quantity_snapshot: string;
    previously_invoiced_quantity_snapshot: string;
    available_quantity_snapshot: string;
    purchase_order_unit_price_snapshot: string;
    invoice_unit_price_snapshot: string;
    price_variance_per_unit: string;
    price_variance_amount: string;
    matched_amount: string;
}

export interface SupplierInvoiceLine {
    id: number;
    line_number: number;
    purchase_order_line_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    description: string | null;
    ordered_quantity_snapshot: string;
    received_quantity_snapshot: string;
    previously_invoiced_quantity_snapshot: string;
    available_to_invoice_quantity_snapshot: string;
    invoiced_quantity: string;
    matched_quantity: string;
    purchase_order_unit_price_snapshot: string;
    invoice_unit_price: string;
    gross_amount: string;
    discount_amount: string;
    net_amount: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    quantity_variance: string;
    price_variance_amount: string;
    discount_variance_amount: string;
    tax_variance_amount: string;
    total_variance_amount: string;
    match_status: SupplierInvoiceMatchStatus;
    match_status_label: string;
    variance_reason: string | null;
    matches: SupplierInvoiceMatch[];
}

export interface SupplierInvoiceDetail
    extends SupplierInvoiceSummary {
    purchase_order_id: number;
    supplier_id: number;
    can_view_purchase_returns: boolean;
    can_view_supplier_debit_notes: boolean;
    branch_id: number;
    exchange_rate: string;
    subtotal_amount: string;
    discount_amount: string;
    tax_amount: string;
    other_charges: string;
    rounding_adjustment: string;
    quantity_variance: string;
    price_variance_amount: string;
    discount_variance_amount: string;
    tax_variance_amount: string;
    total_variance_amount: string;
    notes: string | null;
    matching_notes: string | null;
    revision: number;
    matching_reserved_at: string | null;
    validated_at: string | null;
    approved_at: string | null;
    disputed_at: string | null;
    dispute_reason: string | null;
    posted_at: string | null;
    accounting_posting_reference: string | null;
    reversal_posting_date: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    accounting_reversal_reference: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    validated_by: SupplierInvoiceReference | null;
    approved_by: SupplierInvoiceReference | null;
    disputed_by: SupplierInvoiceReference | null;
    posted_by: SupplierInvoiceReference | null;
    reversed_by: SupplierInvoiceReference | null;
    cancelled_by: SupplierInvoiceReference | null;
    lines: SupplierInvoiceLine[];
}

export interface SupplierInvoiceFormMatch {
    goods_receipt_line_id: number;
    matched_quantity: string;
}

export interface SupplierInvoiceFormLinePayload {
    purchase_order_line_id: number;
    invoiced_quantity: string;
    invoice_unit_price: string;
    discount_amount: string;
    tax_rate: string;
    variance_reason: string;
    matches: SupplierInvoiceFormMatch[];
}

export interface SupplierInvoiceFormLine
    extends SupplierInvoiceFormLinePayload {
    include: boolean;
}

export interface SupplierInvoiceFormPayload {
    purchase_order_id: number | null;
    supplier_invoice_number: string;
    invoice_date: string;
    posting_date: string;
    due_date: string;
    currency_code: string;
    exchange_rate: string;
    other_charges: string;
    rounding_adjustment: string;
    notes: string;
    matching_notes: string;
    lines: SupplierInvoiceFormLinePayload[];
}

export interface SupplierInvoiceFormData
    extends Omit<SupplierInvoiceFormPayload, 'lines'> {
    lines: SupplierInvoiceFormLine[];
}

export interface ExistingSupplierInvoiceFormData
    extends SupplierInvoiceFormPayload {
    id: number;
}

export interface SupplierInvoiceFilters {
    search: string;
    branch_id: number | null;
    supplier_id: number | null;
    purchase_order_id: number | null;
    status: SupplierInvoiceStatus | '';
    match_status: SupplierInvoiceMatchStatus | '';
    invoice_date_from: string;
    invoice_date_to: string;
    due_date_from: string;
    due_date_to: string;
    sort: SupplierInvoiceSort;
    direction: SupplierInvoiceSortDirection;
    per_page: number;
}

export interface SupplierInvoicePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SupplierInvoicePagination {
    data: SupplierInvoiceSummary[];
    meta: SupplierInvoicePaginationMeta;
}

export interface SupplierInvoiceIndexProps {
    supplierInvoices: SupplierInvoicePagination;
    filters: SupplierInvoiceFilters;
    branches: SupplierInvoiceBranchOption[];
    suppliers: SupplierInvoiceSupplierOption[];
    purchaseOrders:
        SupplierInvoicePurchaseOrderFilterOption[];
    statuses:
        SupplierInvoiceOption<SupplierInvoiceStatus>[];
    matchStatuses:
        SupplierInvoiceOption<SupplierInvoiceMatchStatus>[];
    can: {
        create: boolean;
    };
}

export interface SupplierInvoiceFormProps {
    branches: SupplierInvoiceBranchOption[];
    purchaseOrders:
        SupplierInvoicePurchaseOrderOption[];
    selectedPurchaseOrderId: number | null;
    matchStatuses:
        SupplierInvoiceOption<SupplierInvoiceMatchStatus>[];
    defaults: SupplierInvoiceDefaults;
}

export type SupplierInvoiceCreateProps =
    SupplierInvoiceFormProps;

export interface SupplierInvoiceEditProps
    extends SupplierInvoiceFormProps {
    supplierInvoice: ExistingSupplierInvoiceFormData;
}

export interface SupplierInvoiceShowProps {
    supplierInvoice: SupplierInvoiceDetail;
}
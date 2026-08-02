export type PurchaseReturnStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type PurchaseReturnSort =
    | 'return_number'
    | 'return_date'
    | 'posting_date'
    | 'purchase_order_number'
    | 'goods_receipt_number'
    | 'supplier_name'
    | 'total_return_quantity'
    | 'total_supplier_value'
    | 'total_inventory_value'
    | 'status'
    | 'created_at';

export type PurchaseReturnSortDirection =
    | 'asc'
    | 'desc';

export interface PurchaseReturnOption<
    TValue extends string = string,
> {
    value: TValue;
    label: string;
}

export interface PurchaseReturnReference {
    id: number;
    name: string;
}

export interface PurchaseReturnBranch {
    id: number;
    name: string;
    code: string;
}

export interface PurchaseReturnWarehouse {
    id: number;
    name: string;
    code: string;
}

export interface PurchaseReturnSupplier {
    id: number;
    name: string;
    code: string;
}

export interface PurchaseReturnBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface PurchaseReturnWarehouseOption {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    status: string;
}

export interface PurchaseReturnSupplierOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface PurchaseReturnPurchaseOrderFilterOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    document_number: string | null;
    status: string;
}

export interface PurchaseReturnGoodsReceiptFilterOption {
    id: number;
    purchase_order_id: number;
    branch_id: number;
    warehouse_id: number | null;
    supplier_id: number;
    receipt_number: string | null;
    status: string;
}

export interface PurchaseReturnSupplierInvoiceFilterOption {
    id: number;
    purchase_order_id: number;
    branch_id: number;
    supplier_id: number;
    document_number: string | null;
    supplier_invoice_number: string;
    status: string;
}

export interface PurchaseReturnSupplierInvoiceOption {
    id: number;
    document_number: string | null;
    supplier_invoice_number: string;
    invoice_date: string | null;
    status: string;
    total_amount: string;
}

export interface PurchaseReturnGoodsReceiptLineOption {
    id: number;
    purchase_order_line_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    allow_decimal: boolean;
    decimal_places: number;
    accepted_quantity: string;
    return_reserved_quantity: string;
    returned_quantity: string;
    returnable_quantity: string;
    supplier_unit_cost: string;
    batch_number: string | null;
    manufacturing_date: string | null;
    expiry_date: string | null;
    serial_numbers: string[];
    storage_location: string | null;
}

export interface PurchaseReturnGoodsReceiptOption {
    id: number;
    purchase_order_id: number;
    purchase_order_number: string | null;
    receipt_number: string | null;
    receipt_date: string | null;
    branch_id: number;
    branch_name: string;
    branch_code: string;
    warehouse_id: number | null;
    warehouse_name: string | null;
    warehouse_code: string | null;
    supplier_id: number;
    supplier_name: string;
    supplier_code: string;
    supplier_invoices:
        PurchaseReturnSupplierInvoiceOption[];
    lines: PurchaseReturnGoodsReceiptLineOption[];
}

export interface PurchaseReturnDefaults {
    return_date: string;
    posting_date: string;
}

export interface PurchaseReturnPermissions {
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

export interface PurchaseReturnSummary {
    id: number;
    return_number: string | null;
    return_date: string | null;
    posting_date: string | null;
    purchase_order_id: number;
    purchase_order_number: string | null;
    goods_receipt_id: number;
    goods_receipt_number: string | null;
    supplier_invoice_id: number | null;
    supplier_invoice_number: string | null;
    branch: PurchaseReturnBranch;
    warehouse: PurchaseReturnWarehouse | null;
    supplier: PurchaseReturnSupplier;
    total_return_quantity: string;
    total_supplier_value: string;
    total_inventory_value: string;
    total_cost_variance: string;
    status: PurchaseReturnStatus;
    status_label: string;
    created_at: string | null;
    created_by: PurchaseReturnReference | null;
    can: PurchaseReturnPermissions;
}

export interface PurchaseReturnLine {
    id: number;
    line_number: number;
    goods_receipt_line_id: number;
    purchase_order_line_id: number;
    product_id: number;
    unit_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    accepted_quantity_snapshot: string;
    previously_returned_quantity_snapshot: string;
    previously_reserved_quantity_snapshot: string;
    returnable_quantity_snapshot: string;
    return_quantity: string;
    supplier_unit_cost: string;
    supplier_total_cost: string;
    inventory_unit_cost: string;
    inventory_total_cost: string;
    cost_variance_amount: string;
    batch_number: string | null;
    serial_numbers: string[];
    return_reason: string | null;
    notes: string | null;
}

export interface PurchaseReturnStockLedgerEntry {
    id: number;
    movement_type: string;
    document_number: string | null;
    occurred_at: string | null;
    product_name: string;
    product_sku: string;
    unit_code: string;
    quantity_in: string;
    quantity_out: string;
    unit_cost: string;
    total_cost: string;
    balance_quantity: string;
    balance_value: string;
    created_by: PurchaseReturnReference | null;
}

export interface PurchaseReturnDetail
    extends PurchaseReturnSummary {
    branch_id: number;
    warehouse_id: number | null;
    supplier_id: number;
    document_number_allocation_id: number | null;
    supplier_reference: string | null;
    return_reason: string;
    notes: string | null;
    revision: number;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    reversal_posting_date: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    submitted_by: PurchaseReturnReference | null;
    approved_by: PurchaseReturnReference | null;
    posted_by: PurchaseReturnReference | null;
    reversed_by: PurchaseReturnReference | null;
    cancelled_by: PurchaseReturnReference | null;
    lines: PurchaseReturnLine[];
    stock_ledger_entries: PurchaseReturnStockLedgerEntry[];
    supplier_debit_noe: PurchaseReturnSupplierDebitNoteReference | null;
    can_view_supplier_debit_notes: boolean;
    can_create_supplier_debit_note: boolean;
}

export interface PurchaseReturnFormLinePayload {
    goods_receipt_line_id: number;
    return_quantity: string;
    return_reason: string;
    notes: string;
}

export interface PurchaseReturnFormLine
    extends PurchaseReturnFormLinePayload {
    include: boolean;
}

export interface PurchaseReturnFormPayload {
    goods_receipt_id: number | null;
    supplier_invoice_id: number | null;
    return_date: string;
    posting_date: string;
    supplier_reference: string;
    return_reason: string;
    notes: string;
    lines: PurchaseReturnFormLinePayload[];
}

export interface PurchaseReturnFormData
    extends Omit<
        PurchaseReturnFormPayload,
        'lines'
    > {
    lines: PurchaseReturnFormLine[];
}

export interface ExistingPurchaseReturnFormData
    extends PurchaseReturnFormPayload {
    id: number;
    return_number: string | null;
    status: PurchaseReturnStatus;
    lines: PurchaseReturnFormLine[];
}

export interface PurchaseReturnFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    supplier_id: number | null;
    purchase_order_id: number | null;
    goods_receipt_id: number | null;
    supplier_invoice_id: number | null;
    status: PurchaseReturnStatus | '';
    return_date_from: string;
    return_date_to: string;
    sort: PurchaseReturnSort;
    direction: PurchaseReturnSortDirection;
    per_page: number;
}

export interface PurchaseReturnPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface PurchaseReturnPagination {
    data: PurchaseReturnSummary[];
    meta: PurchaseReturnPaginationMeta;
}

export interface PurchaseReturnIndexProps {
    purchaseReturns: PurchaseReturnPagination;
    filters: PurchaseReturnFilters;
    branches: PurchaseReturnBranchOption[];
    warehouses: PurchaseReturnWarehouseOption[];
    suppliers: PurchaseReturnSupplierOption[];
    purchaseOrders:
        PurchaseReturnPurchaseOrderFilterOption[];
    goodsReceipts:
        PurchaseReturnGoodsReceiptFilterOption[];
    supplierInvoices:
        PurchaseReturnSupplierInvoiceFilterOption[];
    statuses:
        PurchaseReturnOption<PurchaseReturnStatus>[];
    can: {
        create: boolean;
    };
}

export interface PurchaseReturnFormProps {
    goodsReceipts:
        PurchaseReturnGoodsReceiptOption[];
    selectedGoodsReceiptId: number | null;
    defaults: PurchaseReturnDefaults;
}

export type PurchaseReturnCreateProps =
    PurchaseReturnFormProps;

export interface PurchaseReturnEditProps
    extends PurchaseReturnFormProps {
    purchaseReturn:
        ExistingPurchaseReturnFormData;
}

export interface PurchaseReturnShowProps {
    purchaseReturn: PurchaseReturnDetail;
}

export interface PurchaseReturnSupplierDebitNoteReference {
    id: number;
    debit_note_number: string | null;
    status: string;
}
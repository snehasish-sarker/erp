export type SupplierDebitNoteStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'posted'
    | 'reversed'
    | 'cancelled';

export type SupplierDebitNoteAllocationStatus =
    | 'draft'
    | 'reserved'
    | 'applied'
    | 'reversed'
    | 'cancelled';

export type SupplierDebitNoteSort =
    | 'debit_note_number'
    | 'debit_note_date'
    | 'posting_date'
    | 'purchase_return_number'
    | 'supplier_invoice_number'
    | 'purchase_order_number'
    | 'goods_receipt_number'
    | 'supplier_name'
    | 'total_amount'
    | 'allocated_amount'
    | 'unallocated_amount'
    | 'status'
    | 'created_at';

export type SupplierDebitNoteSortDirection =
    | 'asc'
    | 'desc';

export interface SupplierDebitNoteOption<
    TValue extends string = string,
> {
    value: TValue;
    label: string;
}

export interface SupplierDebitNoteUserReference {
    id: number;
    name: string;
}

export interface SupplierDebitNoteBranch {
    id: number;
    name: string;
    code: string;
}

export interface SupplierDebitNoteSupplier {
    id: number;
    name: string;
    code: string;
}

export interface SupplierDebitNoteBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface SupplierDebitNoteSupplierOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface SupplierDebitNotePurchaseReturnFilterOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    purchase_order_id: number;
    goods_receipt_id: number;
    supplier_invoice_id: number | null;
    return_number: string | null;
    status: string;
}

export interface SupplierDebitNoteSupplierInvoiceFilterOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    purchase_order_id: number;
    document_number: string | null;
    supplier_invoice_number: string;
    status: string;
}

export interface SupplierDebitNotePurchaseOrderFilterOption {
    id: number;
    branch_id: number;
    supplier_id: number;
    document_number: string | null;
    status: string;
}

export interface SupplierDebitNoteGoodsReceiptFilterOption {
    id: number;
    purchase_order_id: number;
    branch_id: number;
    supplier_id: number;
    receipt_number: string | null;
    status: string;
}

export interface SupplierDebitNoteSupplierInvoiceLineOption {
    id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
}

export interface SupplierDebitNoteSupplierInvoiceOption {
    id: number;
    document_number: string | null;
    supplier_invoice_number: string;
    invoice_date: string | null;
    status: string;
    currency_code: string;
    exchange_rate: string;
    total_amount: string;
    debit_note_reserved_amount: string;
    debited_amount: string;
    available_debit_note_amount: string;
    lines: SupplierDebitNoteSupplierInvoiceLineOption[];
}

export interface SupplierDebitNotePurchaseReturnLineOption {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    return_quantity: string;
    supplier_unit_cost: string;
    supplier_total_cost: string;
    inventory_unit_cost: string;
    inventory_total_cost: string;
    cost_variance_amount: string;
}

export interface SupplierDebitNotePurchaseReturnOption {
    id: number;
    return_number: string | null;
    return_date: string | null;
    status: string;
    branch_id: number;
    branch_name: string;
    branch_code: string;
    supplier_id: number;
    supplier_name: string;
    supplier_code: string;
    purchase_order_id: number;
    purchase_order_number: string | null;
    goods_receipt_id: number;
    goods_receipt_number: string | null;
    source_supplier_invoice_id: number | null;
    source_supplier_invoice_number: string | null;
    currency_code: string;
    exchange_rate: string;
    total_return_quantity: string;
    total_supplier_value: string;
    total_inventory_value: string;
    total_cost_variance: string;
    supplier_invoices: SupplierDebitNoteSupplierInvoiceOption[];
    lines: SupplierDebitNotePurchaseReturnLineOption[];
}

export interface SupplierDebitNoteDefaults {
    debit_note_date: string;
    posting_date: string;
}

export interface SupplierDebitNotePermissions {
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

export interface SupplierDebitNoteSummary {
    id: number;
    debit_note_number: string | null;
    debit_note_date: string | null;
    posting_date: string | null;
    currency_code: string;
    exchange_rate: string;
    purchase_return_id: number;
    purchase_return_number: string | null;
    supplier_invoice_id: number | null;
    supplier_invoice_number: string | null;
    purchase_order_id: number;
    purchase_order_number: string | null;
    goods_receipt_id: number;
    goods_receipt_number: string | null;
    branch: SupplierDebitNoteBranch;
    supplier: SupplierDebitNoteSupplier;
    gross_amount: string;
    discount_amount: string;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    allocated_amount: string;
    unallocated_amount: string;
    status: SupplierDebitNoteStatus;
    status_label: string;
    created_at: string | null;
    created_by: SupplierDebitNoteUserReference | null;
    can: SupplierDebitNotePermissions;
}

export interface SupplierDebitNoteLine {
    id: number;
    line_number: number;
    purchase_return_line_id: number;
    supplier_invoice_line_id: number | null;
    product_id: number;
    unit_id: number;
    product_name: string;
    product_sku: string;
    unit_name: string;
    unit_code: string;
    return_quantity: string;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    total_amount: string;
    purchase_return_supplier_unit_cost: string;
    purchase_return_supplier_total_cost: string;
    purchase_return_inventory_unit_cost: string;
    purchase_return_inventory_total_cost: string;
    purchase_return_cost_variance: string;
    description: string | null;
    notes: string | null;
}

export interface SupplierDebitNoteAllocation {
    id: number;
    supplier_invoice_id: number;
    supplier_invoice_number: string;
    document_number: string | null;
    amount: string;
    status: SupplierDebitNoteAllocationStatus;
    reserved_at: string | null;
    applied_at: string | null;
    reversed_at: string | null;
    cancelled_at: string | null;
}

export interface SupplierDebitNoteDetail
    extends SupplierDebitNoteSummary {
    branch_id: number;
    supplier_id: number;
    document_number_allocation_id: number | null;
    source_purchase_return_revision: number;
    purchase_return_supplier_value: string;
    purchase_return_inventory_value: string;
    purchase_return_cost_variance: string;
    supplier_reference: string | null;
    reason: string;
    notes: string | null;
    revision: number;
    submitted_at: string | null;
    approved_at: string | null;
    posted_at: string | null;
    accounting_posting_reference: string | null;
    reversal_posting_date: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    accounting_reversal_reference: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    submitted_by: SupplierDebitNoteUserReference | null;
    approved_by: SupplierDebitNoteUserReference | null;
    posted_by: SupplierDebitNoteUserReference | null;
    reversed_by: SupplierDebitNoteUserReference | null;
    cancelled_by: SupplierDebitNoteUserReference | null;
    lines: SupplierDebitNoteLine[];
    allocations: SupplierDebitNoteAllocation[];
}

export interface SupplierDebitNoteFormLinePayload {
    purchase_return_line_id: number;
    supplier_invoice_line_id: number | null;
    return_quantity: string;
    unit_price: string;
    discount_per_unit: string;
    tax_rate: string;
    description: string;
    notes: string;
}

export interface SupplierDebitNoteFormPayload {
    purchase_return_id: number | null;
    supplier_invoice_id: number | null;
    debit_note_date: string;
    posting_date: string;
    supplier_reference: string;
    reason: string;
    notes: string;
    lines: SupplierDebitNoteFormLinePayload[];
}

export type SupplierDebitNoteFormData =
    SupplierDebitNoteFormPayload;

export interface ExistingSupplierDebitNoteFormData
    extends SupplierDebitNoteFormPayload {
    id: number;
    debit_note_number: string | null;
    status: SupplierDebitNoteStatus;
}

export interface SupplierDebitNoteFilters {
    search: string;
    branch_id: number | null;
    supplier_id: number | null;
    purchase_return_id: number | null;
    supplier_invoice_id: number | null;
    purchase_order_id: number | null;
    goods_receipt_id: number | null;
    status: SupplierDebitNoteStatus | '';
    debit_note_date_from: string;
    debit_note_date_to: string;
    sort: SupplierDebitNoteSort;
    direction: SupplierDebitNoteSortDirection;
    per_page: number;
}

export interface SupplierDebitNotePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SupplierDebitNotePagination {
    data: SupplierDebitNoteSummary[];
    meta: SupplierDebitNotePaginationMeta;
}

export interface SupplierDebitNoteIndexProps {
    supplierDebitNotes: SupplierDebitNotePagination;
    filters: SupplierDebitNoteFilters;
    branches: SupplierDebitNoteBranchOption[];
    suppliers: SupplierDebitNoteSupplierOption[];
    purchaseReturns: SupplierDebitNotePurchaseReturnFilterOption[];
    supplierInvoices: SupplierDebitNoteSupplierInvoiceFilterOption[];
    purchaseOrders: SupplierDebitNotePurchaseOrderFilterOption[];
    goodsReceipts: SupplierDebitNoteGoodsReceiptFilterOption[];
    statuses: SupplierDebitNoteOption<SupplierDebitNoteStatus>[];
    can: {
        create: boolean;
    };
}

export interface SupplierDebitNoteFormProps {
    purchaseReturns: SupplierDebitNotePurchaseReturnOption[];
    selectedPurchaseReturnId: number | null;
    defaults: SupplierDebitNoteDefaults;
}

export type SupplierDebitNoteCreateProps =
    SupplierDebitNoteFormProps;

export interface SupplierDebitNoteEditProps
    extends SupplierDebitNoteFormProps {
    supplierDebitNote: ExistingSupplierDebitNoteFormData;
}

export interface SupplierDebitNoteShowProps {
    supplierDebitNote: SupplierDebitNoteDetail;
}
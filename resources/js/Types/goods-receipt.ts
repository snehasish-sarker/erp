export type GoodsReceiptStatus =
    | 'draft'
    | 'posted'
    | 'reversed';

export type GoodsReceiptInspectionStatus =
    | 'not_required'
    | 'pending'
    | 'passed'
    | 'partial'
    | 'failed';

export type GoodsReceiptSort =
    | 'receipt_number'
    | 'receipt_date'
    | 'purchase_order_number'
    | 'supplier_name'
    | 'total_inventory_value'
    | 'status'
    | 'inspection_status'
    | 'created_at';

export type GoodsReceiptSortDirection =
    | 'asc'
    | 'desc';

export interface GoodsReceiptOption {
    value: string;
    label: string;
}

export interface GoodsReceiptBranchOption {
    value: number;
    label: string;
    status: string;
}

export interface GoodsReceiptWarehouseOption {
    value: number;
    branch_id: number;
    label: string;
    status: string;
}

export interface GoodsReceiptSupplierOption {
    value: number;
    label: string;
    status: string;
}

export interface GoodsReceiptPurchaseOrderFilterOption {
    value: number;
    label: string;
    status: string;
}

export interface GoodsReceiptReference {
    id: number;
    name: string;
}

export interface GoodsReceiptBranch {
    id: number;
    name: string;
    code: string;
}

export interface GoodsReceiptWarehouse {
    id: number;
    branch_id: number;
    name: string;
    code: string;
}

export interface GoodsReceiptPurchaseOrderLineOption {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    unit_allows_decimal: boolean;
    unit_decimal_places: number;
    ordered_quantity: string;
    received_quantity: string;
    outstanding_quantity: string;
    provisional_unit_cost: string;
}

export interface GoodsReceiptPurchaseOrderOption {
    value: number;
    label: string;
    document_number: string | null;
    status: string;
    order_date: string | null;
    expected_delivery_date: string | null;
    currency_code: string;
    branch: GoodsReceiptBranch;
    warehouse: {
        id: number;
        name: string;
        code: string;
    } | null;
    supplier: {
        id: number;
        name: string;
        code: string;
    };
    lines: GoodsReceiptPurchaseOrderLineOption[];
}

export interface GoodsReceiptDefaults {
    receipt_date: string;
    inspection_status: GoodsReceiptInspectionStatus;
    selected_purchase_order_id: number | null;
}

export interface GoodsReceiptPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    post: boolean;
    reverse: boolean;
}

export interface GoodsReceiptSummary {
    id: number;
    receipt_number: string | null;
    receipt_date: string | null;
    supplier_delivery_note: string | null;
    purchase_order_id: number;
    purchase_order_number: string | null;
    supplier_name: string;
    supplier_code: string;
    status: GoodsReceiptStatus;
    status_label: string;
    inspection_status: GoodsReceiptInspectionStatus;
    inspection_status_label: string;
    total_received_quantity: string;
    total_accepted_quantity: string;
    total_rejected_quantity: string;
    total_inventory_value: string;
    branch: GoodsReceiptBranch;
    warehouse: GoodsReceiptWarehouse | null;
    created_by: GoodsReceiptReference | null;
    posted_by: GoodsReceiptReference | null;
    reversed_by: GoodsReceiptReference | null;
    created_at: string | null;
    updated_at: string | null;
    posted_at: string | null;
    reversed_at: string | null;
    can: GoodsReceiptPermissions;
}

export interface GoodsReceiptLine {
    id: number;
    line_number: number;
    purchase_order_line_id: number;
    product_id: number;
    unit_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    ordered_quantity_snapshot: string;
    previously_received_quantity_snapshot: string;
    receipt_quantity: string;
    accepted_quantity: string;
    rejected_quantity: string;
    unit_cost: string;
    total_cost: string;
    batch_number: string | null;
    manufacturing_date: string | null;
    expiry_date: string | null;
    serial_numbers: string[];
    storage_location: string | null;
    variance_reason: string | null;
}

export interface GoodsReceiptStockLedgerEntry {
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
    created_by: GoodsReceiptReference | null;
}

export interface GoodsReceiptDetail
    extends GoodsReceiptSummary {
    branch_id: number;
    warehouse_id: number | null;
    supplier_id: number;
    can_create_supplier_invoice: boolean;
    document_number_allocation_id: number | null;
    notes: string | null;
    reversal_reason: string | null;
    can_view_purchase_returns: boolean;
can_create_purchase_return: boolean;
can_view_supplier_debit_notes: boolean;
    purchase_order: {
        id: number;
        document_number: string | null;
        status: string;
        order_date: string | null;
        expected_delivery_date: string | null;
        currency_code: string;
        total_amount: string;
    };
    lines: GoodsReceiptLine[];
    stock_ledger_entries: GoodsReceiptStockLedgerEntry[];
}

export interface GoodsReceiptFormLine {
    id?: number;
    include: boolean;
    purchase_order_line_id: number;
    receipt_quantity: string;
    accepted_quantity: string;
    rejected_quantity: string;
    batch_number: string;
    manufacturing_date: string;
    expiry_date: string;
    serial_numbers: string[];
    storage_location: string;
    variance_reason: string;
}

export interface GoodsReceiptFormData {
    purchase_order_id: number | null;
    receipt_date: string;
    supplier_delivery_note: string;
    inspection_status: GoodsReceiptInspectionStatus;
    notes: string;
    lines: GoodsReceiptFormLine[];
}

export interface ExistingGoodsReceiptFormData
    extends GoodsReceiptFormData {
    id: number;
    receipt_number: string | null;
    status: GoodsReceiptStatus;
}

export interface GoodsReceiptFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    supplier_id: number | null;
    purchase_order_id: number | null;
    status: GoodsReceiptStatus | '';
    inspection_status:
        | GoodsReceiptInspectionStatus
        | '';
    receipt_date_from: string;
    receipt_date_to: string;
    sort: GoodsReceiptSort;
    direction: GoodsReceiptSortDirection;
    per_page: number;
}

export interface GoodsReceiptPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface GoodsReceiptPagination {
    data: GoodsReceiptSummary[];
    meta: GoodsReceiptPaginationMeta;
}

export interface GoodsReceiptIndexProps {
    goodsReceipts: GoodsReceiptPagination;
    filters: GoodsReceiptFilters;
    branchOptions: GoodsReceiptBranchOption[];
    warehouseOptions: GoodsReceiptWarehouseOption[];
    supplierOptions: GoodsReceiptSupplierOption[];
    purchaseOrderFilterOptions:
        GoodsReceiptPurchaseOrderFilterOption[];
    statusOptions: GoodsReceiptOption[];
    inspectionStatusOptions: GoodsReceiptOption[];
    can: {
        create: boolean;
    };
}

export interface GoodsReceiptFormProps {
    purchaseOrderOptions:
        GoodsReceiptPurchaseOrderOption[];
    inspectionStatusOptions: GoodsReceiptOption[];
    defaults: GoodsReceiptDefaults;
}

export type GoodsReceiptCreateProps =
    GoodsReceiptFormProps;

export interface GoodsReceiptEditProps
    extends GoodsReceiptFormProps {
    goodsReceipt: ExistingGoodsReceiptFormData;
}

export interface GoodsReceiptShowProps {
    goodsReceipt: GoodsReceiptDetail;
}
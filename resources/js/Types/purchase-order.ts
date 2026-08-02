export type PurchaseOrderStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'partially_received'
    | 'received'
    | 'closed'
    | 'cancelled';

export type PurchaseOrderSort =
    | 'document_number'
    | 'order_date'
    | 'expected_delivery_date'
    | 'supplier_name'
    | 'total_amount'
    | 'status'
    | 'created_at';

export type SortDirection = 'asc' | 'desc';

export interface PurchaseOrderStatusOption {
    value: PurchaseOrderStatus;
    label: string;
}

export interface PurchaseOrderBranchOption {
    value: number;
    label: string;
    status: string;
    address?: string | null;
}

export interface PurchaseOrderWarehouseOption {
    value: number;
    branch_id: number;
    label: string;
    status: string;
    is_default?: boolean;
    address?: string | null;
}

export interface PurchaseOrderSupplierOption {
    value: number;
    label: string;
    name?: string;
    code?: string;
    status: string;
    payment_terms_days?: number;
    address?: string | null;
}

export interface PurchaseOrderUnitOption {
    id: number;
    name: string;
    code: string;
    symbol: string | null;
    allow_decimal: boolean;
    decimal_places: number;
    status: string;
}

export interface PurchaseOrderProductOption {
    value: number;
    label: string;
    name: string;
    sku: string;
    product_type: string;
    status: string;
    is_purchasable: boolean;
    default_unit_price: string;
    branch_ids: number[];
    warehouse_ids: number[];
    base_unit: PurchaseOrderUnitOption;
}

export interface PurchaseOrderDefaults {
    order_date: string;
    currency_code: string;
    exchange_rate: string;
    shipping_amount: string;
    other_charges: string;
}

export interface PurchaseOrderReference {
    id: number;
    name: string;
}

export interface PurchaseOrderBranch {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface PurchaseOrderWarehouse {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    status: string;
}

export interface PurchaseOrderPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    cancel: boolean;
    receive_goods: boolean;
}

export interface PurchaseOrderSummary {
    id: number;
    document_number: string | null;
    order_date: string | null;
    expected_delivery_date: string | null;
    supplier_reference: string | null;
    supplier_name: string;
    supplier_code: string;
    currency_code: string;
    total_amount: string;
    status: PurchaseOrderStatus;
    status_label: string;
    revision: number;
    branch: PurchaseOrderBranch;
    warehouse: PurchaseOrderWarehouse | null;
    created_by: PurchaseOrderReference | null;
    created_at: string | null;
    updated_at: string | null;
    can: PurchaseOrderPermissions;
}

export interface PurchaseOrderLine {
    id: number;
    line_number: number;
    product_id: number;
    unit_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    ordered_quantity: string;
    received_quantity: string;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    is_fully_received: boolean;
    has_outstanding_quantity: boolean;
}

export interface PurchaseOrderDetail extends PurchaseOrderSummary {
    branch_id: number;
    warehouse_id: number | null;
    supplier_id: number;
    can_view_purchase_returns: boolean;
    can_create_supplier_invoice: boolean;
    document_number_allocation_id: number | null;
    exchange_rate: string;
    supplier_contact_person: string | null;
    supplier_email: string | null;
    supplier_phone: string | null;
    supplier_tax_number: string | null;
    supplier_address: string | null;
    delivery_address: string | null;
    payment_terms_days: number;
    can_view_supplier_debit_notes: boolean;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    shipping_amount: string;
    other_charges: string;
    terms_and_conditions: string | null;
    notes: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    submitted_by: PurchaseOrderReference | null;
    approved_by: PurchaseOrderReference | null;
    cancelled_by: PurchaseOrderReference | null;
    lines: PurchaseOrderLine[];
}

export interface PurchaseOrderFormLine {
    id?: number;
    product_id: number | null;
    unit_id: number | null;
    description: string;
    ordered_quantity: string;
    unit_price: string;
    discount_amount: string;
    tax_rate: string;
    gross_amount?: string;
    tax_amount?: string;
    line_total?: string;
}

export interface PurchaseOrderFormData {
    branch_id: number | null;
    warehouse_id: number | null;
    supplier_id: number | null;
    order_date: string;
    expected_delivery_date: string;
    supplier_reference: string;
    currency_code: string;
    exchange_rate: string;
    delivery_address: string;
    payment_terms_days: number | null;
    shipping_amount: string;
    other_charges: string;
    terms_and_conditions: string;
    notes: string;
    lines: PurchaseOrderFormLine[];
}

export interface ExistingPurchaseOrderFormData
    extends PurchaseOrderFormData {
    id: number;
    document_number: string | null;
    status: PurchaseOrderStatus;
    revision: number;
}

export interface PurchaseOrderFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    supplier_id: number | null;
    status: PurchaseOrderStatus | '';
    order_date_from: string;
    order_date_to: string;
    expected_delivery_from: string;
    expected_delivery_to: string;
    sort: PurchaseOrderSort;
    direction: SortDirection;
    per_page: number;
}

export interface PurchaseOrderPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface PurchaseOrderPagination {
    data: PurchaseOrderSummary[];
    meta: PurchaseOrderPaginationMeta;
}

export interface PurchaseOrderIndexPermissions {
    create: boolean;
}

export interface PurchaseOrderIndexProps {
    purchaseOrders: PurchaseOrderPagination;
    filters: PurchaseOrderFilters;
    branchOptions: PurchaseOrderBranchOption[];
    warehouseOptions: PurchaseOrderWarehouseOption[];
    supplierOptions: PurchaseOrderSupplierOption[];
    statusOptions: PurchaseOrderStatusOption[];
    can: PurchaseOrderIndexPermissions;
}

export interface PurchaseOrderFormProps {
    branchOptions: PurchaseOrderBranchOption[];
    warehouseOptions: PurchaseOrderWarehouseOption[];
    supplierOptions: PurchaseOrderSupplierOption[];
    productOptions: PurchaseOrderProductOption[];
    defaults: PurchaseOrderDefaults;
}

export interface PurchaseOrderCreateProps
    extends PurchaseOrderFormProps {}

export interface PurchaseOrderEditProps
    extends PurchaseOrderFormProps {
    purchaseOrder: ExistingPurchaseOrderFormData;
}

export interface PurchaseOrderShowProps {
    purchaseOrder: PurchaseOrderDetail;
}
export type SalesOrderStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'partially_allocated'
    | 'allocated'
    | 'partially_dispatched'
    | 'dispatched'
    | 'partially_invoiced'
    | 'invoiced'
    | 'closed'
    | 'cancelled';

export type SalesOrderSort =
    | 'document_number'
    | 'order_date'
    | 'requested_delivery_date'
    | 'customer_name'
    | 'total_amount'
    | 'status'
    | 'created_at';

export type SortDirection =
    | 'asc'
    | 'desc';

export type SalesOrderAllocationStatus =
    | 'active'
    | 'released'
    | 'superseded';

export type InventoryReservationStatus =
    | 'active'
    | 'partially_consumed'
    | 'consumed'
    | 'released';

export interface SalesOrderStatusOption {
    value: SalesOrderStatus;
    label: string;
}

export interface SalesOrderBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface SalesOrderWarehouseOption {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    status: string;
}

export interface SalesOrderCustomerOption {
    id: number;
    name: string;
    code: string;
    customer_type?: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    tax_number?: string | null;
    billing_address?: string | null;
    shipping_address?: string | null;
    payment_terms_days?: number;
    credit_limit?: string;
    status: string;
}

export interface SalesOrderUnitOption {
    id: number;
    name: string;
    code: string;
    symbol: string | null;
    allow_decimal: boolean;
    decimal_places: number;
}

export interface SalesOrderProductBranchSetting {
    branch_id: number;
    selling_price: string;
}

export interface SalesOrderProductWarehouseSetting {
    branch_id: number;
    warehouse_id: number;
}

export interface SalesOrderProductOption {
    id: number;
    name: string;
    sku: string;
    product_type: string;
    selling_price: string;
    base_unit: SalesOrderUnitOption | null;

    branch_settings:
        SalesOrderProductBranchSetting[];

    warehouse_settings:
        SalesOrderProductWarehouseSetting[];
}

export interface SalesOrderDefaults {
    order_date: string;
    currency_code: string;
    exchange_rate: string;
    shipping_amount: string;
    other_charges: string;
}

export interface SalesOrderReference {
    id: number;
    name: string;
}

export interface SalesOrderBranch {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface SalesOrderWarehouse {
    id: number;
    branch_id?: number;
    name: string;
    code: string;
    status?: string;
}

export interface SalesOrderPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    submit: boolean;
    return_to_draft: boolean;
    approve: boolean;
    allocate: boolean;
    cancel: boolean;
}

export interface SalesOrderSummary {
    id: number;
    document_number: string | null;
    order_date: string | null;
    requested_delivery_date: string | null;
    customer_reference: string | null;
    customer_name: string;
    customer_code: string;
    currency_code: string;
    total_amount: string;
    status: SalesOrderStatus;
    status_label: string;
    revision: number;
    branch: SalesOrderBranch | null;
    warehouse: SalesOrderWarehouse | null;
    created_by: SalesOrderReference | null;
    created_at: string | null;
    can: SalesOrderPermissions;
}

export interface SalesOrderLine {
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
    allocated_quantity: string;
    dispatched_quantity: string;
    invoiced_quantity: string;
    returned_quantity: string;
    unit_price: string;
    gross_amount: string;
    discount_amount: string;
    tax_rate: string;
    tax_amount: string;
    line_total: string;
    is_fully_allocated: boolean;
    is_fully_dispatched: boolean;
    is_fully_invoiced: boolean;

    has_outstanding_allocation_quantity:
        boolean;

    has_outstanding_dispatch_quantity:
        boolean;

    has_outstanding_invoice_quantity:
        boolean;
}

export interface SalesOrderDetail
    extends SalesOrderSummary {
    branch_id: number;
    warehouse_id: number | null;
    customer_id: number;
    exchange_rate: string;
    customer_type: string;

    customer_contact_person:
        string | null;

    customer_email: string | null;
    customer_phone: string | null;
    customer_tax_number: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    payment_terms_days: number;
    credit_limit_snapshot: string;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    shipping_amount: string;
    other_charges: string;

    delivery_instructions:
        string | null;

    terms_and_conditions:
        string | null;

    notes: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    submitted_by: SalesOrderReference | null;
    approved_by: SalesOrderReference | null;
    cancelled_by: SalesOrderReference | null;
    lines: SalesOrderLine[];
}

export interface SalesOrderFormLine {
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

export interface SalesOrderFormData {
    branch_id: number | null;
    warehouse_id: number | null;
    customer_id: number | null;
    order_date: string;
    requested_delivery_date: string;
    customer_reference: string;
    currency_code: string;
    exchange_rate: string;
    billing_address: string;
    shipping_address: string;
    payment_terms_days: number | null;
    shipping_amount: string;
    other_charges: string;
    delivery_instructions: string;
    terms_and_conditions: string;
    notes: string;
    lines: SalesOrderFormLine[];
}

export interface ExistingSalesOrderFormData
    extends SalesOrderFormData {
    id: number;
    document_number: string | null;
    status: SalesOrderStatus;
    revision: number;
}

export interface SalesOrderFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    customer_id: number | null;
    status: SalesOrderStatus | '';
    order_date_from: string;
    order_date_to: string;
    requested_delivery_from: string;
    requested_delivery_to: string;
    sort: SalesOrderSort;
    direction: SortDirection;
    per_page: number;
}

export interface SalesOrderPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SalesOrderPagination {
    data: SalesOrderSummary[];
    meta: SalesOrderPaginationMeta;
}

export interface SalesOrderIndexProps {
    salesOrders: SalesOrderPagination;
    filters: SalesOrderFilters;
    branches: SalesOrderBranchOption[];
    warehouses: SalesOrderWarehouseOption[];
    customers: SalesOrderCustomerOption[];
    statuses: SalesOrderStatusOption[];

    can: {
        create: boolean;
    };
}

export interface SalesOrderFormPermissions {
    override_price: boolean;
    override_discount: boolean;
}

export interface SalesOrderFormProps {
    branches: SalesOrderBranchOption[];
    warehouses: SalesOrderWarehouseOption[];
    customers: SalesOrderCustomerOption[];
    products: SalesOrderProductOption[];
    defaults: SalesOrderDefaults;
    can: SalesOrderFormPermissions;
}

export interface SalesOrderCreateProps
    extends SalesOrderFormProps {
}

export interface SalesOrderEditProps
    extends SalesOrderFormProps {
    salesOrder: ExistingSalesOrderFormData;
}

export interface SalesOrderShowProps {
    salesOrder: SalesOrderDetail;
}

export interface SalesOrderAllocationPageLine {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    ordered_quantity: string;
    allocated_quantity: string;
    dispatched_quantity: string;
    invoiced_quantity: string;
    quantity_on_hand: string;
    quantity_reserved_total: string;

    quantity_reserved_current_order:
        string;

    quantity_reserved_other: string;
    quantity_available_to_order: string;

    maximum_allocatable_quantity:
        string;
}

export interface SalesOrderAllocationPageOrder {
    id: number;
    document_number: string | null;
    status: SalesOrderStatus;
    status_label: string;
    order_date: string | null;

    requested_delivery_date:
        string | null;

    customer_name: string;
    customer_code: string;
    branch: SalesOrderBranch | null;
    warehouse: SalesOrderWarehouse | null;
    lines: SalesOrderAllocationPageLine[];

    can: {
        allocate: boolean;
        release: boolean;
    };
}

export interface InventoryReservationSummary {
    id: number;
    status: InventoryReservationStatus;
    reserved_quantity: string;
    consumed_quantity: string;
    released_quantity: string;
    outstanding_quantity: string;
}

export interface SalesOrderActiveAllocationLine {
    id: number;
    sales_order_line_id: number;
    line_number: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_code: string;
    requested_quantity: string;
    allocated_quantity: string;

    quantity_on_hand_snapshot:
        string;

    quantity_reserved_other_snapshot:
        string;

    quantity_available_snapshot:
        string;

    reservation:
        InventoryReservationSummary | null;
}

export interface SalesOrderActiveAllocation {
    id: number;
    revision: number;
    status: SalesOrderAllocationStatus;
    notes: string | null;
    created_at: string | null;
    created_by: SalesOrderReference | null;
    lines: SalesOrderActiveAllocationLine[];
}

export interface SalesOrderAllocationHistoryItem {
    id: number;
    revision: number;
    status: SalesOrderAllocationStatus;
    notes: string | null;
    created_at: string | null;
    released_at: string | null;
    release_reason: string | null;
    created_by: SalesOrderReference | null;
    released_by: SalesOrderReference | null;
}

export interface SalesOrderAllocationProps {
    salesOrder:
        SalesOrderAllocationPageOrder;

    activeAllocation:
        SalesOrderActiveAllocation | null;

    history:
        SalesOrderAllocationHistoryItem[];
}

export interface SalesOrderAllocationFormLine {
    sales_order_line_id: number;
    allocated_quantity: string;
}
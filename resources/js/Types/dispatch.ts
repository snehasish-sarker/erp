export type CustomerDispatchStatus =
    | 'draft'
    | 'posted'
    | 'reversed';

export type CustomerDispatchSort =
    | 'dispatch_number'
    | 'dispatch_date'
    | 'customer_name'
    | 'sales_order_number'
    | 'status'
    | 'created_at';

export type SortDirection =
    | 'asc'
    | 'desc';

export interface DispatchReference {
    id: number;
    name: string;
}

export interface DispatchBranch {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface DispatchWarehouse {
    id: number;
    name: string;
    code: string;
    status?: string;
}

export interface CustomerDispatchPermissions {
    view: boolean;
    update: boolean;
    delete: boolean;
    post: boolean;
    reverse: boolean;
    print: boolean;
}

export interface CustomerDispatchSummary {
    id: number;
    dispatch_number: string | null;
    dispatch_date: string | null;
    sales_order_number: string;
    customer_name: string;
    customer_code: string;
    status: CustomerDispatchStatus;
    status_label: string;
    tracking_number: string | null;
    branch: DispatchBranch | null;
    warehouse: DispatchWarehouse | null;
    created_at: string | null;
    can: CustomerDispatchPermissions;
}

export interface CustomerDispatchLine {
    id: number;
    line_number: number;
    sales_order_line_id: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    dispatched_quantity: string;
    unit_cost: string;
    total_cost: string;
    stock_ledger_entry_id: number | null;

    reversal_stock_ledger_entry_id:
        number | null;
}

export interface CustomerDispatchDetail
    extends CustomerDispatchSummary {
    sales_order_id: number;
    sales_order_allocation_id: number;
    allocation_revision: number;
    branch_id: number;
    warehouse_id: number | null;
    customer_id: number;

    customer_contact_person:
        string | null;

    customer_phone: string | null;
    shipping_address: string | null;

    delivery_instructions:
        string | null;

    carrier_name: string | null;
    vehicle_number: string | null;
    notes: string | null;
    posted_at: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    created_by: DispatchReference | null;
    posted_by: DispatchReference | null;
    reversed_by: DispatchReference | null;
    lines: CustomerDispatchLine[];
}

export interface DispatchableSalesOrderSummary {
    id: number;
    document_number: string | null;
    order_date: string | null;
    customer_name: string;
    customer_code: string;
    status: string;
}

export interface DispatchableSalesOrderLine {
    id: number;
    line_number: number;
    product_name: string;
    product_sku: string;
    product_type: string;
    unit_name: string;
    unit_code: string;
    description: string | null;
    ordered_quantity: string;
    allocated_quantity: string;

    already_dispatched_quantity:
        string;

    remaining_dispatchable_quantity:
        string;

    reservation_outstanding_quantity:
        string | null;
}

export interface DispatchableSalesOrder {
    id: number;
    document_number: string | null;
    order_date: string | null;

    requested_delivery_date:
        string | null;

    status: string;
    customer_name: string;
    customer_code: string;

    customer_contact_person:
        string | null;

    customer_phone: string | null;
    shipping_address: string | null;

    delivery_instructions:
        string | null;

    branch: DispatchBranch | null;
    warehouse: DispatchWarehouse | null;
    allocation_revision: number;
    lines: DispatchableSalesOrderLine[];
}

export interface CustomerDispatchFormLine {
    id?: number;
    sales_order_line_id: number;
    dispatched_quantity: string;
    description: string;
}

export interface CustomerDispatchFormData {
    sales_order_id: number;
    dispatch_date: string;
    shipping_address: string;
    delivery_instructions: string;
    carrier_name: string;
    vehicle_number: string;
    tracking_number: string;
    notes: string;
    lines: CustomerDispatchFormLine[];
}

export interface ExistingCustomerDispatchFormData
    extends CustomerDispatchFormData {
    id: number;
    status: CustomerDispatchStatus;
}

export interface CustomerDispatchFilters {
    search: string;
    branch_id: number | null;
    status: CustomerDispatchStatus | '';
    dispatch_date_from: string;
    dispatch_date_to: string;
    sort: CustomerDispatchSort;
    direction: SortDirection;
    per_page: number;
}

export interface CustomerDispatchPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface CustomerDispatchPagination {
    data: CustomerDispatchSummary[];
    meta: CustomerDispatchPaginationMeta;
}

export interface CustomerDispatchIndexProps {
    dispatches: CustomerDispatchPagination;
    filters: CustomerDispatchFilters;

    branches: Array<
        DispatchBranch & {
            status: string;
        }
    >;

    statuses: Array<{
        value: CustomerDispatchStatus;
        label: string;
    }>;

    can: {
        create: boolean;
    };
}

export interface CustomerDispatchCreateProps {
    salesOrders:
        DispatchableSalesOrderSummary[];

    selectedSalesOrder:
        DispatchableSalesOrder | null;

    defaults: {
        dispatch_date: string;
    };
}

export interface CustomerDispatchEditProps {
    dispatch:
        ExistingCustomerDispatchFormData;

    selectedSalesOrder:
        DispatchableSalesOrder;
}

export interface CustomerDispatchShowProps {
    dispatch: CustomerDispatchDetail;
}

export interface CustomerDispatchPrintProps {
    dispatch: CustomerDispatchDetail;

    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}
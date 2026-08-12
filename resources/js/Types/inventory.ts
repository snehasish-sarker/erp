export type InventoryStockState =
    | ''
    | 'available'
    | 'reserved'
    | 'out_of_stock';

export type InventorySort =
    | 'updated_at'
    | 'quantity_on_hand'
    | 'quantity_reserved'
    | 'inventory_value'
    | 'average_unit_cost';

export interface InventoryBranchOption {
    id: number;
    name: string;
    code: string;
    status: string;
}

export interface InventoryWarehouseOption {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    status: string;
    branch_name: string | null;
}

export interface InventoryBalanceRecord {
    id: number;
    branch: {
        id: number;
        name: string;
        code: string;
    };
    warehouse: {
        id: number;
        name: string;
        code: string;
    };
    product: {
        id: number;
        name: string;
        sku: string;
        product_type: string;
        status: string;
    };
    unit: {
        id: number;
        name: string;
        code: string;
        symbol: string | null;
    };
    quantity_on_hand: string;
    quantity_reserved: string;
    quantity_available: string;
    inventory_value: string | null;
    average_unit_cost: string | null;
    updated_at: string | null;
}

export interface InventoryPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface InventoryPagination {
    data: InventoryBalanceRecord[];
    meta: InventoryPaginationMeta;
}

export interface InventorySummary {
    location_count: number;
    quantity_on_hand: string;
    quantity_reserved: string;
    quantity_available: string;
    inventory_value: string | null;
}

export interface InventoryFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    stock_state: InventoryStockState;
    sort: InventorySort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export type StockLedgerMovementType =
    | ''
    | 'goods_receipt'
    | 'goods_receipt_reversal'
    | 'purchase_return'
    | 'purchase_return_reversal'
    | 'dispatch'
    | 'dispatch_reversal'
    | 'sales_return'
    | 'sales_return_reversal'
    | 'transfer_in'
    | 'transfer_out'
    | 'adjustment_in'
    | 'adjustment_out';

export type StockLedgerSort =
    | 'occurred_at'
    | 'document_number'
    | 'quantity_in'
    | 'quantity_out'
    | 'balance_quantity'
    | 'unit_cost'
    | 'total_cost'
    | 'balance_value';

export interface StockLedgerMovementOption {
    value: Exclude<StockLedgerMovementType, ''>;
    label: string;
}

export interface StockLedgerEntryRecord {
    id: number;
    movement_type: Exclude<StockLedgerMovementType, ''>;
    movement_label: string;
    posting_key: string;
    document_number: string | null;
    source_type: string;
    source_id: number;
    source_line_id: number | null;
    occurred_at: string | null;
    quantity_in: string;
    quantity_out: string;
    balance_quantity: string;
    unit_cost: string | null;
    total_cost: string | null;
    balance_value: string | null;
    reversal_of_id: number | null;
    branch: {
        id: number;
        name: string;
        code: string;
    };
    warehouse: {
        id: number;
        name: string;
        code: string;
    };
    product: {
        id: number;
        name: string;
        sku: string;
    };
    unit: {
        id: number;
        name: string;
        code: string;
        symbol: string | null;
    };
    created_by: {
        id: number;
        name: string;
        email: string;
    };
}

export interface StockLedgerPagination {
    data: StockLedgerEntryRecord[];
    meta: InventoryPaginationMeta;
}

export interface StockLedgerSummary {
    entry_count: number;
    quantity_in: string;
    quantity_out: string;
    net_movement: string;
    movement_value: string | null;
}

export interface StockLedgerFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    movement_type: StockLedgerMovementType;
    date_from: string | null;
    date_to: string | null;
    sort: StockLedgerSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}


export type InventoryTransferStatus =
    | ''
    | 'draft'
    | 'posted'
    | 'cancelled';

export type InventoryTransferSort =
    | 'transfer_date'
    | 'transfer_number'
    | 'status'
    | 'created_at';

export interface InventoryTransferWarehouseOption {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    branch_name: string;
}

export interface InventoryTransferStockOption {
    warehouse_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    quantity_available: string;
}

export interface InventoryTransferListRecord {
    id: number;
    transfer_number: string | null;
    transfer_date: string;
    status: Exclude<InventoryTransferStatus, ''>;
    line_count: number;
    source_branch: {
        id: number;
        name: string;
        code: string;
    };
    destination_branch: {
        id: number;
        name: string;
        code: string;
    };
    source_warehouse: {
        id: number;
        name: string;
        code: string;
    };
    destination_warehouse: {
        id: number;
        name: string;
        code: string;
    };
    created_by: {
        id: number;
        name: string;
    };
    created_at: string | null;
}

export interface InventoryTransferPagination {
    data: InventoryTransferListRecord[];
    meta: InventoryPaginationMeta;
}

export interface InventoryTransferFilters {
    search: string;
    branch_id: number | null;
    status: InventoryTransferStatus;
    sort: InventoryTransferSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface InventoryTransferLineRecord {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    quantity: string;
    unit_cost: string | null;
    transfer_value: string | null;
}

export interface InventoryTransferRecord {
    id: number;
    transfer_number: string | null;
    transfer_date: string;
    status: Exclude<InventoryTransferStatus, ''>;
    notes: string | null;
    source_branch: {
        id: number;
        name: string;
        code: string;
    };
    destination_branch: {
        id: number;
        name: string;
        code: string;
    };
    source_warehouse: {
        id: number;
        name: string;
        code: string;
    };
    destination_warehouse: {
        id: number;
        name: string;
        code: string;
    };
    created_by: {
        id: number;
        name: string;
    };
    posted_by: {
        id: number;
        name: string;
    } | null;
    posted_at: string | null;
    cancelled_by: {
        id: number;
        name: string;
    } | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    lines: InventoryTransferLineRecord[];
}


export type InventoryAdjustmentStatus =
    | ''
    | 'draft'
    | 'posted'
    | 'cancelled';

export type InventoryAdjustmentType =
    | 'increase'
    | 'decrease';

export type InventoryAdjustmentSort =
    | 'adjustment_date'
    | 'adjustment_number'
    | 'status'
    | 'created_at';

export interface InventoryAdjustmentStockOption {
    warehouse_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    quantity_on_hand: string;
    quantity_reserved: string;
    quantity_available: string;
}

export interface InventoryAdjustmentListRecord {
    id: number;
    adjustment_number: string | null;
    adjustment_date: string;
    status: Exclude<InventoryAdjustmentStatus, ''>;
    reason: string;
    line_count: number;
    total_quantity_in: string;
    total_quantity_out: string;
    total_value_in: string | null;
    total_value_out: string | null;
    branch: { id: number; name: string; code: string };
    warehouse: { id: number; name: string; code: string };
    created_by: { id: number; name: string };
    created_at: string | null;
}

export interface InventoryAdjustmentPagination {
    data: InventoryAdjustmentListRecord[];
    meta: InventoryPaginationMeta;
}

export interface InventoryAdjustmentFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    status: InventoryAdjustmentStatus;
    sort: InventoryAdjustmentSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface InventoryAdjustmentLineRecord {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    adjustment_type: InventoryAdjustmentType;
    quantity: string;
    unit_cost: string | null;
    adjustment_value: string | null;
    quantity_before: string;
    quantity_after: string;
}

export interface InventoryAdjustmentRecord {
    id: number;
    adjustment_number: string | null;
    adjustment_date: string;
    status: Exclude<InventoryAdjustmentStatus, ''>;
    reason: string;
    notes: string | null;
    total_quantity_in: string;
    total_quantity_out: string;
    total_value_in: string | null;
    total_value_out: string | null;
    branch: { id: number; name: string; code: string };
    warehouse: { id: number; name: string; code: string };
    created_by: { id: number; name: string };
    posted_by: { id: number; name: string } | null;
    posted_at: string | null;
    cancelled_by: { id: number; name: string } | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    lines: InventoryAdjustmentLineRecord[];
}

export type InventoryStockCountStatus =
    | ''
    | 'draft'
    | 'posted'
    | 'cancelled';

export type InventoryStockCountSort =
    | 'count_date'
    | 'count_number'
    | 'status'
    | 'variance_line_count'
    | 'created_at';

export interface InventoryStockCountStockOption {
    warehouse_id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    quantity_on_hand: string;
    quantity_reserved: string;
}

export interface InventoryStockCountListRecord {
    id: number;
    count_number: string | null;
    count_date: string;
    status: Exclude<InventoryStockCountStatus, ''>;
    total_lines: number;
    variance_line_count: number;
    total_positive_variance: string;
    total_negative_variance: string;
    total_value_gain: string | null;
    total_value_loss: string | null;
    branch: { id: number; name: string; code: string };
    warehouse: { id: number; name: string; code: string };
    created_by: { id: number; name: string };
    created_at: string | null;
}

export interface InventoryStockCountPagination {
    data: InventoryStockCountListRecord[];
    meta: InventoryPaginationMeta;
}

export interface InventoryStockCountFilters {
    search: string;
    branch_id: number | null;
    warehouse_id: number | null;
    status: InventoryStockCountStatus;
    sort: InventoryStockCountSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface InventoryStockCountLineRecord {
    id: number;
    line_number: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    unit_id: number;
    unit_name: string;
    unit_code: string;
    system_quantity: string;
    reserved_quantity: string;
    counted_quantity: string;
    variance_quantity: string;
    unit_cost: string | null;
    variance_value: string | null;
}

export interface InventoryStockCountRecord {
    id: number;
    count_number: string | null;
    count_date: string;
    status: Exclude<InventoryStockCountStatus, ''>;
    notes: string | null;
    total_lines: number;
    variance_line_count: number;
    total_positive_variance: string;
    total_negative_variance: string;
    total_value_gain: string | null;
    total_value_loss: string | null;
    branch: { id: number; name: string; code: string };
    warehouse: { id: number; name: string; code: string };
    created_by: { id: number; name: string };
    posted_by: { id: number; name: string } | null;
    posted_at: string | null;
    cancelled_by: { id: number; name: string } | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    lines: InventoryStockCountLineRecord[];
}
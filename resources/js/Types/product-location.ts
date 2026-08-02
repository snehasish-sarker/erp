import type {
    ProductStatus,
    ProductType,
} from '@/Types/product';

export type ProductLocationStatus =
    | 'active'
    | 'inactive';

export type LocationMasterStatus =
    | 'active'
    | 'inactive'
    | 'archived';

export interface ProductLocationStatusOption {
    value: ProductLocationStatus;
    label: string;
}

export interface ProductLocationSummary {
    id: number;
    name: string;
    sku: string;
    product_type: ProductType;
    status: ProductStatus;
    is_purchasable: boolean;
    is_sellable: boolean;
    selling_price: string;
}

export interface ProductLocationBranch {
    id: number;
    name: string;
    code: string;
    status: LocationMasterStatus;
}

export interface ProductLocationWarehouse {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    status: LocationMasterStatus;
}

export interface ProductBranchSettingRecord {
    id: number;
    branch_id: number;
    branch_name: string;
    branch_code: string;
    branch_status: LocationMasterStatus;
    status: ProductLocationStatus;
    is_purchasable: boolean;
    is_sellable: boolean;
    selling_price: string | null;
    effective_selling_price: string;
}

export interface ProductWarehouseSettingRecord {
    id: number;
    branch_id: number;
    branch_name: string;
    warehouse_id: number;
    warehouse_name: string;
    warehouse_code: string;
    warehouse_status: LocationMasterStatus;
    status: ProductLocationStatus;
    minimum_stock: string;
    reorder_level: string;
    maximum_stock: string | null;
    bin_location: string | null;
    allow_negative_stock: boolean;
}

export interface ProductBranchSettingFormData {
    branch_id: number | null;
    status: ProductLocationStatus;
    is_purchasable: boolean;
    is_sellable: boolean;
    selling_price: string;
}

export interface ProductWarehouseSettingFormData {
    branch_id: number | null;
    warehouse_id: number | null;
    status: ProductLocationStatus;
    minimum_stock: string;
    reorder_level: string;
    maximum_stock: string;
    bin_location: string;
    allow_negative_stock: boolean;
}
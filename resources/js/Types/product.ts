export type ProductStatus =
    | 'active'
    | 'inactive';

export type ProductType =
    | 'stock'
    | 'non_stock'
    | 'service';

export type ProductSort =
    | 'name'
    | 'sku'
    | 'product_type'
    | 'cost_price'
    | 'selling_price'
    | 'status'
    | 'created_at';

export interface ProductOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface ProductRelationOption {
    id: number;
    label: string;
    status: ProductStatus;
}

export interface ProductRecord {
    id: number;
    product_category_id: number;
    category_name: string;
    brand_id: number | null;
    brand_name: string | null;
    base_unit_id: number;
    base_unit_name: string;
    base_unit_symbol: string | null;
    name: string;
    sku: string;
    slug: string;
    barcode: string | null;
    product_type: ProductType;
    product_type_label: string;
    description: string | null;
    cost_price: string | null;
    selling_price: string;
    is_purchasable: boolean;
    is_sellable: boolean;
    status: ProductStatus;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProductFormData {
    product_category_id: number | null;
    brand_id: number | null;
    base_unit_id: number | null;
    name: string;
    sku: string;
    slug: string;
    barcode: string;
    product_type: ProductType;
    description: string;
    cost_price: string;
    selling_price: string;
    is_purchasable: boolean;
    is_sellable: boolean;
    status: ProductStatus;
}

export interface ProductFilters {
    search: string;
    product_category_id: number | null;
    brand_id: number | null;
    product_type: '' | ProductType;
    status: '' | ProductStatus;
    sort: ProductSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface ProductPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface ProductPagination {
    data: ProductRecord[];
    meta: ProductPaginationMeta;
}
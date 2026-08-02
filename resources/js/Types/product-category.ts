export type ProductCategoryStatus =
    | 'active'
    | 'inactive';

export type ProductCategorySort =
    | 'name'
    | 'code'
    | 'slug'
    | 'sort_order'
    | 'status'
    | 'created_at';

export interface ProductCategoryOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface ProductCategoryParentOption {
    id: number;
    label: string;
    status: ProductCategoryStatus;
    depth: number;
}

export interface ProductCategoryRecord {
    id: number;
    parent_id: number | null;
    parent_name: string | null;
    name: string;
    code: string;
    slug: string;
    description: string | null;
    sort_order: number;
    status: ProductCategoryStatus;
    children_count: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProductCategoryFormData {
    parent_id: number | null;
    name: string;
    code: string;
    slug: string;
    description: string;
    sort_order: number;
    status: ProductCategoryStatus;
}

export interface ProductCategoryFilters {
    search: string;
    status: '' | ProductCategoryStatus;
    sort: ProductCategorySort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface ProductCategoryPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface ProductCategoryPagination {
    data: ProductCategoryRecord[];
    meta: ProductCategoryPaginationMeta;
}
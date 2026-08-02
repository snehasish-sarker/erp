export type BrandStatus =
    | 'active'
    | 'inactive';

export type BrandSort =
    | 'name'
    | 'code'
    | 'slug'
    | 'sort_order'
    | 'status'
    | 'created_at';

export interface BrandOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface BrandRecord {
    id: number;
    name: string;
    code: string;
    slug: string;
    website_url: string | null;
    description: string | null;
    sort_order: number;
    status: BrandStatus;
    created_at: string | null;
    updated_at: string | null;
}

export interface BrandFormData {
    name: string;
    code: string;
    slug: string;
    website_url: string;
    description: string;
    sort_order: number;
    status: BrandStatus;
}

export interface BrandFilters {
    search: string;
    status: '' | BrandStatus;
    sort: BrandSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface BrandPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface BrandPagination {
    data: BrandRecord[];
    meta: BrandPaginationMeta;
}
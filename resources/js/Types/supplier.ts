export type SupplierStatus =
    | 'active'
    | 'inactive';

export type SupplierType =
    | 'company'
    | 'individual'
    | 'government'
    | 'other';

export type SupplierSort =
    | 'name'
    | 'code'
    | 'supplier_type'
    | 'payment_terms_days'
    | 'status'
    | 'created_at';

export interface SupplierOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface SupplierRecord {
    id: number;
    name: string;
    code: string;
    supplier_type: SupplierType;
    supplier_type_label: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    alternate_phone: string | null;
    tax_number: string | null;
    registration_number: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country_code: string | null;
    payment_terms_days: number;
    notes: string | null;
    status: SupplierStatus;
    created_at: string | null;
    updated_at: string | null;
}

export interface SupplierFormData {
    name: string;
    code: string;
    supplier_type: SupplierType;
    contact_person: string;
    email: string;
    phone: string;
    alternate_phone: string;
    tax_number: string;
    registration_number: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    postal_code: string;
    country_code: string;
    payment_terms_days: number;
    notes: string;
    status: SupplierStatus;
}

export interface SupplierFilters {
    search: string;
    supplier_type: '' | SupplierType;
    status: '' | SupplierStatus;
    sort: SupplierSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface SupplierPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface SupplierPagination {
    data: SupplierRecord[];
    meta: SupplierPaginationMeta;
}
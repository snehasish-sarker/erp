export type DashboardMetricFormat = 'money' | 'number';

export type DashboardTone =
    | 'neutral'
    | 'success'
    | 'info'
    | 'warning'
    | 'danger';

export interface DashboardMetric {
    key: string;
    label: string;
    value: string | number;
    format: DashboardMetricFormat;
    hint: string;
    href: string;
    tone: DashboardTone;
}

export interface DashboardTrendPoint {
    period: string;
    label: string;
    sales: string | null;
    purchases: string | null;
}

export interface DashboardActionItem {
    key: string;
    label: string;
    count: number;
    href: string;
    tone: DashboardTone;
}

export interface DashboardRecentDocument {
    key: string;
    type: string;
    number: string;
    date: string | null;
    status: string;
    amount: string;
    currency_code: string;
    href: string;
    updated_at: string | null;
}

export interface DashboardQuickLink {
    key: string;
    label: string;
    description: string;
    href: string;
}

export interface TenantDashboardData {
    generated_at: string;
    tenant: {
        name: string;
        code: string;
        currency_code: string;
        timezone: string;
    };
    period: {
        label: string;
        start: string;
        end: string;
    };
    branch_scope: {
        mode: 'company' | 'assigned';
        label: string;
        branch_count: number;
    };
    visibility: {
        sales: boolean;
        purchasing: boolean;
        inventory: boolean;
        inventory_cost: boolean;
        receivables: boolean;
        payables: boolean;
    };
    metrics: DashboardMetric[];
    trend: DashboardTrendPoint[];
    action_items: DashboardActionItem[];
    recent_documents: DashboardRecentDocument[];
    quick_links: DashboardQuickLink[];
}

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type {
    Component,
    ComputedRef,
} from 'vue';
import {
    BellIcon,
    BoxCubeIcon,
    CalenderIcon,
    ChevronDownIcon,
    GridIcon,
    HorizontalDots,
    SettingsIcon,
    TableIcon,
    UserCircleIcon,
    UserGroupIcon,
    BarChartIcon
} from '@/Icons';
import { useAuthorization } from '@/Composables/useAuthorization';
import { useSaasEntitlements } from '@/Composables/useSaasEntitlements';
import { useSidebar } from '@/Composables/useSidebar';

const namedPath = (name: string): string =>
    route(name, undefined, false);

interface SubmenuItem {
    name: string;
    path: string;
    permission?: string;
    features?: readonly string[];
}

interface MenuItem {
    name: string;
    icon: Component;
    path?: string;
    permission?: string;
    features?: readonly string[];
    subItems?: SubmenuItem[];
}

interface MenuGroup {
    title: string;
    items: MenuItem[];
}

const page = usePage();

const { can } = useAuthorization();
const { canUseAll } = useSaasEntitlements();

const {
    isExpanded,
    isMobileOpen,
    isHovered,
    openSubmenu,
    closeMobileSidebar,
    setActiveItem,
    setIsHovered,
    toggleSubmenu,
} = useSidebar();

const menuGroups: MenuGroup[] = [
    {
        title: 'Menu',
        items: [
            {
                name: 'Dashboard',
                icon: GridIcon,
                path: '/',
                permission: 'dashboard.view',
            },
        ],
    },
    {
        title: 'Administration',
        items: [
            {
                name: 'Organisation',
                icon: SettingsIcon,
                subItems: [
                    {
                        name: 'Company Settings',
                        path: '/erp/settings',
                        permission: 'company_settings.view',
                    },
                    {
                        name: 'Branches',
                        path: '/erp/branches',
                        permission: 'branches.view',
                    },
                    {
                        name: 'Warehouses',
                        path: '/erp/warehouses',
                        permission: 'warehouses.view',
                    },
                    {
                        name: 'Document Numbering',
                        path: '/erp/document-numbering',
                        permission: 'document_numbering.view',
                    },
                ],
            },
            {
                name: 'Exports',
                icon: TableIcon,
                path: '/erp/exports',
                permission: 'exports.view',
                features: ['audit_exports.module'],
            },
            {
                name: 'System Operations',
                icon: SettingsIcon,
                subItems: [
                    {
                        name: 'Operations Dashboard',
                        path: namedPath('operations.index'),
                        permission: 'operations.view',
                    },
                    {
                        name: 'Database Backups',
                        path: namedPath('operations.backups.index'),
                        permission: 'operations.backups.view',
                    },
                    {
                        name: 'Failed Jobs',
                        path: namedPath('operations.failed-jobs.index'),
                        permission: 'operations.failed_jobs.view',
                    },
                    {
                        name: 'Deployment Preflight',
                        path: namedPath('operations.preflight'),
                        permission: 'operations.preflight.view',
                    },
                    {
                        name: 'Production Acceptance',
                        path: namedPath('production-acceptance.index'),
                        permission: 'production_acceptance.view',
                    },
                    {
                        name: 'Release Candidates',
                        path: namedPath('release-candidates.index'),
                        permission: 'release_candidates.view',
                    },
                ],
            },
            {
                name: 'Notifications',
                icon: BellIcon,
                path: '/erp/notifications',
            },
            {
                name: 'Accounting Periods',
                icon: CalenderIcon,
                path: '/erp/accounting-periods',
                permission: 'accounting_periods.view',
                features: ['financial_accounting.module'],
            },
            {
                name: 'Access Control',
                icon: UserGroupIcon,
                subItems: [
                    {
                        name: 'Users',
                        path: '/erp/users',
                        permission: 'users.view',
                    },
                    {
                        name: 'Roles',
                        path: '/erp/roles',
                        permission: 'roles.view',
                    },
                ],
            },
            {
                name: 'Audit Logs',
                icon: TableIcon,
                path: '/erp/audit-logs',
                permission: 'audit_logs.view',
                features: ['audit_exports.module'],
            },
        ],
    },
    {
        title: 'Master Data',
        items: [
            {
                name: 'Catalogue',
                icon: BoxCubeIcon,
                subItems: [
                    {
                        name: 'Products',
                        path: '/erp/products',
                        permission: 'products.view',
                        features: ['master_data.module'],
                    },
                    {
                        name: 'Categories',
                        path: '/erp/product-categories',
                        permission: 'product_categories.view',
                        features: ['master_data.module'],
                    },
                    {
                        name: 'Brands',
                        path: '/erp/brands',
                        permission: 'brands.view',
                        features: ['master_data.module'],
                    },
                    {
                        name: 'Units',
                        path: '/erp/units',
                        permission: 'units.view',
                        features: ['master_data.module'],
                    },
                ],
            },
            {
                name: 'Suppliers',
                icon: UserCircleIcon,
                path: '/erp/suppliers',
                permission: 'suppliers.view',
                features: ['master_data.module'],
            },
            {
                name: 'Customers',
                icon: UserCircleIcon,
                path: '/erp/customers',
                permission: 'customers.view',
                features: ['master_data.module'],
            },
        ],
    },
    {
        title: 'Operations',
        items: [
            {
                name: 'Procurement',
                icon: TableIcon,
                subItems: [
                    {
                        name: 'Purchase Orders',
                        path: '/erp/purchase-orders',
                        permission: 'purchase_orders.view',
                        features: ['purchasing.module'],
                    },
                    {
                        name: 'Goods Receipts',
                        path: '/erp/goods-receipts',
                        permission: 'goods_receipts.view',
                        features: ['purchasing.module'],
                    },
                    {
                        name: 'Purchase Returns',
                        path: '/erp/purchase-returns',
                        permission: 'purchase_returns.view',
                        features: ['purchasing.module'],
                    },
                    {
                        name: 'Supplier Invoices',
                        path: '/erp/supplier-invoices',
                        permission: 'supplier_invoices.view',
                        features: ['purchasing.module'],
                    },
                    {
                        name: 'Supplier Debit Notes',
                        path: '/erp/supplier-debit-notes',
                        permission: 'supplier_debit_notes.view',
                        features: ['purchasing.module'],
                    },
                    {
                        name: 'Supplier Payments',
                        path: '/erp/supplier-payments',
                        permission: 'supplier_payments.view',
                        features: ['accounts_payable.module'],
                    },
                ],
            },
            {
                name: 'Sales',
                icon: TableIcon,
                subItems: [
                    {
                        name: 'Sales Orders',
                        path: '/erp/sales-orders',
                        permission: 'sales_orders.view',
                        features: ['sales.module'],
                    },
                    {
                        name: 'Dispatches',
                        path: '/erp/dispatches',
                        permission: 'dispatches.view',
                        features: ['sales.module'],
                    },
                    {
                        name: 'Sales Invoices',
                        path: '/erp/sales-invoices',
                        permission: 'sales_invoices.view',
                        features: ['sales.module'],
                    },
                    {
                        name: 'Sales Returns',
                        path: '/erp/sales-returns',
                        permission: 'sales_returns.view',
                        features: ['sales.module'],
                    },
                    {
                        name: 'Customer Receipts',
                        path: '/erp/customer-receipts',
                        permission: 'customer_receipts.view',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Customer Credits',
                        path: '/erp/customer-credits',
                        permission: 'customer_credits.view',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Credit Applications',
                        path: '/erp/customer-credit-applications',
                        permission: 'customer_credit_applications.view',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Customer Refunds',
                        path: '/erp/customer-refunds',
                        permission: 'customer_refunds.view',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'AR Adjustments',
                        path: '/erp/customer-ar-adjustments',
                        permission: 'customer_ar_adjustments.view',
                        features: ['accounts_receivable.module'],
                    },
                ],
            },
            {
                name: 'Treasury',
                icon: BarChartIcon,
                subItems: [
                    {
                        name: 'Treasury Dashboard',
                        path: '/erp/treasury',
                        permission: 'treasury.view',
                        features: ['treasury.module'],
                    },
                    {
                        name: 'Transaction Register',
                        path: '/erp/treasury/register',
                        permission: 'treasury.view',
                        features: ['treasury.module'],
                    },
                    {
                        name: 'Transfers',
                        path: '/erp/treasury/transfers',
                        permission: 'treasury_transfers.view',
                        features: ['treasury.module'],
                    },
                    {
                        name: 'Adjustments',
                        path: '/erp/treasury/adjustments',
                        permission: 'treasury_adjustments.view',
                        features: ['treasury.module'],
                    },
                    {
                        name: 'Bank Statements',
                        path: '/erp/treasury/bank-statements',
                        permission: 'bank_statements.view',
                        features: ['treasury.module'],
                    },
                    {
                        name: 'Bank Reconciliations',
                        path: '/erp/treasury/bank-reconciliations',
                        permission: 'bank_reconciliations.view',
                        features: ['treasury.module'],
                    },
                ],
            },
            {
                name: 'Inventory',
                icon: BoxCubeIcon,
                subItems: [
                    {
                        name: 'Stock Summary',
                        path: namedPath('inventory.index'),
                        permission: 'inventory.view',
                        features: ['inventory.module'],
                    },
                    {
                        name: 'Stock Ledger',
                        path: namedPath(
                            'inventory.ledger.index',
                        ),
                        permission: 'inventory.view_ledger',
                        features: ['inventory.module'],
                    },
                    {
                        name: 'Transfers',
                        path: namedPath(
                            'inventory.transfers.index',
                        ),
                        permission: 'inventory.transfer',
                        features: ['inventory.module', 'inventory.advanced'],
                    },
                    {
                        name: 'Adjustments',
                        path: namedPath(
                            'inventory.adjustments.index',
                        ),
                        permission: 'inventory.adjust',
                        features: ['inventory.module', 'inventory.advanced'],
                    },
                    {
                        name: 'Stock Counts',
                        path: namedPath(
                            'inventory.counts.index',
                        ),
                        permission: 'inventory.count',
                        features: ['inventory.module', 'inventory.advanced'],
                    },
                ],
            },
        ],
    },
    {
        title: 'Reports',
        items: [
            {
                name: 'Accounts Payable',
                icon: BarChartIcon,
                subItems: [
                    {
                        name: 'AP Aging',
                        path: namedPath(
                            'reports.accounts-payable.aging',
                        ),
                        permission: 'reports.payables',
                        features: ['accounts_payable.module'],
                    },
                    {
                        name: 'Supplier Statement',
                        path: namedPath(
                            'reports.accounts-payable.supplier-statement',
                        ),
                        permission: 'reports.payables',
                        features: ['accounts_payable.module'],
                    },
                ],
            },
            {
                name: 'Financial Control',
                icon: BarChartIcon,
                subItems: [
                    {
                        name: 'Control Dashboard',
                        path: namedPath('financial-control.index'),
                        permission: 'financial_control.view',
                        features: ['financial_accounting.module'],
                    },
                    {
                        name: 'Trial Balance',
                        path: namedPath('reports.financial-statements.trial-balance'),
                        permission: 'financial_statements.view',
                        features: ['financial_accounting.module'],
                    },
                    {
                        name: 'Profit and Loss',
                        path: namedPath('reports.financial-statements.profit-and-loss'),
                        permission: 'financial_statements.view',
                        features: ['financial_accounting.module'],
                    },
                    {
                        name: 'Balance Sheet',
                        path: namedPath('reports.financial-statements.balance-sheet'),
                        permission: 'financial_statements.view',
                        features: ['financial_accounting.module'],
                    },
                    {
                        name: 'Cash Flow',
                        path: namedPath('reports.financial-statements.cash-flow'),
                        permission: 'financial_statements.view',
                        features: ['financial_accounting.module'],
                    },
                    {
                        name: 'Reconciliations',
                        path: namedPath('financial-control.reconciliations'),
                        permission: 'financial_control.view',
                        features: ['financial_accounting.module'],
                    },
                ],
            },
            {
                name: 'Management',
                icon: BarChartIcon,
                subItems: [
                    {
                        name: 'Executive Dashboard',
                        path: namedPath('management.index'),
                        permission: 'management_dashboard.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Budgets',
                        path: namedPath('management.budgets.index'),
                        permission: 'management_budgets.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Branch Profitability',
                        path: namedPath('management.reports.branch-profitability'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Budget vs Actual',
                        path: namedPath('management.reports.budget-vs-actual'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Product Profitability',
                        path: namedPath('management.reports.product-profitability'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Customer Profitability',
                        path: namedPath('management.reports.customer-profitability'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Supplier Spend',
                        path: namedPath('management.reports.supplier-spend'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Gross Margin',
                        path: namedPath('management.reports.gross-margin'),
                        permission: 'management_reports.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Scheduled Reports',
                        path: namedPath('management.schedules.index'),
                        permission: 'management_report_schedules.view',
                        features: ['management_reporting.module'],
                    },
                    {
                        name: 'Production Readiness',
                        path: namedPath('management.production-readiness'),
                        permission: 'management_readiness.view',
                        features: ['management_reporting.module'],
                    },
                ],
            },
            {
                name: 'Accounts Receivable',
                icon: BarChartIcon,
                subItems: [
                    {
                        name: 'AR Aging',
                        path: namedPath(
                            'reports.accounts-receivable.aging',
                        ),
                        permission: 'reports.receivables',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Customer Statement',
                        path: namedPath(
                            'reports.accounts-receivable.customer-statement',
                        ),
                        permission: 'reports.receivables',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Open Invoices',
                        path: namedPath(
                            'reports.accounts-receivable.open-invoices',
                        ),
                        permission: 'reports.receivables',
                        features: ['accounts_receivable.module'],
                    },
                    {
                        name: 'Overdue Invoices',
                        path: namedPath(
                            'reports.accounts-receivable.overdue-invoices',
                        ),
                        permission: 'reports.receivables',
                        features: ['accounts_receivable.module'],
                    },
                ],
            },
        ],
    },
];

const currentPath: ComputedRef<string> = computed((): string => {
    const [path] = page.url.split('?');

    return path || '/';
});

const shouldShowText: ComputedRef<boolean> = computed(
    (): boolean =>
        isExpanded.value
        || isHovered.value
        || isMobileOpen.value,
);

const visibleMenuGroups: ComputedRef<MenuGroup[]> = computed(
    (): MenuGroup[] => menuGroups.reduce<MenuGroup[]>(
        (
            groups: MenuGroup[],
            group: MenuGroup,
        ): MenuGroup[] => {
            const items = group.items.reduce<MenuItem[]>(
                (
                    visibleItems: MenuItem[],
                    item: MenuItem,
                ): MenuItem[] => {
                    if (item.subItems !== undefined) {
                        const subItems = item.subItems.filter(
                            (subItem: SubmenuItem): boolean => {
                                const hasPermission =
                                    subItem.permission === undefined
                                    || can(subItem.permission);

                                const hasFeatures =
                                    subItem.features === undefined
                                    || canUseAll(subItem.features);

                                return hasPermission && hasFeatures;
                            },
                        );

                        if (subItems.length === 0) {
                            return visibleItems;
                        }

                        visibleItems.push({
                            ...item,
                            subItems,
                        });

                        return visibleItems;
                    }

                    if (
                        item.permission !== undefined
                        && !can(item.permission)
                    ) {
                        return visibleItems;
                    }

                    if (
                        item.features !== undefined
                        && !canUseAll(item.features)
                    ) {
                        return visibleItems;
                    }

                    visibleItems.push(item);

                    return visibleItems;
                },
                [],
            );

            if (items.length > 0) {
                groups.push({
                    ...group,
                    items,
                });
            }

            return groups;
        },
        [],
    ),
);

const isActive = (path: string): boolean => {
    if (path === '/') {
        return currentPath.value === '/';
    }

    return currentPath.value === path
        || currentPath.value.startsWith(`${path}/`);
};

const submenuKey = (
    groupIndex: number,
    itemIndex: number,
): string => `${groupIndex}-${itemIndex}`;

const isSubmenuOpen = (
    groupIndex: number,
    itemIndex: number,
    item: MenuItem,
): boolean => {
    const key = submenuKey(groupIndex, itemIndex);

    const containsActiveItem = item.subItems?.some(
        (subItem: SubmenuItem): boolean =>
            isActive(subItem.path),
    ) ?? false;

    return openSubmenu.value === key || containsActiveItem;
};

const handleSubmenuToggle = (
    groupIndex: number,
    itemIndex: number,
): void => {
    toggleSubmenu(submenuKey(groupIndex, itemIndex));
};

const handleNavigation = (path: string): void => {
    setActiveItem(path);
    closeMobileSidebar();
};

const handleMouseEnter = (): void => {
    if (!isExpanded.value) {
        setIsHovered(true);
    }
};

const handleMouseLeave = (): void => {
    setIsHovered(false);
};

const startTransition = (element: Element): void => {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    element.style.height = 'auto';

    const height = element.scrollHeight;

    element.style.height = '0';
    element.offsetHeight;
    element.style.height = `${height}px`;
};

const endTransition = (element: Element): void => {
    if (element instanceof HTMLElement) {
        element.style.height = '';
    }
};
</script>

<template>
    <aside
        :class="[
            'fixed top-0 left-0 z-99999 mt-16 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:mt-0',
            {
                'lg:w-[290px]':
                    isExpanded || isMobileOpen || isHovered,
                'lg:w-[90px]':
                    !isExpanded && !isHovered,
                'w-[290px] translate-x-0':
                    isMobileOpen,
                '-translate-x-full':
                    !isMobileOpen,
                'lg:translate-x-0': true,
            },
        ]"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div
            :class="[
                'flex py-8',
                !isExpanded && !isHovered
                    ? 'lg:justify-center'
                    : 'justify-start',
            ]"
        >
            <Link
                href="/"
                aria-label="Go to dashboard"
                @click="handleNavigation('/')"
            >
                <img
                    v-if="shouldShowText"
                    class="dark:hidden"
                    src="/images/logo/logo.svg"
                    alt="ERP"
                    width="150"
                    height="40"
                >

                <img
                    v-if="shouldShowText"
                    class="hidden dark:block"
                    src="/images/logo/logo-dark.svg"
                    alt="ERP"
                    width="150"
                    height="40"
                >

                <img
                    v-if="!shouldShowText"
                    src="/images/logo/logo-icon.svg"
                    alt="ERP"
                    width="32"
                    height="32"
                >
            </Link>
        </div>

        <div
            class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear"
        >
            <nav class="mb-6">
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(
                            menuGroup,
                            groupIndex
                        ) in visibleMenuGroups"
                        :key="menuGroup.title"
                    >
                        <h2
                            :class="[
                                'mb-4 flex text-xs leading-5 text-gray-400 uppercase',
                                !isExpanded && !isHovered
                                    ? 'lg:justify-center'
                                    : 'justify-start',
                            ]"
                        >
                            <template v-if="shouldShowText">
                                {{ menuGroup.title }}
                            </template>

                            <HorizontalDots v-else />
                        </h2>

                        <ul class="flex flex-col gap-4">
                            <li
                                v-for="(
                                    item,
                                    itemIndex
                                ) in menuGroup.items"
                                :key="item.name"
                            >
                                <button
                                    v-if="item.subItems"
                                    type="button"
                                    :class="[
                                        'menu-item group w-full',
                                        isSubmenuOpen(
                                            groupIndex,
                                            itemIndex,
                                            item,
                                        )
                                            ? 'menu-item-active'
                                            : 'menu-item-inactive',
                                        !isExpanded && !isHovered
                                            ? 'lg:justify-center'
                                            : 'lg:justify-start',
                                    ]"
                                    :aria-expanded="isSubmenuOpen(
                                        groupIndex,
                                        itemIndex,
                                        item,
                                    )"
                                    @click="handleSubmenuToggle(
                                        groupIndex,
                                        itemIndex,
                                    )"
                                >
                                    <span
                                        :class="isSubmenuOpen(
                                            groupIndex,
                                            itemIndex,
                                            item,
                                        )
                                            ? 'menu-item-icon-active'
                                            : 'menu-item-icon-inactive'"
                                    >
                                        <component :is="item.icon" />
                                    </span>

                                    <span
                                        v-if="shouldShowText"
                                        class="menu-item-text"
                                    >
                                        {{ item.name }}
                                    </span>

                                    <ChevronDownIcon
                                        v-if="shouldShowText"
                                        :class="[
                                            'ml-auto h-5 w-5 transition-transform duration-200',
                                            {
                                                'rotate-180 text-brand-500':
                                                    isSubmenuOpen(
                                                        groupIndex,
                                                        itemIndex,
                                                        item,
                                                    ),
                                            },
                                        ]"
                                    />
                                </button>

                                <Link
                                    v-else-if="item.path"
                                    :href="item.path"
                                    :class="[
                                        'menu-item group',
                                        isActive(item.path)
                                            ? 'menu-item-active'
                                            : 'menu-item-inactive',
                                        !isExpanded && !isHovered
                                            ? 'lg:justify-center'
                                            : 'lg:justify-start',
                                    ]"
                                    @click="handleNavigation(item.path)"
                                >
                                    <span
                                        :class="isActive(item.path)
                                            ? 'menu-item-icon-active'
                                            : 'menu-item-icon-inactive'"
                                    >
                                        <component :is="item.icon" />
                                    </span>

                                    <span
                                        v-if="shouldShowText"
                                        class="menu-item-text"
                                    >
                                        {{ item.name }}
                                    </span>
                                </Link>

                                <Transition
                                    @enter="startTransition"
                                    @after-enter="endTransition"
                                    @before-leave="startTransition"
                                    @after-leave="endTransition"
                                >
                                    <div
                                        v-show="
                                            item.subItems
                                                && isSubmenuOpen(
                                                    groupIndex,
                                                    itemIndex,
                                                    item,
                                                )
                                                && shouldShowText
                                        "
                                    >
                                        <ul class="mt-2 ml-9 space-y-1">
                                            <li
                                                v-for="subItem in (
                                                    item.subItems ?? []
                                                )"
                                                :key="subItem.path"
                                            >
                                                <Link
                                                    :href="subItem.path"
                                                    :class="[
                                                        'menu-dropdown-item',
                                                        isActive(subItem.path)
                                                            ? 'menu-dropdown-item-active'
                                                            : 'menu-dropdown-item-inactive',
                                                    ]"
                                                    @click="handleNavigation(
                                                        subItem.path,
                                                    )"
                                                >
                                                    {{ subItem.name }}
                                                </Link>
                                            </li>
                                        </ul>
                                    </div>
                                </Transition>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </aside>
</template>
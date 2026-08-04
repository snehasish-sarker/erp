<script setup lang="ts">
import { computed } from 'vue';

import PrintLayout from './PrintLayout.vue';

type AgingBucketKey =
    | 'current'
    | 'days_1_30'
    | 'days_31_60'
    | 'days_61_90'
    | 'days_91_120'
    | 'days_over_120';

type BalanceSide =
    | 'payable'
    | 'credit';

interface PrintTenant {
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    currency_code: string;
    timezone: string;
}

interface PrintGeneratedBy {
    id: number;
    name: string;
    email: string;
}

interface SupplierSummary {
    id: number;
    code: string;
    name: string;
    status: string;
    email: string | null;
    phone: string | null;
    payment_terms_days: number;
}

interface AgingBucketOption {
    value: AgingBucketKey;
    label: string;
}

interface AgingFilters {
    as_of_date: string;
    branch_id: number | null;
    supplier_id: number | null;
    currency_code: string | null;
    search: string;
    sort: string;
    direction: string;
    per_page: number;
}

interface AgingSummary {
    total_payable: string;
    unapplied_credit: string;
    net_outstanding: string;
    current: string;
    days_1_30: string;
    days_31_60: string;
    days_61_90: string;
    days_91_120: string;
    days_over_120: string;
}

interface CurrencyBreakdown {
    currency_code: string;
    total_payable: string;
    unapplied_credit: string;
    net_outstanding: string;
    base_total_payable: string;
    base_unapplied_credit: string;
    base_net_outstanding: string;
}

interface SupplierAgingItem {
    id: number;
    ledger_entry_id: number;
    branch_id: number;
    branch_code: string;
    branch_name: string;
    item_type: string;
    item_type_label: string;
    entry_type: string;
    entry_type_label: string;
    balance_side: BalanceSide;
    source_type: string;
    source_id: number;
    document_number: string | null;
    document_date: string;
    posting_date: string;
    due_date: string | null;
    currency_code: string;
    exchange_rate: string;
    original_amount: string;
    historical_allocated_amount: string;
    outstanding_amount: string;
    base_original_amount: string;
    historical_base_allocated_amount: string;
    base_outstanding_amount: string;
    days_overdue: number | null;
    bucket_key: AgingBucketKey | null;
    bucket_label: string;
}

interface SupplierAgingReport {
    supplier: SupplierSummary;
    filters: AgingFilters;
    base_currency_code: string;
    buckets: AgingBucketOption[];
    summary: AgingSummary;
    currencies: CurrencyBreakdown[];
    items: SupplierAgingItem[];
}

interface Props {
    title: string;
    tenant: PrintTenant;
    generatedBy: PrintGeneratedBy;
    generatedAt: string;
    autoprint?: boolean;
    report: SupplierAgingReport;
}

const props = withDefaults(
    defineProps<Props>(),
    {
        autoprint: false,
    },
);

const bucketKeys: AgingBucketKey[] = [
    'current',
    'days_1_30',
    'days_31_60',
    'days_61_90',
    'days_91_120',
    'days_over_120',
];

const bucketLabels = computed(
    (): Record<AgingBucketKey, string> => {
        const labels: Record<
            AgingBucketKey,
            string
        > = {
            current: 'Current',
            days_1_30: '1–30 Days',
            days_31_60: '31–60 Days',
            days_61_90: '61–90 Days',
            days_91_120: '91–120 Days',
            days_over_120: 'Over 120 Days',
        };

        for (const bucket of props.report.buckets) {
            labels[bucket.value] = bucket.label;
        }

        return labels;
    },
);

const decimalValue = (
    value: string | number | null,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const formatAmount = (
    value: string | number | null,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(
        decimalValue(value),
    );
};

const formatRate = (
    value: string | number | null,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 8,
            maximumFractionDigits: 8,
        },
    ).format(
        decimalValue(value),
    );
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
        return value;
    }

    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);

    if (
        !Number.isInteger(year)
        || !Number.isInteger(month)
        || !Number.isInteger(day)
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        },
    ).format(
        new Date(
            Date.UTC(
                year,
                month - 1,
                day,
            ),
        ),
    );
};

const overdueLabel = (
    daysOverdue: number | null,
): string | null => {
    if (daysOverdue === null) {
        return null;
    }

    if (daysOverdue <= 0) {
        return 'Not overdue';
    }

    return `${daysOverdue} day(s) overdue`;
};

const itemDocumentLabel = (
    item: SupplierAgingItem,
): string => {
    return item.document_number
        ?? `Open Item #${item.id}`;
};
</script>

<template>
    <PrintLayout
        :title="title"
        :tenant="tenant"
        :generated-by="generatedBy"
        :generated-at="generatedAt"
        :autoprint="autoprint"
    >
        <h2 class="ap-print-report-title">
            Supplier Aging —
            {{ report.supplier.name }}
        </h2>

        <p class="ap-print-report-subtitle">
            {{ report.supplier.code }}

            · As of
            {{ formatDate(report.filters.as_of_date) }}

            · Payment terms
            {{ report.supplier.payment_terms_days }}
            day(s)

            <template
                v-if="
                    report.filters.currency_code
                    !== null
                "
            >
                · Document currency
                {{ report.filters.currency_code }}
            </template>

            <template
                v-if="
                    report.filters.branch_id
                    !== null
                "
            >
                · Branch ID
                {{ report.filters.branch_id }}
            </template>

            <template
                v-if="
                    report.filters.search.trim()
                    !== ''
                "
            >
                · Search:
                {{ report.filters.search }}
            </template>
        </p>

        <section class="ap-print-summary-grid">
            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Gross Payables
                </div>

                <div class="ap-print-summary-value">
                    {{
                        formatAmount(
                            report.summary
                                .total_payable,
                        )
                    }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Unapplied Credits
                </div>

                <div
                    class="
                        ap-print-summary-value
                        ap-print-credit
                    "
                >
                    {{
                        formatAmount(
                            report.summary
                                .unapplied_credit,
                        )
                    }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Net Outstanding
                </div>

                <div
                    class="
                        ap-print-summary-value
                        ap-print-payable
                    "
                >
                    {{
                        formatAmount(
                            report.summary
                                .net_outstanding,
                        )
                    }}
                </div>
            </div>
        </section>

        <section
            class="
                ap-print-summary-grid
                ap-print-summary-grid-six
            "
        >
            <div
                v-for="bucket in bucketKeys"
                :key="bucket"
                class="ap-print-summary-card"
            >
                <div class="ap-print-summary-label">
                    {{ bucketLabels[bucket] }}
                </div>

                <div class="ap-print-summary-value">
                    {{
                        formatAmount(
                            report.summary[bucket],
                        )
                    }}
                </div>
            </div>
        </section>

        <template
            v-if="
                report.currencies.length > 0
            "
        >
            <h3 class="ap-print-section-title">
                Currency Breakdown
            </h3>

            <div class="ap-print-table-wrapper">
                <table class="ap-print-table">
                    <thead>
                        <tr>
                            <th>Currency</th>

                            <th class="ap-print-number">
                                Payable
                            </th>

                            <th class="ap-print-number">
                                Credits
                            </th>

                            <th class="ap-print-number">
                                Net
                            </th>

                            <th class="ap-print-number">
                                Base Payable
                            </th>

                            <th class="ap-print-number">
                                Base Credits
                            </th>

                            <th class="ap-print-number">
                                Base Net
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="
                                currency
                                in report.currencies
                            "
                            :key="
                                currency.currency_code
                            "
                        >
                            <td>
                                <strong>
                                    {{
                                        currency
                                            .currency_code
                                    }}
                                </strong>
                            </td>

                            <td class="ap-print-number">
                                {{
                                    formatAmount(
                                        currency
                                            .total_payable,
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    ap-print-number
                                    ap-print-credit
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .unapplied_credit,
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    ap-print-number
                                    ap-print-payable
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .net_outstanding,
                                    )
                                }}
                            </td>

                            <td class="ap-print-number">
                                {{
                                    formatAmount(
                                        currency
                                            .base_total_payable,
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    ap-print-number
                                    ap-print-credit
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .base_unapplied_credit,
                                    )
                                }}
                            </td>

                            <td
                                class="
                                    ap-print-number
                                    ap-print-payable
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .base_net_outstanding,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <h3 class="ap-print-section-title">
            Outstanding Open Items
        </h3>

        <div class="ap-print-table-wrapper">
            <table class="ap-print-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Branch / Type</th>
                        <th>Dates</th>
                        <th>Currency / Rate</th>

                        <th class="ap-print-number">
                            Original
                        </th>

                        <th class="ap-print-number">
                            Allocated
                        </th>

                        <th class="ap-print-number">
                            Outstanding
                        </th>

                        <th class="ap-print-number">
                            Base Outstanding
                        </th>

                        <th>Aging</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="item in report.items"
                        :key="item.id"
                    >
                        <td>
                            <strong>
                                {{
                                    itemDocumentLabel(
                                        item,
                                    )
                                }}
                            </strong>

                            <div class="ap-print-muted">
                                {{
                                    item.entry_type_label
                                }}
                            </div>
                        </td>

                        <td>
                            {{ item.branch_code }}
                            —
                            {{ item.branch_name }}

                            <div class="ap-print-muted">
                                {{
                                    item.item_type_label
                                }}
                            </div>
                        </td>

                        <td>
                            <div>
                                Document:
                                {{
                                    formatDate(
                                        item.document_date,
                                    )
                                }}
                            </div>

                            <div>
                                Posting:
                                {{
                                    formatDate(
                                        item.posting_date,
                                    )
                                }}
                            </div>

                            <div>
                                Due:
                                {{
                                    formatDate(
                                        item.due_date,
                                    )
                                }}
                            </div>
                        </td>

                        <td>
                            {{ item.currency_code }}

                            <div class="ap-print-muted">
                                {{
                                    formatRate(
                                        item.exchange_rate,
                                    )
                                }}
                            </div>
                        </td>

                        <td class="ap-print-number">
                            {{
                                formatAmount(
                                    item.original_amount,
                                )
                            }}
                        </td>

                        <td class="ap-print-number">
                            {{
                                formatAmount(
                                    item
                                        .historical_allocated_amount,
                                )
                            }}
                        </td>

                        <td
                            class="ap-print-number"
                            :class="{
                                'ap-print-credit':
                                    item.balance_side
                                    === 'credit',

                                'ap-print-payable':
                                    item.balance_side
                                    === 'payable',
                            }"
                        >
                            {{
                                formatAmount(
                                    item
                                        .outstanding_amount,
                                )
                            }}
                        </td>

                        <td class="ap-print-number">
                            {{
                                formatAmount(
                                    item
                                        .base_outstanding_amount,
                                )
                            }}
                        </td>

                        <td>
                            {{ item.bucket_label }}

                            <div
                                v-if="
                                    overdueLabel(
                                        item.days_overdue,
                                    ) !== null
                                "
                                class="ap-print-muted"
                            >
                                {{
                                    overdueLabel(
                                        item.days_overdue,
                                    )
                                }}
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-if="
                            report.items.length === 0
                        "
                    >
                        <td
                            colspan="9"
                            class="ap-print-empty"
                        >
                            No outstanding Supplier
                            open items matched the
                            selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>
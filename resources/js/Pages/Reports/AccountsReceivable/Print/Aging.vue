<script setup lang="ts">
import type {
    AccountsReceivableAgingBucketKey,
    AccountsReceivableAgingPrintProps,
} from '@/Types/accounts-receivable-report';

import PrintLayout from './PrintLayout.vue';

const props = defineProps<AccountsReceivableAgingPrintProps>();

const bucketKeys: AccountsReceivableAgingBucketKey[] = [
    'current',
    'days_1_30',
    'days_31_60',
    'days_61_90',
    'days_91_120',
    'days_over_120',
];

const decimalValue = (
    value: string | number | null | undefined,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const formatAmount = (
    value: string | number | null | undefined,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(decimalValue(value));
};

const formatDate = (value: string): string => {
    const parts = value.split('-');

    if (parts.length !== 3) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    ).format(
        new Date(
            Date.UTC(
                Number(parts[0]),
                Number(parts[1]) - 1,
                Number(parts[2]),
            ),
        ),
    );
};

const bucketLabel = (
    key: AccountsReceivableAgingBucketKey,
): string => {
    return props.report.buckets.find(
        (bucket) => bucket.value === key,
    )?.label ?? key;
};
</script>

<template>
    <PrintLayout
        title="Accounts Receivable Aging"
        :subtitle="`Historical open-item balances as of ${formatDate(report.filters.as_of_date)}.`"
        :company="company"
        :base-currency-code="report.base_currency_code"
    >
        <div class="ar-print-filter-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    As-of Date
                </p>

                <p class="ar-print-card-value">
                    {{ formatDate(report.filters.as_of_date) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Branch
                </p>

                <p class="ar-print-card-value">
                    {{ report.filters.branch_id ?? 'All permitted branches' }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Customer
                </p>

                <p class="ar-print-card-value">
                    {{ report.filters.customer_id ?? 'All customers' }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Transaction Currency
                </p>

                <p class="ar-print-card-value">
                    {{ report.filters.currency_code ?? 'All currencies' }}
                </p>
            </div>
        </div>

        <div class="ar-print-kpi-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Gross Receivable
                </p>

                <p class="ar-print-card-value">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.total_receivable) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Unapplied Credit
                </p>

                <p class="ar-print-card-value">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.unapplied_credit) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Net Receivable
                </p>

                <p class="ar-print-card-value">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.net_receivable) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Overdue Receivable
                </p>

                <p class="ar-print-card-value">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.overdue_receivable) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">
                    Customers / Invoices
                </p>

                <p class="ar-print-card-value">
                    {{ report.dashboard.customer_count }} /
                    {{ report.dashboard.open_invoice_count }}
                </p>
            </div>
        </div>

        <div
            v-if="Math.abs(decimalValue(report.totals.difference ?? '0')) > 0.000001"
            class="ar-print-note"
        >
            Customer-ledger reconciliation difference:
            {{ report.base_currency_code }}
            {{ formatAmount(report.totals.difference ?? '0') }}.
        </div>

        <div class="ar-print-table-wrap">
            <table class="ar-print-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="ar-print-right">Gross AR</th>
                        <th class="ar-print-right">Credits</th>
                        <th class="ar-print-right">Net AR</th>

                        <th
                            v-for="bucket in bucketKeys"
                            :key="bucket"
                            class="ar-print-right"
                        >
                            {{ bucketLabel(bucket) }}
                        </th>

                        <th class="ar-print-right">Ledger</th>
                        <th class="ar-print-right">Difference</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="row in report.customers"
                        :key="row.customer.id"
                    >
                        <td>
                            <strong>{{ row.customer.name }}</strong>

                            <div class="ar-print-muted">
                                {{ row.customer.code }} ·
                                {{ row.customer.customer_type }} ·
                                {{ row.open_invoice_count }} invoice(s)
                            </div>
                        </td>

                        <td class="ar-print-right">
                            {{ formatAmount(row.total_receivable) }}
                        </td>

                        <td class="ar-print-right">
                            {{ formatAmount(row.unapplied_credit) }}
                        </td>

                        <td class="ar-print-right">
                            <strong>{{ formatAmount(row.net_outstanding) }}</strong>
                        </td>

                        <td
                            v-for="bucket in bucketKeys"
                            :key="bucket"
                            class="ar-print-right"
                        >
                            {{ formatAmount(row.buckets[bucket]) }}
                        </td>

                        <td class="ar-print-right">
                            {{ formatAmount(row.ledger_balance) }}
                        </td>

                        <td
                            :class="[
                                'ar-print-right',
                                Math.abs(decimalValue(row.difference)) > 0.000001
                                    ? 'ar-print-danger'
                                    : 'ar-print-success',
                            ]"
                        >
                            {{ formatAmount(row.difference) }}
                        </td>
                    </tr>

                    <tr v-if="report.customers.length === 0">
                        <td
                            colspan="13"
                            class="ar-print-center ar-print-muted"
                        >
                            No Accounts Receivable balances match the selected filters.
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr>
                        <td>Report Total</td>
                        <td class="ar-print-right">
                            {{ formatAmount(report.totals.total_receivable) }}
                        </td>
                        <td class="ar-print-right">
                            {{ formatAmount(report.totals.unapplied_credit) }}
                        </td>
                        <td class="ar-print-right">
                            {{ formatAmount(report.totals.net_outstanding) }}
                        </td>

                        <td
                            v-for="bucket in bucketKeys"
                            :key="bucket"
                            class="ar-print-right"
                        >
                            {{ formatAmount(report.totals[bucket]) }}
                        </td>

                        <td class="ar-print-right">
                            {{ formatAmount(report.totals.ledger_balance ?? '0') }}
                        </td>

                        <td class="ar-print-right">
                            {{ formatAmount(report.totals.difference ?? '0') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </PrintLayout>
</template>

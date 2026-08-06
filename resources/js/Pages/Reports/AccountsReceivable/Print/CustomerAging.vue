<script setup lang="ts">
import type {
    CustomerAgingPrintProps,
} from '@/Types/accounts-receivable-report';

import PrintLayout from './PrintLayout.vue';

const props = defineProps<CustomerAgingPrintProps>();

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

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

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
</script>

<template>
    <PrintLayout
        :title="`${report.customer.name} — Aging Detail`"
        :subtitle="`${report.customer.code} · Open-item balances as of ${formatDate(report.filters.as_of_date)}.`"
        :company="company"
        :base-currency-code="report.base_currency_code"
    >
        <div class="ar-print-filter-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">Customer</p>
                <p class="ar-print-card-value">{{ report.customer.name }}</p>
                <div class="ar-print-muted">{{ report.customer.code }}</div>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">As-of Date</p>
                <p class="ar-print-card-value">{{ formatDate(report.filters.as_of_date) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Payment Terms</p>
                <p class="ar-print-card-value">{{ report.customer.payment_terms_days ?? 0 }} days</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Credit Limit</p>
                <p class="ar-print-card-value">
                    {{ decimalValue(report.customer.credit_limit ?? '0') > 0
                        ? `${report.base_currency_code} ${formatAmount(report.customer.credit_limit ?? '0')}`
                        : 'Unlimited' }}
                </p>
            </div>
        </div>

        <div class="ar-print-kpi-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">Gross Receivable</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.total_receivable) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Unapplied Credit</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.unapplied_credit) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Net Outstanding</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.net_outstanding) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">91–120 Days</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.days_91_120) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Over 120 Days</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.days_over_120) }}</p>
            </div>
        </div>

        <div class="ar-print-table-wrap">
            <table class="ar-print-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Document Date</th>
                        <th>Due Date</th>
                        <th class="ar-print-right">Days</th>
                        <th>Bucket</th>
                        <th>Currency</th>
                        <th class="ar-print-right">Original</th>
                        <th class="ar-print-right">Allocated</th>
                        <th class="ar-print-right">Outstanding</th>
                        <th class="ar-print-right">Base Outstanding</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="item in report.items"
                        :key="item.id"
                    >
                        <td>
                            <strong>{{ item.document_number ?? item.reference }}</strong>
                            <div class="ar-print-muted">{{ item.journal_reference }}</div>
                        </td>
                        <td>{{ item.branch.code }}</td>
                        <td>{{ item.item_type_label }}</td>
                        <td>{{ formatDate(item.document_date) }}</td>
                        <td>{{ formatDate(item.due_date) }}</td>
                        <td class="ar-print-right">{{ item.days_overdue ?? '—' }}</td>
                        <td>{{ item.bucket_label }}</td>
                        <td>{{ item.currency_code }}</td>
                        <td class="ar-print-right">{{ formatAmount(item.original_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(item.historical_allocated_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(item.outstanding_amount) }}</td>
                        <td class="ar-print-right">
                            <strong>{{ formatAmount(item.base_outstanding_amount) }}</strong>
                        </td>
                    </tr>

                    <tr v-if="report.items.length === 0">
                        <td
                            colspan="12"
                            class="ar-print-center ar-print-muted"
                        >
                            No historical open items match the selected filters.
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="11">Customer Net Outstanding (Base)</td>
                        <td class="ar-print-right">{{ formatAmount(report.summary.net_outstanding) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </PrintLayout>
</template>

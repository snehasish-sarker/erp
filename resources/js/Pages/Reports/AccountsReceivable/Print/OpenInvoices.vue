<script setup lang="ts">
import { computed } from 'vue';

import type {
    OpenInvoicePrintProps,
} from '@/Types/accounts-receivable-report';

import PrintLayout from './PrintLayout.vue';

const props = defineProps<OpenInvoicePrintProps>();

const title = computed(
    (): string => props.report.mode === 'overdue'
        ? 'Overdue Accounts Receivable'
        : 'Open Customer Invoices',
);

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
        :title="title"
        :subtitle="`Historical invoice balances reconstructed as of ${formatDate(report.filters.as_of_date)}.`"
        :company="company"
        :base-currency-code="report.base_currency_code"
    >
        <div class="ar-print-filter-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">As-of Date</p>
                <p class="ar-print-card-value">{{ formatDate(report.filters.as_of_date) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Branch</p>
                <p class="ar-print-card-value">{{ report.filters.branch_id ?? 'All permitted branches' }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Customer</p>
                <p class="ar-print-card-value">{{ report.filters.customer_id ?? 'All customers' }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Currency</p>
                <p class="ar-print-card-value">{{ report.filters.currency_code ?? 'All currencies' }}</p>
            </div>
        </div>

        <div class="ar-print-kpi-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">Invoice Count</p>
                <p class="ar-print-card-value">{{ report.summary.invoice_count }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Customer Count</p>
                <p class="ar-print-card-value">{{ report.summary.customer_count }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Original Base Amount</p>
                <p class="ar-print-card-value">{{ report.base_currency_code }} {{ formatAmount(report.summary.base_original_amount) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Base Outstanding</p>
                <p class="ar-print-card-value">{{ report.base_currency_code }} {{ formatAmount(report.summary.base_outstanding_amount) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Overdue Base Amount</p>
                <p class="ar-print-card-value">{{ report.base_currency_code }} {{ formatAmount(report.summary.overdue_base_amount) }}</p>
            </div>
        </div>

        <div class="ar-print-table-wrap">
            <table class="ar-print-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th class="ar-print-right">Days Overdue</th>
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
                        v-for="invoice in report.invoices"
                        :key="invoice.id"
                    >
                        <td>
                            <strong>{{ invoice.document_number ?? invoice.reference }}</strong>
                            <div class="ar-print-muted">{{ invoice.journal_reference }}</div>
                        </td>
                        <td>
                            <strong>{{ invoice.customer.name }}</strong>
                            <div class="ar-print-muted">{{ invoice.customer.code }}</div>
                        </td>
                        <td>{{ invoice.branch.code }}</td>
                        <td>{{ formatDate(invoice.document_date) }}</td>
                        <td>{{ formatDate(invoice.due_date) }}</td>
                        <td class="ar-print-right">{{ invoice.days_overdue ?? '—' }}</td>
                        <td>{{ invoice.bucket_label }}</td>
                        <td>{{ invoice.currency_code }}</td>
                        <td class="ar-print-right">{{ formatAmount(invoice.original_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(invoice.historical_allocated_amount) }}</td>
                        <td class="ar-print-right"><strong>{{ formatAmount(invoice.outstanding_amount) }}</strong></td>
                        <td class="ar-print-right"><strong>{{ formatAmount(invoice.base_outstanding_amount) }}</strong></td>
                    </tr>

                    <tr v-if="report.invoices.length === 0">
                        <td
                            colspan="12"
                            class="ar-print-center ar-print-muted"
                        >
                            No invoices match the selected filters.
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="8">Report Total</td>
                        <td class="ar-print-right">
                            {{ report.filters.currency_code !== null
                                ? formatAmount(report.summary.original_amount)
                                : 'Multiple' }}
                        </td>
                        <td class="ar-print-right">
                            {{ report.filters.currency_code !== null
                                ? formatAmount(report.summary.allocated_amount)
                                : 'Multiple' }}
                        </td>
                        <td class="ar-print-right">
                            {{ report.filters.currency_code !== null
                                ? formatAmount(report.summary.outstanding_amount)
                                : 'Multiple' }}
                        </td>
                        <td class="ar-print-right">{{ formatAmount(report.summary.base_outstanding_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </PrintLayout>
</template>

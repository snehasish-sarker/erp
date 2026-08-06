<script setup lang="ts">
import type {
    CustomerStatementPrintProps,
} from '@/Types/accounts-receivable-report';

import PrintLayout from './PrintLayout.vue';

const props = defineProps<CustomerStatementPrintProps>();

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
        :title="`${report.customer.name} — Customer Statement`"
        :subtitle="`${report.customer.code} · ${formatDate(report.filters.date_from)} to ${formatDate(report.filters.date_to)}.`"
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
                <p class="ar-print-card-label">Statement Period</p>
                <p class="ar-print-card-value">
                    {{ formatDate(report.filters.date_from) }} –
                    {{ formatDate(report.filters.date_to) }}
                </p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Branch</p>
                <p class="ar-print-card-value">{{ report.filters.branch_id ?? 'All permitted branches' }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Currency Filter</p>
                <p class="ar-print-card-value">{{ report.filters.currency_code ?? 'All currencies' }}</p>
            </div>
        </div>

        <div class="ar-print-kpi-grid">
            <div class="ar-print-card">
                <p class="ar-print-card-label">Opening Balance</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.base.opening_balance) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Period Debits</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.base.period_debit) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Period Credits</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.base.period_credit) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Closing Balance</p>
                <p class="ar-print-card-value">{{ formatAmount(report.summary.base.closing_balance) }}</p>
            </div>

            <div class="ar-print-card">
                <p class="ar-print-card-label">Transactions</p>
                <p class="ar-print-card-value">{{ report.entries.length }}</p>
            </div>
        </div>

        <h3
            v-if="report.summary.currencies.length > 0"
            class="ar-print-section-title"
        >
            Currency Summary
        </h3>

        <table
            v-if="report.summary.currencies.length > 0"
            class="ar-print-table"
        >
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="ar-print-right">Opening</th>
                    <th class="ar-print-right">Debit</th>
                    <th class="ar-print-right">Credit</th>
                    <th class="ar-print-right">Closing</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="summary in report.summary.currencies"
                    :key="summary.currency_code"
                >
                    <td>{{ summary.currency_code }}</td>
                    <td class="ar-print-right">{{ formatAmount(summary.opening_balance) }}</td>
                    <td class="ar-print-right">{{ formatAmount(summary.period_debit) }}</td>
                    <td class="ar-print-right">{{ formatAmount(summary.period_credit) }}</td>
                    <td class="ar-print-right"><strong>{{ formatAmount(summary.closing_balance) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <h3 class="ar-print-section-title">
            Ledger Entries
        </h3>

        <div class="ar-print-table-wrap">
            <table class="ar-print-table">
                <thead>
                    <tr>
                        <th>Posting Date</th>
                        <th>Document Date</th>
                        <th>Document / Reference</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Currency</th>
                        <th class="ar-print-right">Debit</th>
                        <th class="ar-print-right">Credit</th>
                        <th class="ar-print-right">Currency Balance</th>
                        <th class="ar-print-right">Base Debit</th>
                        <th class="ar-print-right">Base Credit</th>
                        <th class="ar-print-right">Base Balance</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="entry in report.entries"
                        :key="entry.id"
                    >
                        <td>{{ formatDate(entry.posting_date) }}</td>
                        <td>{{ formatDate(entry.document_date) }}</td>
                        <td>
                            <strong>{{ entry.source_document_number ?? entry.reference }}</strong>
                            <div class="ar-print-muted">{{ entry.journal_reference }}</div>
                        </td>
                        <td>{{ entry.entry_type_label }}</td>
                        <td>{{ entry.branch.code ?? '—' }}</td>
                        <td>{{ entry.currency_code }}</td>
                        <td class="ar-print-right">{{ formatAmount(entry.debit_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(entry.credit_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(entry.currency_running_balance) }}</td>
                        <td class="ar-print-right">{{ formatAmount(entry.base_debit_amount) }}</td>
                        <td class="ar-print-right">{{ formatAmount(entry.base_credit_amount) }}</td>
                        <td class="ar-print-right"><strong>{{ formatAmount(entry.base_running_balance) }}</strong></td>
                    </tr>

                    <tr v-if="report.entries.length === 0">
                        <td
                            colspan="12"
                            class="ar-print-center ar-print-muted"
                        >
                            No customer-ledger entries match the selected statement period.
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="9">Closing Base Balance</td>
                        <td class="ar-print-right">{{ formatAmount(report.summary.base.period_debit) }}</td>
                        <td class="ar-print-right">{{ formatAmount(report.summary.base.period_credit) }}</td>
                        <td class="ar-print-right">{{ formatAmount(report.summary.base.closing_balance) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </PrintLayout>
</template>

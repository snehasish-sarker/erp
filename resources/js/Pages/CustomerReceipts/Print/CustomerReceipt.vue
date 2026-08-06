<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CustomerReceiptPrintProps,
} from '@/Types/customer-receipt';

const props =
    defineProps<CustomerReceiptPrintProps>();

const title = computed(
    (): string => {
        return props.customerReceipt
            .receipt_number
            ?? `Customer Receipt Draft #${props.customerReceipt.id}`;
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
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(decimalValue(value));
};

const formatRate = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8,
        },
    ).format(decimalValue(value));
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const date = new Date(
        `${value}T00:00:00`,
    );

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        },
    ).format(date);
};

const totalExchangeDifference = computed(
    (): number => {
        return props.customerReceipt
            .allocations
            .reduce(
                (total, allocation): number => {
                    return total
                        + decimalValue(
                            allocation
                                .exchange_difference_amount,
                        );
                },
                0,
            );
    },
);

const printPage = (): void => {
    window.print();
};
</script>

<template>
    <Head :title="`Customer Receipt ${title}`" />

    <div class="print-page">
        <div class="screen-actions">
            <button
                type="button"
                @click="printPage"
            >
                Print / Save PDF
            </button>
        </div>

        <header class="header">
            <div>
                <h1>{{ company.name }}</h1>

                <p v-if="company.address">
                    {{ company.address }}
                </p>

                <p>
                    <span v-if="company.phone">
                        {{ company.phone }}
                    </span>

                    <span
                        v-if="company.phone && company.email"
                    >
                        ·
                    </span>

                    <span v-if="company.email">
                        {{ company.email }}
                    </span>
                </p>
            </div>

            <div class="document-title">
                <h2>CUSTOMER RECEIPT</h2>
                <p>{{ title }}</p>

                <span
                    :class="[
                        'status',
                        customerReceipt.status,
                    ]"
                >
                    {{ customerReceipt.status_label }}
                </span>
            </div>
        </header>

        <section class="meta-grid">
            <div>
                <h3>Received From</h3>

                <strong>
                    {{ customerReceipt.customer.name }}
                </strong>

                <p>
                    Customer Code:
                    {{ customerReceipt.customer.code }}
                </p>
            </div>

            <div>
                <h3>Receipt Information</h3>

                <dl>
                    <dt>Receipt Date</dt>
                    <dd>
                        {{ formatDate(customerReceipt.receipt_date) }}
                    </dd>

                    <dt>Posting Date</dt>
                    <dd>
                        {{ formatDate(customerReceipt.posting_date) }}
                    </dd>

                    <dt>Branch</dt>
                    <dd>
                        {{ customerReceipt.branch.name ?? '—' }}
                    </dd>

                    <dt>Method</dt>
                    <dd>
                        {{ customerReceipt.receipt_method_label }}
                    </dd>

                    <dt>Account</dt>
                    <dd>
                        {{ customerReceipt.receipt_account.code }}
                        —
                        {{ customerReceipt.receipt_account.name }}
                    </dd>

                    <dt>Reference</dt>
                    <dd>
                        {{ customerReceipt.receipt_reference ?? '—' }}
                    </dd>

                    <dt>Currency / Rate</dt>
                    <dd>
                        {{ customerReceipt.currency_code }}
                        /
                        {{ formatRate(customerReceipt.exchange_rate) }}
                    </dd>

                    <template
                        v-if="customerReceipt.cheque_number"
                    >
                        <dt>Cheque</dt>
                        <dd>
                            {{ customerReceipt.cheque_number }}
                            ·
                            {{ formatDate(customerReceipt.cheque_date) }}
                        </dd>
                    </template>
                </dl>
            </div>
        </section>

        <section class="amount-banner">
            <span>Amount Received</span>

            <strong>
                {{ customerReceipt.currency_code }}
                {{ formatAmount(customerReceipt.total_amount) }}
            </strong>
        </section>

        <section class="allocation-section">
            <h3>Sales Invoice Allocations</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sales Invoice</th>
                        <th>Due Date</th>
                        <th class="right">Allocated</th>
                        <th class="right">Receivable Base</th>
                        <th class="right">Receipt Base</th>
                        <th class="right">FX Difference</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-if="customerReceipt.allocations.length === 0"
                    >
                        <td
                            colspan="7"
                            class="empty"
                        >
                            No Sales Invoice allocation was recorded.
                            The receipt remains an unallocated customer advance when posted.
                        </td>
                    </tr>

                    <tr
                        v-for="allocation in customerReceipt.allocations"
                        :key="allocation.id"
                    >
                        <td>{{ allocation.line_number }}</td>

                        <td>
                            {{ allocation.invoice_document_number ?? `Invoice #${allocation.sales_invoice_id}` }}
                        </td>

                        <td>
                            {{ formatDate(allocation.invoice_due_date) }}
                        </td>

                        <td class="right">
                            {{ formatAmount(allocation.amount) }}
                        </td>

                        <td class="right">
                            {{ formatAmount(allocation.receivable_base_amount) }}
                        </td>

                        <td class="right">
                            {{ formatAmount(allocation.receipt_base_amount) }}
                        </td>

                        <td class="right">
                            {{ formatAmount(allocation.exchange_difference_amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="summary-wrap">
            <div class="notes-column">
                <div v-if="customerReceipt.notes">
                    <h3>Notes</h3>
                    <p class="pre-line">
                        {{ customerReceipt.notes }}
                    </p>
                </div>

                <div
                    v-if="customerReceipt.accounting_posting_reference"
                >
                    <h3>Accounting Reference</h3>
                    <p>
                        {{ customerReceipt.accounting_posting_reference }}
                    </p>
                </div>
            </div>

            <dl class="summary">
                <dt>Receipt Amount</dt>
                <dd>
                    {{ formatAmount(customerReceipt.total_amount) }}
                </dd>

                <dt>Allocated to Invoices</dt>
                <dd>
                    {{ formatAmount(customerReceipt.allocated_amount) }}
                </dd>

                <dt>Unallocated Advance</dt>
                <dd>
                    {{ formatAmount(customerReceipt.unallocated_amount) }}
                </dd>

                <dt>Base Receipt Amount</dt>
                <dd>
                    {{ formatAmount(customerReceipt.base_total_amount) }}
                </dd>

                <dt>Realized FX Difference</dt>
                <dd>
                    {{ formatAmount(totalExchangeDifference) }}
                </dd>

                <dt class="total-label">
                    Amount Received
                </dt>

                <dd class="total-value">
                    {{ customerReceipt.currency_code }}
                    {{ formatAmount(customerReceipt.total_amount) }}
                </dd>
            </dl>
        </section>

        <section
            v-if="customerReceipt.status === 'cancelled'"
            class="exception-note"
        >
            <strong>CANCELLED</strong>
            <p>
                {{ customerReceipt.cancellation_reason ?? 'No cancellation reason recorded.' }}
            </p>
        </section>

        <section
            v-if="customerReceipt.status === 'reversed'"
            class="exception-note"
        >
            <strong>REVERSED</strong>
            <p>
                Posting date:
                {{ formatDate(customerReceipt.reversal_posting_date) }}
            </p>
            <p>
                {{ customerReceipt.reversal_reason ?? 'No reversal reason recorded.' }}
            </p>
        </section>

        <footer>
            <div>
                <span>Prepared By</span>
                <strong>
                    {{ customerReceipt.created_by?.name ?? '—' }}
                </strong>
            </div>

            <div>
                <span>Approved By</span>
                <strong>
                    {{ customerReceipt.approved_by?.name ?? '—' }}
                </strong>
            </div>

            <div>
                <span>Received By / Customer</span>
                <strong>________________________</strong>
            </div>
        </footer>
    </div>
</template>

<style scoped>
:global(body) {
    margin: 0;
    background: #f3f4f6;
    color: #111827;
    font-family: Arial, Helvetica, sans-serif;
}

.print-page {
    width: 210mm;
    min-height: 297mm;
    margin: 18px auto;
    padding: 14mm;
    background: white;
    box-sizing: border-box;
}

.screen-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 16px;
}

.screen-actions button {
    border: 0;
    border-radius: 8px;
    background: #465fff;
    color: white;
    padding: 10px 16px;
    cursor: pointer;
}

.header {
    display: flex;
    justify-content: space-between;
    gap: 30px;
    border-bottom: 2px solid #111827;
    padding-bottom: 16px;
}

.header h1,
.header h2,
.header p {
    margin: 0 0 6px;
}

.document-title {
    text-align: right;
}

.status {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e5e7eb;
    font-size: 11px;
    text-transform: uppercase;
}

.status.posted {
    background: #dcfce7;
    color: #166534;
}

.status.reversed,
.status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.status.approved,
.status.submitted {
    background: #dbeafe;
    color: #1d4ed8;
}

.meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 24px 0;
}

.meta-grid h3,
.allocation-section h3,
.notes-column h3 {
    margin: 0 0 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4b5563;
}

.meta-grid p,
.notes-column p {
    margin: 4px 0;
    font-size: 12px;
    line-height: 1.5;
}

.meta-grid dl {
    display: grid;
    grid-template-columns: 108px 1fr;
    gap: 6px 12px;
    margin: 0;
    font-size: 12px;
}

.meta-grid dt {
    color: #6b7280;
}

.meta-grid dd {
    margin: 0;
    font-weight: 600;
}

.amount-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 18px 0 24px;
    border: 1px solid #111827;
    padding: 12px 14px;
}

.amount-banner span {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.amount-banner strong {
    font-size: 20px;
}

.allocation-section {
    margin-top: 18px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5px;
}

th,
td {
    border: 1px solid #d1d5db;
    padding: 7px;
    vertical-align: top;
}

th {
    background: #f3f4f6;
    text-align: left;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.right {
    text-align: right;
}

.empty {
    padding: 18px;
    text-align: center;
    color: #6b7280;
}

.summary-wrap {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 290px;
    gap: 24px;
    margin-top: 24px;
}

.notes-column > div + div {
    margin-top: 18px;
}

.summary {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 16px;
    margin: 0;
    font-size: 12px;
}

.summary dt {
    color: #4b5563;
}

.summary dd {
    margin: 0;
    text-align: right;
    font-weight: 600;
}

.summary .total-label,
.summary .total-value {
    border-top: 1px solid #111827;
    padding-top: 9px;
    color: #111827;
    font-size: 14px;
}

.exception-note {
    margin-top: 22px;
    border: 2px solid #b91c1c;
    padding: 12px;
    text-align: center;
    color: #991b1b;
}

.exception-note p {
    margin: 5px 0 0;
    font-size: 12px;
}

.pre-line {
    white-space: pre-line;
}

footer {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 55px;
}

footer div {
    border-top: 1px solid #111827;
    padding-top: 8px;
    text-align: center;
}

footer span,
footer strong {
    display: block;
    font-size: 12px;
}

footer span {
    margin-bottom: 5px;
    color: #6b7280;
}

@media print {
    @page {
        size: A4;
        margin: 0;
    }

    :global(body) {
        background: white;
    }

    .print-page {
        margin: 0;
        box-shadow: none;
    }

    .screen-actions {
        display: none;
    }
}
</style>

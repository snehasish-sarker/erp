<script setup lang="ts">
import PrintLayout from './PrintLayout.vue';

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

interface StatementFilters {
    supplier_id: number;
    branch_id: number | null;
    currency_code: string | null;
    date_from: string;
    date_to: string;
}

interface BaseSummary {
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

interface CurrencySummary {
    currency_code: string;
    opening_balance: string;
    period_debit: string;
    period_credit: string;
    closing_balance: string;
}

interface StatementBranch {
    id: number;
    code: string | null;
    name: string | null;
}

interface ReversalReference {
    reference: string;
    source_document_number: string | null;
}

interface StatementEntry {
    id: number;
    reference: string;
    journal_reference: string | null;
    entry_type: string;
    entry_type_label: string;
    source_document_number: string | null;
    document_date: string;
    posting_date: string;
    due_date: string | null;
    branch: StatementBranch;
    currency_code: string;
    exchange_rate: string;
    debit_amount: string;
    credit_amount: string;
    transaction_change: string;
    currency_running_balance: string;
    base_debit_amount: string;
    base_credit_amount: string;
    base_change: string;
    base_running_balance: string;
    description: string | null;
    reversal_of: ReversalReference | null;
}

interface SupplierStatementReport {
    supplier: SupplierSummary;
    filters: StatementFilters;
    base_currency_code: string;

    summary: {
        base: BaseSummary;
        currencies: CurrencySummary[];
    };

    entries: StatementEntry[];
}

interface Props {
    title: string;
    tenant: PrintTenant;
    generatedBy: PrintGeneratedBy;
    generatedAt: string;
    autoprint?: boolean;
    report: SupplierStatementReport;
}

withDefaults(
    defineProps<Props>(),
    {
        autoprint: false,
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
            minimumFractionDigits: 2,
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

const documentLabel = (
    entry: StatementEntry,
): string => {
    return entry.source_document_number
        ?? entry.reference;
};

const branchLabel = (
    branch: StatementBranch,
): string => {
    if (branch.code && branch.name) {
        return `${branch.code} — ${branch.name}`;
    }

    if (branch.name) {
        return branch.name;
    }

    if (branch.code) {
        return branch.code;
    }

    return `Branch #${branch.id}`;
};

const amountClass = (
    value: string | number | null,
): string => {
    const amount = decimalValue(value);

    if (amount > 0) {
        return 'ap-print-payable';
    }

    if (amount < 0) {
        return 'ap-print-credit';
    }

    return '';
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
            Supplier Statement —
            {{ report.supplier.name }}
        </h2>

        <p class="ap-print-report-subtitle">
            {{ report.supplier.code }}

            ·
            {{ formatDate(report.filters.date_from) }}

            to

            {{ formatDate(report.filters.date_to) }}

            <template
                v-if="
                    report.filters.currency_code
                    !== null
                "
            >
                · Currency
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
        </p>

        <section class="ap-print-summary-grid">
            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Opening Balance
                </div>

                <div
                    class="ap-print-summary-value"
                    :class="
                        amountClass(
                            report.summary.base
                                .opening_balance,
                        )
                    "
                >
                    {{
                        formatAmount(
                            report.summary.base
                                .opening_balance,
                        )
                    }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Period Debit
                </div>

                <div
                    class="
                        ap-print-summary-value
                        ap-print-credit
                    "
                >
                    {{
                        formatAmount(
                            report.summary.base
                                .period_debit,
                        )
                    }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Period Credit
                </div>

                <div
                    class="
                        ap-print-summary-value
                        ap-print-payable
                    "
                >
                    {{
                        formatAmount(
                            report.summary.base
                                .period_credit,
                        )
                    }}
                </div>
            </div>
        </section>

        <section class="ap-print-summary-grid">
            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Closing Balance
                </div>

                <div
                    class="ap-print-summary-value"
                    :class="
                        amountClass(
                            report.summary.base
                                .closing_balance,
                        )
                    "
                >
                    {{
                        formatAmount(
                            report.summary.base
                                .closing_balance,
                        )
                    }}

                    {{ report.base_currency_code }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Statement Period
                </div>

                <div class="ap-print-summary-value">
                    {{
                        formatDate(
                            report.filters.date_from,
                        )
                    }}

                    —

                    {{
                        formatDate(
                            report.filters.date_to,
                        )
                    }}
                </div>
            </div>

            <div class="ap-print-summary-card">
                <div class="ap-print-summary-label">
                    Statement Entries
                </div>

                <div class="ap-print-summary-value">
                    {{ report.entries.length }}
                </div>
            </div>
        </section>

        <template
            v-if="
                report.summary.currencies.length
                > 0
            "
        >
            <h3 class="ap-print-section-title">
                Currency Summary
            </h3>

            <div class="ap-print-table-wrapper">
                <table class="ap-print-table">
                    <thead>
                        <tr>
                            <th>Currency</th>

                            <th class="ap-print-number">
                                Opening
                            </th>

                            <th class="ap-print-number">
                                Debit
                            </th>

                            <th class="ap-print-number">
                                Credit
                            </th>

                            <th class="ap-print-number">
                                Closing
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="
                                currency
                                in report.summary.currencies
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

                            <td
                                class="ap-print-number"
                                :class="
                                    amountClass(
                                        currency
                                            .opening_balance,
                                    )
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .opening_balance,
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
                                            .period_debit,
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
                                            .period_credit,
                                    )
                                }}
                            </td>

                            <td
                                class="ap-print-number"
                                :class="
                                    amountClass(
                                        currency
                                            .closing_balance,
                                    )
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .closing_balance,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <h3 class="ap-print-section-title">
            Supplier Ledger Entries
        </h3>

        <div class="ap-print-table-wrapper">
            <table class="ap-print-table">
                <thead>
                    <tr>
                        <th>Date / Document</th>
                        <th>Branch / Entry</th>
                        <th>Description</th>
                        <th>Currency / Rate</th>

                        <th class="ap-print-number">
                            Debit
                        </th>

                        <th class="ap-print-number">
                            Credit
                        </th>

                        <th class="ap-print-number">
                            Currency Balance
                        </th>

                        <th class="ap-print-number">
                            Base Debit
                        </th>

                        <th class="ap-print-number">
                            Base Credit
                        </th>

                        <th class="ap-print-number">
                            Base Balance
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="entry in report.entries"
                        :key="entry.id"
                    >
                        <td>
                            <strong>
                                {{
                                    formatDate(
                                        entry.posting_date,
                                    )
                                }}
                            </strong>

                            <div>
                                {{
                                    documentLabel(
                                        entry,
                                    )
                                }}
                            </div>

                            <div class="ap-print-muted">
                                Document date:
                                {{
                                    formatDate(
                                        entry.document_date,
                                    )
                                }}
                            </div>

                            <div
                                v-if="entry.due_date"
                                class="ap-print-muted"
                            >
                                Due:
                                {{
                                    formatDate(
                                        entry.due_date,
                                    )
                                }}
                            </div>

                            <div
                                v-if="
                                    entry.journal_reference
                                "
                                class="ap-print-muted"
                            >
                                Journal:
                                {{
                                    entry.journal_reference
                                }}
                            </div>
                        </td>

                        <td>
                            {{
                                branchLabel(
                                    entry.branch,
                                )
                            }}

                            <div class="ap-print-muted">
                                {{
                                    entry.entry_type_label
                                }}
                            </div>

                            <div
                                v-if="entry.reversal_of"
                                class="ap-print-muted"
                            >
                                Reverses:
                                {{
                                    entry.reversal_of
                                        .source_document_number
                                    ?? entry.reversal_of
                                        .reference
                                }}
                            </div>
                        </td>

                        <td>
                            {{
                                entry.description
                                ?? '—'
                            }}
                        </td>

                        <td>
                            <strong>
                                {{ entry.currency_code }}
                            </strong>

                            <div class="ap-print-muted">
                                Rate:
                                {{
                                    formatRate(
                                        entry.exchange_rate,
                                    )
                                }}
                            </div>
                        </td>

                        <td
                            class="
                                ap-print-number
                                ap-print-credit
                            "
                        >
                            {{
                                formatAmount(
                                    entry.debit_amount,
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
                                    entry.credit_amount,
                                )
                            }}
                        </td>

                        <td
                            class="ap-print-number"
                            :class="
                                amountClass(
                                    entry
                                        .currency_running_balance,
                                )
                            "
                        >
                            {{
                                formatAmount(
                                    entry
                                        .currency_running_balance,
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
                                    entry
                                        .base_debit_amount,
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
                                    entry
                                        .base_credit_amount,
                                )
                            }}
                        </td>

                        <td
                            class="ap-print-number"
                            :class="
                                amountClass(
                                    entry
                                        .base_running_balance,
                                )
                            "
                        >
                            {{
                                formatAmount(
                                    entry
                                        .base_running_balance,
                                )
                            }}
                        </td>
                    </tr>

                    <tr
                        v-if="
                            report.entries.length === 0
                        "
                    >
                        <td
                            colspan="10"
                            class="ap-print-empty"
                        >
                            No Supplier Ledger entries
                            matched the selected statement
                            period and filters.
                        </td>
                    </tr>
                </tbody>

                <tfoot
                    v-if="
                        report.entries.length > 0
                    "
                >
                    <tr>
                        <th colspan="7">
                            Statement Period Totals
                        </th>

                        <th class="ap-print-number">
                            {{
                                formatAmount(
                                    report.summary.base
                                        .period_debit,
                                )
                            }}
                        </th>

                        <th class="ap-print-number">
                            {{
                                formatAmount(
                                    report.summary.base
                                        .period_credit,
                                )
                            }}
                        </th>

                        <th class="ap-print-number">
                            {{
                                formatAmount(
                                    report.summary.base
                                        .closing_balance,
                                )
                            }}
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </PrintLayout>
</template>
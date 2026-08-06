<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    nextTick,
    onMounted,
} from 'vue';

import type {
    ReportCompany,
} from '@/Types/accounts-receivable-report';

interface Props {
    title: string;
    subtitle?: string;
    company: ReportCompany;
    baseCurrencyCode: string;
    autoprint?: boolean;
}

const props = withDefaults(
    defineProps<Props>(),
    {
        subtitle: '',
        autoprint: false,
    },
);

const generatedAt = new Intl.DateTimeFormat(
    'en-GB',
    {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    },
).format(new Date());

const printDocument = (): void => {
    window.print();
};

const closeWindow = (): void => {
    window.close();
};

onMounted(
    async (): Promise<void> => {
        if (!props.autoprint) {
            return;
        }

        await nextTick();

        window.requestAnimationFrame(
            (): void => {
                window.requestAnimationFrame(
                    (): void => {
                        window.print();
                    },
                );
            },
        );
    },
);
</script>

<template>
    <Head :title="title" />

    <div class="ar-print-page">
        <div class="ar-print-toolbar">
            <button
                type="button"
                class="ar-print-button"
                @click="closeWindow"
            >
                Close
            </button>

            <button
                type="button"
                class="ar-print-button ar-print-button-primary"
                @click="printDocument"
            >
                Print / Save PDF
            </button>
        </div>

        <main class="ar-print-document">
            <header class="ar-print-header">
                <div>
                    <h1 class="ar-print-company-name">
                        {{ company.name }}
                    </h1>

                    <div class="ar-print-company-meta">
                        <div>
                            Company code:
                            {{ company.code }}
                        </div>

                        <div v-if="company.address">
                            {{ company.address }}
                        </div>

                        <div
                            v-if="
                                company.email
                                || company.phone
                            "
                        >
                            <span v-if="company.email">
                                {{ company.email }}
                            </span>

                            <span
                                v-if="
                                    company.email
                                    && company.phone
                                "
                            >
                                ·
                            </span>

                            <span v-if="company.phone">
                                {{ company.phone }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="ar-print-generated-meta">
                    <div>
                        <strong>Generated:</strong>
                        {{ generatedAt }}
                    </div>

                    <div>
                        <strong>Base currency:</strong>
                        {{ baseCurrencyCode }}
                    </div>
                </div>
            </header>

            <section class="ar-print-title-block">
                <h2 class="ar-print-report-title">
                    {{ title }}
                </h2>

                <p
                    v-if="subtitle"
                    class="ar-print-report-subtitle"
                >
                    {{ subtitle }}
                </p>
            </section>

            <slot />

            <footer class="ar-print-footer">
                <span>
                    Confidential tenant financial report
                </span>

                <span>
                    {{ company.name }} ·
                    {{ baseCurrencyCode }}
                </span>
            </footer>
        </main>
    </div>
</template>

<style>
:root {
    color-scheme: light;
}

.ar-print-page {
    min-height: 100vh;
    margin: 0;
    padding: 24px;
    background: #f3f4f6;
    color: #111827;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
}

.ar-print-page,
.ar-print-page * {
    box-sizing: border-box;
}

.ar-print-toolbar {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    width: min(100%, 1480px);
    margin: 0 auto 12px;
}

.ar-print-button {
    cursor: pointer;
    border: 1px solid #9ca3af;
    border-radius: 6px;
    padding: 8px 14px;
    background: #ffffff;
    color: #111827;
    font: inherit;
    font-weight: 600;
}

.ar-print-button-primary {
    border-color: #2563eb;
    background: #2563eb;
    color: #ffffff;
}

.ar-print-document {
    width: min(100%, 1480px);
    margin: 0 auto;
    border: 1px solid #d1d5db;
    padding: 26px;
    background: #ffffff;
    box-shadow: 0 8px 30px rgb(15 23 42 / 8%);
}

.ar-print-header {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    border-bottom: 2px solid #111827;
    padding-bottom: 16px;
}

.ar-print-company-name {
    margin: 0;
    font-size: 22px;
    line-height: 1.25;
}

.ar-print-company-meta,
.ar-print-generated-meta {
    margin-top: 6px;
    color: #4b5563;
    line-height: 1.55;
}

.ar-print-generated-meta {
    text-align: right;
}

.ar-print-title-block {
    margin: 20px 0 16px;
}

.ar-print-report-title {
    margin: 0;
    font-size: 20px;
    line-height: 1.25;
}

.ar-print-report-subtitle {
    margin: 5px 0 0;
    color: #4b5563;
    line-height: 1.5;
}

.ar-print-filter-grid,
.ar-print-kpi-grid {
    display: grid;
    gap: 10px;
    margin-bottom: 16px;
}

.ar-print-filter-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.ar-print-kpi-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.ar-print-card {
    border: 1px solid #d1d5db;
    border-radius: 5px;
    padding: 9px;
}

.ar-print-card-label {
    margin: 0;
    color: #6b7280;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.ar-print-card-value {
    margin: 4px 0 0;
    font-size: 13px;
    font-weight: 700;
}

.ar-print-table-wrap {
    overflow: visible;
}

.ar-print-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
}

.ar-print-table th,
.ar-print-table td {
    border: 1px solid #d1d5db;
    padding: 6px 7px;
    vertical-align: top;
}

.ar-print-table th {
    background: #f3f4f6;
    color: #374151;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.035em;
    text-align: left;
    text-transform: uppercase;
}

.ar-print-table tbody tr:nth-child(even) {
    background: #f9fafb;
}

.ar-print-table tfoot td {
    background: #f3f4f6;
    font-weight: 700;
}

.ar-print-right {
    text-align: right !important;
    white-space: nowrap;
}

.ar-print-center {
    text-align: center !important;
}

.ar-print-muted {
    color: #6b7280;
}

.ar-print-danger {
    color: #b91c1c;
}

.ar-print-success {
    color: #047857;
}

.ar-print-section-title {
    margin: 18px 0 8px;
    font-size: 13px;
}

.ar-print-note {
    margin: 12px 0;
    border: 1px solid #f59e0b;
    background: #fffbeb;
    padding: 9px 11px;
    color: #92400e;
}

.ar-print-footer {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 24px;
    border-top: 1px solid #d1d5db;
    padding-top: 10px;
    color: #6b7280;
    font-size: 9px;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    .ar-print-page {
        min-height: auto;
        padding: 0;
        background: #ffffff;
    }

    .ar-print-toolbar {
        display: none;
    }

    .ar-print-document {
        width: 100%;
        margin: 0;
        border: 0;
        padding: 0;
        box-shadow: none;
    }

    .ar-print-table thead {
        display: table-header-group;
    }

    .ar-print-table tfoot {
        display: table-row-group;
    }

    .ar-print-table tr,
    .ar-print-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .ar-print-footer {
        break-inside: avoid;
    }
}
</style>

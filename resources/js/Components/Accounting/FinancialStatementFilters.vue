<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    FinancialBranchOption,
    FinancialStatementFilters,
} from '@/Types/financial-control';

const props = defineProps<{
    routeName: string;
    exportType: string;
    filters: FinancialStatementFilters;
    branches: FinancialBranchOption[];
    showComparison?: boolean;
    showMethod?: boolean;
    asOfOnly?: boolean;
}>();

const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const form = reactive<FinancialStatementFilters>({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    as_of_date: props.filters.as_of_date ?? '',
    branch_id: props.filters.branch_id ?? null,
    comparison: props.filters.comparison ?? 'none',
    method: props.filters.method ?? 'direct',
});

const queryParameters = (): Record<string, string | number> => {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form)) {
        if (value !== null && value !== undefined && value !== '') {
            query[key] = value;
        }
    }

    return query;
};

const apply = (): void => {
    router.get(
        route(props.routeName),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const printReport = (): void => {
    window.open(
        route(`${props.routeName}.print`, queryParameters()),
        '_blank',
        'noopener,noreferrer',
    );
};

const requestExport = (format: 'csv' | 'xlsx'): void => {
    router.post(
        route('exports.store'),
        {
            export_type: props.exportType,
            format,
            filters: queryParameters(),
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <label v-if="!asOfOnly" class="text-sm">
                <span class="mb-1 block text-gray-500">From</span>
                <input
                    v-model="form.date_from"
                    type="date"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                />
            </label>

            <label v-if="!asOfOnly" class="text-sm">
                <span class="mb-1 block text-gray-500">To</span>
                <input
                    v-model="form.date_to"
                    type="date"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                />
            </label>

            <label v-if="asOfOnly" class="text-sm">
                <span class="mb-1 block text-gray-500">As of</span>
                <input
                    v-model="form.as_of_date"
                    type="date"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                />
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-gray-500">Branch</span>
                <select
                    v-model="form.branch_id"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                >
                    <option :value="null">All accessible branches</option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.code }} — {{ branch.name }}
                    </option>
                </select>
            </label>

            <label v-if="showComparison" class="text-sm">
                <span class="mb-1 block text-gray-500">Comparison</span>
                <select
                    v-model="form.comparison"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                >
                    <option value="none">None</option>
                    <option value="previous_period">Previous period</option>
                    <option value="previous_year">Previous year</option>
                </select>
            </label>

            <label v-if="showMethod" class="text-sm">
                <span class="mb-1 block text-gray-500">Method</span>
                <select
                    v-model="form.method"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                >
                    <option value="direct">Direct</option>
                    <option value="indirect">Indirect</option>
                </select>
            </label>

            <div class="flex flex-wrap items-end gap-2 xl:col-span-2">
                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white"
                    @click="apply"
                >
                    Apply
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                    @click="printReport"
                >
                    Print
                </button>

                <template v-if="canExport">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                        @click="requestExport('csv')"
                    >
                        CSV
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                        @click="requestExport('xlsx')"
                    >
                        Excel
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

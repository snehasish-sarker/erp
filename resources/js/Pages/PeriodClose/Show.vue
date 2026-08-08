<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    PeriodClosePeriod,
    PeriodCloseRun,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    period: PeriodClosePeriod;
    runs: PeriodCloseRun[];
}>();

const { can } = useAuthorization();
const processing = ref(false);

const latestRun = computed((): PeriodCloseRun | null => props.runs[0] ?? null);
const canPrepare = computed((): boolean => can('period_close.prepare'));
const canClose = computed((): boolean => can('period_close.close'));
const canReopen = computed((): boolean => can('period_close.reopen'));

const amount = (value: string): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};

const prepare = (): void => {
    if (
        processing.value
        || !canPrepare.value
        || !window.confirm('Run the complete period-close checklist now?')
    ) {
        return;
    }

    processing.value = true;
    router.post(
        route('financial-control.period-close.prepare', props.period.id),
        {},
        {
            preserveScroll: true,
            onFinish: (): void => {
                processing.value = false;
            },
        },
    );
};

const close = (): void => {
    if (processing.value || !canClose.value) {
        return;
    }

    const reason = window.prompt(
        'Enter the reason for closing this accounting period:',
    );

    const normalizedReason = reason?.trim() ?? '';

    if (normalizedReason.length < 10 || normalizedReason.length > 500) {
        window.alert('The reason must contain between 10 and 500 characters.');

        return;
    }

    if (!window.confirm('Close this period? Posting will be blocked afterward.')) {
        return;
    }

    processing.value = true;
    router.post(
        route('financial-control.period-close.close', props.period.id),
        { reason: normalizedReason },
        {
            preserveScroll: true,
            onFinish: (): void => {
                processing.value = false;
            },
        },
    );
};

const reopen = (): void => {
    if (processing.value || !canReopen.value) {
        return;
    }

    const reason = window.prompt(
        'Enter the reason for reopening this accounting period:',
    );

    const normalizedReason = reason?.trim() ?? '';

    if (normalizedReason.length < 10 || normalizedReason.length > 500) {
        window.alert('The reason must contain between 10 and 500 characters.');

        return;
    }

    if (
        !window.confirm(
            'Reopen this period and reverse any year-end closing journals?',
        )
    ) {
        return;
    }

    processing.value = true;
    router.post(
        route('financial-control.period-close.reopen', props.period.id),
        { reason: normalizedReason },
        {
            preserveScroll: true,
            onFinish: (): void => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Period Close ${period.code}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Period Close — {{ period.code }}
                </h1>
                <p class="text-sm text-gray-500">
                    {{ period.name }} · {{ period.start_date }} to {{ period.end_date }} ·
                    Fiscal year {{ period.fiscal_year.code }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="period.status === 'open' && canPrepare"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium disabled:opacity-50 dark:border-gray-700"
                    :disabled="processing"
                    @click="prepare"
                >
                    Prepare checklist
                </button>

                <button
                    v-if="period.status === 'open' && latestRun?.status === 'ready' && canClose"
                    type="button"
                    class="rounded-lg bg-error-600 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                    :disabled="processing"
                    @click="close"
                >
                    Close period
                </button>

                <button
                    v-if="period.status === 'closed' && canReopen"
                    type="button"
                    class="rounded-lg bg-warning-500 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                    :disabled="processing"
                    @click="reopen"
                >
                    Reopen period
                </button>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex flex-wrap gap-4">
                <span
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="period.status === 'closed'
                        ? 'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300'
                        : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300'"
                >
                    {{ period.status }}
                </span>
                <span v-if="period.closed_at" class="text-sm text-gray-500">
                    Closed {{ period.closed_at }} by {{ period.closed_by ?? 'Unknown user' }}
                </span>
            </div>
        </div>

        <div v-if="latestRun" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="item in [
                        ['Run', latestRun.run_number],
                        ['Passed', latestRun.passed_checks],
                        ['Warnings', latestRun.warning_checks],
                        ['Failed', latestRun.failed_checks],
                        ['Difference', amount(latestRun.total_reconciliation_difference)],
                    ]"
                    :key="String(item[0])"
                    class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                >
                    <p class="text-xs uppercase text-gray-500">{{ item[0] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ item[1] }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-2xl border p-4"
                :class="latestRun.status === 'ready' || latestRun.status === 'closed'
                    ? 'border-success-300 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300'
                    : 'border-error-300 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300'"
            >
                <p class="font-semibold">
                    Checklist status: {{ latestRun.status }}
                </p>
                <p v-if="latestRun.status === 'blocked'" class="mt-1 text-sm">
                    Resolve all blocking controls, then prepare a new checklist run.
                </p>
            </div>

            <div
                class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 text-left">Control</th>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Issues</th>
                            <th class="px-4 py-3 text-right">Difference</th>
                            <th class="px-4 py-3 text-left">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="check in latestRun.checks"
                            :key="check.id"
                            class="border-t border-gray-100 dark:border-gray-800"
                        >
                            <td class="px-4 py-3 font-medium">{{ check.label }}</td>
                            <td class="px-4 py-3 capitalize">
                                {{ check.category.replaceAll('_', ' ') }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="check.status === 'passed'
                                        ? 'bg-success-50 text-success-700'
                                        : check.status === 'warning'
                                            ? 'bg-warning-50 text-warning-700'
                                            : 'bg-error-50 text-error-700'"
                                >
                                    {{ check.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ check.issue_count }}</td>
                            <td class="px-4 py-3 text-right">
                                {{ amount(check.difference_amount) }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ check.message }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="latestRun.close_reason || latestRun.reopen_reason"
                class="rounded-2xl border border-gray-200 bg-white p-5 text-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p v-if="latestRun.close_reason">
                    <strong>Close reason:</strong> {{ latestRun.close_reason }}
                </p>
                <p v-if="latestRun.reopen_reason" class="mt-2">
                    <strong>Reopen reason:</strong> {{ latestRun.reopen_reason }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-gray-500 dark:border-gray-700"
        >
            No close checklist has been prepared for this period.
        </div>

        <div
            v-if="runs.length > 1"
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <h2 class="font-semibold text-gray-900 dark:text-white">Close history</h2>
            <div
                v-for="run in runs.slice(1)"
                :key="run.id"
                class="mt-3 flex flex-wrap justify-between gap-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-800"
            >
                <span>Run {{ run.run_number }} · {{ run.status }}</span>
                <span>{{ run.prepared_at ?? 'Not recorded' }} · {{ run.prepared_by ?? 'Unknown user' }}</span>
            </div>
        </div>
    </div>
</template>

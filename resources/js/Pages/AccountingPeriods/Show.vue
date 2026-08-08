<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    AccountingPeriodRecord,
    AccountingPeriodStatus,
    FiscalYearRecord,
} from '@/Types/accounting-period';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    fiscalYear: FiscalYearRecord;
    periods: AccountingPeriodRecord[];
}>();

const { can } = useAuthorization();

const processingPeriodId: Ref<number | null> =
    ref(null);

const processingAction: Ref<
    'close' | 'reopen' | null
> = ref(null);

const completionPercentage: ComputedRef<number> =
    computed(
        (): number => {
            if (
                props.fiscalYear.periods_count
                === 0
            ) {
                return 0;
            }

            return Math.round(
                (
                    props.fiscalYear
                        .closed_periods_count
                    / props.fiscalYear
                        .periods_count
                ) * 100,
            );
        },
    );

const formatDate = (
    value: string,
): string =>
    new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: 'UTC',
        },
    ).format(
        new Date(`${value}T00:00:00Z`),
    );

const formatDateTime = (
    value: string,
): string =>
    new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        },
    ).format(new Date(value));

const periodStatusLabel = (
    status: AccountingPeriodStatus,
): string =>
    status === 'open'
        ? 'Open'
        : 'Closed';

const periodStatusBadgeClass = (
    status: AccountingPeriodStatus,
): string =>
    status === 'open'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400';

const actionIsProcessing = (
    period: AccountingPeriodRecord,
    action: 'close' | 'reopen',
): boolean =>
    processingPeriodId.value === period.id
    && processingAction.value === action;

const closePeriod = (
    period: AccountingPeriodRecord,
): void => {
    if (!period.can_close) {
        return;
    }

    const reason = window.prompt(
        `Enter the reason for closing ${period.name}:`,
    );

    const normalizedReason = reason?.trim() ?? '';

    if (normalizedReason.length < 10 || normalizedReason.length > 500) {
        window.alert(
            'The reason must contain between 10 and 500 characters.',
        );

        return;
    }

    const confirmed = window.confirm(
        `Run financial controls and close ${period.name}? New postings dated within this period will be blocked.`,
    );

    if (!confirmed) {
        return;
    }

    processingPeriodId.value = period.id;
    processingAction.value = 'close';

    router.patch(
        `/erp/accounting-periods/periods/${period.id}/close`,
        { reason: normalizedReason },
        {
            preserveScroll: true,

            onFinish: (): void => {
                processingPeriodId.value = null;
                processingAction.value = null;
            },
        },
    );
};

const reopenPeriod = (
    period: AccountingPeriodRecord,
): void => {
    if (!period.can_reopen) {
        return;
    }

    const reason = window.prompt(
        `Enter the reason for reopening ${period.name}:`,
    );

    const normalizedReason = reason?.trim() ?? '';

    if (normalizedReason.length < 10 || normalizedReason.length > 500) {
        window.alert(
            'The reason must contain between 10 and 500 characters.',
        );

        return;
    }

    const confirmed = window.confirm(
        `Reopen ${period.name}? Authorized users will be able to post transactions into this period again and year-end closing journals will be reversed where applicable.`,
    );

    if (!confirmed) {
        return;
    }

    processingPeriodId.value = period.id;
    processingAction.value = 'reopen';

    router.patch(
        `/erp/accounting-periods/periods/${period.id}/reopen`,
        { reason: normalizedReason },
        {
            preserveScroll: true,

            onFinish: (): void => {
                processingPeriodId.value = null;
                processingAction.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="fiscalYear.name" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div
                    class="flex flex-wrap items-center gap-2"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ fiscalYear.name }}
                    </h1>

                    <span
                        v-if="fiscalYear.is_current"
                        class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                    >
                        Current fiscal year
                    </span>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            fiscalYear.status
                                === 'active'
                                ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
                                : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400'
                        "
                    >
                        {{
                            fiscalYear.status
                                === 'active'
                                ? 'Active'
                                : 'Closed'
                        }}
                    </span>
                </div>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ fiscalYear.code }} ·
                    {{
                        formatDate(
                            fiscalYear.start_date,
                        )
                    }}
                    to
                    {{
                        formatDate(
                            fiscalYear.end_date,
                        )
                    }}
                </p>
            </div>

            <Link
                href="/erp/accounting-periods"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
            >
                Back to fiscal years
            </Link>
        </div>

        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Total periods
                </p>

                <p
                    class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ fiscalYear.periods_count }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Open periods
                </p>

                <p
                    class="mt-2 text-2xl font-semibold text-success-600 dark:text-success-400"
                >
                    {{
                        fiscalYear
                            .open_periods_count
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Closed periods
                </p>

                <p
                    class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{
                        fiscalYear
                            .closed_periods_count
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Closing progress
                </p>

                <p
                    class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ completionPercentage }}%
                </p>
            </div>
        </div>

        <div
            class="rounded-2xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/20 dark:bg-brand-500/10"
        >
            <p
                class="text-sm font-semibold text-brand-800 dark:text-brand-300"
            >
                Sequential period control
            </p>

            <p
                class="mt-1 text-sm text-brand-700 dark:text-brand-400"
            >
                Periods close from oldest to newest and
                reopen from newest to oldest. This
                prevents gaps in the accounting calendar.
            </p>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Period
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Code
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Date range
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Status
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Closure details
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="period in periods"
                            :key="period.id"
                            :class="
                                period.is_current
                                    ? 'bg-brand-50/40 dark:bg-brand-500/[0.06]'
                                    : ''
                            "
                        >
                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-gray-100 px-2 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                    >
                                        {{
                                            period
                                                .period_number
                                        }}
                                    </span>

                                    <div>
                                        <p
                                            class="font-medium text-gray-800 dark:text-white/90"
                                        >
                                            {{ period.name }}
                                        </p>

                                        <p
                                            v-if="
                                                period
                                                    .is_current
                                            "
                                            class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400"
                                        >
                                            Current period
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 align-top font-mono text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ period.code }}
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        formatDate(
                                            period
                                                .start_date,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    to
                                    {{
                                        formatDate(
                                            period
                                                .end_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        periodStatusBadgeClass(
                                            period.status,
                                        )
                                    "
                                >
                                    {{
                                        periodStatusLabel(
                                            period.status,
                                        )
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <template
                                    v-if="
                                        period.closed_at
                                        !== null
                                    "
                                >
                                    <p
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            formatDateTime(
                                                period
                                                    .closed_at,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            period.closed_by
                                            !== null
                                        "
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        by
                                        {{
                                            period
                                                .closed_by
                                                .name
                                        }}
                                    </p>
                                </template>

                                <span
                                    v-else
                                    class="text-sm text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex justify-end"
                                >
                                    <button
                                        v-if="
                                            period.status
                                                === 'open'
                                            && can(
                                                'accounting_periods.close',
                                            )
                                        "
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="
                                            !period.can_close
                                            || processingPeriodId
                                                !== null
                                        "
                                        @click="
                                            closePeriod(
                                                period,
                                            )
                                        "
                                    >
                                        {{
                                            actionIsProcessing(
                                                period,
                                                'close',
                                            )
                                                ? 'Closing...'
                                                : 'Close period'
                                        }}
                                    </button>

                                    <button
                                        v-else-if="
                                            period.status
                                                === 'closed'
                                            && can(
                                                'accounting_periods.reopen',
                                            )
                                        "
                                        type="button"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-brand-400"
                                        :disabled="
                                            !period.can_reopen
                                            || processingPeriodId
                                                !== null
                                        "
                                        @click="
                                            reopenPeriod(
                                                period,
                                            )
                                        "
                                    >
                                        {{
                                            actionIsProcessing(
                                                period,
                                                'reopen',
                                            )
                                                ? 'Reopening...'
                                                : 'Reopen period'
                                        }}
                                    </button>

                                    <span
                                        v-else
                                        class="text-sm text-gray-400"
                                    >
                                        —
                                    </span>
                                </div>

                                <p
                                    v-if="
                                        period.status
                                            === 'open'
                                        && can(
                                            'accounting_periods.close',
                                        )
                                        && !period.can_close
                                    "
                                    class="mt-1 text-right text-xs text-gray-400"
                                >
                                    Close earlier period first
                                </p>

                                <p
                                    v-if="
                                        period.status
                                            === 'closed'
                                        && can(
                                            'accounting_periods.reopen',
                                        )
                                        && !period.can_reopen
                                    "
                                    class="mt-1 text-right text-xs text-gray-400"
                                >
                                    Reopen later period first
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
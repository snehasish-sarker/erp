<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
    watch,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';

interface FiscalYearFormInput {
    name: string;
    code: string;
    start_date: string;
}

interface FiscalYearSuggestion {
    name: string;
    code: string;
    endDate: string;
}

const props = defineProps<{
    timezone: string;
    suggestedStartDate: string;
}>();

const form = useForm<FiscalYearFormInput>({
    name: '',
    code: '',
    start_date: props.suggestedStartDate,
});

const previousSuggestedName: Ref<string> = ref('');
const previousSuggestedCode: Ref<string> = ref('');

const parseDate = (
    value: string,
): Date | null => {
    const parts = value.split('-').map(Number);

    if (parts.length !== 3) {
        return null;
    }

    const [
        year,
        month,
        day,
    ] = parts;

    if (
        year === undefined
        || month === undefined
        || day === undefined
        || month < 1
        || month > 12
        || day < 1
        || day > 31
    ) {
        return null;
    }

    const date = new Date(
        Date.UTC(
            year,
            month - 1,
            day,
        ),
    );

    if (
        date.getUTCFullYear() !== year
        || date.getUTCMonth() !== month - 1
        || date.getUTCDate() !== day
    ) {
        return null;
    }

    return date;
};

const formatDateValue = (
    date: Date,
): string => {
    const year = String(
        date.getUTCFullYear(),
    );

    const month = String(
        date.getUTCMonth() + 1,
    ).padStart(2, '0');

    const day = String(
        date.getUTCDate(),
    ).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const buildSuggestion = (
    startDateValue: string,
): FiscalYearSuggestion | null => {
    const startDate = parseDate(
        startDateValue,
    );

    if (startDate === null) {
        return null;
    }

    const nextYearBoundary = new Date(
        Date.UTC(
            startDate.getUTCFullYear(),
            startDate.getUTCMonth() + 12,
            1,
        ),
    );

    const endDate = new Date(
        nextYearBoundary.getTime()
            - 86_400_000,
    );

    const startYear =
        startDate.getUTCFullYear();

    const endYear =
        endDate.getUTCFullYear();

    if (startYear === endYear) {
        return {
            name: `Fiscal Year ${startYear}`,
            code: `FY${startYear}`,
            endDate: formatDateValue(endDate),
        };
    }

    return {
        name: `Fiscal Year ${startYear}-${endYear}`,

        code:
            `FY${startYear}-`
            + String(endYear).slice(-2),

        endDate: formatDateValue(endDate),
    };
};

const suggestion: ComputedRef<
    FiscalYearSuggestion | null
> = computed(
    (): FiscalYearSuggestion | null =>
        buildSuggestion(form.start_date),
);

const formattedStartDate: ComputedRef<string> =
    computed(
        (): string => {
            const date = parseDate(
                form.start_date,
            );

            if (date === null) {
                return '—';
            }

            return new Intl.DateTimeFormat(
                'en-US',
                {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    timeZone: 'UTC',
                },
            ).format(date);
        },
    );

const formattedEndDate: ComputedRef<string> =
    computed(
        (): string => {
            if (suggestion.value === null) {
                return '—';
            }

            const date = parseDate(
                suggestion.value.endDate,
            );

            if (date === null) {
                return '—';
            }

            return new Intl.DateTimeFormat(
                'en-US',
                {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    timeZone: 'UTC',
                },
            ).format(date);
        },
    );

watch(
    suggestion,
    (
        currentSuggestion:
            FiscalYearSuggestion | null,
    ): void => {
        if (currentSuggestion === null) {
            return;
        }

        if (
            form.name === ''
            || form.name
                === previousSuggestedName.value
        ) {
            form.name =
                currentSuggestion.name;
        }

        if (
            form.code === ''
            || form.code
                === previousSuggestedCode.value
        ) {
            form.code =
                currentSuggestion.code;
        }

        previousSuggestedName.value =
            currentSuggestion.name;

        previousSuggestedCode.value =
            currentSuggestion.code;
    },
    {
        immediate: true,
    },
);

const submit = (): void => {
    form.name = form.name.trim();

    form.code = form.code
        .trim()
        .toUpperCase();

    form.post('/erp/accounting-periods');
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Generate Fiscal Year
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Create one fiscal year and its twelve
                monthly accounting periods in a single
                controlled action.
            </p>
        </div>

        <div
            class="rounded-2xl border border-warning-200 bg-warning-25 p-5 dark:border-warning-500/20 dark:bg-warning-500/10"
        >
            <p
                class="text-sm font-semibold text-warning-800 dark:text-warning-300"
            >
                Fiscal years are generated once
            </p>

            <p
                class="mt-1 text-sm text-warning-700 dark:text-warning-400"
            >
                After generation, dates and period
                boundaries are not editable. This
                protects future accounting and inventory
                postings from calendar changes.
            </p>
        </div>

        <form
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="submit"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Fiscal year details
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div>
                    <label
                        for="fiscal-start-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Start date
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <input
                        id="fiscal-start-date"
                        v-model="form.start_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="
                            form.errors.start_date
                                ? 'border-error-500'
                                : ''
                        "
                    >

                    <p
                        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        The date must be the first day of
                        a month.
                    </p>

                    <p
                        v-if="form.errors.start_date"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.start_date }}
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="fiscal-year-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Fiscal year name
                            <span class="text-error-500">
                                *
                            </span>
                        </label>

                        <input
                            id="fiscal-year-name"
                            v-model="form.name"
                            type="text"
                            maxlength="100"
                            placeholder="Fiscal Year 2026-2027"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="
                                form.errors.name
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            v-if="form.errors.name"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="fiscal-year-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Fiscal year code
                            <span class="text-error-500">
                                *
                            </span>
                        </label>

                        <input
                            id="fiscal-year-code"
                            v-model="form.code"
                            type="text"
                            maxlength="30"
                            placeholder="FY2026-27"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="
                                form.errors.code
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use uppercase letters, numbers,
                            hyphens, or forward slashes.
                        </p>

                        <p
                            v-if="form.errors.code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/20 dark:bg-brand-500/10"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300"
                    >
                        Generated calendar
                    </p>

                    <div
                        class="mt-3 grid gap-4 sm:grid-cols-3"
                    >
                        <div>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Starts
                            </p>

                            <p
                                class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ formattedStartDate }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Ends
                            </p>

                            <p
                                class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ formattedEndDate }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                Periods
                            </p>

                            <p
                                class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                12 monthly periods
                            </p>
                        </div>
                    </div>

                    <p
                        class="mt-3 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Dates are interpreted using
                        {{ timezone }}.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
            >
                <Link
                    href="/erp/accounting-periods"
                    class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? 'Generating...'
                            : 'Generate fiscal year'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
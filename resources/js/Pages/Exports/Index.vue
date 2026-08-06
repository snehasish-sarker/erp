<script setup lang="ts">
import {
    Head,
    router,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    ExportOption,
    ExportRequestFilters,
    ExportRequestFormData,
    ExportRequestPagination,
    ExportRequestRecord,
    ExportRequestStatus,
    ExportStatusOption,
} from '@/Types/export';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    exportRequests: ExportRequestPagination;
    filters: ExportRequestFilters;
    exportOptions: ExportOption[];
    statusOptions: ExportStatusOption[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<ExportRequestFilters>({
    search: props.filters.search,
    export_type: props.filters.export_type,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const requestForm = useForm<ExportRequestFormData>({
    export_type:
        props.exportOptions[0]?.value ?? '',

    format: 'csv',

    filters: {
        search: '',
        event: '',
        subject_type: '',
        actor: '',
        date_from: '',
        date_to: '',
        direction: 'desc',
    },
});

const cancellingExportId: Ref<number | null> =
    ref(null);

let pollingTimer: number | null = null;

const hasPendingExports: ComputedRef<boolean> =
    computed(
        (): boolean =>
            props.exportRequests.data.some(
                (
                    exportRequest:
                        ExportRequestRecord,
                ): boolean =>
                    exportRequest.status === 'queued'
                    || exportRequest.status
                        === 'processing',
            ),
    );

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filterForm.search !== ''
            || filterForm.export_type !== ''
            || filterForm.status !== '',
    );

const selectedExportLabel: ComputedRef<string> =
    computed(
        (): string =>
            props.exportOptions.find(
                (
                    option: ExportOption,
                ): boolean =>
                    option.value
                    === requestForm.export_type,
            )?.label ?? 'Export',
    );

const formError = (
    key: string,
): string | undefined => {
    const errors =
        requestForm.errors as Record<
            string,
            string
        >;

    return errors[key];
};

const navigate = (page = 1): void => {
    router.get(
        '/erp/exports',
        {
            search: filterForm.search,
            export_type: filterForm.export_type,
            status: filterForm.status,
            sort: filterForm.sort,
            direction: filterForm.direction,
            per_page: filterForm.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    navigate();
};

const resetFilters = (): void => {
    filterForm.search = '';
    filterForm.export_type = '';
    filterForm.status = '';
    filterForm.sort = 'created_at';
    filterForm.direction = 'desc';
    filterForm.per_page = 25;

    navigate();
};

const submitExport = (): void => {
    requestForm.filters.search =
        requestForm.filters.search.trim();

    requestForm.filters.event =
        requestForm.filters.event.trim();

    requestForm.filters.subject_type =
        requestForm.filters.subject_type.trim();

    requestForm.filters.actor =
        requestForm.filters.actor.trim();

    requestForm.post(
        '/erp/exports',
        {
            preserveScroll: true,
        },
    );
};

const cancelExport = (
    exportRequest: ExportRequestRecord,
): void => {
    if (!exportRequest.can_cancel) {
        return;
    }

    const confirmed = window.confirm(
        `Cancel “${exportRequest.name}”?`,
    );

    if (!confirmed) {
        return;
    }

    cancellingExportId.value =
        exportRequest.id;

    router.patch(
        `/erp/exports/${exportRequest.id}/cancel`,
        {},
        {
            preserveScroll: true,

            onFinish: (): void => {
                cancellingExportId.value =
                    null;
            },
        },
    );
};

const statusLabel = (
    status: ExportRequestStatus,
): string =>
    props.statusOptions.find(
        (
            option: ExportStatusOption,
        ): boolean =>
            option.value === status,
    )?.label ?? status;

const statusBadgeClass = (
    status: ExportRequestStatus,
): string => {
    const classes: Record<
        ExportRequestStatus,
        string
    > = {
        queued: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        processing: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
        completed: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        failed: 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
        expired: 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-500',
    };

    return classes[status];
};

const progressBarClass = (
    status: ExportRequestStatus,
): string => {
    if (status === 'failed') {
        return 'bg-error-500';
    }

    if (
        status === 'cancelled'
        || status === 'expired'
    ) {
        return 'bg-gray-400';
    }

    if (status === 'completed') {
        return 'bg-success-500';
    }

    return 'bg-brand-500';
};

const formatNumber = (
    value: number,
): string =>
    new Intl.NumberFormat(
        'en-US',
    ).format(value);

const formatDateTime = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        },
    ).format(new Date(value));
};

onMounted((): void => {
    pollingTimer = window.setInterval(
        (): void => {
            if (!hasPendingExports.value) {
                return;
            }

            router.reload({
                only: [
                    'exportRequests',
                ],
                preserveScroll: true,
                preserveState: true,
            });
        },
        5000,
    );
});

onBeforeUnmount((): void => {
    if (pollingTimer !== null) {
        window.clearInterval(
            pollingTimer,
        );
    }
});
</script>

<template>
    <Head title="Exports" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Export Center
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Generate large ERP exports in the
                background and download them when ready.
            </p>
        </div>

        <form
            v-if="
                can('exports.create')
                && exportOptions.length > 0
            "
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="submitExport"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Request an export
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="export-type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Export type
                        </label>

                        <select
                            id="export-type"
                            v-model="
                                requestForm.export_type
                            "
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option
                                v-for="
                                    option
                                    in exportOptions
                                "
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="
                                requestForm.errors
                                    .export_type
                            "
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                requestForm.errors
                                    .export_type
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="export-format"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Format
                        </label>

                        <select
                            id="export-format"
                            v-model="
                                requestForm.format
                            "
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option value="csv">
                                CSV
                            </option>

                            <option value="xlsx">
                                Excel (XLSX)
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="
                        requestForm.export_type
                        === 'audit_logs'
                    "
                    class="space-y-5"
                >
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-800 dark:text-white/90"
                        >
                            Audit Log filters
                        </h3>

                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Leave filters empty to export
                            all tenant Audit Logs.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="export-search"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Search
                            </label>

                            <input
                                id="export-search"
                                v-model="
                                    requestForm
                                        .filters
                                        .search
                                "
                                type="text"
                                maxlength="150"
                                placeholder="Actor, subject, request ID..."
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            >
                        </div>

                        <div>
                            <label
                                for="export-event"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Event
                            </label>

                            <input
                                id="export-event"
                                v-model="
                                    requestForm
                                        .filters
                                        .event
                                "
                                type="text"
                                maxlength="50"
                                placeholder="updated"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            >

                            <p
                                v-if="
                                    formError(
                                        'filters.event',
                                    )
                                "
                                class="mt-1.5 text-sm text-error-500"
                            >
                                {{
                                    formError(
                                        'filters.event',
                                    )
                                }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="export-date-from"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Date from
                            </label>

                            <input
                                id="export-date-from"
                                v-model="
                                    requestForm
                                        .filters
                                        .date_from
                                "
                                type="date"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            >
                        </div>

                        <div>
                            <label
                                for="export-date-to"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Date to
                            </label>

                            <input
                                id="export-date-to"
                                v-model="
                                    requestForm
                                        .filters
                                        .date_to
                                "
                                type="date"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            >

                            <p
                                v-if="
                                    formError(
                                        'filters.date_to',
                                    )
                                "
                                class="mt-1.5 text-sm text-error-500"
                            >
                                {{
                                    formError(
                                        'filters.date_to',
                                    )
                                }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="export-direction"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Row order
                            </label>

                            <select
                                id="export-direction"
                                v-model="
                                    requestForm
                                        .filters
                                        .direction
                                "
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            >
                                <option value="desc">
                                    Newest first
                                </option>

                                <option value="asc">
                                    Oldest first
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <button
                    type="submit"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="
                        requestForm.processing
                        || requestForm.export_type
                            === ''
                    "
                >
                    {{
                        requestForm.processing
                            ? 'Queueing...'
                            : `Export ${selectedExportLabel}`
                    }}
                </button>
            </div>
        </form>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="export-list-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search exports
                    </label>

                    <input
                        id="export-list-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Name, request key, or requester"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="export-list-type"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Export type
                    </label>

                    <select
                        id="export-list-type"
                        v-model="
                            filterForm.export_type
                        "
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="
                                option in exportOptions
                            "
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="export-list-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="export-list-status"
                        v-model="filterForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="
                                option
                                in statusOptions
                            "
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="export-list-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="export-list-per-page"
                        v-model.number="
                            filterForm.per_page
                        "
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex items-end gap-3 sm:col-span-2 lg:col-span-3"
                >
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Export
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Requester
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Progress
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Status
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Requested
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="
                                exportRequest
                                in exportRequests.data
                            "
                            :key="exportRequest.id"
                        >
                            <td class="px-5 py-4 align-top">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        exportRequest
                                            .export_type_label
                                    }}
                                </p>

                                <p
                                    class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        exportRequest
                                            .request_key
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <template
                                    v-if="
                                        exportRequest
                                            .requester
                                        !== null
                                    "
                                >
                                    <p
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            exportRequest
                                                .requester
                                                .name
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            exportRequest
                                                .requester
                                                .email
                                        }}
                                    </p>
                                </template>

                                <span
                                    v-else
                                    class="text-sm text-gray-400"
                                >
                                    Deleted user
                                </span>
                            </td>

                            <td
                                class="min-w-48 px-5 py-4 align-top"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            exportRequest
                                                .progress_percent
                                        }}%
                                    </span>

                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            formatNumber(
                                                exportRequest
                                                    .rows_exported,
                                            )
                                        }}
                                        rows
                                    </span>
                                </div>

                                <div
                                    class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="
                                            progressBarClass(
                                                exportRequest
                                                    .status,
                                            )
                                        "
                                        :style="{
                                            width: `${exportRequest.progress_percent}%`,
                                        }"
                                    />
                                </div>

                                <p
                                    v-if="
                                        exportRequest
                                            .error_message
                                        !== null
                                    "
                                    class="mt-2 text-xs text-error-600 dark:text-error-400"
                                >
                                    {{
                                        exportRequest
                                            .error_message
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClass(
                                            exportRequest
                                                .status,
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            exportRequest
                                                .status,
                                        )
                                    }}
                                </span>

                                <p
                                    v-if="
                                        exportRequest
                                            .expires_at
                                        !== null
                                        && exportRequest
                                            .status
                                            === 'completed'
                                    "
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Expires
                                    {{
                                        formatDateTime(
                                            exportRequest
                                                .expires_at,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDateTime(
                                        exportRequest
                                            .created_at,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex justify-end gap-3"
                                >
                                    <a
                                        v-if="
                                            exportRequest
                                                .can_download
                                            && exportRequest
                                                .download_url
                                                !== null
                                        "
                                        :href="
                                            exportRequest
                                                .download_url
                                        "
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Download
                                    </a>

                                    <button
                                        v-if="
                                            exportRequest
                                                .can_cancel
                                        "
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            cancellingExportId
                                            === exportRequest.id
                                        "
                                        @click="
                                            cancelExport(
                                                exportRequest,
                                            )
                                        "
                                    >
                                        {{
                                            cancellingExportId
                                                === exportRequest.id
                                                ? 'Cancelling...'
                                                : 'Cancel'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                exportRequests.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="6"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No export requests found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Request an export or
                                    adjust the current filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        exportRequests.meta.from
                        ?? 0
                    }}–{{
                        exportRequests.meta.to
                        ?? 0
                    }}
                    of
                    {{ exportRequests.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            exportRequests.meta
                                .current_page <= 1
                        "
                        @click="
                            navigate(
                                exportRequests.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page
                        {{
                            exportRequests.meta
                                .current_page
                        }}
                        of
                        {{
                            exportRequests.meta
                                .last_page
                        }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            exportRequests.meta
                                .current_page
                            >= exportRequests.meta
                                .last_page
                        "
                        @click="
                            navigate(
                                exportRequests.meta
                                    .current_page + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
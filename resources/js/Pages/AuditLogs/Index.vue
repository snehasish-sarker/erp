<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
} from 'vue';
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    AuditActorOption,
    AuditFilterOption,
    AuditLogFilters,
    AuditLogPagination,
    AuditLogSort,
} from '@/Types/audit-log';
import AuditLogExportButton from '@/Pages/AuditLogs/Partials/AuditLogExportButton.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    auditLogs: AuditLogPagination;
    filters: AuditLogFilters;
    eventOptions: AuditFilterOption[];
    subjectTypeOptions: AuditFilterOption[];
    actorOptions: AuditActorOption[];
    tenantTimezone: string;
}>();

const filterForm = reactive<AuditLogFilters>({
    search: props.filters.search,
    event: props.filters.event,
    subject_type: props.filters.subject_type,
    actor: props.filters.actor,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean =>
        filterForm.search !== ''
        || filterForm.event !== ''
        || filterForm.subject_type !== ''
        || filterForm.actor !== ''
        || filterForm.date_from !== ''
        || filterForm.date_to !== '',
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/audit-logs',
        {
            search: filterForm.search,
            event: filterForm.event,
            subject_type: filterForm.subject_type,
            actor: filterForm.actor,
            date_from: filterForm.date_from,
            date_to: filterForm.date_to,
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
    filterForm.event = '';
    filterForm.subject_type = '';
    filterForm.actor = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.sort = 'created_at';
    filterForm.direction = 'desc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (
    column: AuditLogSort,
): void => {
    if (filterForm.sort === column) {
        filterForm.direction =
            filterForm.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction =
            column === 'created_at'
                ? 'desc'
                : 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: AuditLogSort,
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: props.tenantTimezone,
        },
    ).format(new Date(value));
};

const formatEvent = (
    event: string,
): string => event
    .replace(/[._-]+/g, ' ')
    .replace(
        /\b\w/g,
        (character: string): string =>
            character.toUpperCase(),
    );

const eventBadgeClass = (
    event: string,
): string => {
    if (
        event.includes('delete')
        || event.includes('reverse')
        || event.includes('fail')
    ) {
        return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
    }

    if (
        event.includes('create')
        || event.includes('restore')
        || event.includes('login')
        || event.includes('approve')
        || event.includes('post')
    ) {
        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
    }

    if (
        event.includes('update')
        || event.includes('password')
        || event.includes('role')
        || event.includes('permission')
    ) {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
    }

    return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
};
</script>

<template>
    <Head title="Audit Logs" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Audit Logs
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Review immutable activity records for this tenant.
                Times are displayed in {{ tenantTimezone }}.
            </p>
        </div>

        <AuditLogExportButton
            :filters="filters"
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="space-y-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"
                @submit.prevent="applyFilters"
            >
                <div
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div>
                        <label
                            for="audit-search"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Search
                        </label>

                        <input
                            id="audit-search"
                            v-model="filterForm.search"
                            type="search"
                            placeholder="Actor, subject, request ID, route, or IP"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        >
                    </div>

                    <div>
                        <label
                            for="audit-event"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Event
                        </label>

                        <select
                            id="audit-event"
                            v-model="filterForm.event"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option value="">
                                All events
                            </option>

                            <option
                                v-for="option in eventOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="audit-subject"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Subject type
                        </label>

                        <select
                            id="audit-subject"
                            v-model="filterForm.subject_type"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option value="">
                                All subjects
                            </option>

                            <option
                                v-for="option in subjectTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="audit-actor"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Actor
                        </label>

                        <select
                            id="audit-actor"
                            v-model="filterForm.actor"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option value="">
                                All actors
                            </option>

                            <option value="system">
                                System or background process
                            </option>

                            <option
                                v-for="actor in actorOptions"
                                :key="actor.value"
                                :value="actor.value"
                            >
                                {{ actor.name }}
                                <template v-if="actor.email">
                                    — {{ actor.email }}
                                </template>
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-[180px_180px_130px_auto]"
                >
                    <div>
                        <label
                            for="audit-date-from"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Date from
                        </label>

                        <input
                            id="audit-date-from"
                            v-model="filterForm.date_from"
                            type="date"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                    </div>

                    <div>
                        <label
                            for="audit-date-to"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Date to
                        </label>

                        <input
                            id="audit-date-to"
                            v-model="filterForm.date_to"
                            type="date"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                    </div>

                    <div>
                        <label
                            for="audit-per-page"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Per page
                        </label>

                        <select
                            id="audit-per-page"
                            v-model.number="filterForm.per_page"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
                        >
                            Apply filters
                        </button>

                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('created_at')"
                                >
                                    Time
                                    {{ sortIndicator('created_at') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('event')"
                                >
                                    Event
                                    {{ sortIndicator('event') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('actor_name')"
                                >
                                    Actor
                                    {{ sortIndicator('actor_name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('subject_label')"
                                >
                                    Subject
                                    {{ sortIndicator('subject_label') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Request context
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Changes
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="auditLog in auditLogs.data"
                            :key="auditLog.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td
                                class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                {{ formatDate(auditLog.created_at) }}
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="eventBadgeClass(
                                        auditLog.event,
                                    )"
                                >
                                    {{ formatEvent(auditLog.event) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <template v-if="auditLog.actor_name">
                                    <div
                                        class="text-sm font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ auditLog.actor_name }}
                                    </div>

                                    <div
                                        v-if="auditLog.actor_email"
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ auditLog.actor_email }}
                                    </div>
                                </template>

                                <span
                                    v-else
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    System
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="text-sm font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        auditLog.subject_label
                                            ?? auditLog.subject_type_label
                                    }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ auditLog.subject_type_label }}

                                    <template
                                        v-if="auditLog.subject_id !== null"
                                    >
                                        #{{ auditLog.subject_id }}
                                    </template>
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400"
                                >
                                    <span
                                        v-if="auditLog.http_method"
                                        class="rounded bg-gray-100 px-1.5 py-0.5 font-semibold dark:bg-white/10"
                                    >
                                        {{ auditLog.http_method }}
                                    </span>

                                    <span>
                                        {{ auditLog.route_name ?? '—' }}
                                    </span>
                                </div>

                                <div
                                    v-if="auditLog.ip_address"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    IP: {{ auditLog.ip_address }}
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                {{
                                    auditLog.changes_count === 0
                                        ? '—'
                                        : auditLog.changes_count
                                }}
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex justify-end">
                                    <Link
                                        :href="`/erp/audit-logs/${auditLog.id}`"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        View
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="auditLogs.data.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    No audit logs found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or perform an
                                    audited action.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ auditLogs.meta.from ?? 0 }}–{{
                        auditLogs.meta.to ?? 0
                    }}
                    of {{ auditLogs.meta.total }} records
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        :disabled="auditLogs.meta.current_page <= 1"
                        @click="
                            navigate(
                                auditLogs.meta.current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Page {{ auditLogs.meta.current_page }} of
                        {{ auditLogs.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        :disabled="
                            auditLogs.meta.current_page
                                >= auditLogs.meta.last_page
                        "
                        @click="
                            navigate(
                                auditLogs.meta.current_page + 1,
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
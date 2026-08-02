<script setup lang="ts">
import {
    Head,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    UserNotificationCategory,
    UserNotificationFilters,
    UserNotificationOption,
    UserNotificationPagination,
    UserNotificationReadStatus,
    UserNotificationRecord,
    UserNotificationSeverity,
} from '@/Types/notification';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    notificationPage: UserNotificationPagination;
    unreadCount: number;
    filters: UserNotificationFilters;

    categoryOptions:
        UserNotificationOption<UserNotificationCategory>[];

    severityOptions:
        UserNotificationOption<UserNotificationSeverity>[];

    statusOptions:
        UserNotificationOption<UserNotificationReadStatus>[];
}>();

const filterForm =
    reactive<UserNotificationFilters>({
        search: props.filters.search,
        category: props.filters.category,
        severity: props.filters.severity,
        status: props.filters.status,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
    });

const processingNotificationId:
    Ref<number | null> = ref(null);

const markingAllRead: Ref<boolean> =
    ref(false);

const hasActiveFilters:
    ComputedRef<boolean> = computed(
        (): boolean =>
            filterForm.search !== ''
            || filterForm.category !== ''
            || filterForm.severity !== ''
            || filterForm.status !== '',
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/notifications',
        {
            search: filterForm.search,
            category: filterForm.category,
            severity: filterForm.severity,
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
    filterForm.category = '';
    filterForm.severity = '';
    filterForm.status = '';
    filterForm.sort = 'created_at';
    filterForm.direction = 'desc';
    filterForm.per_page = 25;

    navigate();
};

const markRead = (
    notification: UserNotificationRecord,
): void => {
    if (notification.is_read) {
        return;
    }

    processingNotificationId.value =
        notification.id;

    router.patch(
        `/erp/notifications/${notification.id}/read`,
        {},
        {
            preserveScroll: true,

            onFinish: (): void => {
                processingNotificationId.value =
                    null;
            },
        },
    );
};

const openNotification = (
    notification: UserNotificationRecord,
): void => {
    const openAction = (): void => {
        if (notification.action_url !== null) {
            router.visit(
                notification.action_url,
            );
        }
    };

    if (notification.is_read) {
        openAction();

        return;
    }

    processingNotificationId.value =
        notification.id;

    router.patch(
        `/erp/notifications/${notification.id}/read`,
        {},
        {
            preserveScroll: true,

            onSuccess: (): void => {
                openAction();
            },

            onFinish: (): void => {
                processingNotificationId.value =
                    null;
            },
        },
    );
};

const markAllRead = (): void => {
    if (
        markingAllRead.value
        || props.unreadCount === 0
    ) {
        return;
    }

    markingAllRead.value = true;

    router.patch(
        '/erp/notifications/read-all',
        {},
        {
            preserveScroll: true,

            onFinish: (): void => {
                markingAllRead.value = false;
            },
        },
    );
};

const severityBadgeClass = (
    severity: UserNotificationSeverity,
): string => {
    const classes: Record<
        UserNotificationSeverity,
        string
    > = {
        info: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
        success: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        warning: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        error: 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
    };

    return classes[severity];
};

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
</script>

<template>
    <Head title="Notifications" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        Notifications
                    </h1>

                    <span
                        v-if="unreadCount > 0"
                        class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                    >
                        {{ unreadCount }} unread
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Review approval requests, operational
                    warnings, exports, and ERP activity.
                </p>
            </div>

            <button
                v-if="unreadCount > 0"
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                :disabled="markingAllRead"
                @click="markAllRead"
            >
                {{
                    markingAllRead
                        ? 'Marking...'
                        : 'Mark all as read'
                }}
            </button>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-5"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="notification-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="notification-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Title, message, actor, or source"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="notification-category"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Category
                    </label>

                    <select
                        id="notification-category"
                        v-model="filterForm.category"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All categories
                        </option>

                        <option
                            v-for="option in categoryOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="notification-severity"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Severity
                    </label>

                    <select
                        id="notification-severity"
                        v-model="filterForm.severity"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All severities
                        </option>

                        <option
                            v-for="option in severityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="notification-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="notification-status"
                        v-model="filterForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="notification-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="notification-per-page"
                        v-model.number="filterForm.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex items-end gap-3 sm:col-span-2 xl:col-span-4"
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

            <div
                class="divide-y divide-gray-100 dark:divide-gray-800"
            >
                <article
                    v-for="notification in notificationPage.data"
                    :key="notification.id"
                    class="p-5 sm:p-6"
                    :class="
                        !notification.is_read
                            ? 'bg-brand-50/30 dark:bg-brand-500/[0.04]'
                            : ''
                    "
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold uppercase"
                            :class="
                                severityBadgeClass(
                                    notification.severity,
                                )
                            "
                        >
                            {{
                                notification.category
                                    .charAt(0)
                            }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="font-semibold text-gray-800 dark:text-white/90"
                                        >
                                            {{ notification.title }}
                                        </h2>

                                        <span
                                            v-if="
                                                !notification.is_read
                                            "
                                            class="inline-flex rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300"
                                        >
                                            Unread
                                        </span>

                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                severityBadgeClass(
                                                    notification.severity,
                                                )
                                            "
                                        >
                                            {{
                                                notification
                                                    .category_label
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300"
                                    >
                                        {{ notification.message }}
                                    </p>

                                    <div
                                        class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        <span
                                            v-if="
                                                notification.actor
                                                    .name !== null
                                            "
                                        >
                                            By
                                            {{
                                                notification.actor
                                                    .name
                                            }}
                                        </span>

                                        <span>
                                            {{
                                                formatDateTime(
                                                    notification
                                                        .created_at,
                                                )
                                            }}
                                        </span>

                                        <span
                                            v-if="
                                                notification
                                                    .source_id
                                                !== null
                                            "
                                            class="font-mono"
                                        >
                                            {{
                                                notification
                                                    .source_id
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 items-center gap-3"
                                >
                                    <button
                                        v-if="
                                            !notification.is_read
                                        "
                                        type="button"
                                        class="text-sm font-medium text-gray-600 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                                        :disabled="
                                            processingNotificationId
                                            === notification.id
                                        "
                                        @click="
                                            markRead(
                                                notification,
                                            )
                                        "
                                    >
                                        Mark read
                                    </button>

                                    <button
                                        v-if="
                                            notification.action_url
                                            !== null
                                        "
                                        type="button"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-brand-400"
                                        :disabled="
                                            processingNotificationId
                                            === notification.id
                                        "
                                        @click="
                                            openNotification(
                                                notification,
                                            )
                                        "
                                    >
                                        {{
                                            notification
                                                .action_label
                                            ?? 'Open'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <div
                    v-if="
                        notificationPage.data.length === 0
                    "
                    class="px-5 py-16 text-center"
                >
                    <p
                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        No notifications found.
                    </p>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        New ERP activity will appear here.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        notificationPage.meta.from
                        ?? 0
                    }}–{{
                        notificationPage.meta.to
                        ?? 0
                    }}
                    of
                    {{ notificationPage.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            notificationPage.meta
                                .current_page <= 1
                        "
                        @click="
                            navigate(
                                notificationPage.meta
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
                            notificationPage.meta
                                .current_page
                        }}
                        of
                        {{
                            notificationPage.meta
                                .last_page
                        }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            notificationPage.meta
                                .current_page
                            >= notificationPage.meta
                                .last_page
                        "
                        @click="
                            navigate(
                                notificationPage.meta
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
<script setup lang="ts">
import {
    Link,
    router,
    usePage,
} from '@inertiajs/vue3';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import type {
    SharedHeaderNotifications,
    UserNotificationRecord,
    UserNotificationSeverity,
} from '@/Types/notification';

const page = usePage();

const dropdownOpen: Ref<boolean> = ref(false);

const dropdownRef: Ref<HTMLElement | null> =
    ref(null);

const processingNotificationId:
    Ref<number | null> = ref(null);

const markingAllRead: Ref<boolean> =
    ref(false);

let pollingTimer: number | null = null;

const headerNotifications:
    ComputedRef<SharedHeaderNotifications> =
    computed(
        (): SharedHeaderNotifications =>
            page.props.headerNotifications,
    );

const unreadCount: ComputedRef<number> =
    computed(
        (): number =>
            headerNotifications.value
                .unread_count,
    );

const notifications:
    ComputedRef<UserNotificationRecord[]> =
    computed(
        (): UserNotificationRecord[] =>
            headerNotifications.value.items,
    );

const displayedUnreadCount:
    ComputedRef<string> = computed(
        (): string =>
            unreadCount.value > 99
                ? '99+'
                : String(unreadCount.value),
    );

const toggleDropdown = (): void => {
    dropdownOpen.value =
        !dropdownOpen.value;

    if (dropdownOpen.value) {
        refreshNotifications();
    }
};

const closeDropdown = (): void => {
    dropdownOpen.value = false;
};

const refreshNotifications = (): void => {
    router.reload({
        only: [
            'headerNotifications',
        ],
        preserveScroll: true,
        preserveState: true,
    });
};

const openNotification = (
    notification: UserNotificationRecord,
): void => {
    const visitAction = (): void => {
        closeDropdown();

        if (notification.action_url !== null) {
            router.visit(
                notification.action_url,
            );
        }
    };

    if (notification.is_read) {
        visitAction();

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
                visitAction();
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
        || unreadCount.value === 0
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

const severityCircleClass = (
    severity: UserNotificationSeverity,
): string => {
    const classes: Record<
        UserNotificationSeverity,
        string
    > = {
        info: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
        success: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        warning: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
        error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
    };

    return classes[severity];
};

const relativeTime = (
    value: string | null,
): string => {
    if (value === null) {
        return '';
    }

    const timestamp = new Date(value)
        .getTime();

    const difference = Math.max(
        0,
        Date.now() - timestamp,
    );

    const minutes = Math.floor(
        difference / 60_000,
    );

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(
        minutes / 60,
    );

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(
        hours / 24,
    );

    if (days < 30) {
        return `${days}d ago`;
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        },
    ).format(new Date(value));
};

const handleClickOutside = (
    event: MouseEvent,
): void => {
    const target = event.target;

    if (
        !(target instanceof Node)
        || dropdownRef.value === null
        || dropdownRef.value.contains(target)
    ) {
        return;
    }

    closeDropdown();
};

const handleEscape = (
    event: KeyboardEvent,
): void => {
    if (event.key === 'Escape') {
        closeDropdown();
    }
};

onMounted((): void => {
    document.addEventListener(
        'click',
        handleClickOutside,
    );

    document.addEventListener(
        'keydown',
        handleEscape,
    );

    pollingTimer = window.setInterval(
        refreshNotifications,
        30_000,
    );
});

onUnmounted((): void => {
    document.removeEventListener(
        'click',
        handleClickOutside,
    );

    document.removeEventListener(
        'keydown',
        handleEscape,
    );

    if (pollingTimer !== null) {
        window.clearInterval(
            pollingTimer,
        );
    }
});
</script>

<template>
    <div
        ref="dropdownRef"
        class="relative"
    >
        <button
            type="button"
            class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            aria-label="Open notifications"
            :aria-expanded="dropdownOpen"
            @click="toggleDropdown"
        >
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 z-10 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-error-500 px-1 text-[10px] font-semibold leading-none text-white dark:border-gray-900"
            >
                {{ displayedUnreadCount }}
            </span>

            <svg
                class="fill-current"
                width="20"
                height="20"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                />
            </svg>
        </button>

        <div
            v-if="dropdownOpen"
            class="absolute -right-[240px] mt-[17px] flex h-[500px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark sm:w-[380px] lg:right-0"
        >
            <div
                class="mb-3 flex items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800"
            >
                <div>
                    <h5
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        Notifications
                    </h5>

                    <p
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ unreadCount }}
                        unread
                    </p>
                </div>

                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-brand-400"
                    :disabled="markingAllRead"
                    @click="markAllRead"
                >
                    {{
                        markingAllRead
                            ? 'Marking...'
                            : 'Mark all read'
                    }}
                </button>
            </div>

            <div
                class="custom-scrollbar flex-1 overflow-y-auto"
            >
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full gap-3 rounded-xl border-b border-gray-100 px-3 py-3 text-left transition hover:bg-gray-100 disabled:cursor-wait disabled:opacity-60 dark:border-gray-800 dark:hover:bg-white/5"
                    :class="
                        !notification.is_read
                            ? 'bg-brand-50/40 dark:bg-brand-500/[0.05]'
                            : ''
                    "
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
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold uppercase"
                        :class="
                            severityCircleClass(
                                notification.severity,
                            )
                        "
                    >
                        {{
                            notification.category
                                .charAt(0)
                        }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span
                            class="flex items-start justify-between gap-2"
                        >
                            <span
                                class="line-clamp-1 text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ notification.title }}
                            </span>

                            <span
                                v-if="
                                    !notification.is_read
                                "
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"
                            />
                        </span>

                        <span
                            class="mt-1 line-clamp-2 block text-xs leading-5 text-gray-500 dark:text-gray-400"
                        >
                            {{ notification.message }}
                        </span>

                        <span
                            class="mt-2 flex items-center gap-2 text-xs text-gray-400"
                        >
                            <span>
                                {{
                                    notification
                                        .category_label
                                }}
                            </span>

                            <span
                                class="h-1 w-1 rounded-full bg-gray-400"
                            />

                            <span>
                                {{
                                    relativeTime(
                                        notification
                                            .created_at,
                                    )
                                }}
                            </span>
                        </span>
                    </span>
                </button>

                <div
                    v-if="notifications.length === 0"
                    class="flex h-full flex-col items-center justify-center px-6 text-center"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400"
                    >
                        <svg
                            class="fill-current"
                            width="22"
                            height="22"
                            viewBox="0 0 20 20"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875Z"
                            />
                        </svg>
                    </div>

                    <p
                        class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        No notifications
                    </p>

                    <p
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        New ERP activity will appear here.
                    </p>
                </div>
            </div>

            <Link
                href="/erp/notifications"
                class="mt-3 flex justify-center rounded-lg border border-gray-300 bg-white p-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                @click="closeDropdown"
            >
                View all notifications
            </Link>
        </div>
    </div>
</template>
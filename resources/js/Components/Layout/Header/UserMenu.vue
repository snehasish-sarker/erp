<script setup lang="ts">
import {
    Link,
    usePage,
} from '@inertiajs/vue3';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
} from 'vue';
import type { ComputedRef } from 'vue';

const page = usePage();

const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const userName: ComputedRef<string> = computed(
    (): string => page.props.auth.user?.name ?? 'ERP User',
);

const userEmail: ComputedRef<string> = computed(
    (): string => page.props.auth.user?.email ?? '',
);

const userAvatar: ComputedRef<string> = computed(
    (): string =>
        page.props.auth.user?.avatar
        ?? '/images/user/owner.jpg',
);

const tenantName: ComputedRef<string> = computed(
    (): string =>
        page.props.auth.tenant?.name
        ?? 'Wholesale Distribution ERP',
);

const tenantCode: ComputedRef<string> = computed(
    (): string => page.props.auth.tenant?.code ?? '',
);

const toggleDropdown = (): void => {
    dropdownOpen.value = !dropdownOpen.value;
};

const closeDropdown = (): void => {
    dropdownOpen.value = false;
};

const handleClickOutside = (event: MouseEvent): void => {
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

const handleEscape = (event: KeyboardEvent): void => {
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
});
</script>

<template>
    <div
        ref="dropdownRef"
        class="relative"
    >
        <button
            type="button"
            class="flex items-center text-gray-700 dark:text-gray-400"
            aria-label="Open user menu"
            :aria-expanded="dropdownOpen"
            @click="toggleDropdown"
        >
            <span
                class="mr-3 h-11 w-11 overflow-hidden rounded-full"
            >
                <img
                    :src="userAvatar"
                    :alt="userName"
                    class="h-full w-full object-cover"
                >
            </span>

            <span
                class="mr-1 hidden max-w-36 truncate font-medium text-theme-sm sm:block"
            >
                {{ userName }}
            </span>

            <svg
                class="stroke-gray-500 transition-transform duration-200 dark:stroke-gray-400"
                :class="{ 'rotate-180': dropdownOpen }"
                width="18"
                height="18"
                viewBox="0 0 18 18"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <path
                    d="M4.3125 6.65625L9 11.3437L13.6875 6.65625"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>

        <div
            v-if="dropdownOpen"
            class="absolute right-0 mt-[17px] flex w-[280px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        >
            <div class="px-2 py-1">
                <span
                    class="block truncate font-medium text-gray-700 text-theme-sm dark:text-gray-300"
                >
                    {{ userName }}
                </span>

                <span
                    class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ userEmail }}
                </span>
            </div>

            <div
                class="mt-3 rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/[0.03]"
            >
                <p
                    class="truncate text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ tenantName }}
                </p>

                <p
                    v-if="tenantCode"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                >
                    Company code: {{ tenantCode }}
                </p>
            </div>

            <ul
                class="mt-3 flex flex-col gap-1 border-b border-gray-200 pb-3 dark:border-gray-800"
            >
                <li>
                    <button
                        type="button"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left font-medium text-gray-700 text-theme-sm hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        @click="closeDropdown"
                    >
                        <svg
                            class="stroke-gray-500 group-hover:stroke-gray-700 dark:group-hover:stroke-gray-300"
                            width="20"
                            height="20"
                            viewBox="0 0 20 20"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 10.625C12.0711 10.625 13.75 8.94607 13.75 6.875C13.75 4.80393 12.0711 3.125 10 3.125C7.92893 3.125 6.25 4.80393 6.25 6.875C6.25 8.94607 7.92893 10.625 10 10.625Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />

                            <path
                                d="M3.75 17.5C3.75 14.0482 6.54822 11.25 10 11.25C13.4518 11.25 16.25 14.0482 16.25 17.5"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>

                        My profile
                    </button>
                </li>

                <li>
                    <button
                        type="button"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left font-medium text-gray-700 text-theme-sm hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        @click="closeDropdown"
                    >
                        <svg
                            class="stroke-gray-500 group-hover:stroke-gray-700 dark:group-hover:stroke-gray-300"
                            width="20"
                            height="20"
                            viewBox="0 0 20 20"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 12.5C11.3807 12.5 12.5 11.3807 12.5 10C12.5 8.61929 11.3807 7.5 10 7.5C8.61929 7.5 7.5 8.61929 7.5 10C7.5 11.3807 8.61929 12.5 10 12.5Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />

                            <path
                                d="M16.25 10C16.25 9.64583 16.2208 9.3 16.1646 8.9625L17.5 7.91667L15.8333 5.02917L14.2667 5.65833C13.7333 5.2125 13.125 4.85833 12.4583 4.61667L12.25 2.91667H8.91667L8.70833 4.61667C8.04167 4.85833 7.43333 5.2125 6.9 5.65833L5.33333 5.02917L3.66667 7.91667L5.00208 8.9625C4.94583 9.3 4.91667 9.64583 4.91667 10C4.91667 10.3542 4.94583 10.7 5.00208 11.0375L3.66667 12.0833L5.33333 14.9708L6.9 14.3417C7.43333 14.7875 8.04167 15.1417 8.70833 15.3833L8.91667 17.0833H12.25L12.4583 15.3833C13.125 15.1417 13.7333 14.7875 14.2667 14.3417L15.8333 14.9708L17.5 12.0833L16.1646 11.0375C16.2208 10.7 16.25 10.3542 16.25 10Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linejoin="round"
                            />
                        </svg>

                        Account settings
                    </button>
                </li>
            </ul>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="group mt-3 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left font-medium text-gray-700 text-theme-sm hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                @click="closeDropdown"
            >
                <svg
                    class="stroke-gray-500 group-hover:stroke-gray-700 dark:group-hover:stroke-gray-300"
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path
                        d="M7.5 3.33333H4.16667C3.70643 3.33333 3.33333 3.70643 3.33333 4.16667V15.8333C3.33333 16.2936 3.70643 16.6667 4.16667 16.6667H7.5"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />

                    <path
                        d="M12.5 13.3333L15.8333 10L12.5 6.66667"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <path
                        d="M15.8333 10H7.5"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />
                </svg>

                Sign out
            </Link>
        </div>
    </div>
</template>
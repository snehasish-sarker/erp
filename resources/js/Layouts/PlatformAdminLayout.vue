<script setup lang="ts">
import {
    Link,
    router,
    usePage,
} from '@inertiajs/vue3';
import {
    computed,
} from 'vue';
import ThemeProvider from '@/Components/Layout/ThemeProvider.vue';
import ThemeToggler from '@/Components/Common/ThemeToggler.vue';

const page = usePage();

const tenantSectionActive = computed(
    (): boolean => page.url.startsWith('/super-admin/tenants'),
);

const planSectionActive = computed(
    (): boolean => page.url.startsWith('/super-admin/plans'),
);

const billingSectionActive = computed(
    (): boolean => page.url.startsWith('/super-admin/billing'),
);

const dashboardActive = computed(
    (): boolean => page.url === '/super-admin'
        || page.url === '/super-admin/',
);

const logout = (): void => {
    router.post(
        route('platform.logout'),
    );
};
</script>

<template>
    <ThemeProvider>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
            <header
                class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"
                >
                    <div class="flex flex-wrap items-center gap-4">
                        <Link
                            :href="route('platform.dashboard')"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            ERP Super Admin
                        </Link>

                        <span
                            class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                        >
                            Platform Console
                        </span>

                        <nav class="flex flex-wrap items-center gap-1">
                            <Link
                                :href="route('platform.dashboard')"
                                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="dashboardActive
                                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/70 dark:hover:text-white'"
                            >
                                Dashboard
                            </Link>

                            <Link
                                :href="route('platform.tenants.index')"
                                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="tenantSectionActive
                                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/70 dark:hover:text-white'"
                            >
                                Tenants
                            </Link>

                            <Link
                                :href="route('platform.plans.index')"
                                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="planSectionActive
                                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/70 dark:hover:text-white'"
                            >
                                Plans
                            </Link>

                            <Link
                                :href="route('platform.billing.invoices.index')"
                                class="rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="billingSectionActive
                                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800/70 dark:hover:text-white'"
                            >
                                Billing
                            </Link>
                        </nav>
                    </div>

                    <div class="flex items-center gap-3">
                        <ThemeToggler />

                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </ThemeProvider>
</template>

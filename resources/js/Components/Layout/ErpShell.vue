<script setup lang="ts">
import AppHeader from '@/Components/Layout/AppHeader.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import Backdrop from '@/Components/Layout/Backdrop.vue';
import SubscriptionStatusBanner from '@/Components/Saas/SubscriptionStatusBanner.vue';
import { useSidebar } from '@/Composables/useSidebar';

const {
    isExpanded,
    isHovered,
} = useSidebar();
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 xl:flex">
        <AppSidebar />

        <Backdrop />

        <div
            class="min-w-0 flex-1 transition-all duration-300 ease-in-out"
            :class="
                isExpanded || isHovered
                    ? 'lg:ml-[290px]'
                    : 'lg:ml-[90px]'
            "
        >
            <AppHeader />

            <SubscriptionStatusBanner />

            <main
                class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
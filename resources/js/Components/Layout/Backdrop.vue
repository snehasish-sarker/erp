<script setup lang="ts">
import { useSidebar } from '@/Composables/useSidebar';

const {
    isMobileOpen,
    closeMobileSidebar,
} = useSidebar();
</script>

<template>
    <div
        v-if="isMobileOpen"
        class="fixed inset-0 z-9999 bg-gray-900/50 lg:hidden"
        role="button"
        tabindex="0"
        aria-label="Close sidebar"
        @click="closeMobileSidebar"
        @keydown.escape="closeMobileSidebar"
    />
</template>
<script setup lang="ts">
import ToastNotification from '@/Components/Common/ToastNotification.vue';
import ErpShell from '@/Components/Layout/ErpShell.vue';
import SidebarProvider from '@/Components/Layout/SidebarProvider.vue';
import ThemeProvider from '@/Components/Layout/ThemeProvider.vue';
</script>

<template>
    <ThemeProvider>
        <SidebarProvider>
            <ErpShell>
                <slot />
            </ErpShell>
        </SidebarProvider>

        <ToastNotification />
    </ThemeProvider>
</template>
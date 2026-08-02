<script setup lang="ts">
import ThemeToggler from '@/Components/Common/ThemeToggler.vue';
import ThemeProvider from '@/Components/Layout/ThemeProvider.vue';
</script>

<template>
    <ThemeProvider>
        <div
            class="relative min-h-screen bg-gray-50 px-4 py-8 dark:bg-gray-900 sm:px-6"
        >
            <div class="absolute top-5 right-5">
                <ThemeToggler />
            </div>

            <div
                class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md items-center"
            >
                <div class="w-full">
                    <slot />
                </div>
            </div>
        </div>
    </ThemeProvider>
</template>
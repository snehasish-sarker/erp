<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        href="/"
        class="inline-flex lg:hidden"
        aria-label="Go to dashboard"
    >
        <img
            class="h-8 w-auto dark:hidden"
            src="/images/logo/logo.svg"
            alt="ERP"
        >

        <img
            class="hidden h-8 w-auto dark:block"
            src="/images/logo/logo-dark.svg"
            alt="ERP"
        >
    </Link>
</template>
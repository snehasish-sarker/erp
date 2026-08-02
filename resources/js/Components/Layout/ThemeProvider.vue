<script setup lang="ts">
import { provideTheme } from '@/Composables/useTheme';

provideTheme();
</script>

<template>
    <slot />
</template>
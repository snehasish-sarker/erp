<script setup lang="ts">
import { computed } from 'vue';
import { useSaasEntitlements } from '@/Composables/useSaasEntitlements';

const {
    subscription,
} = useSaasEntitlements();

const isPastDue = computed(
    (): boolean => subscription.value?.status === 'past_due',
);

const graceEndsLabel = computed((): string => {
    const value = subscription.value?.grace_ends_at;

    if (value === null || value === undefined) {
        return 'soon';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
});
</script>

<template>
    <div
        v-if="isPastDue"
        class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
    >
        <div class="mx-auto max-w-(--breakpoint-2xl)">
            <span class="font-semibold">Subscription payment is past due.</span>
            ERP access remains available during the grace period until
            <span class="font-semibold">{{ graceEndsLabel }}</span>.
            After that time the company account will be suspended automatically unless the subscription is restored.
        </div>
    </div>
</template>

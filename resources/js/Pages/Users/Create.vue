<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import UserForm from '@/Pages/Users/Partials/UserForm.vue';
import type {
    UserBranchOption,
    UserRoleOption,
    UserStatusOption,
} from '@/Types/user';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    branchOptions: UserBranchOption[];
    roleOptions: UserRoleOption[];
    statusOptions: UserStatusOption[];
}>();
</script>

<template>
    <Head title="Create User" />

    <UserForm
        mode="create"
        :branch-options="branchOptions"
        :role-options="roleOptions"
        :status-options="statusOptions"
    />
</template>
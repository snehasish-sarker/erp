<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import UserForm from '@/Pages/Users/Partials/UserForm.vue';
import type {
    ManagedUserRecord,
    UserBranchOption,
    UserRoleOption,
} from '@/Types/user';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    managedUser: ManagedUserRecord;
    branchOptions: UserBranchOption[];
    roleOptions: UserRoleOption[];
}>();
</script>

<template>
    <Head :title="`Edit ${managedUser.name}`" />

    <UserForm
        mode="edit"
        :managed-user="managedUser"
        :branch-options="branchOptions"
        :role-options="roleOptions"
        :status-options="[]"
    />
</template>
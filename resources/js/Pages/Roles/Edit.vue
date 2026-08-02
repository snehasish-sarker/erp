<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
} from 'vue';
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    RoleAbilities,
    RoleEditRecord,
    RolePermission,
    RolePermissionGroup,
} from '@/Types/role';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    role: RoleEditRecord;
    permissionGroups: RolePermissionGroup[];
    abilities: RoleAbilities;
}>();

const permissionSearch = ref<string>('');

const detailsForm = useForm<{
    name: string;
}>({
    name: props.role.name,
});

const permissionForm = useForm<{
    permission_ids: number[];
}>({
    permission_ids: [...props.role.permission_ids],
});

const permissionsLocked: ComputedRef<boolean> = computed(
    (): boolean =>
        props.role.is_tenant_owner
        || !props.abilities.assign_permissions,
);

const selectedPermissionSet: ComputedRef<Set<number>> = computed(
    (): Set<number> =>
        new Set(permissionForm.permission_ids),
);

const selectedPermissionCount: ComputedRef<number> = computed(
    (): number => permissionForm.permission_ids.length,
);

const filteredGroups: ComputedRef<RolePermissionGroup[]> = computed(
    (): RolePermissionGroup[] => {
        const search = permissionSearch.value
            .trim()
            .toLowerCase();

        if (search === '') {
            return props.permissionGroups;
        }

        return props.permissionGroups
            .map(
                (
                    group: RolePermissionGroup,
                ): RolePermissionGroup => ({
                    ...group,

                    permissions: group.permissions.filter(
                        (
                            permission: RolePermission,
                        ): boolean =>
                            permission.label
                                .toLowerCase()
                                .includes(search)
                            || permission.name
                                .toLowerCase()
                                .includes(search)
                            || group.label
                                .toLowerCase()
                                .includes(search),
                    ),
                }),
            )
            .filter(
                (group: RolePermissionGroup): boolean =>
                    group.permissions.length > 0,
            );
    },
);

const visiblePermissionIds: ComputedRef<number[]> = computed(
    (): number[] => filteredGroups.value.flatMap(
        (group: RolePermissionGroup): number[] =>
            group.permissions.map(
                (permission: RolePermission): number =>
                    permission.id,
            ),
    ),
);

const allVisibleSelected: ComputedRef<boolean> = computed(
    (): boolean =>
        visiblePermissionIds.value.length > 0
        && visiblePermissionIds.value.every(
            (permissionId: number): boolean =>
                selectedPermissionSet.value.has(permissionId),
        ),
);

const groupIsSelected = (
    group: RolePermissionGroup,
): boolean => group.permissions.length > 0
    && group.permissions.every(
        (permission: RolePermission): boolean =>
            selectedPermissionSet.value.has(permission.id),
    );

const toggleGroup = (
    group: RolePermissionGroup,
): void => {
    if (permissionsLocked.value) {
        return;
    }

    const selected = new Set(
        permissionForm.permission_ids,
    );

    const shouldClear = groupIsSelected(group);

    group.permissions.forEach(
        (permission: RolePermission): void => {
            if (shouldClear) {
                selected.delete(permission.id);

                return;
            }

            selected.add(permission.id);
        },
    );

    permissionForm.permission_ids = Array.from(selected)
        .sort(
            (
                first: number,
                second: number,
            ): number => first - second,
        );
};

const toggleVisiblePermissions = (): void => {
    if (permissionsLocked.value) {
        return;
    }

    const selected = new Set(
        permissionForm.permission_ids,
    );

    visiblePermissionIds.value.forEach(
        (permissionId: number): void => {
            if (allVisibleSelected.value) {
                selected.delete(permissionId);

                return;
            }

            selected.add(permissionId);
        },
    );

    permissionForm.permission_ids = Array.from(selected)
        .sort(
            (
                first: number,
                second: number,
            ): number => first - second,
        );
};

const clearPermissions = (): void => {
    if (permissionsLocked.value) {
        return;
    }

    permissionForm.permission_ids = [];
};

const submitDetails = (): void => {
    detailsForm.patch(
        `/erp/roles/${props.role.id}`,
        {
            preserveScroll: true,
        },
    );
};

const submitPermissions = (): void => {
    permissionForm.patch(
        `/erp/roles/${props.role.id}/permissions`,
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="`Role: ${role.name}`" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ role.name }}
                    </h1>

                    <span
                        v-if="role.is_system"
                        class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                    >
                        System role
                    </span>

                    <span
                        v-else
                        class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400"
                    >
                        Custom role
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Review the role identity and its effective permission
                    assignments.
                </p>
            </div>

            <Link
                href="/erp/roles"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
            >
                Back to roles
            </Link>
        </div>

        <form
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="submitDetails"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Role identity
                </h2>
            </div>

            <div class="p-5 sm:p-6">
                <label
                    for="role-name"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Role name
                </label>

                <input
                    id="role-name"
                    v-model="detailsForm.name"
                    type="text"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800 dark:disabled:bg-white/[0.02]"
                    :class="detailsForm.errors.name
                        ? 'border-error-500'
                        : ''"
                    :disabled="
                        role.is_system
                        || !abilities.update_details
                    "
                >

                <p
                    v-if="detailsForm.errors.name"
                    class="mt-1.5 text-sm text-error-500"
                >
                    {{ detailsForm.errors.name }}
                </p>

                <p
                    v-if="role.is_system"
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Seeded system role names cannot be changed.
                </p>

                <p
                    v-else-if="!abilities.update_details"
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    You can inspect this role but do not have permission
                    to rename it.
                </p>
            </div>

            <div
                v-if="
                    !role.is_system
                    && abilities.update_details
                "
                class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="
                        detailsForm.processing
                        || !detailsForm.isDirty
                    "
                >
                    {{
                        detailsForm.processing
                            ? 'Saving...'
                            : 'Save role name'
                    }}
                </button>
            </div>
        </form>

        <form
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="submitPermissions"
        >
            <div
                class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        Permissions
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ selectedPermissionCount }} permissions selected
                    </p>
                </div>

                <input
                    v-model="permissionSearch"
                    type="search"
                    placeholder="Search permissions"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:w-72"
                >
            </div>

            <div
                v-if="role.is_tenant_owner"
                class="m-5 rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/20 dark:bg-brand-500/10 sm:m-6"
            >
                <p
                    class="text-sm leading-6 text-brand-700 dark:text-brand-300"
                >
                    Tenant Owner is the protected full-access role.
                    Its permissions cannot be removed from this screen.
                </p>
            </div>

            <div
                v-else-if="!abilities.assign_permissions"
                class="m-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02] sm:m-6"
            >
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Permission assignments are read-only because you do
                    not have the roles.assign_permissions permission.
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                    :disabled="
                        permissionsLocked
                        || visiblePermissionIds.length === 0
                    "
                    @click="toggleVisiblePermissions"
                >
                    {{
                        allVisibleSelected
                            ? 'Clear visible'
                            : 'Select visible'
                    }}
                </button>

                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-error-300 bg-white px-3 text-sm font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-error-500/40 dark:bg-gray-800 dark:text-error-400"
                    :disabled="
                        permissionsLocked
                        || permissionForm.permission_ids.length === 0
                    "
                    @click="clearPermissions"
                >
                    Clear all
                </button>
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                <section
                    v-for="group in filteredGroups"
                    :key="group.key"
                    class="rounded-xl border border-gray-200 dark:border-gray-800"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <div>
                            <h3
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ group.label }}
                            </h3>

                            <p
                                class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ group.permissions.length }}
                                permissions
                            </p>
                        </div>

                        <button
                            type="button"
                            class="text-sm font-medium text-brand-600 disabled:cursor-not-allowed disabled:text-gray-400 dark:text-brand-400"
                            :disabled="permissionsLocked"
                            @click="toggleGroup(group)"
                        >
                            {{
                                groupIsSelected(group)
                                    ? 'Clear group'
                                    : 'Select group'
                            }}
                        </button>
                    </div>

                    <div
                        class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <label
                            v-for="permission in group.permissions"
                            :key="permission.id"
                            class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 transition dark:border-gray-800"
                            :class="
                                permissionsLocked
                                    ? 'cursor-default'
                                    : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                            "
                        >
                            <input
                                v-model="permissionForm.permission_ids"
                                type="checkbox"
                                :value="permission.id"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-900"
                                :disabled="permissionsLocked"
                            >

                            <span>
                                <span
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ permission.label }}
                                </span>

                                <span
                                    class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ permission.name }}
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <div
                    v-if="filteredGroups.length === 0"
                    class="py-10 text-center"
                >
                    <p
                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        No matching permissions
                    </p>
                </div>

                <p
                    v-if="permissionForm.errors.permission_ids"
                    class="text-sm text-error-500"
                >
                    {{ permissionForm.errors.permission_ids }}
                </p>
            </div>

            <div
                v-if="!permissionsLocked"
                class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="permissionForm.processing"
                >
                    {{
                        permissionForm.processing
                            ? 'Saving...'
                            : 'Save permissions'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
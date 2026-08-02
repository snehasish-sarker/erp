import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

interface AuthorizationContext {
    roles: ComputedRef<readonly string[]>;
    permissions: ComputedRef<readonly string[]>;
    can: (permission: string) => boolean;
    canAny: (permissions: readonly string[]) => boolean;
    canAll: (permissions: readonly string[]) => boolean;
    hasRole: (role: string) => boolean;
    hasAnyRole: (roles: readonly string[]) => boolean;
}

export function useAuthorization(): AuthorizationContext {
    const page = usePage();

    const roles: ComputedRef<readonly string[]> = computed(
        (): readonly string[] => page.props.auth.roles,
    );

    const permissions: ComputedRef<readonly string[]> = computed(
        (): readonly string[] => page.props.auth.permissions,
    );

    const permissionSet: ComputedRef<ReadonlySet<string>> = computed(
        (): ReadonlySet<string> => new Set(permissions.value),
    );

    const roleSet: ComputedRef<ReadonlySet<string>> = computed(
        (): ReadonlySet<string> => new Set(roles.value),
    );

    const can = (permission: string): boolean =>
        permissionSet.value.has(permission);

    const canAny = (
        requiredPermissions: readonly string[],
    ): boolean => requiredPermissions.some(can);

    const canAll = (
        requiredPermissions: readonly string[],
    ): boolean => requiredPermissions.every(can);

    const hasRole = (role: string): boolean =>
        roleSet.value.has(role);

    const hasAnyRole = (
        requiredRoles: readonly string[],
    ): boolean => requiredRoles.some(hasRole);

    return {
        roles,
        permissions,
        can,
        canAny,
        canAll,
        hasRole,
        hasAnyRole,
    };
}
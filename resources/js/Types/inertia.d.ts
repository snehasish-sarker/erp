import '@inertiajs/core';

import type {
    SharedHeaderNotifications,
} from '@/Types/notification';

import {
    route as routeFn,
} from 'ziggy-js';

type ZiggyConfig = NonNullable<
    Parameters<typeof routeFn>[3]
>;

type SharedZiggyConfig =
    Omit<ZiggyConfig, 'location'> & {
        location: string;
    };

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            appName: string;

            ziggy: SharedZiggyConfig;

            auth: {
                user: {
                    id: number;
                    name: string;
                    email: string;
                    status: string;
                    avatar: string | null;
                } | null;

                tenant: {
                    id: number;
                    name: string;
                    code: string;
                    slug: string;
                    status: string;
                    currency_code: string;
                    timezone: string;
                } | null;

                roles: string[];
                permissions: string[];

                branch_access: {
                    mode:
                        | 'none'
                        | 'company'
                        | 'assigned';

                    can_access_all: boolean;

                    assigned_branch: {
                        id: number;
                        name: string;
                        code: string;
                        status: string;
                    } | null;
                };
            };

            saas: {
                subscription: {
                    status: string;
                    trial_ends_at: string | null;
                    current_period_ends_at: string | null;
                    grace_ends_at: string | null;
                    plan: {
                        id: number;
                        code: string;
                        name: string;
                    };
                } | null;

                features: string[];
                limits: Record<string, number | null>;
            };

            headerNotifications:
                SharedHeaderNotifications;
        };

        flashDataType: {
            toast?: {
                type:
                    | 'success'
                    | 'error'
                    | 'warning'
                    | 'info';

                message: string;
                code?: string;
            };
        };
    }
}
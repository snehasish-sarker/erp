import '@inertiajs/core';
import type {
    SharedHeaderNotifications,
} from '@/Types/notification';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            appName: string;

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
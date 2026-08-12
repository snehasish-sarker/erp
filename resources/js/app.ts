import {
    createInertiaApp,
} from '@inertiajs/vue3';

import {
    route as routeFn,
    ZiggyVue,
} from 'ziggy-js';

type ZiggyConfig = NonNullable<
    Parameters<typeof routeFn>[3]
>;

type SharedZiggyConfig =
    Omit<ZiggyConfig, 'location'> & {
        location: string;
    };

const createZiggyConfig = (
    sharedConfig: SharedZiggyConfig,
    ssr: boolean,
): ZiggyConfig => {
    const {
        location,
        ...config
    } = sharedConfig;

    /*
     * In the browser, deliberately omit an explicit location.
     *
     * Ziggy can then use the live browser location rather than capturing
     * the location from the initial Inertia response.
     */
    if (!ssr) {
        return config as ZiggyConfig;
    }

    /*
     * Node SSR has no browser window/location, so supply the current
     * request URL that Laravel shared through Inertia.
     */
    return {
        ...config,
        location: new URL(location),
    } as ZiggyConfig;
};

createInertiaApp({
    title: (title: string): string =>
        title
            ? `${title} - ERP`
            : 'ERP',

    withApp(
        app,
        {
            page,
            ssr,
        },
    ): void {
        const sharedConfig =
            page.props.ziggy as SharedZiggyConfig;

        const ziggyConfig =
            createZiggyConfig(
                sharedConfig,
                ssr,
            );

        /*
         * Existing ERP components already call route() directly from
         * <script setup>. Preserve that architecture centrally instead
         * of modifying hundreds of components.
         */
        Object.assign(
            globalThis,
            {
                Ziggy: ziggyConfig,
                route: routeFn,
            },
        );

        /*
         * Register Ziggy with Vue as well so route() remains available
         * through Vue's plugin/template integration.
         */
        app.use(
            ZiggyVue,
            ziggyConfig,
        );
    },
});
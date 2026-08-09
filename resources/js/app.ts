import { createInertiaApp } from '@inertiajs/vue3';
import { route as routeFn, ZiggyVue } from 'ziggy-js';

import { Ziggy } from './ziggy.js';

type ZiggyConfig = NonNullable<
    Parameters<typeof ZiggyVue.install>[1]
>;

const ziggyConfig = Ziggy as ZiggyConfig;

/*
 * The ERP already uses Laravel's route() helper throughout hundreds of
 * Vue components, including inside <script setup>.
 *
 * ZiggyVue makes route() available to Vue templates.
 * The global registration below also makes the same helper available to
 * component scripts and to Inertia's SSR environment.
 */
Object.assign(globalThis, {
    Ziggy: ziggyConfig,
    route: routeFn,
});

createInertiaApp({
    title: (title: string): string =>
        title ? `${title} - ERP` : 'ERP',

    withApp(app): void {
        app.use(ZiggyVue, ziggyConfig);
    },
});
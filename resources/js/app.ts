import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title: string): string =>
        title ? `${title} - ERP` : 'ERP',
});
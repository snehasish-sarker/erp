import {
    route as routeFn,
} from 'ziggy-js';

declare global {
    var route: typeof routeFn;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof routeFn;
    }
}

export {};
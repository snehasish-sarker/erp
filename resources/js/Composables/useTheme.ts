import {
    computed,
    inject,
    onMounted,
    provide,
    ref,
    watch,
} from 'vue';
import type {
    ComputedRef,
    InjectionKey,
    Ref,
} from 'vue';

export type Theme = 'light' | 'dark';

interface ThemeContext {
    theme: Ref<Theme>;
    isDarkMode: ComputedRef<boolean>;
    toggleTheme: () => void;
    setTheme: (theme: Theme) => void;
}

const themeKey: InjectionKey<ThemeContext> = Symbol('ThemeContext');

export function provideTheme(): ThemeContext {
    const theme = ref<Theme>('light');
    const isInitialized = ref(false);

    const applyTheme = (value: Theme): void => {
        document.documentElement.classList.toggle(
            'dark',
            value === 'dark',
        );

        localStorage.setItem('theme', value);
    };

    const toggleTheme = (): void => {
        theme.value = theme.value === 'light'
            ? 'dark'
            : 'light';
    };

    const setTheme = (value: Theme): void => {
        theme.value = value;
    };

    onMounted((): void => {
        const savedTheme = localStorage.getItem('theme');

        theme.value = savedTheme === 'dark'
            ? 'dark'
            : 'light';

        applyTheme(theme.value);
        isInitialized.value = true;
    });

    watch(theme, (value: Theme): void => {
        if (!isInitialized.value) {
            return;
        }

        applyTheme(value);
    });

    const context: ThemeContext = {
        theme,
        isDarkMode: computed(
            (): boolean => theme.value === 'dark',
        ),
        toggleTheme,
        setTheme,
    };

    provide(themeKey, context);

    return context;
}

export function useTheme(): ThemeContext {
    const context = inject(themeKey);

    if (context === undefined) {
        throw new Error(
            'useTheme must be used inside the ERP theme provider.',
        );
    }

    return context;
}
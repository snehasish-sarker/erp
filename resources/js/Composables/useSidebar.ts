import {
    computed,
    inject,
    onMounted,
    onUnmounted,
    provide,
    ref,
} from 'vue';
import type {
    ComputedRef,
    InjectionKey,
    Ref,
} from 'vue';

interface SidebarContext {
    isExpanded: ComputedRef<boolean>;
    isMobileOpen: Ref<boolean>;
    isHovered: Ref<boolean>;
    activeItem: Ref<string | null>;
    openSubmenu: Ref<string | null>;
    toggleSidebar: () => void;
    toggleMobileSidebar: () => void;
    closeMobileSidebar: () => void;
    setIsHovered: (value: boolean) => void;
    setActiveItem: (item: string | null) => void;
    toggleSubmenu: (item: string) => void;
}

const sidebarKey: InjectionKey<SidebarContext> = Symbol('SidebarContext');

export function provideSidebar(): SidebarContext {
    const expanded = ref(true);
    const isMobileOpen = ref(false);
    const isMobile = ref(false);
    const isHovered = ref(false);
    const activeItem = ref<string | null>(null);
    const openSubmenu = ref<string | null>(null);

    const handleResize = (): void => {
        isMobile.value = window.innerWidth < 1024;

        if (!isMobile.value) {
            isMobileOpen.value = false;
        }
    };

    const toggleSidebar = (): void => {
        if (isMobile.value) {
            isMobileOpen.value = !isMobileOpen.value;

            return;
        }

        expanded.value = !expanded.value;
    };

    const toggleMobileSidebar = (): void => {
        isMobileOpen.value = !isMobileOpen.value;
    };

    const closeMobileSidebar = (): void => {
        isMobileOpen.value = false;
    };

    const setIsHovered = (value: boolean): void => {
        isHovered.value = value;
    };

    const setActiveItem = (item: string | null): void => {
        activeItem.value = item;
    };

    const toggleSubmenu = (item: string): void => {
        openSubmenu.value = openSubmenu.value === item
            ? null
            : item;
    };

    onMounted((): void => {
        handleResize();
        window.addEventListener('resize', handleResize);
    });

    onUnmounted((): void => {
        window.removeEventListener('resize', handleResize);
    });

    const context: SidebarContext = {
        isExpanded: computed(
            (): boolean => !isMobile.value && expanded.value,
        ),
        isMobileOpen,
        isHovered,
        activeItem,
        openSubmenu,
        toggleSidebar,
        toggleMobileSidebar,
        closeMobileSidebar,
        setIsHovered,
        setActiveItem,
        toggleSubmenu,
    };

    provide(sidebarKey, context);

    return context;
}

export function useSidebar(): SidebarContext {
    const context = inject(sidebarKey);

    if (context === undefined) {
        throw new Error(
            'useSidebar must be used inside the ERP layout sidebar provider.',
        );
    }

    return context;
}
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (_value: Appearance) => void;
};

export function updateTheme(_value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Dark mode is intentionally disabled; keep UI in light mode.
    document.documentElement.classList.remove('dark');
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Force light mode and normalize persisted preference.
    updateTheme('light');
    localStorage.setItem('appearance', 'light');
    setCookie('appearance', 'light');
}

const appearance = ref<Appearance>('light');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = getStoredAppearance();

        appearance.value = savedAppearance === 'light' ? 'light' : 'light';
        updateTheme('light');
        localStorage.setItem('appearance', 'light');
        setCookie('appearance', 'light');
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return 'light';
    });

    function updateAppearance(_value: Appearance) {
        appearance.value = 'light';

        localStorage.setItem('appearance', 'light');
        setCookie('appearance', 'light');
        updateTheme('light');
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}

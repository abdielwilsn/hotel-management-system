import { usePage } from '@inertiajs/vue3';
import { computed  } from 'vue';
import type {ComputedRef} from 'vue';

const DEFAULT_CURRENCY = 'NGN';
const DEFAULT_LOCALE = 'en-NG';

/**
 * Format a monetary value using an explicit currency/locale.
 * Prefer the `useFormatters()` composable inside components so the current
 * team's currency is applied automatically.
 */
export function formatCurrency(
    value: number | string | null | undefined,
    currency: string = DEFAULT_CURRENCY,
    locale: string = DEFAULT_LOCALE,
): string {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency || DEFAULT_CURRENCY,
    }).format(Number.isFinite(amount) ? amount : 0);
}

/**
 * Format an ISO date string (YYYY-MM-DD or full datetime) for display.
 */
export function formatDate(
    value: string | null | undefined,
    locale: string = DEFAULT_LOCALE,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    },
): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat(locale, options).format(date);
}

/**
 * Turn a snake_case / kebab value into a human "Title Case" label.
 */
export function labelize(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

/**
 * Composable that binds the formatters to the current team's currency & locale
 * (shared via Inertia props), so every screen shows money the same way.
 */
export function useFormatters(): {
    currency: ComputedRef<string>;
    locale: ComputedRef<string>;
    formatCurrency: (value: number | string | null | undefined) => string;
    formatDate: (
        value: string | null | undefined,
        options?: Intl.DateTimeFormatOptions,
    ) => string;
    labelize: (value: string | null | undefined) => string;
} {
    const page = usePage();

    const currency = computed(
        () => page.props.currentTeam?.currency ?? DEFAULT_CURRENCY,
    );
    const locale = computed(
        () => page.props.currentTeam?.locale ?? DEFAULT_LOCALE,
    );

    return {
        currency,
        locale,
        formatCurrency: (value) =>
            formatCurrency(value, currency.value, locale.value),
        formatDate: (value, options) =>
            formatDate(value, locale.value, options),
        labelize,
    };
}

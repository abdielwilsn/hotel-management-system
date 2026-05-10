type HotelTheme = {
    key: string;
    name: string;
    primary: string;
    accent: string;
    background: string;
    surface: string;
    surfaceAlt: string;
};

const STORAGE_KEY = 'hotel-theme';

const DEFAULT_HOTEL_THEME: HotelTheme = {
    key: 'anns-haven-standard',
    name: 'Anns Haven Standard',
    primary: '#0F766E',
    accent: '#D4A24F',
    background: '#F6F4EF',
    surface: '#FFFFFF',
    surfaceAlt: '#F1EFE8',
};

const toRgb = (hexColor: string) => {
    const hex = hexColor.replace('#', '');

    if (!/^([\dA-Fa-f]{6})$/.test(hex)) {
        return null;
    }

    return {
        r: Number.parseInt(hex.substring(0, 2), 16),
        g: Number.parseInt(hex.substring(2, 4), 16),
        b: Number.parseInt(hex.substring(4, 6), 16),
    };
};

const rgbToHslChannels = (r: number, g: number, b: number): string => {
    const rr = r / 255;
    const gg = g / 255;
    const bb = b / 255;

    const max = Math.max(rr, gg, bb);
    const min = Math.min(rr, gg, bb);
    const delta = max - min;

    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (delta !== 0) {
        s = delta / (1 - Math.abs(2 * l - 1));

        switch (max) {
            case rr:
                h = ((gg - bb) / delta) % 6;
                break;
            case gg:
                h = (bb - rr) / delta + 2;
                break;
            default:
                h = (rr - gg) / delta + 4;
                break;
        }
    }

    const hue = Math.round(h * 60 < 0 ? h * 60 + 360 : h * 60);
    const saturation = Math.round(s * 100);
    const lightness = Math.round(l * 100);

    return `${hue} ${saturation}% ${lightness}%`;
};

const applyTheme = (theme: HotelTheme): void => {
    if (typeof window === 'undefined') {
        return;
    }

    const primaryRgb = toRgb(theme.primary);
    const accentRgb = toRgb(theme.accent);
    const backgroundRgb = toRgb(theme.background);
    const surfaceRgb = toRgb(theme.surface);
    const surfaceAltRgb = toRgb(theme.surfaceAlt);

    if (
        !primaryRgb ||
        !accentRgb ||
        !backgroundRgb ||
        !surfaceRgb ||
        !surfaceAltRgb
    ) {
        return;
    }

    const root = document.documentElement;

    root.style.setProperty(
        '--hotel-primary',
        rgbToHslChannels(primaryRgb.r, primaryRgb.g, primaryRgb.b),
    );
    root.style.setProperty(
        '--hotel-accent',
        rgbToHslChannels(accentRgb.r, accentRgb.g, accentRgb.b),
    );
    root.style.setProperty(
        '--hotel-bg',
        rgbToHslChannels(backgroundRgb.r, backgroundRgb.g, backgroundRgb.b),
    );
    root.style.setProperty(
        '--hotel-surface',
        rgbToHslChannels(surfaceRgb.r, surfaceRgb.g, surfaceRgb.b),
    );
    root.style.setProperty(
        '--hotel-surface-alt',
        rgbToHslChannels(surfaceAltRgb.r, surfaceAltRgb.g, surfaceAltRgb.b),
    );

    localStorage.removeItem(STORAGE_KEY);
    document.cookie = `${STORAGE_KEY}=;path=/;max-age=0;SameSite=Lax`;
};

export function initializeHotelTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    applyTheme(DEFAULT_HOTEL_THEME);
}

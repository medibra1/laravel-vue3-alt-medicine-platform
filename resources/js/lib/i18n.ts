import ar from '@/locales/ar';
import en from '@/locales/en';
import fr from '@/locales/fr';
import { createI18n } from 'vue-i18n';
import { ar as vuetifyAr, en as vuetifyEn, fr as vuetifyFr } from 'vuetify/locale';

export const SUPPORTED_LOCALES = ['fr', 'en', 'ar'] as const;
export type AppLocale = (typeof SUPPORTED_LOCALES)[number];

export const LOCALE_LABELS: Record<AppLocale, string> = {
    fr: 'Français',
    en: 'English',
    ar: 'العربية',
};

export function isRtl(locale: string): boolean {
    return locale === 'ar';
}

export function normalizeLocale(locale: string | null | undefined): AppLocale {
    return SUPPORTED_LOCALES.includes(locale as AppLocale) ? (locale as AppLocale) : 'fr';
}

/**
 * The locale is decided server-side (SetLocale middleware) and rendered
 * on <html lang>; switching language posts to /locale and reloads, so
 * this instance never changes locale during its lifetime.
 */
export function createAppI18n(locale: string | null | undefined) {
    const instance = createI18n({
        legacy: false,
        locale: normalizeLocale(locale),
        fallbackLocale: 'fr',
        // $vuetify.*: Vuetify's own strings, read through its vue-i18n adapter.
        messages: {
            fr: { ...fr, $vuetify: vuetifyFr },
            en: { ...en, $vuetify: vuetifyEn },
            ar: { ...ar, $vuetify: vuetifyAr },
        },
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    active = instance as any;

    return instance;
}

// Last instance created, for plain .ts helpers (utils/*) that have no
// component context to call useI18n() from.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let active: any = null;

/** Translate outside of a component (label helpers in utils/). */
export function tr(key: string, named: Record<string, unknown> = {}): string {
    return String(ensureActive().global.t(key, named));
}

export function currentLocale(): string {
    return String(ensureActive().global.locale.value);
}

// utils/* build their option lists at import time, which can run before
// the app bootstrap creates its instance — fall back to <html lang>.
function ensureActive() {
    if (!active) {
        createAppI18n(typeof document !== 'undefined' ? document.documentElement.lang : 'fr');
    }

    return active;
}

export type AppI18n = ReturnType<typeof createAppI18n>;

/** Locale-aware number/currency/date helpers used across pages. */
export function formatMoney(amount: number, currency: string, locale: string): string {
    try {
        return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
    } catch {
        // Unknown ISO code typed in a center's settings.
        return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(amount)} ${currency}`;
    }
}

export function formatDate(value: string | Date | null | undefined, locale: string, options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }): string {
    if (!value) {
        return '—';
    }

    // A bare YYYY-MM-DD is a calendar day, not a UTC instant.
    const date = typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);

    return new Intl.DateTimeFormat(locale, options).format(date);
}

/**
 * Resolves a {fr, en, ar} value (raw translatable JSON sent by the admin
 * resources) for the current locale, falling back to French — the same
 * fallback the backend applies.
 */
export function localized(value: { fr?: string | null; en?: string | null; ar?: string | null } | null | undefined): string {
    if (!value) {
        return '';
    }

    return value[currentLocale() as AppLocale] || value.fr || value.en || '';
}

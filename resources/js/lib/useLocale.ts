import { LOCALE_LABELS, SUPPORTED_LOCALES, type AppLocale } from '@/lib/i18n';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Language switch: saved server-side (user preference / session), then a
 * full reload so <html lang/dir>, Vuetify RTL and every server-rendered
 * label (translated catalogue data) all change together.
 */
export function useLocale() {
    const { locale } = useI18n();

    function setLocale(next: AppLocale | string) {
        if (next === locale.value || !SUPPORTED_LOCALES.includes(next as AppLocale)) {
            return;
        }

        router.post(route('locale.update'), { locale: next }, {
            preserveScroll: true,
            onSuccess: () => window.location.reload(),
        });
    }

    const options = SUPPORTED_LOCALES.map((value) => ({ value, label: LOCALE_LABELS[value] }));

    return { locale, setLocale, options };
}

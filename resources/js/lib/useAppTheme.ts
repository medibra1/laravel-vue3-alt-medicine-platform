import { useTheme } from 'vuetify';

const STORAGE_KEY = 'ruqya-theme';

export type AppThemeName = 'ruqyaTheme' | 'ruqyaThemeDark';

function readStoredTheme(): AppThemeName {
    if (typeof localStorage === 'undefined') {
        return 'ruqyaTheme';
    }

    return localStorage.getItem(STORAGE_KEY) === 'dark'
        ? 'ruqyaThemeDark'
        : 'ruqyaTheme';
}

export function useAppTheme() {
    const theme = useTheme();

    function apply(name: AppThemeName) {
        // theme.change(), not the deprecated theme.global.name.value =
        // assignment — the latter still worked but logged a
        // "[Vuetify UPGRADE] ... is deprecated" console warning on every
        // theme switch, found via real browser testing.
        void theme.change(name);
    }

    function init() {
        apply(readStoredTheme());
    }

    function toggle() {
        const next: AppThemeName =
            theme.global.name.value === 'ruqyaThemeDark'
                ? 'ruqyaTheme'
                : 'ruqyaThemeDark';

        apply(next);
        localStorage.setItem(
            STORAGE_KEY,
            next === 'ruqyaThemeDark' ? 'dark' : 'light',
        );
    }

    const isDark = () => theme.global.name.value === 'ruqyaThemeDark';

    return { init, toggle, isDark };
}

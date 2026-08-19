import { useTheme } from 'vuetify';

const STORAGE_KEY = 'ruqya-theme';

export type AppThemeName = 'indigoTheme' | 'indigoThemeDark';

function readStoredTheme(): AppThemeName {
    if (typeof localStorage === 'undefined') {
        return 'indigoTheme';
    }

    return localStorage.getItem(STORAGE_KEY) === 'dark'
        ? 'indigoThemeDark'
        : 'indigoTheme';
}

export function useAppTheme() {
    const theme = useTheme();

    function apply(name: AppThemeName) {
        theme.global.name.value = name;
    }

    function init() {
        apply(readStoredTheme());
    }

    function toggle() {
        const next: AppThemeName =
            theme.global.name.value === 'indigoThemeDark'
                ? 'indigoTheme'
                : 'indigoThemeDark';

        apply(next);
        localStorage.setItem(
            STORAGE_KEY,
            next === 'indigoThemeDark' ? 'dark' : 'light',
        );
    }

    const isDark = () => theme.global.name.value === 'indigoThemeDark';

    return { init, toggle, isDark };
}

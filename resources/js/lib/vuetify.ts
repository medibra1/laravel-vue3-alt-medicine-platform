import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';

const indigoTheme = {
    dark: false,
    colors: {
        primary: '#4F46E5',
        secondary: '#6B7280',
        error: '#DC2626',
        success: '#16A34A',
        warning: '#D97706',
        background: '#F3F4F6',
        surface: '#FFFFFF',
    },
};

export const vuetify = createVuetify({
    theme: {
        defaultTheme: 'indigoTheme',
        themes: {
            indigoTheme,
        },
    },
});

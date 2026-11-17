import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { APP_BRAND } from '@/lib/brand';
import { createAppI18n } from '@/lib/i18n';
import { createAppVuetify } from '@/lib/vuetify';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

createInertiaApp({
    title: (title) => (title ? `${title} · ${APP_BRAND}` : APP_BRAND),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const i18n = createAppI18n(props.initialPage.props.locale as string);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(createAppVuetify(i18n))
            .mount(el);
    },
    progress: {
        color: '#0F766E',
    },
});

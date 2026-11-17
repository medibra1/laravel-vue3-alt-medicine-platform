import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { APP_BRAND } from '@/lib/brand';
import { createAppI18n } from '@/lib/i18n';
import { createAppVuetify } from '@/lib/vuetify';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} · ${APP_BRAND}` : APP_BRAND),
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            const i18n = createAppI18n(page.props.locale as string);

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                })
                .use(i18n)
                .use(createAppVuetify(i18n));
        },
    }),
);

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        css: true,
        setupFiles: ['resources/js/vitest.setup.ts'],
        // tests/e2e/*.spec.ts are Playwright specs (run via `npm run
        // test:e2e`), not Vitest's — Vitest's default include pattern
        // would otherwise try to collect them too.
        exclude: ['**/node_modules/**', 'tests/e2e/**'],
        server: {
            deps: {
                inline: ['vuetify'],
            },
        },
    },
});

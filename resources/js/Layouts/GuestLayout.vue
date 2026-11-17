<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { APP_BRAND } from '@/lib/brand';
import { useLocale } from '@/lib/useLocale';
import { useI18n } from 'vue-i18n';

defineProps<{
    title: string;
    subtitle?: string;
}>();

const { t } = useI18n();
const { locale, setLocale, options: localeOptions } = useLocale();

const features = [
    { icon: 'mdi-account-heart-outline', key: 'patients' },
    { icon: 'mdi-calendar-check-outline', key: 'agenda' },
    { icon: 'mdi-receipt-text-check-outline', key: 'billing' },
];
</script>

<template>
    <v-app>
        <v-main class="guest">
            <div class="guest__grid">
                <aside class="guest__hero d-none d-md-flex">
                    <div class="d-flex align-center ga-3">
                        <ApplicationLogo class="guest__logo" />
                        <span class="guest__brand">{{ APP_BRAND }}</span>
                    </div>

                    <div>
                        <h1 class="guest__headline">{{ t('auth.heroTitle') }}</h1>
                        <p class="guest__lead">{{ t('auth.heroText') }}</p>

                        <ul class="guest__features">
                            <li v-for="feature in features" :key="feature.key">
                                <span class="guest__feature-icon"><v-icon :icon="feature.icon" size="20" /></span>
                                {{ t(`auth.features.${feature.key}`) }}
                            </li>
                        </ul>
                    </div>

                    <div class="text-caption" style="opacity: 0.6">© {{ new Date().getFullYear() }} {{ APP_BRAND }}</div>
                </aside>

                <section class="guest__panel">
                    <div class="guest__topbar">
                        <div class="d-flex d-md-none align-center ga-2">
                            <ApplicationLogo style="width: 32px; height: 32px" />
                            <span class="font-weight-bold">{{ APP_BRAND }}</span>
                        </div>
                        <v-spacer />
                        <v-menu>
                            <template #activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" prepend-icon="mdi-translate" append-icon="mdi-chevron-down" size="small">
                                    {{ localeOptions.find((option) => option.value === locale)?.label }}
                                </v-btn>
                            </template>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="option in localeOptions"
                                    :key="option.value"
                                    :title="option.label"
                                    :active="locale === option.value"
                                    color="primary"
                                    @click="setLocale(option.value)"
                                />
                            </v-list>
                        </v-menu>
                    </div>

                    <div class="guest__form">
                        <h2 class="guest__title">{{ title }}</h2>
                        <p v-if="subtitle" class="text-body-1 text-medium-emphasis mb-8">{{ subtitle }}</p>
                        <slot />
                    </div>
                </section>
            </div>
        </v-main>
    </v-app>
</template>

<style scoped>
.guest {
    background: rgb(var(--v-theme-surface));
}

.guest__grid {
    display: grid;
    min-height: 100vh;
    grid-template-columns: 1fr;
}

@media (min-width: 960px) {
    .guest__grid {
        grid-template-columns: minmax(380px, 5fr) 6fr;
    }
}

.guest__hero {
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 56px;
    color: #fff;
    background:
        radial-gradient(900px 500px at 110% -10%, rgb(45 212 191 / 0.35), transparent 60%),
        radial-gradient(700px 500px at -20% 110%, rgb(184 134 47 / 0.28), transparent 60%),
        linear-gradient(160deg, #0b2f2b 0%, #0f4d46 55%, #0f766e 100%);
}

.guest__logo {
    width: 44px;
    height: 44px;
}

.guest__brand {
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.guest__headline {
    font-size: clamp(2rem, 3.4vw, 3rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: -0.03em;
    margin-bottom: 20px;
}

.guest__lead {
    max-width: 30rem;
    font-size: 1.05rem;
    line-height: 1.6;
    opacity: 0.82;
    margin-bottom: 36px;
}

.guest__features {
    display: grid;
    gap: 14px;
    padding: 0;
    list-style: none;
    font-weight: 500;
}

.guest__features li {
    display: flex;
    align-items: center;
    gap: 12px;
}

.guest__feature-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 10px;
    background: rgb(255 255 255 / 0.12);
    border: 1px solid rgb(255 255 255 / 0.16);
}

.guest__panel {
    display: flex;
    flex-direction: column;
    padding: 20px 24px 48px;
}

.guest__topbar {
    display: flex;
    align-items: center;
}

.guest__title {
    margin-bottom: 8px;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.15;
}

.guest__form {
    width: 100%;
    max-width: 420px;
    margin: auto;
    padding-block: 32px;
}
</style>

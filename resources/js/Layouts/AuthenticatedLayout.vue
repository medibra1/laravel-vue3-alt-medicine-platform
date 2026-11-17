<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppCenterSwitcher from '@/Components/App/AppCenterSwitcher.vue';
import AppCommandPalette, { type CommandItem } from '@/Components/App/AppCommandPalette.vue';
import AppNotificationBell from '@/Components/App/AppNotificationBell.vue';
import { APP_BRAND } from '@/lib/brand';
import { useAppDensity, type AppDensity } from '@/lib/useAppDensity';
import { useAppTheme } from '@/lib/useAppTheme';
import { useLocale } from '@/lib/useLocale';
import type { AppModule } from '@/types';
import { handleNavClick } from '@/utils/inertiaNavClick';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeMount, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDisplay } from 'vuetify';

const RAIL_STORAGE_KEY = 'ruqya-nav-rail';

const { t } = useI18n();
const { mdAndUp } = useDisplay();
const page = usePage();

const drawer = ref(mdAndUp.value);
const rail = ref(localStorage.getItem(RAIL_STORAGE_KEY) === '1');
const paletteOpen = ref(false);

const { density, setDensity } = useAppDensity();
const { toggle: toggleTheme, isDark, init: initTheme } = useAppTheme();
const { locale, setLocale, options: localeOptions } = useLocale();

const densityOptions = computed<{ value: AppDensity; label: string; icon: string }[]>(() => [
    { value: 'default', label: t('shell.densityDefault'), icon: 'mdi-view-agenda-outline' },
    { value: 'comfortable', label: t('shell.densityComfortable'), icon: 'mdi-view-sequential-outline' },
    { value: 'compact', label: t('shell.densityCompact'), icon: 'mdi-view-headline' },
]);

const auth = computed(() => page.props.auth);
const isRail = computed(() => mdAndUp.value && rail.value);

const roleLabel = computed(() => {
    if (auth.value.is_super_admin) return t('shell.roles.super_admin');
    if (auth.value.is_admin) return t('shell.roles.admin');
    if (auth.value.is_manager) return t('shell.roles.manager');
    if (auth.value.is_practitioner) return t('shell.roles.practitioner');
    return '';
});

const initials = computed(() =>
    auth.value.user.name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

interface NavItem {
    label: string;
    icon: string;
    href: string;
    active: boolean;
}

interface NavGroup {
    key: string;
    label: string;
    items: NavItem[];
}

// route().current() reads window.location imperatively — reading
// usePage().url here is what makes the active flags recompute after
// every Inertia visit (the layout is never remounted).
const navGroups = computed<NavGroup[]>(() => {
    void page.url;

    const has = (module: AppModule) => auth.value.modules.includes(module);
    const manages = auth.value.is_global_admin || auth.value.is_manager;
    const item = (key: string, icon: string, routeName: string, pattern: string): NavItem => ({
        label: t(`shell.nav.${key}`),
        icon,
        href: route(routeName),
        active: Boolean(route().current(pattern)),
    });

    const activity = [item('dashboard', 'mdi-view-dashboard-outline', 'dashboard', 'dashboard')];
    if (has('scheduling')) activity.push(item('agenda', 'mdi-calendar-month-outline', 'admin.agenda', 'admin.agenda'));
    if (has('patients')) activity.push(item('patients', 'mdi-account-heart-outline', 'admin.patients.index', 'admin.patients.*'));
    if (has('treatments')) activity.push(item('treatments', 'mdi-medical-bag', 'admin.treatments.index', 'admin.treatments.*'));
    if (has('billing')) activity.push(item('billing', 'mdi-receipt-text-outline', 'admin.billing.index', 'admin.billing.*'));

    const management: NavItem[] = [];
    if (manages && has('practitioners')) management.push(item('practitioners', 'mdi-account-tie-outline', 'admin.practitioners.index', 'admin.practitioners.*'));
    if (manages && has('scheduling')) management.push(item('availabilities', 'mdi-calendar-clock-outline', 'admin.availabilities.index', 'admin.availabilities.*'));
    if (auth.value.is_manager && !auth.value.is_global_admin) {
        management.push(item('centerSettings', 'mdi-cog-outline', 'admin.center-settings', 'admin.centers.settings.*'));
    }

    const administration: NavItem[] = [];
    if (auth.value.is_global_admin) {
        administration.push(
            item('centers', 'mdi-domain', 'admin.centers.index', 'admin.centers.*'),
            item('users', 'mdi-account-cog-outline', 'admin.users.index', 'admin.users.*'),
            item('consentTemplates', 'mdi-file-sign', 'admin.consent-templates.index', 'admin.consent-templates.*'),
        );
    }

    const reference: NavItem[] = [];
    if (auth.value.is_super_admin) {
        reference.push(
            item('diseaseCategories', 'mdi-shape-outline', 'admin.disease-categories.index', 'admin.disease-categories.*'),
            item('diseases', 'mdi-virus-outline', 'admin.diseases.index', 'admin.diseases.*'),
            item('careCategories', 'mdi-shape-plus-outline', 'admin.care-categories.index', 'admin.care-categories.*'),
            item('careItems', 'mdi-leaf', 'admin.care-items.index', 'admin.care-items.*'),
            item('zones', 'mdi-earth', 'admin.zones.index', 'admin.zones.*'),
            item('countries', 'mdi-flag-outline', 'admin.countries.index', 'admin.countries.*'),
            item('enumOptions', 'mdi-tune-variant', 'admin.enum-options.index', 'admin.enum-options.*'),
        );
    }

    return [
        { key: 'activity', label: t('shell.groups.activity'), items: activity },
        { key: 'management', label: t('shell.groups.management'), items: management },
        { key: 'administration', label: t('shell.groups.administration'), items: administration },
        { key: 'reference', label: t('shell.groups.reference'), items: reference },
    ].filter((group) => group.items.length > 0);
});

const commandItems = computed<CommandItem[]>(() =>
    navGroups.value.flatMap((group) =>
        group.items.map((item) => ({ label: item.label, icon: item.icon, href: item.href, group: group.label })),
    ),
);

function toggleRail() {
    rail.value = !rail.value;
    localStorage.setItem(RAIL_STORAGE_KEY, rail.value ? '1' : '0');
}

function logout() {
    router.post(route('logout'));
}

function onKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        paletteOpen.value = true;
    }
}

onBeforeMount(() => initTheme());
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <v-defaults-provider :defaults="{ global: { density } }">
        <v-app class="app-shell">
            <v-navigation-drawer
                v-model="drawer"
                :rail="isRail"
                :permanent="mdAndUp"
                :temporary="!mdAndUp"
                :width="272"
                :rail-width="76"
                class="app-drawer"
            >
                <div class="app-drawer__brand">
                    <Link :href="route('dashboard')" class="d-flex align-center ga-3 text-decoration-none overflow-hidden">
                        <ApplicationLogo class="app-drawer__logo" />
                        <div v-if="!isRail" class="overflow-hidden">
                            <div class="app-drawer__name">{{ APP_BRAND }}</div>
                            <div class="text-caption text-medium-emphasis text-truncate">{{ t('shell.tagline') }}</div>
                        </div>
                    </Link>
                </div>

                <div class="app-drawer__nav">
                    <template v-for="group in navGroups" :key="group.key">
                        <div v-if="!isRail" class="app-eyebrow px-4 pt-5 pb-2">{{ group.label }}</div>
                        <v-divider v-else-if="group.key !== 'activity'" class="my-3 mx-4" />

                        <v-list nav density="comfortable" class="py-0 px-3">
                            <v-list-item
                                v-for="item in group.items"
                                :key="item.href"
                                :prepend-icon="item.icon"
                                :title="item.label"
                                :active="item.active"
                                :href="item.href"
                                rounded="lg"
                                color="primary"
                                class="app-nav-item"
                                @click="handleNavClick($event, item.href)"
                            >
                                <v-tooltip v-if="isRail" activator="parent" location="end" :text="item.label" />
                            </v-list-item>
                        </v-list>
                    </template>
                </div>

                <template #append>
                    <div class="pa-3 d-none d-md-block">
                        <v-btn
                            block
                            variant="text"
                            color="secondary"
                            :prepend-icon="isRail ? undefined : 'mdi-chevron-double-left'"
                            :icon="isRail ? 'mdi-chevron-double-right' : false"
                            :aria-label="isRail ? t('shell.expand') : t('shell.collapse')"
                            class="justify-start"
                            @click="toggleRail"
                        >
                            <template v-if="!isRail">{{ t('shell.collapse') }}</template>
                        </v-btn>
                    </div>
                </template>
            </v-navigation-drawer>

            <v-app-bar flat height="68" class="app-bar">
                <v-app-bar-nav-icon class="d-md-none" :aria-label="t('shell.openMenu')" @click="drawer = !drawer" />

                <button type="button" class="app-search ms-md-2" @click="paletteOpen = true">
                    <v-icon icon="mdi-magnify" size="20" />
                    <span class="d-none d-sm-inline flex-1-1 text-start">{{ t('shell.quickSearchHint') }}</span>
                    <kbd class="d-none d-lg-inline">Ctrl K</kbd>
                </button>

                <v-spacer />

                <AppCenterSwitcher />

                <v-menu>
                    <template #activator="{ props: menuProps }">
                        <v-btn v-bind="menuProps" icon variant="text" :aria-label="t('shell.language')">
                            <v-icon icon="mdi-translate" />
                        </v-btn>
                    </template>
                    <v-list density="compact" min-width="180">
                        <v-list-subheader>{{ t('shell.language') }}</v-list-subheader>
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

                <v-menu :close-on-content-click="false">
                    <template #activator="{ props: menuProps }">
                        <v-btn v-bind="menuProps" icon variant="text" :aria-label="t('shell.display')">
                            <v-icon icon="mdi-palette-outline" />
                        </v-btn>
                    </template>
                    <v-list density="compact" min-width="220">
                        <v-list-item
                            :prepend-icon="isDark() ? 'mdi-weather-sunny' : 'mdi-weather-night'"
                            :title="isDark() ? t('shell.lightMode') : t('shell.darkMode')"
                            @click="toggleTheme"
                        />
                        <v-divider class="my-1" />
                        <v-list-subheader>{{ t('shell.density') }}</v-list-subheader>
                        <v-list-item
                            v-for="option in densityOptions"
                            :key="option.value"
                            :prepend-icon="option.icon"
                            :title="option.label"
                            :active="density === option.value"
                            color="primary"
                            @click="setDensity(option.value)"
                        />
                    </v-list>
                </v-menu>

                <AppNotificationBell />

                <v-menu>
                    <template #activator="{ props: menuProps }">
                        <button v-bind="menuProps" type="button" class="app-user ms-2 me-3">
                            <v-avatar color="primary" variant="tonal" size="36">
                                <span class="text-body-2 font-weight-bold">{{ initials }}</span>
                            </v-avatar>
                            <span class="d-none d-md-flex flex-column text-start">
                                <span class="text-body-2 font-weight-bold">{{ auth.user.name }}</span>
                                <span class="text-caption text-medium-emphasis">{{ roleLabel }}</span>
                            </span>
                            <v-icon icon="mdi-chevron-down" size="18" class="d-none d-md-inline-flex" />
                        </button>
                    </template>
                    <v-list density="compact" min-width="220">
                        <v-list-item :title="auth.user.name" :subtitle="auth.user.email" class="d-md-none" />
                        <v-divider class="d-md-none my-1" />
                        <v-list-item
                            :href="route('profile.edit')"
                            :title="t('shell.profile')"
                            prepend-icon="mdi-account-circle-outline"
                            @click="handleNavClick($event, route('profile.edit'))"
                        />
                        <v-list-item :title="t('shell.logout')" prepend-icon="mdi-logout" base-color="error" @click="logout" />
                    </v-list>
                </v-menu>
            </v-app-bar>

            <v-main class="app-main">
                <div class="app-content">
                    <slot />
                </div>

                <footer class="app-footer">© {{ new Date().getFullYear() }} {{ APP_BRAND }} — {{ t('shell.footer') }}</footer>
            </v-main>

            <AppCommandPalette v-model="paletteOpen" :items="commandItems" :can-search-patients="auth.modules.includes('patients')" />
        </v-app>
    </v-defaults-provider>
</template>

<style scoped>
.app-drawer {
    border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
    background: rgb(var(--v-theme-surface));
}

.app-drawer__brand {
    display: flex;
    align-items: center;
    height: 68px;
    padding-inline: 18px;
    color: rgb(var(--v-theme-on-surface));
}

.app-drawer__brand a {
    color: inherit;
}

.app-drawer__logo {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 11px;
    box-shadow: 0 6px 14px -6px rgb(15 118 110 / 0.7);
}

.app-drawer__name {
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.app-drawer__nav {
    padding-bottom: 12px;
}

.app-nav-item {
    margin-bottom: 2px;
    font-weight: 500;
}

.app-nav-item :deep(.v-list-item-title) {
    font-size: 0.9rem;
    font-weight: 500;
}

.app-nav-item.v-list-item--active :deep(.v-list-item-title) {
    font-weight: 700;
}

.app-nav-item :deep(.v-list-item__prepend > .v-icon) {
    opacity: 0.75;
}

.app-nav-item.v-list-item--active :deep(.v-list-item__prepend > .v-icon) {
    opacity: 1;
}

.app-bar {
    background: rgba(var(--v-theme-background), 0.82) !important;
    backdrop-filter: saturate(160%) blur(14px);
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}

.app-search {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 42px;
    width: min(420px, 46vw);
    padding-inline: 14px;
    border-radius: 12px;
    border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1.6));
    background: rgb(var(--v-theme-surface));
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.875rem;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.app-search:hover,
.app-search:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.14);
    outline: none;
}

.app-search kbd {
    padding: 2px 6px;
    border-radius: 6px;
    border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 2));
    font-family: inherit;
    font-size: 0.7rem;
    font-weight: 600;
}

.app-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 8px 4px 4px;
    border-radius: 999px;
    cursor: pointer;
    line-height: 1.2;
    color: rgb(var(--v-theme-on-surface));
    transition: background-color 0.15s;
}

.app-user:hover,
.app-user:focus-visible {
    background: rgba(var(--v-theme-on-surface), 0.06);
    outline: none;
}

.app-main {
    background: rgb(var(--v-theme-background));
}

.app-content {
    max-width: 1480px;
    margin-inline: auto;
    padding: 28px 16px 24px;
    min-height: calc(100vh - 68px - 56px);
}

@media (min-width: 960px) {
    .app-content {
        padding: 32px 32px 24px;
    }
}

.app-footer {
    padding: 16px;
    text-align: center;
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>

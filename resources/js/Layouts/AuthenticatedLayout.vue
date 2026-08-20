<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useAppDensity, type AppDensity } from '@/lib/useAppDensity';
import { useAppTheme } from '@/lib/useAppTheme';
import { Link, router } from '@inertiajs/vue3';
import { onBeforeMount, ref } from 'vue';
import { useDisplay } from 'vuetify';

const RAIL_STORAGE_KEY = 'ruqya-nav-rail';

const { mdAndUp } = useDisplay();

const drawer = ref(mdAndUp.value);
const rail = ref(localStorage.getItem(RAIL_STORAGE_KEY) === '1');

const { density, setDensity } = useAppDensity();
const { toggle: toggleTheme, isDark, init: initTheme } = useAppTheme();

const densityOptions: { value: AppDensity; label: string; icon: string }[] = [
    { value: 'default', label: 'Confortable large', icon: 'mdi-view-agenda-outline' },
    { value: 'comfortable', label: 'Confortable', icon: 'mdi-view-sequential-outline' },
    { value: 'compact', label: 'Compact', icon: 'mdi-view-headline' },
];

const navItems = [
    { label: 'Dashboard', icon: 'mdi-view-dashboard-outline', href: () => route('dashboard'), active: () => route().current('dashboard') },
    { label: 'Patients', icon: 'mdi-account-heart-outline', href: () => route('admin.patients.index'), active: () => route().current('admin.patients.*') },
    { label: 'Traitements', icon: 'mdi-medical-bag', href: () => route('admin.treatments.index'), active: () => route().current('admin.treatments.*') },
    { label: 'Praticiens', icon: 'mdi-account-tie-outline', href: () => route('admin.practitioners.index'), active: () => route().current('admin.practitioners.*') },
];

function toggleRail() {
    rail.value = !rail.value;
    localStorage.setItem(RAIL_STORAGE_KEY, rail.value ? '1' : '0');
}

function logout() {
    router.post(route('logout'));
}

onBeforeMount(() => {
    initTheme();
});
</script>

<template>
    <v-defaults-provider :defaults="{ global: { density } }">
    <v-app>
        <v-navigation-drawer
            v-model="drawer"
            :rail="mdAndUp ? rail : false"
            :permanent="mdAndUp"
            :temporary="!mdAndUp"
            border="0"
            class="app-drawer"
        >
            <div class="d-flex align-center px-3 py-3" style="min-height: 64px">
                <Link :href="route('dashboard')" class="d-flex align-center flex-1-1-0" style="overflow: hidden">
                    <ApplicationLogo class="app-drawer-logo flex-shrink-0" />
                    <span v-if="!rail" class="text-subtitle-1 font-weight-bold ms-2 text-truncate">
                        Ruqya App
                    </span>
                </Link>

                <v-btn
                    v-if="!rail"
                    icon="mdi-chevron-left"
                    variant="text"
                    density="comfortable"
                    size="small"
                    class="d-none d-md-inline-flex flex-shrink-0"
                    @click.stop="toggleRail"
                />
            </div>

            <div v-if="rail" class="d-flex justify-center pb-2 d-none d-md-flex">
                <v-btn
                    icon="mdi-chevron-right"
                    variant="text"
                    density="comfortable"
                    size="small"
                    @click.stop="toggleRail"
                />
            </div>

            <v-divider />

            <v-list nav density="comfortable">
                <v-list-item
                    v-for="item in navItems"
                    :key="item.label"
                    :prepend-icon="item.icon"
                    :title="item.label"
                    :active="item.active()"
                    :href="item.href()"
                    rounded="lg"
                />
            </v-list>
        </v-navigation-drawer>

        <v-app-bar border="0" flat>
            <v-app-bar-nav-icon
                class="d-md-none"
                @click="drawer = !drawer"
            />

            <v-app-bar-title>
                <slot name="header" />
            </v-app-bar-title>

            <v-spacer />

            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn
                        v-bind="menuProps"
                        icon="mdi-tune-variant"
                        variant="text"
                    />
                </template>
                <v-list density="compact">
                    <v-list-subheader>Densité</v-list-subheader>
                    <v-list-item
                        v-for="option in densityOptions"
                        :key="option.value"
                        :prepend-icon="option.icon"
                        :title="option.label"
                        :active="density === option.value"
                        @click="setDensity(option.value)"
                    />
                </v-list>
            </v-menu>

            <v-btn
                :icon="isDark() ? 'mdi-weather-sunny' : 'mdi-weather-night'"
                variant="text"
                @click="toggleTheme"
            />

            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" variant="text" class="text-none">
                        {{ $page.props.auth.user.name }}
                        <v-icon icon="mdi-chevron-down" end />
                    </v-btn>
                </template>
                <v-list density="compact">
                    <v-list-item :href="route('profile.edit')" title="Profil" prepend-icon="mdi-account-outline" />
                    <v-list-item
                        title="Se déconnecter"
                        prepend-icon="mdi-logout"
                        @click="logout"
                    />
                </v-list>
            </v-menu>
        </v-app-bar>

        <v-main>
            <v-container fluid class="py-6">
                <slot />
            </v-container>
        </v-main>

        <v-footer border="0" app class="text-caption text-medium-emphasis justify-center">
            © {{ new Date().getFullYear() }} Ruqya App — Centre de médecine alternative
        </v-footer>
    </v-app>
    </v-defaults-provider>
</template>

<style scoped>
.app-drawer-logo {
    height: 2rem;
    width: auto;
    fill: currentColor;
}
</style>

<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppCenterSwitcher from '@/Components/App/AppCenterSwitcher.vue';
import AppNotificationBell from '@/Components/App/AppNotificationBell.vue';
import { useAppDensity, type AppDensity } from '@/lib/useAppDensity';
import { useAppTheme } from '@/lib/useAppTheme';
import { handleNavClick } from '@/utils/inertiaNavClick';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeMount, ref } from 'vue';
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

const isSuperAdmin = computed(() => Boolean((usePage().props.auth as { is_super_admin?: boolean }).is_super_admin));
const isAdmin = computed(() => Boolean((usePage().props.auth as { is_admin?: boolean }).is_admin));
const isManager = computed(() => Boolean((usePage().props.auth as { is_manager?: boolean }).is_manager));

// route().current() reads window.location imperatively — it has no Vue
// reactivity of its own, so each `active` flag is computed here (reading
// usePage().url, which Inertia does update reactively on every visit)
// rather than left as a function called at render time. Without this,
// nav highlighting would only ever reflect the very first page load.
const currentUrl = computed(() => usePage().url);

const generalNavItems = computed(() => {
    void currentUrl.value;

    const items = [
        { label: 'Dashboard', icon: 'mdi-view-dashboard-outline', href: route('dashboard'), active: route().current('dashboard') },
        { label: 'Patients', icon: 'mdi-account-heart-outline', href: route('admin.patients.index'), active: route().current('admin.patients.*') },
        { label: 'Traitements', icon: 'mdi-medical-bag', href: route('admin.treatments.index'), active: route().current('admin.treatments.*') },
    ];

    // A pure practitioner account (no manager/admin/super_admin role)
    // has no practitioners.viewAny permission — the CRUD page would
    // just 403. Managers/admins/super_admins keep seeing it as before.
    if (isSuperAdmin.value || isAdmin.value || isManager.value) {
        items.push({ label: 'Praticiens', icon: 'mdi-account-tie-outline', href: route('admin.practitioners.index'), active: route().current('admin.practitioners.*') });
    }

    return items;
});

// Visually grouped (v-list-subheader) apart from the business menus
// above — not collapsible for now; a real collapsible group is a
// possible future improvement if the shell's nav list keeps growing.
// 'Utilisateurs' is visible to admin too (not just super_admin, unlike
// the rest of this group) — UserPolicy/CenterPolicy already confine
// what an admin can actually do once there, this only gates the link.
const adminNavItems = computed(() => {
    void currentUrl.value;

    const items = [];

    if (isSuperAdmin.value) {
        items.push(
            { label: 'Centres', icon: 'mdi-domain', href: route('admin.centers.index'), active: route().current('admin.centers.*') },
            { label: 'Zones', icon: 'mdi-earth', href: route('admin.zones.index'), active: route().current('admin.zones.*') },
            { label: 'Pays', icon: 'mdi-flag-outline', href: route('admin.countries.index'), active: route().current('admin.countries.*') },
            { label: 'Catégories de maladies', icon: 'mdi-shape-outline', href: route('admin.disease-categories.index'), active: route().current('admin.disease-categories.*') },
            { label: 'Maladies', icon: 'mdi-virus-outline', href: route('admin.diseases.index'), active: route().current('admin.diseases.*') },
            { label: 'Catégories de soins', icon: 'mdi-shape-plus-outline', href: route('admin.care-categories.index'), active: route().current('admin.care-categories.*') },
            { label: 'Soins', icon: 'mdi-leaf', href: route('admin.care-items.index'), active: route().current('admin.care-items.*') },
            { label: 'Options dynamiques', icon: 'mdi-tune-variant', href: route('admin.enum-options.index'), active: route().current('admin.enum-options.*') },
        );
    }

    if (isSuperAdmin.value || isAdmin.value) {
        items.push(
            { label: 'Utilisateurs', icon: 'mdi-account-cog-outline', href: route('admin.users.index'), active: route().current('admin.users.*') },
            { label: 'Modèles de consentement', icon: 'mdi-file-sign', href: route('admin.consent-templates.index'), active: route().current('admin.consent-templates.*') },
        );
    }

    return items;
});

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
    <v-app class="ruqya-shell">
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
                    v-for="item in generalNavItems"
                    :key="item.label"
                    :prepend-icon="item.icon"
                    :title="item.label"
                    :active="item.active"
                    :href="item.href"
                    rounded="lg"
                    @click="handleNavClick($event, item.href)"
                />
            </v-list>

            <template v-if="adminNavItems.length">
                <v-divider />
                <v-list nav density="comfortable">
                    <v-list-subheader v-if="!rail">Administration</v-list-subheader>
                    <v-list-item
                        v-for="item in adminNavItems"
                        :key="item.label"
                        :prepend-icon="item.icon"
                        :title="item.label"
                        :active="item.active"
                        :href="item.href"
                        rounded="lg"
                        @click="handleNavClick($event, item.href)"
                    />
                </v-list>
            </template>
        </v-navigation-drawer>

        <v-app-bar color="brand" border="0" flat>
            <v-app-bar-nav-icon
                class="d-md-none"
                @click="drawer = !drawer"
            />

            <v-app-bar-title>
                <slot name="header" />
            </v-app-bar-title>

            <v-spacer />

            <AppCenterSwitcher />

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

            <AppNotificationBell />

            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" variant="text" class="text-none">
                        {{ $page.props.auth.user.name }}
                        <v-icon icon="mdi-chevron-down" end />
                    </v-btn>
                </template>
                <v-list density="compact">
                    <v-list-item
                        :href="route('profile.edit')"
                        title="Profil"
                        prepend-icon="mdi-account-outline"
                        @click="handleNavClick($event, route('profile.edit'))"
                    />
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

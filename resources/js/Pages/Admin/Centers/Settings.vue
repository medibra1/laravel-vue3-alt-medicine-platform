<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppModulePicker from '@/Components/App/AppModulePicker.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTabs, { type AppTabItem } from '@/Components/App/AppTabs.vue';
import CenterClosuresDialog from '@/Components/Scheduling/CenterClosuresDialog.vue';
import CenterOperatingHoursDialog from '@/Components/Scheduling/CenterOperatingHoursDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { AppModule } from '@/types';
import { summarizeSchedule, type WeeklySlot } from '@/utils/weeklySchedule';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface CareCategory {
    id: number;
    label: string;
    items: { id: number; label: string; price: number | null }[];
}

const props = defineProps<{
    center: {
        id: number;
        name: string;
        city: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        currency: string;
        timezone: string;
        modules: AppModule[];
        settings: {
            session_price_in_person: number;
            session_price_remote: number;
            default_session_duration: number;
            default_modality: string;
        };
        operating_hours: WeeklySlot[];
    };
    careCategories: CareCategory[];
    allModules: AppModule[];
    timezones: string[];
    canEditModules: boolean;
}>();

const { t } = useI18n();

const tabs = computed<AppTabItem[]>(() => [
    { title: t('centers.settingsPage.tabs.general'), value: 'general' },
    { title: t('centers.settingsPage.tabs.pricing'), value: 'pricing' },
    { title: t('centers.settingsPage.tabs.modules'), value: 'modules' },
    { title: t('centers.settingsPage.tabs.schedule'), value: 'schedule' },
]);
const activeTab = ref('general');
const hoursDialog = ref(false);
const closuresDialog = ref(false);

const form = useForm({
    phone: props.center.phone ?? '',
    email: props.center.email ?? '',
    address: props.center.address ?? '',
    currency: props.center.currency,
    timezone: props.center.timezone,
    modules: [...props.center.modules] as AppModule[],
    settings: { ...props.center.settings } as {
        session_price_in_person: number | null;
        session_price_remote: number | null;
        default_session_duration: number | null;
        default_modality: string;
    },
    care_prices: props.careCategories.flatMap((category) =>
        category.items.map((item) => ({ care_item_id: item.id, price: item.price })),
    ) as { care_item_id: number; price: number | null }[],
});

const priceIndex = computed(() => new Map(form.care_prices.map((row, index) => [row.care_item_id, index])));
const timezoneOptions = computed(() => props.timezones.map((value) => ({ value, label: value.replace(/_/g, ' ') })));
const modalityOptions = computed(() => ['in_person', 'remote'].map((value) => ({ value, label: t(`domain.modality.${value}`) })));
const scheduleSummary = computed(() => summarizeSchedule(props.center.operating_hours) || t('centers.noHours'));

// Maps a validation error (settings.x, care_prices.N.price) to the tab that holds the field.
const errorTab = computed(() => {
    const keys = Object.keys(form.errors);

    if (keys.some((key) => key.startsWith('settings') || key.startsWith('care_prices'))) return 'pricing';
    if (keys.some((key) => key.startsWith('modules'))) return 'modules';

    return keys.length ? 'general' : null;
});

function submit() {
    form.put(route('admin.centers.settings.update', props.center.id), {
        preserveScroll: true,
        onError: () => {
            if (errorTab.value) {
                activeTab.value = errorTab.value;
            }
        },
    });
}

function error(key: string): string | undefined {
    return (form.errors as Record<string, string>)[key];
}
</script>

<template>
    <Head :title="t('centers.settingsPage.title')" />

    <AuthenticatedLayout>
        <AppPageHeader
            :title="center.name"
            :subtitle="t('centers.settingsPage.subtitle', { name: center.name })"
            :breadcrumbs="[
                { label: t('shell.nav.dashboard'), href: route('dashboard') },
                ...($page.props.auth.is_global_admin ? [{ label: t('centers.title'), href: route('admin.centers.index') }] : []),
                { label: t('centers.settingsPage.title') },
            ]"
        >
            <template #actions>
                <v-fade-transition>
                    <v-chip v-if="form.recentlySuccessful" color="success" variant="tonal" prepend-icon="mdi-check">
                        {{ t('centers.settingsPage.saved') }}
                    </v-chip>
                </v-fade-transition>
                <AppButton icon="mdi-content-save-outline" :label="t('common.save')" :loading="form.processing" @click="submit" />
            </template>
        </AppPageHeader>

        <form @submit.prevent="submit">
            <AppTabs v-model="activeTab" :tabs="tabs">
                <template #general>
                    <v-row>
                        <v-col cols="12" lg="6">
                            <AppCard variant="flat" class="h-100">
                                <v-card-text class="pa-6 d-flex flex-column ga-4">
                                    <div>
                                        <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.contact') }}</h2>
                                        <p class="text-body-2 text-medium-emphasis mb-0">{{ t('centers.settingsPage.contactHint') }}</p>
                                    </div>
                                    <AppInputText v-model="form.phone" :label="t('common.phone')" :error="form.errors.phone" />
                                    <AppInputText v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" />
                                    <AppInputText v-model="form.address" :label="t('common.address')" :error="form.errors.address" />
                                </v-card-text>
                            </AppCard>
                        </v-col>
                        <v-col cols="12" lg="6">
                            <AppCard variant="flat" class="h-100">
                                <v-card-text class="pa-6 d-flex flex-column ga-4">
                                    <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.regional') }}</h2>
                                    <div>
                                        <AppInputText v-model="form.currency" :maxlength="3" :label="t('centers.settingsPage.currency')" :error="form.errors.currency" />
                                        <p class="text-caption text-medium-emphasis mt-1 mb-0">{{ t('centers.settingsPage.currencyHint') }}</p>
                                    </div>
                                    <div>
                                        <v-autocomplete
                                            v-model="form.timezone"
                                            :items="timezoneOptions"
                                            item-title="label"
                                            item-value="value"
                                            :label="t('centers.settingsPage.timezone')"
                                            :error-messages="form.errors.timezone"
                                            variant="outlined"
                                            hide-details="auto"
                                        />
                                        <p class="text-caption text-medium-emphasis mt-1 mb-0">{{ t('centers.settingsPage.timezoneHint') }}</p>
                                    </div>
                                </v-card-text>
                            </AppCard>
                        </v-col>
                    </v-row>
                </template>

                <template #pricing>
                    <v-row>
                        <v-col cols="12" lg="5">
                            <AppCard variant="flat">
                                <v-card-text class="pa-6 d-flex flex-column ga-4">
                                    <div>
                                        <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.sessions') }}</h2>
                                        <p class="text-body-2 text-medium-emphasis mb-0">{{ t('centers.settingsPage.sessionsHint') }}</p>
                                    </div>
                                    <AppInputNumber
                                        v-model="form.settings.session_price_in_person"
                                        :min="0"
                                        :label="`${t('centers.settingsPage.priceInPerson')} (${form.currency})`"
                                        :error="error('settings.session_price_in_person')"
                                    />
                                    <AppInputNumber
                                        v-model="form.settings.session_price_remote"
                                        :min="0"
                                        :label="`${t('centers.settingsPage.priceRemote')} (${form.currency})`"
                                        :error="error('settings.session_price_remote')"
                                    />
                                    <AppInputNumber
                                        v-model="form.settings.default_session_duration"
                                        :min="5"
                                        :label="t('centers.settingsPage.defaultDuration')"
                                        :error="error('settings.default_session_duration')"
                                    />
                                    <AppSelect
                                        v-model="form.settings.default_modality"
                                        :options="modalityOptions"
                                        option-label="label"
                                        option-value="value"
                                        :label="t('centers.settingsPage.defaultModality')"
                                    />
                                </v-card-text>
                            </AppCard>
                        </v-col>
                        <v-col cols="12" lg="7">
                            <AppCard variant="flat">
                                <v-card-text class="pa-6">
                                    <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.carePrices') }}</h2>
                                    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('centers.settingsPage.carePricesHint') }}</p>

                                    <div v-for="category in careCategories" :key="category.id" class="mb-5">
                                        <div class="app-eyebrow mb-2">{{ category.label }}</div>
                                        <div v-for="item in category.items" :key="item.id" class="care-price">
                                            <span class="text-body-2">{{ item.label }}</span>
                                            <div style="width: 170px">
                                                <AppInputNumber
                                                    v-model="form.care_prices[priceIndex.get(item.id)!].price"
                                                    :min="0"
                                                    :label="form.currency"
                                                    :error="error(`care_prices.${priceIndex.get(item.id)}.price`)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </v-card-text>
                            </AppCard>
                        </v-col>
                    </v-row>
                </template>

                <template #modules>
                    <AppCard variant="flat">
                        <v-card-text class="pa-6">
                            <h2 class="text-subtitle-1 font-weight-bold">{{ t('modules.title') }}</h2>
                            <p class="text-body-2 text-medium-emphasis mb-4">{{ t('modules.hint') }}</p>
                            <v-alert v-if="!canEditModules" type="info" variant="tonal" density="compact" class="mb-4">
                                {{ t('centers.settingsPage.modulesLocked') }}
                            </v-alert>
                            <AppModulePicker v-model="form.modules" :available="allModules" :disabled="!canEditModules" />
                        </v-card-text>
                    </AppCard>
                </template>

                <template #schedule>
                    <v-row>
                        <v-col cols="12" md="6">
                            <AppCard variant="flat" class="h-100">
                                <v-card-text class="pa-6">
                                    <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.hours') }}</h2>
                                    <p class="text-body-2 text-medium-emphasis">{{ t('centers.settingsPage.hoursHint') }}</p>
                                    <p class="text-body-1 font-weight-medium">{{ scheduleSummary }}</p>
                                    <AppButton severity="secondary" icon="mdi-clock-edit-outline" :label="t('centers.settingsPage.editHours')" @click="hoursDialog = true" />
                                </v-card-text>
                            </AppCard>
                        </v-col>
                        <v-col cols="12" md="6">
                            <AppCard variant="flat" class="h-100">
                                <v-card-text class="pa-6">
                                    <h2 class="text-subtitle-1 font-weight-bold">{{ t('centers.settingsPage.closures') }}</h2>
                                    <p class="text-body-2 text-medium-emphasis">{{ t('centers.settingsPage.closuresHint') }}</p>
                                    <AppButton severity="secondary" icon="mdi-calendar-remove-outline" :label="t('centers.settingsPage.manageClosures')" @click="closuresDialog = true" />
                                </v-card-text>
                            </AppCard>
                        </v-col>
                    </v-row>
                </template>
            </AppTabs>
        </form>

        <CenterOperatingHoursDialog v-model:visible="hoursDialog" :center="center" :initial-slots="center.operating_hours" />
        <CenterClosuresDialog v-model:visible="closuresDialog" :center="center" />
    </AuthenticatedLayout>
</template>

<style scoped>
.care-price {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>

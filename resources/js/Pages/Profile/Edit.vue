<script setup lang="ts">
import AppCard from '@/Components/App/AppCard.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useLocale } from '@/lib/useLocale';
import { Head } from '@inertiajs/vue3';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const { locale, setLocale, options: localeOptions } = useLocale();
</script>

<template>
    <Head :title="$t('auth.profile.title')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('auth.profile.title')" />

        <div class="d-flex flex-column ga-6" style="max-width: 720px">
            <AppCard variant="flat">
                <v-card-text class="pa-6">
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                </v-card-text>
            </AppCard>

            <AppCard variant="flat">
                <v-card-text class="pa-6">
                    <h2 class="text-h6 font-weight-bold">{{ $t('auth.profile.language') }}</h2>
                    <p class="text-body-2 text-medium-emphasis mt-1 mb-4">{{ $t('auth.profile.languageHint') }}</p>
                    <v-btn-toggle :model-value="locale" mandatory color="primary" variant="outlined" divided @update:model-value="setLocale">
                        <v-btn v-for="option in localeOptions" :key="option.value" :value="option.value">{{ option.label }}</v-btn>
                    </v-btn-toggle>
                </v-card-text>
            </AppCard>

            <AppCard variant="flat">
                <v-card-text class="pa-6">
                    <UpdatePasswordForm />
                </v-card-text>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>

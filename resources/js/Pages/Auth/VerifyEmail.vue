<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout :title="$t('auth.verify.title')" :subtitle="$t('auth.verify.subtitle')">
        <Head :title="$t('auth.verify.title')" />

        <v-alert v-if="verificationLinkSent" type="success" variant="tonal" class="mb-6">{{ $t('auth.verify.sent') }}</v-alert>

        <form class="d-flex flex-column ga-4" @submit.prevent="submit">
            <AppButton type="submit" size="large" block :label="$t('auth.verify.resend')" :loading="form.processing" />

            <Link :href="route('logout')" method="post" as="button" class="text-body-2 font-weight-medium text-primary text-center">
                {{ $t('auth.verify.logout') }}
            </Link>
        </form>
    </GuestLayout>
</template>

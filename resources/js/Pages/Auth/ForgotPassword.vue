<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout :title="$t('auth.forgot.title')" :subtitle="$t('auth.forgot.subtitle')">
        <Head :title="$t('auth.forgot.title')" />

        <v-alert v-if="status" type="success" variant="tonal" class="mb-6">{{ status }}</v-alert>

        <form class="d-flex flex-column ga-5" @submit.prevent="submit">
            <AppInputText
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('common.email')"
                autofocus
                autocomplete="username"
                :error="form.errors.email"
            />

            <AppButton type="submit" size="large" block :label="$t('auth.forgot.submit')" :loading="form.processing" />

            <Link :href="route('login')" class="text-body-2 font-weight-medium text-primary text-center">
                {{ $t('auth.forgot.backToLogin') }}
            </Link>
        </form>
    </GuestLayout>
</template>

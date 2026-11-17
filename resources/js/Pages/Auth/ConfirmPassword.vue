<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout :title="$t('auth.confirm.title')" :subtitle="$t('auth.confirm.subtitle')">
        <Head :title="$t('auth.confirm.title')" />

        <form class="d-flex flex-column ga-5" @submit.prevent="submit">
            <AppInputText
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.password')"
                autofocus
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <AppButton type="submit" size="large" block :label="$t('auth.confirm.submit')" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>

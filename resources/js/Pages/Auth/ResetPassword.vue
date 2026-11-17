<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout :title="$t('auth.reset.title')" :subtitle="$t('auth.reset.subtitle')">
        <Head :title="$t('auth.reset.title')" />

        <form class="d-flex flex-column ga-5" @submit.prevent="submit">
            <AppInputText
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('common.email')"
                autocomplete="username"
                :error="form.errors.email"
            />

            <AppInputText
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.newPassword')"
                autofocus
                autocomplete="new-password"
                :error="form.errors.password"
            />

            <AppInputText
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="$t('auth.passwordConfirm')"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <AppButton type="submit" size="large" block :label="$t('auth.reset.submit')" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>

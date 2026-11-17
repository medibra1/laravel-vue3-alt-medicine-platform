<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout :title="$t('auth.login.title')" :subtitle="$t('auth.login.subtitle')">
        <Head :title="$t('auth.login.submit')" />

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

            <AppInputText
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.password')"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                <AppCheckbox v-model="form.remember" :label="$t('auth.login.remember')" />
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-body-2 font-weight-medium text-primary">
                    {{ $t('auth.login.forgot') }}
                </Link>
            </div>

            <AppButton type="submit" size="large" block :label="$t('auth.login.submit')" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>

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
    <GuestLayout>
        <Head title="Reset Password" />

        <form @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">Email</label>
                <AppInputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    autofocus
                    autocomplete="username"
                    :error="form.errors.email"
                />
            </div>

            <div class="mt-4 flex flex-col gap-1">
                <label class="text-sm text-gray-600">Password</label>
                <AppInputText
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password"
                />
            </div>

            <div class="mt-4 flex flex-col gap-1">
                <label class="text-sm text-gray-600">Confirm Password</label>
                <AppInputText
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <AppButton
                    type="submit"
                    label="Reset Password"
                    :loading="form.processing"
                />
            </div>
        </form>
    </GuestLayout>
</template>

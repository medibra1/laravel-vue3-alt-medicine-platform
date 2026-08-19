<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

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
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-4 text-sm text-gray-600">
            Forgot your password? No problem. Just let us know your email
            address and we will email you a password reset link that will allow
            you to choose a new one.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

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

            <div class="mt-4 flex items-center justify-end">
                <AppButton
                    type="submit"
                    label="Email Password Reset Link"
                    :loading="form.processing"
                />
            </div>
        </form>
    </GuestLayout>
</template>

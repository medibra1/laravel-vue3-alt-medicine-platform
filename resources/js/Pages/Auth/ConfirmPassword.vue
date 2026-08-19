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
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="mb-4 text-sm text-gray-600">
            This is a secure area of the application. Please confirm your
            password before continuing.
        </div>

        <form @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">Password</label>
                <AppInputText
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    autofocus
                    :error="form.errors.password"
                />
            </div>

            <div class="mt-4 flex justify-end">
                <AppButton
                    type="submit"
                    label="Confirm"
                    class="ms-4"
                    :loading="form.processing"
                />
            </div>
        </form>
    </GuestLayout>
</template>

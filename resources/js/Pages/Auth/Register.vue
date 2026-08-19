<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <form @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">Name</label>
                <AppInputText
                    id="name"
                    v-model="form.name"
                    autofocus
                    autocomplete="name"
                    :error="form.errors.name"
                />
            </div>

            <div class="mt-4 flex flex-col gap-1">
                <label class="text-sm text-gray-600">Email</label>
                <AppInputText
                    id="email"
                    v-model="form.email"
                    type="email"
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
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Already registered?
                </Link>

                <AppButton
                    type="submit"
                    label="Register"
                    class="ms-4"
                    :loading="form.processing"
                />
            </div>
        </form>
    </GuestLayout>
</template>

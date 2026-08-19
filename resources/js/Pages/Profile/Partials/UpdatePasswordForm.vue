<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<InstanceType<typeof AppInputText> | null>(null);
const currentPasswordInput = ref<InstanceType<typeof AppInputText> | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Update Password
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">Current Password</label>
                <AppInputText
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    :error="form.errors.current_password"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">New Password</label>
                <AppInputText
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm text-gray-600">Confirm Password</label>
                <AppInputText
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password_confirmation"
                />
            </div>

            <div class="flex items-center gap-4">
                <AppButton type="submit" label="Save" :loading="form.processing" />

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

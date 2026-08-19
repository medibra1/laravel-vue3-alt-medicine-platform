<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<InstanceType<typeof AppInputText> | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Delete Account
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <AppButton severity="danger" @click="confirmUserDeletion">
            Delete Account
        </AppButton>

        <AppDialog
            :visible="confirmingUserDeletion"
            header="Are you sure you want to delete your account?"
            @update:visible="closeModal"
        >
            <p class="text-sm text-gray-600">
                Once your account is deleted, all of its resources and data
                will be permanently deleted. Please enter your password to
                confirm you would like to permanently delete your account.
            </p>

            <div class="mt-6">
                <AppInputText
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Password"
                    :error="form.errors.password"
                    @keyup.enter="deleteUser"
                />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <AppButton severity="secondary" @click="closeModal">
                    Cancel
                </AppButton>

                <AppButton
                    severity="danger"
                    :loading="form.processing"
                    @click="deleteUser"
                >
                    Delete Account
                </AppButton>
            </div>
        </AppDialog>
    </section>
</template>

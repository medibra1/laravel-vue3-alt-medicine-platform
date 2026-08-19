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
    <section class="d-flex flex-column ga-6">
        <header>
            <h2 class="text-h6 font-weight-medium">
                Delete Account
            </h2>

            <p class="text-body-2 text-medium-emphasis mt-1">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <AppButton severity="danger" class="align-self-start" @click="confirmUserDeletion">
            Delete Account
        </AppButton>

        <AppDialog
            :visible="confirmingUserDeletion"
            header="Are you sure you want to delete your account?"
            @update:visible="closeModal"
        >
            <p class="text-body-2 text-medium-emphasis">
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

            <div class="mt-6 d-flex justify-end ga-2">
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

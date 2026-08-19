<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>Profil</template>

        <div class="d-flex flex-column ga-6" style="max-width: 640px">
            <v-card>
                <v-card-text class="pa-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </v-card-text>
            </v-card>

            <v-card>
                <v-card-text class="pa-6">
                    <UpdatePasswordForm />
                </v-card-text>
            </v-card>

            <v-card>
                <v-card-text class="pa-6">
                    <DeleteUserForm />
                </v-card-text>
            </v-card>
        </div>
    </AuthenticatedLayout>
</template>

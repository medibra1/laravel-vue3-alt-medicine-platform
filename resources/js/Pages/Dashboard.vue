<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>Dashboard</template>

        <v-card>
            <v-card-text>You're logged in!</v-card-text>
        </v-card>
    </AuthenticatedLayout>
</template>

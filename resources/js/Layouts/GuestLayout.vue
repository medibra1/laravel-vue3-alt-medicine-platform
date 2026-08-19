<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <v-app>
        <v-main class="bg-grey-lighten-4">
            <v-container class="d-flex flex-column align-center justify-center" style="min-height: 100vh">
                <Link href="/" class="mb-6">
                    <ApplicationLogo class="h-16 w-16 fill-current text-grey-darken-1" style="height: 4rem; width: 4rem" />
                </Link>

                <v-card class="w-100 pa-6" max-width="440" elevation="2">
                    <slot />
                </v-card>
            </v-container>
        </v-main>
    </v-app>
</template>

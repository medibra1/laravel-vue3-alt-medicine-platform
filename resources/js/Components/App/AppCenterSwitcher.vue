<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CenterOption {
    id: number;
    name: string;
}

const page = usePage();

const centers = computed(
    () => (page.props.auth as { accessible_centers?: CenterOption[] }).accessible_centers ?? [],
);
const activeCenterId = computed(
    () => (page.props.auth as { active_center_id?: number | null }).active_center_id ?? null,
);
const activeCenterName = computed(
    () => centers.value.find((center) => center.id === activeCenterId.value)?.name ?? '',
);

function switchCenter(centerId: number) {
    if (centerId === activeCenterId.value) {
        return;
    }

    router.post(
        route('admin.active-center.update'),
        { center_id: centerId },
        { onSuccess: () => router.reload() },
    );
}
</script>

<template>
    <v-menu v-if="centers.length > 1">
        <template #activator="{ props: menuProps }">
            <v-btn v-bind="menuProps" variant="text" prepend-icon="mdi-domain" class="text-none">
                {{ activeCenterName }}
                <v-icon icon="mdi-chevron-down" end />
            </v-btn>
        </template>
        <v-list density="compact">
            <v-list-subheader>Centre actif</v-list-subheader>
            <v-list-item
                v-for="center in centers"
                :key="center.id"
                :title="center.name"
                :active="center.id === activeCenterId"
                @click="switchCenter(center.id)"
            />
        </v-list>
    </v-menu>
</template>

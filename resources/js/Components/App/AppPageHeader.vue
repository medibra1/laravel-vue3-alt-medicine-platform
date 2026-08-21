<script setup lang="ts">
import { computed } from 'vue';

export interface AppBreadcrumbItem {
    label: string;
    href?: string;
}

const props = withDefaults(
    defineProps<{
        title: string;
        breadcrumbs?: AppBreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const items = computed(() =>
    props.breadcrumbs.map((item) => ({
        title: item.label,
        href: item.href,
        disabled: !item.href,
    })),
);
</script>

<template>
    <div class="d-flex flex-column flex-md-row flex-wrap align-md-center justify-space-between ga-3 mb-4">
        <div class="d-flex flex-column ga-1">
            <h1 class="text-h5 text-md-h4 font-weight-bold mb-0">{{ title }}</h1>
            <v-breadcrumbs v-if="items.length" :items="items" density="compact" class="pa-0" />
        </div>

        <div v-if="$slots.actions" class="d-flex flex-wrap ga-2">
            <slot name="actions" />
        </div>
    </div>
</template>

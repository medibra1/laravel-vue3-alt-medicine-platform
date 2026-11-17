<script setup lang="ts">
import { handleNavClick } from '@/utils/inertiaNavClick';
import { computed } from 'vue';

export interface AppBreadcrumbItem {
    label: string;
    href?: string;
}

const props = withDefaults(
    defineProps<{
        title: string;
        breadcrumbs?: AppBreadcrumbItem[];
        subtitle?: string;
    }>(),
    {
        breadcrumbs: () => [],
        subtitle: undefined,
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
    <div class="d-flex flex-column flex-md-row flex-wrap align-md-end justify-space-between ga-4 mb-6">
        <div class="d-flex flex-column ga-1" style="min-width: 0">
            <v-breadcrumbs v-if="items.length" :items="items" density="compact" class="pa-0 text-body-2 text-medium-emphasis">
                <template #item="{ item }">
                    <v-breadcrumbs-item
                        :title="item.title"
                        :href="item.href"
                        :disabled="item.disabled"
                        @click="item.href && handleNavClick($event, item.href)"
                    />
                </template>
                <template #divider><v-icon icon="mdi-chevron-right" size="16" class="app-flip-rtl" /></template>
            </v-breadcrumbs>
            <div class="d-flex align-center flex-wrap ga-3">
                <h1 class="app-page-title">{{ title }}</h1>
                <slot name="title-suffix" />
            </div>
            <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-0">{{ subtitle }}</p>
        </div>

        <div v-if="$slots.actions" class="d-flex flex-wrap ga-2">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.app-page-title {
    margin: 0;
    font-size: clamp(1.5rem, 2.2vw, 1.9rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.025em;
}

:global([dir='rtl'] .app-flip-rtl) {
    transform: scaleX(-1);
}
</style>

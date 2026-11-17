<script setup lang="ts">
import { handleNavClick } from '@/utils/inertiaNavClick';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon: string;
        /** Theme color name for the icon tile. */
        color?: string;
        hint?: string;
        /** Current vs previous period — renders a signed % trend. */
        current?: number | null;
        previous?: number | null;
        trendLabel?: string;
        href?: string;
    }>(),
    {
        color: 'primary',
        hint: undefined,
        current: null,
        previous: null,
        trendLabel: undefined,
        href: undefined,
    },
);

const trend = computed(() => {
    if (props.current === null || props.previous === null || props.previous === 0) {
        return null;
    }

    return Math.round(((props.current - props.previous) / props.previous) * 100);
});
</script>

<template>
    <v-card variant="flat" class="stat-card h-100" :href="href" @click="href && handleNavClick($event, href)">
        <div class="d-flex align-start justify-space-between ga-3">
            <div style="min-width: 0">
                <div class="text-body-2 font-weight-medium text-medium-emphasis text-truncate">{{ label }}</div>
                <div class="stat-card__value app-tabular">{{ value }}</div>
            </div>
            <v-avatar :color="color" variant="tonal" rounded="lg" size="44">
                <v-icon :icon="icon" size="22" />
            </v-avatar>
        </div>

        <div class="d-flex align-center flex-wrap ga-2 mt-3 text-caption">
            <span v-if="trend !== null" class="stat-card__trend" :class="trend >= 0 ? 'text-success' : 'text-error'">
                <v-icon :icon="trend >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'" size="16" />
                {{ trend > 0 ? '+' : '' }}{{ trend }}%
            </span>
            <span v-if="trend !== null && trendLabel" class="text-medium-emphasis">{{ trendLabel }}</span>
            <span v-else-if="hint" class="text-medium-emphasis">{{ hint }}</span>
        </div>
    </v-card>
</template>

<style scoped>
.stat-card {
    padding: 20px;
    transition: transform 0.15s, box-shadow 0.15s;
}

a.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--app-shadow-md) !important;
}

.stat-card__value {
    margin-top: 6px;
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
}

.stat-card__trend {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-weight: 700;
}
</style>

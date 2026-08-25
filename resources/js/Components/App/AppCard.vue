<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        /** mdi icon name (e.g. 'mdi-stomach'), shown next to the title. No effect without `title`. */
        icon?: string | null;
        clickable?: boolean;
        selected?: boolean;
        /** Default: 'tonal' when selected, 'outlined' otherwise
         * (selection cards — diseases, categories). Pass 'elevated' for
         * a content card (form, table). */
        variant?: 'outlined' | 'tonal' | 'flat' | 'elevated' | 'text' | 'plain';
        elevation?: string | number;
    }>(),
    {
        title: undefined,
        icon: null,
        clickable: false,
        selected: false,
        variant: undefined,
        elevation: undefined,
    },
);

defineEmits<{ click: [] }>();
</script>

<template>
    <v-card
        :variant="variant ?? (selected ? 'tonal' : 'outlined')"
        :color="selected ? 'primary' : undefined"
        :elevation="elevation"
        :link="clickable"
        @click="clickable ? $emit('click') : undefined"
    >
        <template v-if="title" #title>
            <div class="d-flex align-center ga-2 app-card-title">
                <v-icon v-if="icon" :icon="icon" />
                <span>{{ title }}</span>
            </div>
        </template>

        <slot />
    </v-card>
</template>

<style scoped>
/* v-card-title (the #title slot's host) defaults to white-space: nowrap
 * + text-overflow: ellipsis on a single line — fine for short titles,
 * but truncates longer category/disease labels mid-word. Allow wrapping
 * up to 2 lines, ellipsis only past that. */
.app-card-title span {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    white-space: normal;
}
</style>

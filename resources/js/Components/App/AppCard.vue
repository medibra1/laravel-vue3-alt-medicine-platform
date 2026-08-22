<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
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
        :title="title"
        :variant="variant ?? (selected ? 'tonal' : 'outlined')"
        :color="selected ? 'primary' : undefined"
        :elevation="elevation"
        :link="clickable"
        @click="clickable ? $emit('click') : undefined"
    >
        <slot />
    </v-card>
</template>

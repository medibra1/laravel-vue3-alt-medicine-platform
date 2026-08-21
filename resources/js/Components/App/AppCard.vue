<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        clickable?: boolean;
        selected?: boolean;
    }>(),
    {
        title: undefined,
        clickable: false,
        selected: false,
    },
);

defineEmits<{ click: [] }>();
</script>

<template>
    <v-card
        :title="title"
        :variant="selected ? 'tonal' : 'outlined'"
        :color="selected ? 'primary' : undefined"
        :link="clickable"
        @click="clickable ? $emit('click') : undefined"
    >
        <slot />
    </v-card>
</template>

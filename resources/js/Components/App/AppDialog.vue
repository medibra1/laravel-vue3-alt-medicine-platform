<script setup lang="ts">
withDefaults(
    defineProps<{
        visible: boolean;
        header?: string;
        maxWidth?: string;
    }>(),
    {
        header: undefined,
        maxWidth: '480px',
    },
);

defineEmits<{ 'update:visible': [value: boolean] }>();
</script>

<template>
    <v-dialog
        :model-value="visible"
        :max-width="maxWidth"
        @update:model-value="$emit('update:visible', $event)"
    >
        <v-card>
            <v-card-title v-if="header" class="text-wrap">{{ header }}</v-card-title>
            <v-card-text>
                <slot />
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

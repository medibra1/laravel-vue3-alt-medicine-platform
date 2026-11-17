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
        scrollable
        @update:model-value="$emit('update:visible', $event)"
    >
        <v-card>
            <div v-if="header" class="d-flex align-center justify-space-between ga-3 px-6 pt-5 pb-2">
                <h2 class="text-h6 font-weight-bold text-wrap">{{ header }}</h2>
                <v-btn icon="mdi-close" variant="text" size="small" :aria-label="$t('common.close')" @click="$emit('update:visible', false)" />
            </div>
            <v-card-text class="px-6 pb-6 pt-3">
                <slot />
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

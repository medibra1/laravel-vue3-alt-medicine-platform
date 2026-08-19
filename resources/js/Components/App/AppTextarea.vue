<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: string | null;
        rows?: number | string;
        error?: string | null;
    }>(),
    {
        rows: 3,
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <v-textarea
        :model-value="modelValue"
        :rows="rows"
        :error-messages="error ?? undefined"
        density="compact"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

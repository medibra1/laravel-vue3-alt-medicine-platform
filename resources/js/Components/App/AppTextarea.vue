<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: string | null;
        rows?: number | string;
        label?: string;
        error?: string | null;
    }>(),
    {
        rows: 3,
        label: undefined,
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <v-textarea
        :model-value="modelValue"
        :rows="rows"
        :label="label"
        :error-messages="error ?? undefined"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

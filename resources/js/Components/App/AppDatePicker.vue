<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: Date | null;
        label?: string;
        error?: string | null;
    }>(),
    {
        label: undefined,
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: Date | null] }>();
</script>

<template>
    <v-date-input
        :model-value="modelValue"
        :label="label"
        :error-messages="error ?? undefined"
        variant="outlined"
        hide-details="auto"
        prepend-icon=""
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

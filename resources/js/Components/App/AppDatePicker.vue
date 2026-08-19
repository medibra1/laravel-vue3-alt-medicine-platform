<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: Date | null;
        error?: string | null;
    }>(),
    {
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: Date | null] }>();
</script>

<template>
    <v-date-input
        :model-value="modelValue"
        :error-messages="error ?? undefined"
        density="compact"
        variant="outlined"
        hide-details="auto"
        prepend-icon=""
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

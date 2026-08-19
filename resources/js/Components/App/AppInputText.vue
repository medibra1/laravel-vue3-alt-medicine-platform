<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: string | null;
        type?: string;
        maxlength?: number;
        error?: string | null;
        placeholder?: string;
        id?: string;
    }>(),
    {
        type: 'text',
        maxlength: undefined,
        error: null,
        placeholder: undefined,
        id: undefined,
    },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <v-text-field
        :id="id"
        :model-value="modelValue"
        :type="type"
        :maxlength="maxlength"
        :placeholder="placeholder"
        :error-messages="error ?? undefined"
        density="compact"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

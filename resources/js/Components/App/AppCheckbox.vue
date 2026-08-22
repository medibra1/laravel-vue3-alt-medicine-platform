<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    label?: string;
    disabled?: boolean;
}>();

defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <v-checkbox
        :model-value="modelValue"
        :label="label"
        :disabled="disabled"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', Boolean($event))"
    />
</template>

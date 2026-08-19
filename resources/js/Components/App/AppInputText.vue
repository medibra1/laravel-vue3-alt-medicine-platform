<script setup lang="ts">
import { ref } from 'vue';

withDefaults(
    defineProps<{
        modelValue: string | null;
        type?: string;
        maxlength?: number;
        error?: string | null;
        placeholder?: string;
        label?: string;
        id?: string;
        autocomplete?: string;
        autofocus?: boolean;
    }>(),
    {
        type: 'text',
        maxlength: undefined,
        error: null,
        placeholder: undefined,
        label: undefined,
        id: undefined,
        autocomplete: undefined,
        autofocus: false,
    },
);

defineEmits<{ 'update:modelValue': [value: string] }>();

const field = ref<{ focus: () => void } | null>(null);

defineExpose({
    focus: () => field.value?.focus(),
});
</script>

<template>
    <v-text-field
        ref="field"
        :id="id"
        :model-value="modelValue"
        :type="type"
        :maxlength="maxlength"
        :placeholder="placeholder"
        :label="label"
        :autocomplete="autocomplete"
        :autofocus="autofocus"
        :error-messages="error ?? undefined"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

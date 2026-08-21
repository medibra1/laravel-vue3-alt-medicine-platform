<script setup lang="ts">
import { computed } from 'vue';

export interface AppStepperStep {
    title: string;
    value: string | number;
}

const props = defineProps<{
    steps: AppStepperStep[];
    modelValue: string | number;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>();

const currentIndex = computed(() => props.steps.findIndex((step) => step.value === props.modelValue));

const canGoPrev = computed(() => currentIndex.value > 0);
const canGoNext = computed(() => currentIndex.value >= 0 && currentIndex.value < props.steps.length - 1);

function prev() {
    if (canGoPrev.value) {
        emit('update:modelValue', props.steps[currentIndex.value - 1].value);
    }
}

function next() {
    if (canGoNext.value) {
        emit('update:modelValue', props.steps[currentIndex.value + 1].value);
    }
}

defineExpose({ next, prev, canGoNext, canGoPrev });
</script>

<template>
    <v-stepper
        :model-value="modelValue"
        hide-actions
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <v-stepper-header>
            <template v-for="(step, index) in steps" :key="step.value">
                <v-stepper-item :value="step.value" :title="step.title" editable />
                <v-divider v-if="index < steps.length - 1" />
            </template>
        </v-stepper-header>

        <slot :step="modelValue" />
    </v-stepper>
</template>

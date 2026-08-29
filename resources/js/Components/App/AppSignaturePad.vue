<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        label?: string;
    }>(),
    {
        label: undefined,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const canvasEl = ref<HTMLCanvasElement | null>(null);
const drawing = ref(false);
const hasStroke = ref(false);

function context(): CanvasRenderingContext2D | null {
    return canvasEl.value?.getContext('2d') ?? null;
}

function pointerPosition(event: PointerEvent): { x: number; y: number } {
    const rect = (event.target as HTMLCanvasElement).getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function startStroke(event: PointerEvent) {
    const ctx = context();
    if (!ctx) return;

    drawing.value = true;
    const { x, y } = pointerPosition(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function continueStroke(event: PointerEvent) {
    if (!drawing.value) return;
    const ctx = context();
    if (!ctx) return;

    const { x, y } = pointerPosition(event);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1c3250';
    ctx.lineTo(x, y);
    ctx.stroke();
    hasStroke.value = true;
}

function endStroke() {
    if (!drawing.value) return;
    drawing.value = false;
    emit('update:modelValue', hasStroke.value ? canvasEl.value!.toDataURL('image/png') : null);
}

function clear() {
    const ctx = context();
    if (!ctx || !canvasEl.value) return;

    ctx.clearRect(0, 0, canvasEl.value.width, canvasEl.value.height);
    hasStroke.value = false;
    emit('update:modelValue', null);
}

onMounted(() => {
    // Backing store matches the CSS size 1:1 — no devicePixelRatio
    // scaling here, a signature doesn't need retina sharpness and this
    // keeps pointer coordinates simple (no scale factor to apply).
    if (!canvasEl.value) return;
    canvasEl.value.width = canvasEl.value.clientWidth;
    canvasEl.value.height = canvasEl.value.clientHeight;
});
</script>

<template>
    <div class="d-flex flex-column ga-2">
        <p v-if="label" class="text-body-2 text-medium-emphasis mb-0">{{ label }}</p>

        <canvas
            ref="canvasEl"
            class="signature-canvas"
            width="400"
            height="150"
            @pointerdown="startStroke"
            @pointermove="continueStroke"
            @pointerup="endStroke"
            @pointerleave="endStroke"
        />

        <div>
            <v-btn variant="tonal" size="small" prepend-icon="mdi-eraser" @click="clear">Effacer</v-btn>
        </div>
    </div>
</template>

<style scoped>
.signature-canvas {
    width: 100%;
    max-width: 400px;
    height: 150px;
    touch-action: none;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    background: rgb(var(--v-theme-surface));
}
</style>

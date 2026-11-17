<script setup lang="ts">
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    type ChartConfiguration,
} from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTheme } from 'vuetify';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

export interface AppChartSeries {
    label: string;
    data: number[];
    /** Hex color of the series — follows the entity, never its rank. */
    color: string;
}

const props = withDefaults(
    defineProps<{
        type: 'line' | 'bar' | 'doughnut';
        labels: string[];
        series: AppChartSeries[];
        /** Doughnut only: one color per slice. */
        sliceColors?: string[];
        height?: number;
        /** Formats axis ticks and tooltip values. */
        format?: (value: number) => string;
        horizontal?: boolean;
    }>(),
    {
        sliceColors: () => [],
        height: 280,
        format: undefined,
        horizontal: false,
    },
);

const canvas = ref<HTMLCanvasElement | null>(null);
const theme = useTheme();
let chart: Chart | null = null;

function ink(alpha: number): string {
    // #fff and #ffffff are both valid theme values.
    let hex = String(theme.current.value.colors['on-surface']).replace('#', '');
    if (hex.length === 3) {
        hex = hex.split('').map((char) => char + char).join('');
    }
    const value = parseInt(hex, 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

const reducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function build(): ChartConfiguration {
    const surface = theme.current.value.colors.surface;
    const format = props.format ?? ((value: number) => new Intl.NumberFormat().format(value));
    const cartesian = props.type !== 'doughnut';
    const valueAxis = props.horizontal ? 'x' : 'y';
    const categoryAxis = props.horizontal ? 'y' : 'x';

    return {
        type: props.type,
        data: {
            labels: props.labels,
            datasets: props.series.map((series) => ({
                label: series.label,
                data: series.data,
                borderColor: props.type === 'doughnut' ? surface : series.color,
                backgroundColor: props.type === 'doughnut' ? props.sliceColors : props.type === 'line' ? `${series.color}1F` : series.color,
                borderWidth: 2,
                fill: props.type === 'line' && props.series.length === 1,
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointBackgroundColor: series.color,
                pointBorderColor: surface,
                pointBorderWidth: 2,
                borderRadius: 4,
                maxBarThickness: 22,
                hoverOffset: 4,
            })),
        },
        options: {
            responsive: true,
            animation: { duration: reducedMotion ? 0 : 500 },
            maintainAspectRatio: false,
            indexAxis: props.horizontal ? 'y' : 'x',
            interaction: { mode: cartesian ? 'index' : 'nearest', intersect: !cartesian },
            ...(props.type === 'doughnut' ? { cutout: '68%' } : {}),
            plugins: {
                // One series is named by the card title; no legend box needed.
                legend: {
                    display: props.type === 'doughnut' || props.series.length > 1,
                    position: props.type === 'doughnut' ? 'bottom' : 'top',
                    align: props.type === 'doughnut' ? 'center' : 'end',
                    labels: { color: ink(0.72), usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, font: { family: 'inherit', size: 12 } },
                },
                tooltip: {
                    backgroundColor: theme.current.value.dark ? '#0A1211' : '#0F172A',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => {
                            const raw = Number(cartesian ? (props.horizontal ? context.parsed.x : context.parsed.y) : context.parsed);
                            const name = cartesian ? context.dataset.label : context.label;

                            return ` ${name}: ${format(raw)}`;
                        },
                    },
                },
            },
            scales: cartesian
                ? {
                      [categoryAxis]: {
                          grid: { display: false },
                          border: { display: false },
                          ticks: { color: ink(0.55), font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 },
                      },
                      [valueAxis]: {
                          beginAtZero: true,
                          border: { display: false },
                          grid: { color: ink(0.07) },
                          ticks: { color: ink(0.55), font: { size: 11 }, precision: 0, maxTicksLimit: 5, callback: (value) => format(Number(value)) },
                      },
                  }
                : {},
        },
    } as ChartConfiguration;
}

function render() {
    chart?.destroy();

    if (canvas.value) {
        chart = new Chart(canvas.value, build());
    }
}

// Parents pass fresh array literals on every render: compare by content,
// so the chart is only rebuilt (and re-animated) when something changed.
const signature = computed(() => JSON.stringify([props.type, props.labels, props.series, props.sliceColors, theme.global.name.value]));

onMounted(render);
watch(signature, render);
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div :style="{ height: `${height}px`, position: 'relative' }">
        <canvas ref="canvas" role="img" />
    </div>
</template>

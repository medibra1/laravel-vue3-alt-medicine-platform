<script setup lang="ts">
import { computed } from 'vue';

export interface AppCalendarColumn {
    id: string | number;
    label: string;
    /** ISO date this column represents — used to position a slot click's date. */
    date: string;
}

export interface AppCalendarEvent {
    id: number;
    /** Matches one AppCalendarColumn's id — which column this event renders in. */
    columnId: string | number;
    startsAt: string;
    endsAt: string;
    title: string;
    subtitle?: string;
    color?: string;
}

const props = withDefaults(
    defineProps<{
        columns: AppCalendarColumn[];
        events: AppCalendarEvent[];
        startHour?: number;
        endHour?: number;
        /** Height in pixels of one hour row — drives the whole grid's scale. */
        hourHeight?: number;
    }>(),
    { startHour: 8, endHour: 19, hourHeight: 56 },
);

const emit = defineEmits<{
    'slot-click': [payload: { columnId: string | number; date: string; hour: number; minute: number }];
    'event-click': [event: AppCalendarEvent];
}>();

const hours = computed(() => {
    const list: number[] = [];
    for (let h = props.startHour; h < props.endHour; h++) {
        list.push(h);
    }
    return list;
});

const totalHeight = computed(() => (props.endHour - props.startHour) * props.hourHeight);

function minutesFromStart(iso: string): number {
    const date = new Date(iso);
    return (date.getHours() - props.startHour) * 60 + date.getMinutes();
}

function eventStyle(event: AppCalendarEvent): Record<string, string> {
    const top = (minutesFromStart(event.startsAt) / 60) * props.hourHeight;
    const start = new Date(event.startsAt);
    const end = new Date(event.endsAt);
    const height = ((end.getTime() - start.getTime()) / 60000 / 60) * props.hourHeight;

    return {
        top: `${Math.max(top, 0)}px`,
        height: `${Math.max(height, 20)}px`,
    };
}

function eventsForColumn(columnId: string | number): AppCalendarEvent[] {
    return props.events.filter((event) => event.columnId === columnId);
}

function onSlotClick(column: AppCalendarColumn, hour: number, event: MouseEvent) {
    // Coarse half-hour precision from the click's vertical position within
    // the hour row — good enough to prefill AppointmentDialog, the exact
    // time is still adjustable there via the slot picker.
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const offsetY = event.clientY - rect.top;
    const minute = offsetY > props.hourHeight / 2 ? 30 : 0;

    emit('slot-click', { columnId: column.id, date: column.date, hour, minute });
}
</script>

<template>
    <div class="app-week-calendar">
        <div class="app-week-calendar-header">
            <div class="app-week-calendar-gutter" />
            <div v-for="column in columns" :key="column.id" class="app-week-calendar-column-header">
                {{ column.label }}
            </div>
        </div>

        <div class="app-week-calendar-body" :style="{ height: `${totalHeight}px` }">
            <div class="app-week-calendar-gutter">
                <div v-for="hour in hours" :key="hour" class="app-week-calendar-hour-label" :style="{ height: `${hourHeight}px` }">
                    {{ String(hour).padStart(2, '0') }}:00
                </div>
            </div>

            <div v-for="column in columns" :key="column.id" class="app-week-calendar-column">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    class="app-week-calendar-hour-slot"
                    :style="{ height: `${hourHeight}px` }"
                    @click="onSlotClick(column, hour, $event)"
                />

                <div
                    v-for="event in eventsForColumn(column.id)"
                    :key="event.id"
                    class="app-week-calendar-event"
                    :style="{ ...eventStyle(event), backgroundColor: `rgb(var(--v-theme-${event.color ?? 'primary'}))` }"
                    @click.stop="emit('event-click', event)"
                >
                    <p class="app-week-calendar-event-title">{{ event.title }}</p>
                    <p v-if="event.subtitle" class="app-week-calendar-event-subtitle">{{ event.subtitle }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.app-week-calendar {
    display: flex;
    flex-direction: column;
    overflow-x: auto;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 4px;
}

.app-week-calendar-header,
.app-week-calendar-body {
    display: flex;
    min-width: fit-content;
}

.app-week-calendar-gutter {
    flex: 0 0 64px;
}

.app-week-calendar-column-header {
    flex: 1 0 140px;
    padding: 8px;
    font-weight: 500;
    text-align: center;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.app-week-calendar-hour-label {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    padding-right: 8px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.app-week-calendar-column {
    position: relative;
    flex: 1 0 140px;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.app-week-calendar-hour-slot {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    cursor: pointer;
}

.app-week-calendar-hour-slot:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.app-week-calendar-event {
    position: absolute;
    left: 4px;
    right: 4px;
    border-radius: 4px;
    padding: 4px 6px;
    overflow: hidden;
    cursor: pointer;
    color: rgb(var(--v-theme-on-primary));
}

.app-week-calendar-event-title {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.app-week-calendar-event-subtitle {
    margin: 0;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

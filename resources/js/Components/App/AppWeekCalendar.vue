<script setup lang="ts">
import { computed } from 'vue';

export interface AppCalendarColumn {
    id: string | number;
    label: string;
    /** ISO date this column represents — used to position a slot click's date. */
    date: string;
    /**
     * When set, the column is greyed out with this label as a badge and
     * empty slots stop emitting slot-click (e.g. a practitioner time off).
     */
    blockedLabel?: string;
    /**
     * Same blocking as blockedLabel but for a whole-center closure — shown
     * with a distinct badge so it isn't mistaken for a personal time off.
     */
    closedLabel?: string;
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
    /** mdi icon name shown next to the title — e.g. to flag a remote appointment. */
    icon?: string;
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

function eventHeight(event: AppCalendarEvent): number {
    const durationMinutes = (new Date(event.endsAt).getTime() - new Date(event.startsAt).getTime()) / 60000;
    return (durationMinutes / 60) * props.hourHeight;
}

// Title + subtitle on two lines need ~40px; a shorter block (e.g. a 30 min
// appointment) renders them on a single line instead of clipping the subtitle.
const COMPACT_EVENT_MAX_HEIGHT = 40;

function isCompact(event: AppCalendarEvent): boolean {
    return eventHeight(event) < COMPACT_EVENT_MAX_HEIGHT;
}

function eventTooltip(event: AppCalendarEvent): string {
    return event.subtitle ? `${event.title} — ${event.subtitle}` : event.title;
}

function eventStyle(event: AppCalendarEvent): Record<string, string> {
    const top = (minutesFromStart(event.startsAt) / 60) * props.hourHeight;
    const height = eventHeight(event);

    return {
        top: `${Math.max(top, 0)}px`,
        height: `${Math.max(height, 20)}px`,
    };
}

function eventsForColumn(columnId: string | number): AppCalendarEvent[] {
    return props.events.filter((event) => event.columnId === columnId);
}

function onSlotClick(column: AppCalendarColumn, hour: number, event: MouseEvent) {
    if (column.blockedLabel || column.closedLabel) return;

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
                <v-chip v-if="column.blockedLabel" size="x-small" color="warning" variant="tonal" prepend-icon="mdi-beach" class="ml-1">
                    {{ column.blockedLabel }}
                </v-chip>
                <v-chip v-if="column.closedLabel" size="x-small" color="error" variant="tonal" prepend-icon="mdi-store-off-outline" class="ml-1">
                    Fermé — {{ column.closedLabel }}
                </v-chip>
            </div>
        </div>

        <div class="app-week-calendar-body" :style="{ height: `${totalHeight}px` }">
            <div class="app-week-calendar-gutter">
                <div v-for="hour in hours" :key="hour" class="app-week-calendar-hour-label" :style="{ height: `${hourHeight}px` }">
                    {{ String(hour).padStart(2, '0') }}:00
                </div>
            </div>

            <div
                v-for="column in columns"
                :key="column.id"
                class="app-week-calendar-column"
                :class="{ 'app-week-calendar-column--blocked': column.blockedLabel || column.closedLabel }"
            >
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
                    :class="{ 'app-week-calendar-event--compact': isCompact(event) }"
                    :title="eventTooltip(event)"
                    :style="{ ...eventStyle(event), backgroundColor: `rgb(var(--v-theme-${event.color ?? 'primary'}))` }"
                    @click.stop="emit('event-click', event)"
                >
                    <p v-if="isCompact(event)" class="app-week-calendar-event-title">
                        <v-icon v-if="event.icon" :icon="event.icon" size="12" class="mr-1" />{{ event.title
                        }}<span v-if="event.subtitle" class="app-week-calendar-event-inline-subtitle"> · {{ event.subtitle }}</span>
                    </p>
                    <template v-else>
                        <p class="app-week-calendar-event-title">
                            <v-icon v-if="event.icon" :icon="event.icon" size="12" class="mr-1" />{{ event.title }}
                        </p>
                        <p v-if="event.subtitle" class="app-week-calendar-event-subtitle">{{ event.subtitle }}</p>
                    </template>
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
    overflow: hidden;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.app-week-calendar-column--blocked {
    background: repeating-linear-gradient(
        -45deg,
        rgba(var(--v-theme-on-surface), 0.06) 0 6px,
        transparent 6px 12px
    );
}

.app-week-calendar-column--blocked .app-week-calendar-hour-slot {
    cursor: not-allowed;
}

.app-week-calendar-column--blocked .app-week-calendar-hour-slot:hover {
    background-color: transparent;
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

.app-week-calendar-event--compact {
    display: flex;
    align-items: center;
    padding: 0 6px;
}

.app-week-calendar-event--compact .app-week-calendar-event-title {
    line-height: 1.2;
}

.app-week-calendar-event-inline-subtitle {
    font-weight: 400;
    opacity: 0.9;
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

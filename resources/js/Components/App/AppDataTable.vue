<script setup lang="ts" generic="T extends object">
import { computed } from 'vue';

export interface AppDataTableColumn {
    field?: string;
    header: string;
    sortable?: boolean;
}

export interface AppDataTableSortEvent {
    sortField: string | null;
    sortOrder: 1 | -1 | null;
}

const props = defineProps<{
    value: T[];
    columns: AppDataTableColumn[];
    rows: number;
    totalRecords: number;
    page: number;
}>();

const emit = defineEmits<{
    page: [page: number];
    sort: [event: AppDataTableSortEvent];
}>();

const headers = computed(() =>
    props.columns.map((column) => ({
        title: column.header,
        key: column.field ?? column.header,
        sortable: column.sortable ?? false,
    })),
);

function onUpdatePage(page: number) {
    emit('page', page);
}

function onUpdateSortBy(sortBy: { key: string; order?: 'asc' | 'desc' }[]) {
    if (!sortBy.length) {
        emit('sort', { sortField: null, sortOrder: null });
        return;
    }

    const [{ key, order }] = sortBy;
    emit('sort', { sortField: key, sortOrder: order === 'desc' ? -1 : 1 });
}
</script>

<template>
    <v-data-table-server
        :items="value"
        :headers="headers"
        :items-length="totalRecords"
        :items-per-page="rows"
        :page="page"
        :multi-sort="false"
        @update:page="onUpdatePage"
        @update:sort-by="onUpdateSortBy"
    >
        <template
            v-for="column in columns.filter((c) => c.field)"
            :key="column.field"
            #[`item.${column.field}`]="slotProps"
        >
            <slot :name="`column-${column.field}`" v-bind="slotProps">
                {{ (slotProps.item as unknown as Record<string, unknown>)[column.field as string] }}
            </slot>
        </template>

        <template v-if="$slots.actions" #item.actions="slotProps">
            <slot name="actions" v-bind="slotProps" />
        </template>
    </v-data-table-server>
</template>

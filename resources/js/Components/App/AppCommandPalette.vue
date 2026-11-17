<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

export interface CommandItem {
    label: string;
    icon: string;
    href: string;
    group: string;
}

const props = defineProps<{
    modelValue: boolean;
    items: CommandItem[];
    canSearchPatients: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const { t } = useI18n();
const query = ref('');
const highlighted = ref(0);
const input = ref<{ focus: () => void } | null>(null);

function normalize(value: string): string {
    return value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

interface Entry extends CommandItem {
    patientSearch?: boolean;
}

const entries = computed<Entry[]>(() => {
    const needle = normalize(query.value.trim());
    const pages = needle === '' ? props.items : props.items.filter((item) => normalize(item.label).includes(needle));

    if (needle === '' || !props.canSearchPatients) {
        return pages;
    }

    return [
        ...pages,
        {
            label: t('shell.searchPatient', { query: query.value.trim() }),
            icon: 'mdi-account-search-outline',
            href: route('admin.patients.index', { filter: { search: query.value.trim() } }),
            group: t('shell.nav.patients'),
            patientSearch: true,
        },
    ];
});

watch(() => props.modelValue, async (open) => {
    if (open) {
        query.value = '';
        highlighted.value = 0;
        await nextTick();
        input.value?.focus();
    }
});

watch(query, () => {
    highlighted.value = 0;
});

function go(entry: Entry | undefined) {
    if (!entry) {
        return;
    }

    emit('update:modelValue', false);
    router.get(entry.href);
}

function move(step: number) {
    const count = entries.value.length;
    if (count > 0) {
        highlighted.value = (highlighted.value + step + count) % count;
    }
}
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="560" location="top" class="app-palette" @update:model-value="emit('update:modelValue', $event)">
        <v-card class="mt-12">
            <v-text-field
                ref="input"
                v-model="query"
                :placeholder="t('shell.quickSearchHint')"
                prepend-inner-icon="mdi-magnify"
                variant="solo"
                flat
                hide-details
                density="default"
                autocomplete="off"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="go(entries[highlighted])"
            />
            <v-divider />
            <v-list v-if="entries.length" density="compact" nav max-height="360" class="overflow-y-auto">
                <v-list-item
                    v-for="(entry, index) in entries"
                    :key="entry.href"
                    :prepend-icon="entry.icon"
                    :title="entry.label"
                    :subtitle="entry.group"
                    :active="index === highlighted"
                    color="primary"
                    rounded="lg"
                    @click="go(entry)"
                    @mouseenter="highlighted = index"
                />
            </v-list>
            <div v-else class="pa-6 text-center text-medium-emphasis">{{ t('shell.noResult') }}</div>
        </v-card>
    </v-dialog>
</template>

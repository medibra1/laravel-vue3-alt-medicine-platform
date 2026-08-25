<script setup lang="ts">
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import { computed, ref } from 'vue';

interface CareItemOption {
    id: number;
    code: string;
    label: string;
}

interface CareCategoryOption {
    id: number;
    code: string;
    label: string;
    items: CareItemOption[];
}

const props = defineProps<{
    careCategories: CareCategoryOption[];
    modelValue: Set<number>;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: Set<number>] }>();

const search = ref('');

// Same shape as TreatmentWizardDialog's disease search (diseaseSearch/
// searchResults) — flat, cross-category results while a search term is
// active, filtered by label or code.
const searchResults = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return [];
    }

    return props.careCategories.flatMap((category) =>
        category.items
            .filter((item) => item.label.toLowerCase().includes(term) || item.code.includes(term))
            .map((item) => ({ ...item, categoryLabel: category.label })),
    );
});

function selectedCountInCategory(category: CareCategoryOption): number {
    return category.items.filter((item) => props.modelValue.has(item.id)).length;
}

function categoryHeader(category: CareCategoryOption): string {
    const count = selectedCountInCategory(category);

    return count > 0 ? `${category.label} (${count})` : category.label;
}

function toggleItem(itemId: number) {
    const next = new Set(props.modelValue);

    if (next.has(itemId)) {
        next.delete(itemId);
    } else {
        next.add(itemId);
    }

    emit('update:modelValue', next);
}
</script>

<template>
    <div class="d-flex flex-column ga-3">
        <AppInputText v-model="search" label="Rechercher un soin (toutes catégories)" placeholder="Ex. huile, ventouse…" />

        <div v-if="search.trim()" class="d-flex flex-column ga-2">
            <p class="text-body-2 text-medium-emphasis">{{ searchResults.length }} résultat(s)</p>
            <div v-for="item in searchResults" :key="item.id" class="d-flex align-center ga-2">
                <AppCheckbox :model-value="modelValue.has(item.id)" @update:model-value="toggleItem(item.id)" />
                <span>{{ item.label }}</span>
                <v-chip size="small" variant="tonal">{{ item.categoryLabel }}</v-chip>
            </div>
        </div>

        <v-expansion-panels v-else variant="accordion" multiple>
            <v-expansion-panel v-for="category in careCategories" :key="category.id" :title="categoryHeader(category)">
                <v-expansion-panel-text>
                    <div class="d-flex flex-wrap ga-3">
                        <AppCheckbox
                            v-for="item in category.items"
                            :key="item.id"
                            :model-value="modelValue.has(item.id)"
                            :label="item.label"
                            @update:model-value="toggleItem(item.id)"
                        />
                    </div>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>

        <p v-if="!careCategories.length" class="text-body-2 text-medium-emphasis">Aucun soin disponible.</p>
    </div>
</template>

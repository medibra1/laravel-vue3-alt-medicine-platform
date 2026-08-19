<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

function dateBinding(form: { hired_at: string | null }) {
    return computed<Date | null>({
        get: () => (form.hired_at ? new Date(form.hired_at) : null),
        set: (value) => {
            form.hired_at = value ? value.toISOString().slice(0, 10) : null;
        },
    });
}

interface Center {
    id: number;
    name: string;
    code: string;
}

interface Grade {
    id: number;
    label: string;
    coefficient: number;
}

interface Practitioner {
    id: number;
    full_code: string;
    diploma_number: string;
    center_id: number;
    grade_id: number | null;
    level: number | null;
    hired_at: string | null;
    center?: { id: number; name: string };
    grade?: { id: number; label: string } | null;
}

const props = defineProps<{
    practitioners: {
        data: Practitioner[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    centers: Center[];
    grades: Grade[];
}>();

const search = reactive({
    full_code: props.filters.filter?.full_code ?? '',
    diploma_number: props.filters.filter?.diploma_number ?? '',
});

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.practitioners.index'),
        { filter: { ...search }, sort: currentSort.value, ...extra },
        { preserveState: true, replace: true },
    );
}

function onPage(page: number) {
    reload({ page });
}

function onSort(event: AppDataTableSortEvent) {
    if (!event.sortField) {
        return;
    }

    currentSort.value =
        event.sortOrder === -1 ? `-${event.sortField}` : `${event.sortField}`;
    reload();
}

const columns: AppDataTableColumn[] = [
    { field: 'full_code', header: 'Code', sortable: true },
    { field: 'diploma_number', header: 'N° diplôme', sortable: true },
    { field: 'center', header: 'Centre' },
    { field: 'grade', header: 'Grade' },
    { field: 'hired_at', header: 'Embauché le', sortable: true },
    { field: 'actions', header: 'Actions' },
];

const isCreating = ref(false);
const editingPractitioner = ref<Practitioner | null>(null);

const createForm = useForm({
    center_id: null as number | null,
    diploma_number: '',
    grade_id: null as number | null,
    level: null as number | null,
    hired_at: null as string | null,
});
const createHiredAt = dateBinding(createForm);

function openCreate() {
    createForm.reset();
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.practitioners.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    diploma_number: '',
    grade_id: null as number | null,
    level: null as number | null,
    hired_at: null as string | null,
});
const editHiredAt = dateBinding(editForm);

function openEdit(practitioner: Practitioner) {
    editingPractitioner.value = practitioner;
    editForm.reset();
    editForm.diploma_number = practitioner.diploma_number;
    editForm.grade_id = practitioner.grade_id;
    editForm.level = practitioner.level;
    editForm.hired_at = practitioner.hired_at;
}

function submitEdit() {
    if (!editingPractitioner.value) {
        return;
    }

    editForm.put(
        route('admin.practitioners.update', editingPractitioner.value.id),
        { onSuccess: () => (editingPractitioner.value = null) },
    );
}

function destroy(practitioner: Practitioner) {
    if (!confirm(`Supprimer le praticien ${practitioner.full_code} ?`)) {
        return;
    }

    router.delete(route('admin.practitioners.destroy', practitioner.id));
}
</script>

<template>
    <Head title="Praticiens" />

    <AuthenticatedLayout>
        <template #header>Praticiens</template>

        <div class="d-flex flex-column ga-4">
            <div class="d-flex flex-wrap align-end ga-3">
                <AppInputText
                    id="filter-full-code"
                    v-model="search.full_code"
                    label="Code"
                    @keyup.enter="reload()"
                />
                <AppInputText
                    id="filter-diploma"
                    v-model="search.diploma_number"
                    label="N° diplôme"
                    @keyup.enter="reload()"
                />
                <AppButton label="Filtrer" @click="reload()" />
                <AppButton label="Nouveau praticien" class="ms-auto" @click="openCreate" />
            </div>

            <v-card>
                <AppDataTable
                    :value="practitioners.data"
                    :columns="columns"
                    :rows="practitioners.per_page"
                    :total-records="practitioners.total"
                    :page="practitioners.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-center="{ item }">{{ item.center?.name }}</template>
                    <template #column-grade="{ item }">{{ item.grade?.label ?? '—' }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                label="Modifier"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                label="Supprimer"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </v-card>
        </div>

        <AppDialog v-model:visible="isCreating" header="Nouveau praticien">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-if="centers.length"
                    v-model="createForm.center_id"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    label="Centre"
                    placeholder="Choisir un centre"
                    :error="createForm.errors.center_id"
                />

                <AppInputText
                    v-model="createForm.diploma_number"
                    label="N° diplôme (3 chiffres)"
                    :maxlength="3"
                    :error="createForm.errors.diploma_number"
                />

                <AppSelect
                    v-model="createForm.grade_id"
                    :options="grades"
                    option-label="label"
                    option-value="id"
                    label="Grade"
                    show-clear
                    placeholder="Aucun"
                />

                <AppInputNumber v-model="createForm.level" label="Niveau" :min="0" />

                <AppDatePicker v-model="createHiredAt" label="Date d'embauche" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingPractitioner !== null"
            header="Modifier le praticien"
            @update:visible="editingPractitioner = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.diploma_number"
                    label="N° diplôme (3 chiffres)"
                    :maxlength="3"
                    :error="editForm.errors.diploma_number"
                />

                <AppSelect
                    v-model="editForm.grade_id"
                    :options="grades"
                    option-label="label"
                    option-value="id"
                    label="Grade"
                    show-clear
                    placeholder="Aucun"
                />

                <AppInputNumber v-model="editForm.level" label="Niveau" :min="0" />

                <AppDatePicker v-model="editHiredAt" label="Date d'embauche" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingPractitioner = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>

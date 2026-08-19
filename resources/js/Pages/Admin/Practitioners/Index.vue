<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable, {
    type DataTablePageEvent,
    type DataTableSortEvent,
} from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
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

function onPage(event: DataTablePageEvent) {
    reload({ page: event.page + 1 });
}

function onSort(event: DataTableSortEvent) {
    if (!event.sortField) {
        return;
    }

    currentSort.value =
        event.sortOrder === -1 ? `-${event.sortField}` : `${event.sortField}`;
    reload();
}

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
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Praticiens
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label
                            for="filter-full-code"
                            class="text-sm text-gray-600"
                            >Code</label
                        >
                        <InputText
                            id="filter-full-code"
                            v-model="search.full_code"
                            @keyup.enter="reload()"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            for="filter-diploma"
                            class="text-sm text-gray-600"
                            >N° diplôme</label
                        >
                        <InputText
                            id="filter-diploma"
                            v-model="search.diploma_number"
                            @keyup.enter="reload()"
                        />
                    </div>
                    <Button label="Filtrer" @click="reload()" />
                    <Button label="Nouveau praticien" class="ms-auto" @click="openCreate" />
                </div>

                <div class="rounded-lg bg-white shadow">
                    <DataTable
                        :value="practitioners.data"
                        lazy
                        paginator
                        removable-sort
                        sort-mode="single"
                        :rows="practitioners.per_page"
                        :total-records="practitioners.total"
                        :first="(practitioners.current_page - 1) * practitioners.per_page"
                        @page="onPage"
                        @sort="onSort"
                    >
                        <Column field="full_code" header="Code" sortable />
                        <Column field="diploma_number" header="N° diplôme" sortable />
                        <Column header="Centre">
                            <template #body="{ data }">{{ data.center?.name }}</template>
                        </Column>
                        <Column header="Grade">
                            <template #body="{ data }">{{ data.grade?.label ?? '—' }}</template>
                        </Column>
                        <Column field="hired_at" header="Embauché le" sortable />
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button
                                        label="Modifier"
                                        severity="secondary"
                                        size="small"
                                        @click="openEdit(data)"
                                    />
                                    <Button
                                        label="Supprimer"
                                        severity="danger"
                                        size="small"
                                        @click="destroy(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="isCreating" modal header="Nouveau praticien" class="w-full max-w-md">
            <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                <div v-if="centers.length" class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Centre</label>
                    <Select
                        v-model="createForm.center_id"
                        :options="centers"
                        option-label="name"
                        option-value="id"
                        placeholder="Choisir un centre"
                    />
                    <p v-if="createForm.errors.center_id" class="text-sm text-red-600">
                        {{ createForm.errors.center_id }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">N° diplôme (3 chiffres)</label>
                    <InputText v-model="createForm.diploma_number" maxlength="3" />
                    <p v-if="createForm.errors.diploma_number" class="text-sm text-red-600">
                        {{ createForm.errors.diploma_number }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Grade</label>
                    <Select
                        v-model="createForm.grade_id"
                        :options="grades"
                        option-label="label"
                        option-value="id"
                        show-clear
                        placeholder="Aucun"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Niveau</label>
                    <InputNumber v-model="createForm.level" :min="0" />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Date d'embauche</label>
                    <DatePicker v-model="createHiredAt" date-format="yy-mm-dd" />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <Button type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog
            :visible="editingPractitioner !== null"
            modal
            header="Modifier le praticien"
            class="w-full max-w-md"
            @update:visible="editingPractitioner = null"
        >
            <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">N° diplôme (3 chiffres)</label>
                    <InputText v-model="editForm.diploma_number" maxlength="3" />
                    <p v-if="editForm.errors.diploma_number" class="text-sm text-red-600">
                        {{ editForm.errors.diploma_number }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Grade</label>
                    <Select
                        v-model="editForm.grade_id"
                        :options="grades"
                        option-label="label"
                        option-value="id"
                        show-clear
                        placeholder="Aucun"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Niveau</label>
                    <InputNumber v-model="editForm.level" :min="0" />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm text-gray-600">Date d'embauche</label>
                    <DatePicker v-model="editHiredAt" date-format="yy-mm-dd" />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingPractitioner = null"
                    />
                    <Button type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>

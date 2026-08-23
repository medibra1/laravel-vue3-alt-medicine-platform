<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

function dateBinding(form: { hired_at: string | null }) {
    return computed<Date | null>({
        get: () => fromLocalDateString(form.hired_at),
        set: (value) => {
            form.hired_at = value ? toLocalDateString(value) : null;
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
    first_name: string;
    last_name: string;
    full_code: string;
    matricule: string;
    center_id: number;
    grade_id: number | null;
    level: number | null;
    hired_at: string | null;
    phone: string | null;
    address: string | null;
    email: string | null;
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
    search: props.filters.filter?.search ?? '',
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
    { field: 'name', header: 'Nom' },
    { field: 'full_code', header: 'Code', sortable: true },
    { field: 'matricule', header: 'Matricule', sortable: true },
    { field: 'center', header: 'Centre' },
    { field: 'grade', header: 'Grade' },
    { field: 'phone', header: 'Téléphone' },
    { field: 'hired_at', header: 'Embauché le', sortable: true },
    { field: 'actions', header: 'Actions' },
];

const isCreating = ref(false);
const editingPractitioner = ref<Practitioner | null>(null);

const createForm = useForm({
    first_name: '',
    last_name: '',
    center_id: null as number | null,
    matricule: '',
    grade_id: null as number | null,
    level: null as number | null,
    hired_at: null as string | null,
    phone: '',
    address: '',
    email: '',
});
const createHiredAt = dateBinding(createForm);

function openCreate() {
    createForm.reset();
    isCreating.value = true;
}

// Auto-suggested next matricule for the selected center — the field
// stays editable, this only pre-fills it (see PractitionerCodeGenerator
// ::suggestNextMatricule()).
watch(
    () => createForm.center_id,
    async (centerId) => {
        if (!centerId || createForm.matricule) {
            return;
        }

        const response = await fetch(
            route('admin.practitioners.next-matricule', { center_id: centerId }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = await response.json();
            createForm.matricule = data.matricule;
        }
    },
);

function submitCreate() {
    createForm.post(route('admin.practitioners.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    first_name: '',
    last_name: '',
    matricule: '',
    grade_id: null as number | null,
    level: null as number | null,
    hired_at: null as string | null,
    phone: '',
    address: '',
    email: '',
});
const editHiredAt = dateBinding(editForm);

function openEdit(practitioner: Practitioner) {
    editingPractitioner.value = practitioner;
    editForm.reset();
    editForm.first_name = practitioner.first_name;
    editForm.last_name = practitioner.last_name;
    editForm.matricule = practitioner.matricule;
    editForm.grade_id = practitioner.grade_id;
    editForm.level = practitioner.level;
    editForm.hired_at = practitioner.hired_at;
    editForm.phone = practitioner.phone ?? '';
    editForm.address = practitioner.address ?? '';
    editForm.email = practitioner.email ?? '';
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
    if (!confirm(`Supprimer le praticien ${practitioner.first_name} ${practitioner.last_name} (${practitioner.full_code}) ?`)) {
        return;
    }

    router.delete(route('admin.practitioners.destroy', practitioner.id));
}
</script>

<template>
    <Head title="Praticiens" />

    <AuthenticatedLayout>
        <AppPageHeader title="Praticiens" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Praticiens' }]">
            <template #actions>
                <AppButton label="Nouveau praticien" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        label="Rechercher (nom, code, matricule)"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton label="Filtrer" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="practitioners.data"
                    :columns="columns"
                    :rows="practitioners.per_page"
                    :total-records="practitioners.total"
                    :page="practitioners.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-name="{ item }">{{ item.first_name }} {{ item.last_name }}</template>
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
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" header="Nouveau praticien">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText v-model="createForm.first_name" label="Prénom" :error="createForm.errors.first_name" />
                <AppInputText v-model="createForm.last_name" label="Nom" :error="createForm.errors.last_name" />

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
                    v-model="createForm.matricule"
                    label="Matricule (3 chiffres, suggéré automatiquement)"
                    :maxlength="3"
                    :error="createForm.errors.matricule"
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

                <AppInputText v-model="createForm.phone" label="Téléphone" :error="createForm.errors.phone" />
                <AppInputText v-model="createForm.address" label="Adresse" :error="createForm.errors.address" />
                <AppInputText v-model="createForm.email" label="Email" :error="createForm.errors.email" />

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
                <AppInputText v-model="editForm.first_name" label="Prénom" :error="editForm.errors.first_name" />
                <AppInputText v-model="editForm.last_name" label="Nom" :error="editForm.errors.last_name" />

                <AppInputText
                    v-model="editForm.matricule"
                    label="Matricule (3 chiffres)"
                    :maxlength="3"
                    :error="editForm.errors.matricule"
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

                <AppInputText v-model="editForm.phone" label="Téléphone" :error="editForm.errors.phone" />
                <AppInputText v-model="editForm.address" label="Adresse" :error="editForm.errors.address" />
                <AppInputText v-model="editForm.email" label="Email" :error="editForm.errors.email" />

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

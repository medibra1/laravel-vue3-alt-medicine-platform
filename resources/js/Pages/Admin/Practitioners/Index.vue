<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
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
    user_id: number | null;
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
    { field: 'access', header: 'Accès' },
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
    grant_access: false,
    creation_mode: 'invite' as 'direct' | 'invite',
    password: '',
    password_confirmation: '',
});
const createHiredAt = dateBinding(createForm);

// --- "grant access" email check (debounced), drives the rest of the
// create dialog once grant_access is toggled on. Mirrors what
// StorePractitionerRequest re-validates authoritatively on submit —
// this is only a live preview so the manager isn't surprised at submit.
type AccountStatus = 'idle' | 'checking' | 'new' | 'existing' | 'taken';
const accountStatus = ref<AccountStatus>('idle');
const accountPractitionerName = ref<string | null>(null);
const accountCurrentCenters = ref<string[]>([]);
let checkAccountTimeout: ReturnType<typeof setTimeout> | undefined;

function scheduleAccountCheck() {
    clearTimeout(checkAccountTimeout);

    if (!createForm.grant_access || !createForm.email) {
        accountStatus.value = 'idle';
        return;
    }

    accountStatus.value = 'checking';
    checkAccountTimeout = setTimeout(async () => {
        const response = await fetch(
            route('admin.practitioners.check-account', { email: createForm.email }),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            accountStatus.value = 'idle';
            return;
        }

        const data = await response.json();
        accountStatus.value = data.status;
        accountPractitionerName.value = data.practitioner_name;
        accountCurrentCenters.value = data.current_centers ?? [];
    }, 400);
}

watch(() => [createForm.grant_access, createForm.email], scheduleAccountCheck);

const isJoiningExisting = computed(() => createForm.grant_access && accountStatus.value === 'existing');

// Inertia's useForm() always submits every field, even ones hidden
// behind v-if — masking these fields visually was not enough,
// StorePractitionerRequest's 'prohibited' rule on matricule/
// creation_mode/etc. rejected the leftover values from whatever was
// last typed, and the resulting redirect-back looked like a false
// success (302 to the same index URL as a real create) rather than a
// visible error. Clearing them here keeps what's actually submitted in
// sync with what's actually shown.
watch(isJoiningExisting, (joining) => {
    if (!joining) {
        return;
    }

    createForm.first_name = '';
    createForm.last_name = '';
    createForm.matricule = '';
    createForm.grade_id = null;
    createForm.level = null;
    createForm.hired_at = null;
    createForm.phone = '';
    createForm.address = '';
    createForm.creation_mode = 'invite';
    createForm.password = '';
    createForm.password_confirmation = '';
});

// AppDialog's underlying v-dialog keeps its content mounted between
// opens (no real unmount) — createForm.reset() alone can leave Vuetify
// fields (in particular AppCheckbox's v-checkbox) visually out of sync
// with the reset state on a second open. Bumped on every openCreate()
// to force a real remount, same fix already used for
// TreatmentWizardDialog (see CLAUDE.md).
const createDialogKey = ref(0);

function openCreate() {
    createForm.reset();
    accountStatus.value = 'idle';
    createDialogKey.value++;
    isCreating.value = true;
}

// Auto-suggested next matricule for the selected center — the field
// stays editable, this only pre-fills it (see PractitionerCodeGenerator
// ::suggestNextMatricule()). Never applies when auto-joining an
// existing practitioner to this center — no new Practitioner row is
// created there, so a matricule is meaningless (and StorePractitionerRequest
// rejects it outright — see the isJoiningExisting watch above).
watch(
    () => createForm.center_id,
    async (centerId) => {
        if (!centerId || createForm.matricule || isJoiningExisting.value) {
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
            // Inertia recalibrates form.reset()'s target onto whatever
            // was just successfully submitted (unless defaults() is
            // called during onSuccess) — without this, reopening the
            // dialog for a second practitioner would start pre-filled
            // with the previous one's data instead of blank fields.
            // Calling reset() here (before Inertia's own post-success
            // setDefaults() runs) captures a blank state as the new
            // baseline instead.
            createForm.reset();
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
                    <template #column-access="{ item }">
                        <v-chip size="small" :color="item.user_id ? 'success' : undefined" variant="tonal">
                            {{ item.user_id ? 'Avec accès' : 'Sans accès' }}
                        </v-chip>
                    </template>
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

        <AppDialog :key="createDialogKey" v-model:visible="isCreating" header="Nouveau praticien">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppCheckbox
                    v-model="createForm.grant_access"
                    label="Donner un accès à l'application"
                />

                <template v-if="createForm.grant_access">
                    <AppInputText
                        v-model="createForm.email"
                        label="Email"
                        type="email"
                        :error="createForm.errors.email"
                    />

                    <v-alert v-if="accountStatus === 'checking'" type="info" variant="tonal" density="compact">
                        Vérification…
                    </v-alert>

                    <v-alert v-else-if="accountStatus === 'existing'" type="info" variant="tonal" density="compact">
                        {{ accountPractitionerName }} a déjà un compte praticien
                        (centre{{ accountCurrentCenters.length > 1 ? 's' : '' }} :
                        {{ accountCurrentCenters.join(', ') }}). Il/elle sera simplement ajouté·e à ce centre —
                        aucun nouveau compte ni fiche praticien ne sera créé.
                    </v-alert>

                    <v-alert v-else-if="accountStatus === 'taken'" type="error" variant="tonal" density="compact">
                        Cet email est déjà utilisé par un autre compte (non praticien).
                    </v-alert>
                </template>

                <template v-if="!isJoiningExisting">
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
                </template>

                <template v-else>
                    <!-- Auto-join: center comes from context (the manager's
                         active center), no new Practitioner fields needed. -->
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
                </template>

                <template v-if="createForm.grant_access && !isJoiningExisting && accountStatus !== 'taken'">
                    <AppSelect
                        v-model="createForm.creation_mode"
                        :options="[
                            { value: 'invite', label: 'Envoyer une invitation par email' },
                            { value: 'direct', label: 'Définir un mot de passe directement' },
                        ]"
                        option-label="label"
                        option-value="value"
                        label="Mode de création du compte"
                    />

                    <template v-if="createForm.creation_mode === 'direct'">
                        <AppInputText
                            v-model="createForm.password"
                            label="Mot de passe"
                            type="password"
                            :error="createForm.errors.password"
                        />
                        <AppInputText
                            v-model="createForm.password_confirmation"
                            label="Confirmer le mot de passe"
                            type="password"
                        />
                    </template>
                </template>

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton
                        type="submit"
                        label="Créer"
                        :loading="createForm.processing"
                        :disabled="accountStatus === 'taken'"
                    />
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

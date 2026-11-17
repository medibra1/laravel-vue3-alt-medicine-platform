<script setup lang="ts">
import { tr } from '@/lib/i18n';
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import CenterClosuresDialog from '@/Components/Scheduling/CenterClosuresDialog.vue';
import CenterOperatingHoursDialog from '@/Components/Scheduling/CenterOperatingHoursDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { WeeklySlot } from '@/utils/weeklySchedule';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

interface Country {
    id: number;
    name: string;
    code: string;
}

interface Center {
    id: number;
    country_id: number;
    code: string;
    name: string;
    city: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    active: boolean;
    country?: { id: number; name: string; code: string };
    operating_hours?: WeeklySlot[];
}

const props = defineProps<{
    centers: {
        data: Center[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    countries: Country[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.centers.index'),
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
    { field: 'code', header: tr('ui.code'), sortable: true },
    { field: 'name', header: tr('ui.nom'), sortable: true },
    { field: 'city', header: tr('ui.ville'), sortable: true },
    { field: 'country', header: tr('ui.pays') },
    { field: 'active', header: tr('ui.actif') },
    { field: 'actions', header: tr('ui.actions') },
];

function fullCenterCode(center: Center): string {
    return `${center.country?.code ?? ''}${center.code}`;
}

const isCreating = ref(false);
const editingCenter = ref<Center | null>(null);

const createForm = useForm({
    country_id: null as number | null,
    code: '',
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.active = true;
    isCreating.value = true;
}

// Auto-suggested next code for the selected country — the field stays
// editable, this only pre-fills it (see CenterCodeGenerator::suggestNext()).
watch(
    () => createForm.country_id,
    async (countryId) => {
        if (!countryId || createForm.code) {
            return;
        }

        const response = await fetch(
            route('admin.centers.next-code', { country_id: countryId }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = await response.json();
            createForm.code = data.code;
        }
    },
);

function submitCreate() {
    createForm.post(route('admin.centers.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    country_id: null as number | null,
    code: '',
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    active: true,
});

function openEdit(center: Center) {
    editingCenter.value = center;
    editForm.reset();
    editForm.country_id = center.country_id;
    editForm.code = center.code;
    editForm.name = center.name;
    editForm.city = center.city ?? '';
    editForm.address = center.address ?? '';
    editForm.phone = center.phone ?? '';
    editForm.email = center.email ?? '';
    editForm.active = center.active;
}

function submitEdit() {
    if (!editingCenter.value) {
        return;
    }

    editForm.put(
        route('admin.centers.update', editingCenter.value.id),
        { onSuccess: () => (editingCenter.value = null) },
    );
}

const hoursCenter = ref<Center | null>(null);
const hoursDialogVisible = ref(false);

function openOperatingHours(center: Center) {
    hoursCenter.value = center;
    hoursDialogVisible.value = true;
}

const closuresCenter = ref<Center | null>(null);
const closuresDialogVisible = ref(false);

function openClosures(center: Center) {
    closuresCenter.value = center;
    closuresDialogVisible.value = true;
}

function destroy(center: Center) {
    if (!confirm(tr('misc.confirmDeleteNamed', { name: center.name }))) {
        return;
    }

    router.delete(route('admin.centers.destroy', center.id));
}
</script>

<template>
    <Head :title="$t('ui.centres')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.centres')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.centres') }]">
            <template #actions>
                <AppButton :label="$t('ui.nouveauCentre')" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        :label="$t('ui.rechercherNomCode')"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton :label="$t('ui.filtrer')" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="centers.data"
                    :columns="columns"
                    :rows="centers.per_page"
                    :total-records="centers.total"
                    :page="centers.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-code="{ item }">{{ fullCenterCode(item) }}</template>
                    <template #column-country="{ item }">{{ item.country?.name }}</template>
                    <template #column-active="{ item }">{{ item.active ? $t('common.yes') : $t('common.no') }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                :label="$t('ui.modifier')"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                :label="$t('ui.horairesDOuverture')"
                                icon="mdi-clock-outline"
                                severity="secondary"
                                size="small"
                                @click="openOperatingHours(item)"
                            />
                            <AppButton
                                :label="$t('ui.fermetures')"
                                icon="mdi-store-off-outline"
                                severity="secondary"
                                size="small"
                                @click="openClosures(item)"
                            />
                            <AppButton
                                :label="$t('ui.supprimer')"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" :header="$t('ui.nouveauCentre')">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.country_id"
                    :options="countries"
                    option-label="name"
                    option-value="id"
                    :label="$t('ui.pays')"
                    :placeholder="$t('ui.choisirUnPays')"
                    :error="createForm.errors.country_id"
                />

                <AppInputText
                    v-model="createForm.code"
                    :label="$t('ui.codeCentre2ChiffresSuggereAutomatiquement')"
                    :maxlength="2"
                    :error="createForm.errors.code"
                />

                <AppInputText
                    v-model="createForm.name"
                    :label="$t('ui.nom')"
                    :error="createForm.errors.name"
                />

                <AppInputText v-model="createForm.city" :label="$t('ui.ville')" :error="createForm.errors.city" />
                <AppInputText v-model="createForm.address" :label="$t('ui.adresse')" />
                <AppInputText v-model="createForm.phone" :label="$t('ui.telephone')" />
                <AppInputText v-model="createForm.email" :label="$t('ui.email')" :error="createForm.errors.email" />

                <AppCheckbox v-model="createForm.active" :label="$t('ui.actif')" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="$t('ui.annuler')"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton type="submit" :label="$t('ui.creer')" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingCenter !== null"
            :header="$t('ui.modifierLeCentre')"
            @update:visible="editingCenter = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppSelect
                    v-model="editForm.country_id"
                    :options="countries"
                    option-label="name"
                    option-value="id"
                    :label="$t('ui.pays')"
                    :error="editForm.errors.country_id"
                />

                <AppInputText
                    v-model="editForm.code"
                    :label="$t('ui.codeCentre2Chiffres')"
                    :maxlength="2"
                    :error="editForm.errors.code"
                />

                <AppInputText
                    v-model="editForm.name"
                    :label="$t('ui.nom')"
                    :error="editForm.errors.name"
                />

                <AppInputText v-model="editForm.city" :label="$t('ui.ville')" :error="editForm.errors.city" />
                <AppInputText v-model="editForm.address" :label="$t('ui.adresse')" />
                <AppInputText v-model="editForm.phone" :label="$t('ui.telephone')" />
                <AppInputText v-model="editForm.email" :label="$t('ui.email')" :error="editForm.errors.email" />

                <AppCheckbox v-model="editForm.active" :label="$t('ui.actif')" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="$t('ui.annuler')"
                        severity="secondary"
                        @click="editingCenter = null"
                    />
                    <AppButton type="submit" :label="$t('ui.enregistrer')" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>

        <CenterOperatingHoursDialog
            v-model:visible="hoursDialogVisible"
            :center="hoursCenter"
            :initial-slots="hoursCenter?.operating_hours ?? []"
        />

        <CenterClosuresDialog v-model:visible="closuresDialogVisible" :center="closuresCenter" />
    </AuthenticatedLayout>
</template>

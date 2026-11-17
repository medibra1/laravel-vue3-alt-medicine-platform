<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppChart from '@/Components/App/AppChart.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppStatCard from '@/Components/App/AppStatCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate, formatMoney } from '@/lib/i18n';
import { handleNavClick } from '@/utils/inertiaNavClick';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface CurrencyStats {
    currency: string;
    collected_this_month: number;
    collected_last_month: number;
    outstanding: number;
    series: number[];
    methods: Record<string, number>;
}

interface Stats {
    months: string[];
    patients?: {
        total: number;
        new_this_month: number;
        new_last_month: number;
        series: number[];
        recent: { id: number; name: string; created_at: string }[];
    };
    treatments?: {
        ongoing: number;
        closed: number;
        closure_reasons: Record<string, number>;
        sessions_this_month: number;
        sessions_last_month: number;
        sessions_series: number[];
        outcomes: Record<string, number>;
        top_diseases: { label: string; total: number }[];
    };
    appointments?: {
        today: number;
        next_7_days: number;
        month_statuses: Record<string, number>;
        upcoming: {
            id: number;
            starts_at: string;
            duration_minutes: number;
            modality: string;
            patient_id: number;
            patient: string;
            practitioner_id: number;
            practitioner: string;
        }[];
    };
    practitioners?: { total: number };
    billing?: { currencies: CurrencyStats[]; unpaid_invoices: number };
    centers?: { id: number; name: string; city: string | null; patients: number; ongoing_treatments: number; practitioners: number }[];
}

const props = defineProps<{
    stats: Stats;
    centers: { id: number; name: string }[];
    selectedCenterId: number | null;
}>();

const { t, locale } = useI18n();
const page = usePage();

// Categorical series colors, validated for light and dark surfaces.
const SERIES = { patients: '#0D9488', sessions: '#C2811F' };
const OUTCOME_COLORS: Record<string, string> = { cured: '#16A34A', percentage: '#2563EB', ongoing: '#D97706', not_cured: '#DC2626' };
const OUTCOME_ORDER = ['cured', 'percentage', 'ongoing', 'not_cured'];

const greeting = computed(() => {
    const hour = new Date().getHours();
    const key = hour < 12 ? 'greetingMorning' : hour < 18 ? 'greetingAfternoon' : 'greetingEvening';

    return `${t(`dashboard.${key}`)}, ${page.props.auth.user.name.split(' ')[0]}`;
});

const scopeLabel = computed(() => {
    if (props.selectedCenterId) {
        return props.centers.find((center) => center.id === props.selectedCenterId)?.name ?? '';
    }

    return page.props.auth.is_global_admin ? t('dashboard.scopeAll') : (page.props.auth.active_center?.name ?? t('dashboard.scopeCenter'));
});

const centerOptions = computed(() => [{ id: null as number | null, name: t('dashboard.allCenters') }, ...props.centers]);

function selectCenter(centerId: unknown) {
    router.get(route('dashboard'), centerId ? { center_id: Number(centerId) } : {}, { preserveScroll: true });
}

const monthLabels = computed(() =>
    props.stats.months.map((month) => formatDate(`${month}-01`, locale.value, { month: 'short' })),
);

const activitySeries = computed(() => {
    const series = [];

    if (props.stats.patients) {
        series.push({ label: t('dashboard.activity.newPatients'), data: props.stats.patients.series, color: SERIES.patients });
    }
    if (props.stats.treatments) {
        series.push({ label: t('dashboard.activity.sessions'), data: props.stats.treatments.sessions_series, color: SERIES.sessions });
    }

    return series;
});

const currencies = computed(() => props.stats.billing?.currencies ?? []);
const selectedCurrency = ref(currencies.value[0]?.currency ?? null);
const currencyStats = computed(() => currencies.value.find((row) => row.currency === selectedCurrency.value) ?? null);
const money = (amount: number) => (currencyStats.value ? formatMoney(amount, currencyStats.value.currency, locale.value) : String(amount));
const hasRevenue = computed(() => (currencyStats.value?.series ?? []).some((value) => value > 0));

const outcomeRows = computed(() => {
    const outcomes = props.stats.treatments?.outcomes ?? {};

    return OUTCOME_ORDER.filter((key) => (outcomes[key] ?? 0) > 0).map((key) => ({
        key,
        label: t(`domain.outcome.${key}`),
        total: outcomes[key],
        color: OUTCOME_COLORS[key],
    }));
});

const outcomeTotal = computed(() => outcomeRows.value.reduce((sum, row) => sum + row.total, 0));
const cureRate = computed(() => {
    const cured = props.stats.treatments?.outcomes.cured ?? 0;

    return outcomeTotal.value > 0 ? Math.round((cured / outcomeTotal.value) * 100) : null;
});

const closureRows = computed(() => {
    const reasons = props.stats.treatments?.closure_reasons ?? {};
    const total = Object.values(reasons).reduce((sum, value) => sum + value, 0);

    return Object.entries(reasons)
        .sort(([, a], [, b]) => b - a)
        .map(([key, value]) => ({ key, label: t(`domain.closureReason.${key}`), total: value, share: total ? Math.round((value / total) * 100) : 0 }));
});

const topDiseaseMax = computed(() => Math.max(1, ...(props.stats.treatments?.top_diseases ?? []).map((row) => row.total)));

const attendanceRows = computed(() => {
    const statuses = props.stats.appointments?.month_statuses ?? {};
    const colors: Record<string, string> = { completed: 'success', scheduled: 'primary', cancelled: 'secondary', no_show: 'error' };

    return ['completed', 'scheduled', 'cancelled', 'no_show'].map((key) => ({
        key,
        label: t(`dashboard.attendance.${key}`),
        total: statuses[key] ?? 0,
        color: colors[key],
    }));
});

const methodRows = computed(() =>
    Object.entries(currencyStats.value?.methods ?? {})
        .sort(([, a], [, b]) => b - a)
        .map(([key, amount]) => ({ key, label: t(`domain.paymentMethod.${key}`), amount })),
);

const hasAnyModule = computed(() => page.props.auth.modules.length > 0);
const has = (module: string) => page.props.auth.modules.includes(module as never);

function time(value: string): string {
    return formatDate(value, locale.value, { hour: '2-digit', minute: '2-digit' });
}

function day(value: string): string {
    return formatDate(value, locale.value, { weekday: 'short', day: 'numeric', month: 'short' });
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <div class="d-flex flex-column flex-md-row align-md-end justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <div class="app-eyebrow mb-1">{{ formatDate(new Date(), locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}</div>
                <h1 class="dashboard-title">{{ greeting }}</h1>
                <p class="text-body-1 text-medium-emphasis mb-0">{{ t('dashboard.subtitle', { scope: scopeLabel }) }}</p>
            </div>

            <div class="d-flex flex-wrap align-center ga-2">
                <div v-if="centers.length" style="min-width: 220px">
                    <AppSelect
                        :model-value="selectedCenterId"
                        :options="centerOptions"
                        option-label="name"
                        option-value="id"
                        :label="t('common.center')"
                        @update:model-value="selectCenter"
                    />
                </div>
                <AppButton
                    v-if="has('patients')"
                    as="a"
                    :href="route('admin.patients.create')"
                    icon="mdi-plus"
                    :label="t('dashboard.quick.newPatient')"
                    @click="handleNavClick($event, route('admin.patients.create'))"
                />
            </div>
        </div>

        <v-alert v-if="!hasAnyModule" type="info" variant="tonal">{{ t('dashboard.noModule') }}</v-alert>

        <!-- KPIs -->
        <v-row dense class="mb-2">
            <v-col v-if="stats.patients" cols="12" sm="6" lg="3">
                <AppStatCard
                    :label="t('dashboard.kpi.patients')"
                    :value="stats.patients.total"
                    icon="mdi-account-heart-outline"
                    color="primary"
                    :current="stats.patients.new_this_month"
                    :previous="stats.patients.new_last_month"
                    :trend-label="t('common.vsLastMonth')"
                    :hint="t('dashboard.kpi.newThisMonth', { n: stats.patients.new_this_month })"
                    :href="route('admin.patients.index')"
                />
            </v-col>
            <v-col v-if="stats.treatments" cols="12" sm="6" lg="3">
                <AppStatCard
                    :label="t('dashboard.kpi.ongoingTreatments')"
                    :value="stats.treatments.ongoing"
                    icon="mdi-medical-bag"
                    color="info"
                    :hint="t('dashboard.kpi.closedTreatments', { n: stats.treatments.closed })"
                    :href="route('admin.treatments.index')"
                />
            </v-col>
            <v-col v-if="stats.treatments" cols="12" sm="6" lg="3">
                <AppStatCard
                    :label="t('dashboard.kpi.sessionsMonth')"
                    :value="stats.treatments.sessions_this_month"
                    icon="mdi-clipboard-pulse-outline"
                    color="accent"
                    :current="stats.treatments.sessions_this_month"
                    :previous="stats.treatments.sessions_last_month"
                    :trend-label="t('common.vsLastMonth')"
                />
            </v-col>
            <v-col v-if="stats.appointments" cols="12" sm="6" lg="3">
                <AppStatCard
                    :label="t('dashboard.kpi.appointmentsToday')"
                    :value="stats.appointments.today"
                    icon="mdi-calendar-today-outline"
                    color="success"
                    :hint="t('dashboard.kpi.next7Days', { n: stats.appointments.next_7_days })"
                    :href="route('admin.agenda')"
                />
            </v-col>
            <template v-if="stats.billing && currencyStats">
                <v-col cols="12" sm="6" lg="3">
                    <AppStatCard
                        :label="t('dashboard.kpi.collectedMonth')"
                        :value="money(currencyStats.collected_this_month)"
                        icon="mdi-cash-check"
                        color="success"
                        :current="currencyStats.collected_this_month"
                        :previous="currencyStats.collected_last_month"
                        :trend-label="t('common.vsLastMonth')"
                        :href="route('admin.billing.index')"
                    />
                </v-col>
                <v-col cols="12" sm="6" lg="3">
                    <AppStatCard
                        :label="t('dashboard.kpi.outstanding')"
                        :value="money(currencyStats.outstanding)"
                        icon="mdi-cash-clock"
                        color="warning"
                        :hint="t('dashboard.kpi.unpaidInvoices', { n: stats.billing.unpaid_invoices })"
                        :href="route('admin.billing.index', { status: 'unpaid' })"
                    />
                </v-col>
            </template>
            <v-col v-if="stats.practitioners" cols="12" sm="6" lg="3">
                <AppStatCard
                    :label="t('dashboard.kpi.practitioners')"
                    :value="stats.practitioners.total"
                    icon="mdi-account-tie-outline"
                    color="secondary"
                    :href="$page.props.auth.is_global_admin || $page.props.auth.is_manager ? route('admin.practitioners.index') : undefined"
                />
            </v-col>
        </v-row>

        <v-row>
            <!-- Activity -->
            <v-col v-if="activitySeries.length" cols="12" :lg="stats.appointments ? 8 : 12">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <h2 class="text-subtitle-1 font-weight-bold mb-4">{{ t('dashboard.activity.title') }}</h2>
                        <AppChart type="line" :labels="monthLabels" :series="activitySeries" :height="300" />
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Upcoming appointments -->
            <v-col v-if="stats.appointments" cols="12" :lg="activitySeries.length ? 4 : 12">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <div class="d-flex align-center justify-space-between mb-3">
                            <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.upcoming.title') }}</h2>
                            <a :href="route('admin.agenda')" class="text-body-2 font-weight-bold text-primary" @click="handleNavClick($event, route('admin.agenda'))">
                                {{ t('dashboard.upcoming.seeAgenda') }}
                            </a>
                        </div>

                        <div v-if="!stats.appointments.upcoming.length" class="dashboard-empty">
                            <v-icon icon="mdi-calendar-blank-outline" size="36" />
                            {{ t('dashboard.upcoming.empty') }}
                        </div>

                        <a
                            v-for="appointment in stats.appointments.upcoming"
                            :key="appointment.id"
                            class="dashboard-row"
                            :href="route('admin.agenda', { practitioner_id: appointment.practitioner_id, date: appointment.starts_at.slice(0, 10) })"
                            @click="handleNavClick($event, route('admin.agenda', { practitioner_id: appointment.practitioner_id, date: appointment.starts_at.slice(0, 10) }))"
                        >
                            <div class="dashboard-time">
                                <span class="font-weight-bold app-tabular">{{ time(appointment.starts_at) }}</span>
                                <span class="text-caption text-medium-emphasis">{{ day(appointment.starts_at) }}</span>
                            </div>
                            <div style="min-width: 0" class="flex-1-1">
                                <div class="text-body-2 font-weight-bold text-truncate">{{ appointment.patient }}</div>
                                <div class="text-caption text-medium-emphasis text-truncate">
                                    {{ t('dashboard.upcoming.with', { name: appointment.practitioner }) }}
                                </div>
                            </div>
                            <v-icon :icon="appointment.modality === 'remote' ? 'mdi-video-outline' : 'mdi-map-marker-outline'" size="18" class="text-medium-emphasis" />
                        </a>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Revenue -->
            <v-col v-if="stats.billing" cols="12" lg="7">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-4">
                            <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.revenue.title') }}</h2>
                            <v-btn-toggle v-if="currencies.length > 1" v-model="selectedCurrency" mandatory density="compact" color="primary" variant="outlined" divided>
                                <v-btn v-for="row in currencies" :key="row.currency" :value="row.currency" size="small">{{ row.currency }}</v-btn>
                            </v-btn-toggle>
                        </div>

                        <template v-if="currencyStats && hasRevenue">
                            <AppChart
                                type="bar"
                                :labels="monthLabels"
                                :series="[{ label: t('dashboard.kpi.collectedMonth'), data: currencyStats.series, color: SERIES.patients }]"
                                :format="money"
                                :height="260"
                            />
                            <div v-if="methodRows.length" class="mt-4">
                                <div class="text-caption text-medium-emphasis mb-2">{{ t('dashboard.methods') }}</div>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-chip v-for="row in methodRows" :key="row.key" variant="tonal" size="small">
                                        {{ row.label }} · <span class="app-tabular ms-1">{{ money(row.amount) }}</span>
                                    </v-chip>
                                </div>
                            </div>
                        </template>
                        <div v-else class="dashboard-empty">
                            <v-icon icon="mdi-cash-multiple" size="36" />
                            {{ t('dashboard.revenue.empty') }}
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Outcomes -->
            <v-col v-if="stats.treatments" cols="12" :lg="stats.billing ? 5 : 6">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <div class="d-flex align-start justify-space-between ga-3 mb-2">
                            <div>
                                <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.outcomes.title') }}</h2>
                                <p class="text-caption text-medium-emphasis mb-0">{{ t('dashboard.outcomes.hint') }}</p>
                            </div>
                            <div v-if="cureRate !== null" class="text-end">
                                <div class="text-h5 font-weight-bold app-tabular">{{ cureRate }}%</div>
                                <div class="text-caption text-medium-emphasis">{{ t('dashboard.outcomes.successRate') }}</div>
                            </div>
                        </div>

                        <AppChart
                            v-if="outcomeRows.length"
                            type="doughnut"
                            :labels="outcomeRows.map((row) => row.label)"
                            :series="[{ label: t('dashboard.outcomes.title'), data: outcomeRows.map((row) => row.total), color: '#0D9488' }]"
                            :slice-colors="outcomeRows.map((row) => row.color)"
                            :height="260"
                        />
                        <div v-else class="dashboard-empty">
                            <v-icon icon="mdi-chart-donut" size="36" />
                            {{ t('dashboard.outcomes.empty') }}
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Top diseases -->
            <v-col v-if="stats.treatments" cols="12" md="6" :lg="stats.billing ? 4 : 6">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <h2 class="text-subtitle-1 font-weight-bold mb-4">{{ t('dashboard.topDiseases.title') }}</h2>
                        <div v-if="!stats.treatments.top_diseases.length" class="dashboard-empty">{{ t('dashboard.topDiseases.empty') }}</div>
                        <div v-for="row in stats.treatments.top_diseases" :key="row.label" class="mb-4">
                            <div class="d-flex justify-space-between ga-3 text-body-2 mb-1">
                                <span class="text-truncate">{{ row.label }}</span>
                                <span class="font-weight-bold app-tabular">{{ row.total }}</span>
                            </div>
                            <v-progress-linear :model-value="(row.total / topDiseaseMax) * 100" color="primary" height="6" rounded />
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Month attendance + closure reasons -->
            <v-col v-if="stats.appointments || stats.treatments" cols="12" md="6" lg="4">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <template v-if="stats.appointments">
                            <h2 class="text-subtitle-1 font-weight-bold mb-4">{{ t('dashboard.attendance.title') }}</h2>
                            <v-row dense class="mb-4">
                                <v-col v-for="row in attendanceRows" :key="row.key" cols="6">
                                    <div class="dashboard-tile">
                                        <div class="d-flex align-center ga-2 text-caption text-medium-emphasis">
                                            <v-badge dot inline :color="row.color" />
                                            {{ row.label }}
                                        </div>
                                        <div class="text-h6 font-weight-bold app-tabular">{{ row.total }}</div>
                                    </div>
                                </v-col>
                            </v-row>
                        </template>

                        <template v-if="stats.treatments">
                            <h2 class="text-subtitle-1 font-weight-bold mb-3">{{ t('dashboard.closures.title') }}</h2>
                            <div v-if="!closureRows.length" class="text-body-2 text-medium-emphasis">{{ t('dashboard.closures.empty') }}</div>
                            <div v-for="row in closureRows" :key="row.key" class="d-flex justify-space-between ga-3 text-body-2 py-1">
                                <span>{{ row.label }}</span>
                                <span class="font-weight-bold app-tabular">{{ row.total }} <span class="text-medium-emphasis font-weight-regular">· {{ row.share }}%</span></span>
                            </div>
                        </template>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Recent patients -->
            <v-col v-if="stats.patients" cols="12" md="6" lg="4">
                <v-card variant="flat" class="h-100">
                    <v-card-text class="pa-6">
                        <div class="d-flex align-center justify-space-between mb-3">
                            <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.recentPatients.title') }}</h2>
                            <a :href="route('admin.patients.index')" class="text-body-2 font-weight-bold text-primary" @click="handleNavClick($event, route('admin.patients.index'))">
                                {{ t('dashboard.recentPatients.seeAll') }}
                            </a>
                        </div>
                        <div v-if="!stats.patients.recent.length" class="dashboard-empty">{{ t('dashboard.recentPatients.empty') }}</div>
                        <a
                            v-for="patient in stats.patients.recent"
                            :key="patient.id"
                            class="dashboard-row"
                            :href="route('admin.patients.edit', patient.id)"
                            @click="handleNavClick($event, route('admin.patients.edit', patient.id))"
                        >
                            <v-avatar color="primary" variant="tonal" size="36">
                                <span class="text-caption font-weight-bold">{{ patient.name.charAt(0).toUpperCase() }}</span>
                            </v-avatar>
                            <div style="min-width: 0" class="flex-1-1">
                                <div class="text-body-2 font-weight-bold text-truncate">{{ patient.name }}</div>
                                <div class="text-caption text-medium-emphasis">{{ formatDate(patient.created_at, locale) }}</div>
                            </div>
                            <v-icon icon="mdi-chevron-right" size="18" class="text-medium-emphasis app-flip-rtl" />
                        </a>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Centers (global view) -->
            <v-col v-if="stats.centers?.length" cols="12">
                <v-card variant="flat">
                    <v-card-text class="pa-6 pb-2">
                        <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.centers.title') }}</h2>
                    </v-card-text>
                    <v-table>
                        <thead>
                            <tr>
                                <th>{{ t('common.center') }}</th>
                                <th class="text-end">{{ t('dashboard.centers.patients') }}</th>
                                <th class="text-end">{{ t('dashboard.centers.ongoing') }}</th>
                                <th class="text-end">{{ t('dashboard.centers.practitioners') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="center in stats.centers" :key="center.id" class="cursor-pointer" @click="selectCenter(center.id)">
                                <td>
                                    <span class="font-weight-bold">{{ center.name }}</span>
                                    <span v-if="center.city" class="text-medium-emphasis"> · {{ center.city }}</span>
                                </td>
                                <td class="text-end app-tabular">{{ center.patients }}</td>
                                <td class="text-end app-tabular">{{ center.ongoing_treatments }}</td>
                                <td class="text-end app-tabular">{{ center.practitioners }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-title {
    margin: 0 0 4px;
    font-size: clamp(1.6rem, 2.6vw, 2.2rem);
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.15;
}

.dashboard-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 8px;
    margin-inline: -8px;
    border-radius: 12px;
    color: inherit;
    text-decoration: none;
    transition: background-color 0.15s;
}

.dashboard-row:hover {
    background: rgba(var(--v-theme-on-surface), 0.045);
}

.dashboard-time {
    display: flex;
    flex-direction: column;
    width: 86px;
    flex-shrink: 0;
    line-height: 1.25;
}

.dashboard-tile {
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.035);
}

.dashboard-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 32px 12px;
    text-align: center;
    font-size: 0.875rem;
    color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>

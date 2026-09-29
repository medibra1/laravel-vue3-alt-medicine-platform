import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createVuetify } from 'vuetify';
import AppointmentDialog, { type AppointmentPrefill } from './AppointmentDialog.vue';

let mockedSlots: { starts_at: string; ends_at: string }[] = [];
vi.mock('@/lib/http', () => ({
    http: { get: vi.fn(async () => ({ slots: mockedSlots })) },
}));

const vuetify = createVuetify();
let activeWrapper: VueWrapper | null = null;

const prefill: AppointmentPrefill = { centerId: 3, practitionerId: 7, date: '2026-10-05', hour: 10, minute: 15 };

// Setup state of <script setup> is reachable through vm in tests.
type DialogVm = {
    form: { center_id: number | null; practitioner_id: number | null };
    selectedDate: Date | null;
    selectedTime: string | null;
};

async function open(props: Record<string, unknown>) {
    activeWrapper = mount(AppointmentDialog, {
        props: { visible: false, patients: [], practitioners: [], ...props },
        attachTo: document.body,
        global: { plugins: [vuetify] },
    });
    await activeWrapper.setProps({ visible: true });
    await flushPromises();
    return activeWrapper.vm as unknown as DialogVm;
}

beforeEach(() => {
    vi.stubGlobal('route', () => '/stub');
    mockedSlots = [];
});

afterEach(() => {
    activeWrapper?.unmount();
    activeWrapper = null;
    vi.unstubAllGlobals();
});

describe('AppointmentDialog prefill', () => {
    it('initialises center, practitioner and date from the prefill', async () => {
        const vm = await open({ prefill });

        expect(vm.form.center_id).toBe(3);
        expect(vm.form.practitioner_id).toBe(7);
        expect(vm.selectedDate?.getFullYear()).toBe(2026);
        expect(vm.selectedDate?.getMonth()).toBe(9);
        expect(vm.selectedDate?.getDate()).toBe(5);
    });

    it('auto-selects the first available slot at or after the clicked time', async () => {
        mockedSlots = [
            { starts_at: '2026-10-05T09:30:00', ends_at: '2026-10-05T10:00:00' },
            { starts_at: '2026-10-05T10:30:00', ends_at: '2026-10-05T11:00:00' },
            { starts_at: '2026-10-05T11:00:00', ends_at: '2026-10-05T11:30:00' },
        ];
        const vm = await open({ prefill });

        expect(vm.selectedTime).toBe('2026-10-05T10:30:00');
    });

    it('leaves the time empty when no slot matches', async () => {
        mockedSlots = [{ starts_at: '2026-10-05T09:00:00', ends_at: '2026-10-05T09:30:00' }];
        const vm = await open({ prefill });

        expect(vm.selectedTime).toBeNull();
    });

    it('ignores the prefill when rescheduling an existing appointment', async () => {
        mockedSlots = [{ starts_at: '2026-10-05T10:30:00', ends_at: '2026-10-05T11:00:00' }];
        const appointment = {
            id: 1,
            practitioner_id: 2,
            starts_at: '2026-11-12T14:00:00',
            duration_minutes: 30,
            modality: 'in_person',
            meeting_link: null,
            reason: null,
        };
        const vm = await open({ prefill, appointment });

        expect(vm.form.center_id).toBeNull();
        expect(vm.form.practitioner_id).toBe(2);
        expect(vm.selectedDate?.getDate()).toBe(12);
        expect(vm.selectedTime).toBe('2026-11-12T14:00:00');
    });
});

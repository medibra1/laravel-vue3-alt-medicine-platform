import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createVuetify } from 'vuetify';
import CenterClosuresDialog from './CenterClosuresDialog.vue';

vi.mock('@/lib/http', () => ({
    http: {
        get: vi.fn(async () => [{ id: 1, center_id: 1, starts_on: '2026-10-06', ends_on: '2026-10-06', label: 'Fête nationale', notes: null }]),
    },
}));

const deleteSpy = vi.fn();
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    router: { delete: (...args: unknown[]) => deleteSpy(...args) },
}));

const vuetify = createVuetify();

// v-dialog teleports to document.body — unmount between tests so content
// doesn't stack across mounts.
let activeWrapper: VueWrapper | null = null;

beforeEach(() => {
    vi.stubGlobal('route', () => '/stub');
    deleteSpy.mockReset();
});

afterEach(() => {
    activeWrapper?.unmount();
    activeWrapper = null;
    vi.unstubAllGlobals();
});

describe('CenterClosuresDialog', () => {
    it('keeps the closure chip when the delete confirmation is cancelled', async () => {
        vi.stubGlobal('confirm', () => false);
        activeWrapper = mount(CenterClosuresDialog, {
            props: { visible: false, center: { id: 1, name: 'Paris' } },
            attachTo: document.body,
            global: { plugins: [vuetify] },
        });
        await activeWrapper.setProps({ visible: true });
        await flushPromises();

        const close = document.body.querySelector<HTMLElement>('.v-chip__close');
        expect(close).not.toBeNull();
        expect(document.body.querySelectorAll('.v-chip')).toHaveLength(1);
        close!.click();
        await flushPromises();

        expect(deleteSpy).not.toHaveBeenCalled();
        // An uncontrolled closable v-chip hides itself on click even though
        // nothing was deleted — the chip must still be there.
        expect(document.body.querySelectorAll('.v-chip')).toHaveLength(1);
    });
});

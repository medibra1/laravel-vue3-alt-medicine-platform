import 'fake-indexeddb/auto';
import { db } from '@/lib/db';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useResilientForm } from './useResilientForm';

vi.mock('@/lib/http', () => ({
    http: {
        post: vi.fn(async () => ({ id: 42 })),
        patch: vi.fn(async () => ({})),
    },
}));

import { http } from '@/lib/http';

describe('useResilientForm', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        vi.mocked(http.post).mockClear();
        vi.mocked(http.patch).mockClear();
    });

    afterEach(async () => {
        vi.useRealTimers();
        await db.drafts.clear();
    });

    it('persists locally after the local debounce elapses', async () => {
        const { form, scheduleSave } = useResilientForm(
            'patients',
            {
                client_uuid: undefined as string | undefined,
                id: null as number | null,
                first_name: null as string | null,
            },
            { create: '/admin/patients/draft', update: (id) => `/admin/patients/${id}/draft` },
        );

        form.first_name = 'Amina';
        scheduleSave();

        await vi.advanceTimersByTimeAsync(300);

        const rows = await db.drafts.toArray();
        expect(rows).toHaveLength(1);
        expect(rows[0].payload.first_name).toBe('Amina');
    });

    it('uses the create endpoint for the first server save and stores the returned id', async () => {
        const { form, serverId, scheduleSave } = useResilientForm(
            'patients',
            {
                client_uuid: undefined as string | undefined,
                id: null as number | null,
                first_name: null as string | null,
            },
            { create: '/admin/patients/draft', update: (id) => `/admin/patients/${id}/draft` },
        );

        form.first_name = 'Amina';
        scheduleSave();

        await vi.advanceTimersByTimeAsync(50);

        expect(http.post).toHaveBeenCalledTimes(1);
        expect(http.patch).not.toHaveBeenCalled();
        expect(serverId.value).toBe(42);
    });

    it('debounces subsequent edits against the update endpoint once an id exists', async () => {
        const { form, scheduleSave } = useResilientForm(
            'patients',
            {
                client_uuid: undefined as string | undefined,
                id: 42 as number | null,
                first_name: null as string | null,
            },
            { create: '/admin/patients/draft', update: (id) => `/admin/patients/${id}/draft` },
        );

        form.first_name = 'Amina';
        scheduleSave();

        await vi.advanceTimersByTimeAsync(1499);
        expect(http.patch).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(http.patch).toHaveBeenCalledTimes(1);
        expect(http.post).not.toHaveBeenCalled();
    });

    it('flush() bypasses the debounce timer and saves immediately', async () => {
        const { form, flush } = useResilientForm(
            'patients',
            {
                client_uuid: undefined as string | undefined,
                id: 42 as number | null,
                first_name: null as string | null,
            },
            { create: '/admin/patients/draft', update: (id) => `/admin/patients/${id}/draft` },
        );

        form.first_name = 'Amina';
        await flush();

        expect(http.patch).toHaveBeenCalledTimes(1);
    });
});

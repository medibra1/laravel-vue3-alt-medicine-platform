import { db } from '@/lib/db';
import { http } from '@/lib/http';
import { reactive, ref } from 'vue';

interface Endpoints {
    create: string; // POST, first save
    update: (id: number) => string; // PATCH, subsequent saves
}

interface Options {
    localDebounceMs?: number;
    serverDebounceMs?: number;
}

/**
 * The resilient-wizard autosave mechanism (see CLAUDE.md "UX — wizards
 * résilients"): continuous local save (Dexie/IndexedDB) plus debounced
 * background server sync, same code path online and offline. Resource-
 * agnostic on purpose — Patient is the first consumer, Treatment's
 * multi-step wizard is the next. Deliberately narrow: it only knows
 * about autosave, not step navigation or field validation.
 */
export function useResilientForm<T extends Record<string, unknown>>(
    resource: string,
    initial: T,
    endpoints: Endpoints,
    options: Options = {},
) {
    const localId =
        (initial.client_uuid as string | undefined) ?? crypto.randomUUID();
    const form = reactive<T>({ ...initial, client_uuid: localId } as T);
    const serverId = ref<number | null>((initial.id as number | null) ?? null);
    const saving = ref(false);
    const lastSavedAt = ref<number | null>(null);

    let localTimer: ReturnType<typeof setTimeout> | undefined;
    let serverTimer: ReturnType<typeof setTimeout> | undefined;

    async function persistLocal() {
        await db.drafts.put({
            localId,
            resource,
            serverId: serverId.value,
            payload: { ...form },
            updatedAt: Date.now(),
            syncedAt: lastSavedAt.value,
            status: 'draft',
        });
    }

    async function persistServer() {
        saving.value = true;
        try {
            if (serverId.value === null) {
                const data = await http.post<{ id: number }>(endpoints.create, {
                    ...form,
                });
                serverId.value = data.id;
            } else {
                await http.patch(endpoints.update(serverId.value), { ...form });
            }
            lastSavedAt.value = Date.now();
            await persistLocal();
        } finally {
            saving.value = false;
        }
    }

    function scheduleSave() {
        clearTimeout(localTimer);
        localTimer = setTimeout(persistLocal, options.localDebounceMs ?? 300);

        clearTimeout(serverTimer);
        // First save fires fast (not truly debounced) so an id exists
        // ASAP; subsequent edits use the full 1-2s debounce.
        const delay =
            serverId.value === null ? 50 : (options.serverDebounceMs ?? 1500);
        serverTimer = setTimeout(persistServer, delay);
    }

    async function flush() {
        clearTimeout(localTimer);
        clearTimeout(serverTimer);
        await persistServer();
    }

    async function discardLocal() {
        await db.drafts.delete(localId);
    }

    return { form, serverId, saving, lastSavedAt, scheduleSave, flush, discardLocal };
}

import Dexie, { type Table } from 'dexie';

export interface DraftRecord<TPayload = Record<string, unknown>> {
    localId: string; // client-generated UUID, primary key
    resource: string; // 'patients', later 'treatments', etc.
    serverId: number | null; // set once the server has assigned one
    payload: TPayload; // raw form field values, unvalidated
    updatedAt: number; // epoch ms — local write time
    syncedAt: number | null; // epoch ms of last confirmed server ack, null = pending
    status: 'draft' | 'confirmed';
}

class RuqyaDraftsDB extends Dexie {
    drafts!: Table<DraftRecord, string>;

    constructor() {
        super('ruqya_drafts');
        this.version(1).stores({
            drafts: 'localId, resource, serverId, [resource+serverId], updatedAt',
        });
    }
}

export const db = new RuqyaDraftsDB();

import { describe, expect, it } from 'vitest';
import { createRecordingStore } from './recordingStore';
import type { StoredSession, StoreBackend } from './recordingStore';

function memoryBackend(): StoreBackend & {
    sessions: Map<string, StoredSession>;
    chunks: Map<string, Map<number, Blob>>;
} {
    const sessions = new Map<string, StoredSession>();
    const chunks = new Map<string, Map<number, Blob>>();

    return {
        sessions,
        chunks,
        async putSession(session) {
            sessions.set(session.id, session);
        },
        async getSessions() {
            return [...sessions.values()];
        },
        async putChunk(sessionId, seq, blob) {
            // Writes finish out of order in real browsers, so simulate that.
            await new Promise((resolve) => setTimeout(resolve, 10 - (seq % 3)));
            const bySeq = chunks.get(sessionId) ?? new Map<number, Blob>();
            bySeq.set(seq, blob);
            chunks.set(sessionId, bySeq);
        },
        async getChunks(sessionId) {
            const bySeq = chunks.get(sessionId) ?? new Map<number, Blob>();

            return [...bySeq.entries()]
                .sort(([a], [b]) => a - b)
                .map(([, blob]) => blob);
        },
        async deleteSession(sessionId) {
            sessions.delete(sessionId);
            chunks.delete(sessionId);
        },
    };
}

const meta = { mimeType: 'audio/webm;codecs=opus', extension: 'webm' };

async function read(blob: Blob | null): Promise<string> {
    return blob ? await blob.text() : '(none)';
}

describe('recording store', () => {
    it('rebuilds a recording from its chunks in order', async () => {
        const store = createRecordingStore(memoryBackend());
        const id = (await store.begin(meta)) as string;

        ['one-', 'two-', 'three-', 'four'].forEach((text) =>
            store.addChunk(id, new Blob([text])),
        );

        const blob = await store.assemble(id);

        expect(await read(blob)).toBe('one-two-three-four');
        expect(blob?.type).toBe('audio/webm;codecs=opus');
    });

    it('lists unfinished recordings newest first with their size', async () => {
        const backend = memoryBackend();
        const store = createRecordingStore(backend);
        const older = (await store.begin(meta)) as string;
        store.addChunk(older, new Blob(['aaaa']));
        await store.flush();
        backend.sessions.get(older)!.startedAt -= 60_000;
        const newer = (await store.begin(meta)) as string;
        store.addChunk(newer, new Blob(['bb']));
        store.markStopped(newer, 12);

        const found = await store.unfinished();

        expect(found.map((session) => session.id)).toEqual([newer, older]);
        expect(found[0]).toMatchObject({
            sizeBytes: 2,
            elapsedSeconds: 12,
            state: 'stopped',
        });
        expect(found[1].sizeBytes).toBe(4);
    });

    it('ignores sessions that never received any audio', async () => {
        const store = createRecordingStore(memoryBackend());
        await store.begin(meta);

        expect(await store.unfinished()).toEqual([]);
    });

    it('records the elapsed time as it goes', async () => {
        const backend = memoryBackend();
        const store = createRecordingStore(backend);
        const id = (await store.begin(meta)) as string;

        store.touch(id, 5);
        store.touch(id, 10);
        await store.flush();

        expect(backend.sessions.get(id)?.elapsedSeconds).toBe(10);
    });

    it('removes a session and its chunks once discarded', async () => {
        const backend = memoryBackend();
        const store = createRecordingStore(backend);
        const id = (await store.begin(meta)) as string;
        store.addChunk(id, new Blob(['x']));

        await store.discard(id);

        expect(backend.sessions.size).toBe(0);
        expect(backend.chunks.size).toBe(0);
        expect(await store.assemble(id)).toBeNull();
    });

    it('does not lose chunks still being written when it is discarded', async () => {
        const backend = memoryBackend();
        const store = createRecordingStore(backend);
        const id = (await store.begin(meta)) as string;
        store.addChunk(id, new Blob(['late']));

        await store.discard(id);

        expect(backend.chunks.size).toBe(0);
    });

    it('carries on quietly when there is no storage', async () => {
        const store = createRecordingStore(null);

        expect(store.available).toBe(false);
        expect(await store.begin(meta)).toBeNull();
        expect(await store.unfinished()).toEqual([]);
        expect(await store.assemble('x')).toBeNull();
        expect(() => store.addChunk('x', new Blob(['a']))).not.toThrow();
        await expect(store.discard('x')).resolves.toBeUndefined();
    });

    it('never throws when the storage fails', async () => {
        const broken: StoreBackend = {
            putSession: () => Promise.reject(new Error('quota')),
            getSessions: () => Promise.reject(new Error('quota')),
            putChunk: () => Promise.reject(new Error('quota')),
            getChunks: () => Promise.reject(new Error('quota')),
            deleteSession: () => Promise.reject(new Error('quota')),
        };
        const store = createRecordingStore(broken);

        expect(await store.begin(meta)).toBeNull();
        expect(await store.unfinished()).toEqual([]);
        expect(await store.assemble('x')).toBeNull();
        await expect(store.discard('x')).resolves.toBeUndefined();
    });

    it('keeps recording when a chunk write fails part way', async () => {
        const backend = memoryBackend();
        let calls = 0;
        const flaky: StoreBackend = {
            ...backend,
            putChunk: (sessionId, seq, blob) =>
                ++calls === 2
                    ? Promise.reject(new Error('disk full'))
                    : backend.putChunk(sessionId, seq, blob),
        };
        const store = createRecordingStore(flaky);
        const id = (await store.begin(meta)) as string;

        ['a', 'b', 'c'].forEach((text) => store.addChunk(id, new Blob([text])));

        await expect(store.flush()).resolves.toBeUndefined();
        expect(await read(await store.assemble(id))).toBe('ac');
    });
});

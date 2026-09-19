/**
 * Keeps a recording on the user's device while it is being made, so a tab
 * crash, reload or dropped connection doesn't lose it. Chunks are written as
 * they arrive and removed once the upload has been confirmed.
 *
 * Nothing here ever throws. If storage isn't available (private windows,
 * blocked site data, full disk) recording carries on without a backup.
 */

export interface StoredSession {
    id: string;
    mimeType: string;
    extension: string;
    startedAt: number;
    elapsedSeconds: number;
    state: 'recording' | 'stopped';
}

export interface RecoverableRecording extends StoredSession {
    sizeBytes: number;
}

export interface StoreBackend {
    putSession(session: StoredSession): Promise<void>;
    getSessions(): Promise<StoredSession[]>;
    putChunk(sessionId: string, seq: number, blob: Blob): Promise<void>;
    getChunks(sessionId: string): Promise<Blob[]>;
    deleteSession(sessionId: string): Promise<void>;
}

export function createRecordingStore(backend: StoreBackend | null) {
    const sessions = new Map<string, StoredSession>();
    const nextSeq = new Map<string, number>();
    const pending = new Set<Promise<unknown>>();

    function track(work: Promise<unknown>): void {
        const safe = work.catch(() => undefined);
        pending.add(safe);
        void safe.finally(() => pending.delete(safe));
    }

    async function safely<T>(work: () => Promise<T>, fallback: T): Promise<T> {
        try {
            return await work();
        } catch {
            return fallback;
        }
    }

    return {
        get available(): boolean {
            return backend !== null;
        },

        async begin(meta: {
            mimeType: string;
            extension: string;
        }): Promise<string | null> {
            if (!backend) {
                return null;
            }

            const session: StoredSession = {
                id: crypto.randomUUID(),
                mimeType: meta.mimeType,
                extension: meta.extension,
                startedAt: Date.now(),
                elapsedSeconds: 0,
                state: 'recording',
            };

            return safely(async () => {
                await backend.putSession(session);
                sessions.set(session.id, session);
                nextSeq.set(session.id, 0);

                return session.id;
            }, null);
        },

        addChunk(sessionId: string, blob: Blob): void {
            if (!backend || !nextSeq.has(sessionId)) {
                return;
            }

            const seq = nextSeq.get(sessionId) ?? 0;
            nextSeq.set(sessionId, seq + 1);
            track(backend.putChunk(sessionId, seq, blob));
        },

        touch(sessionId: string, elapsedSeconds: number): void {
            this.update(sessionId, { elapsedSeconds });
        },

        markStopped(sessionId: string, elapsedSeconds: number): void {
            this.update(sessionId, { elapsedSeconds, state: 'stopped' });
        },

        update(sessionId: string, patch: Partial<StoredSession>): void {
            const current = sessions.get(sessionId);
            if (!backend || !current) {
                return;
            }

            const next = { ...current, ...patch };
            sessions.set(sessionId, next);
            track(backend.putSession(next));
        },

        /** Waits for chunk and session writes that are still in flight. */
        async flush(): Promise<void> {
            await Promise.all(pending);
        },

        async unfinished(): Promise<RecoverableRecording[]> {
            if (!backend) {
                return [];
            }

            await this.flush();

            return safely(async () => {
                const found = await backend.getSessions();
                const withSizes = await Promise.all(
                    found.map(async (session) => {
                        const chunks = await backend.getChunks(session.id);

                        return {
                            ...session,
                            sizeBytes: chunks.reduce(
                                (total, chunk) => total + chunk.size,
                                0,
                            ),
                        };
                    }),
                );

                return withSizes
                    .filter((session) => session.sizeBytes > 0)
                    .sort((a, b) => b.startedAt - a.startedAt);
            }, []);
        },

        async assemble(sessionId: string): Promise<Blob | null> {
            if (!backend) {
                return null;
            }

            await this.flush();

            return safely(async () => {
                const session = (await backend.getSessions()).find(
                    (candidate) => candidate.id === sessionId,
                );
                const chunks = await backend.getChunks(sessionId);

                return session && chunks.length > 0
                    ? new Blob(chunks, { type: session.mimeType })
                    : null;
            }, null);
        },

        async discard(sessionId: string): Promise<void> {
            sessions.delete(sessionId);
            nextSeq.delete(sessionId);

            if (!backend) {
                return;
            }

            await this.flush();
            await safely(() => backend.deleteSession(sessionId), undefined);
        },
    };
}

function request<T>(req: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function transactionDone(tx: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

export function createIndexedDbBackend(): StoreBackend | null {
    if (typeof indexedDB === 'undefined') {
        return null;
    }

    let opened: Promise<IDBDatabase> | null = null;

    function open(): Promise<IDBDatabase> {
        opened ??= new Promise((resolve, reject) => {
            const req = indexedDB.open('session-scribe-recovery', 1);
            req.onupgradeneeded = () => {
                req.result.createObjectStore('sessions', { keyPath: 'id' });
                req.result.createObjectStore('chunks', {
                    keyPath: ['sessionId', 'seq'],
                });
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

        return opened;
    }

    return {
        async putSession(session) {
            const tx = (await open()).transaction('sessions', 'readwrite');
            tx.objectStore('sessions').put(session);
            await transactionDone(tx);
        },

        async getSessions() {
            const tx = (await open()).transaction('sessions', 'readonly');

            return request(tx.objectStore('sessions').getAll());
        },

        async putChunk(sessionId, seq, blob) {
            const tx = (await open()).transaction('chunks', 'readwrite');
            tx.objectStore('chunks').put({ sessionId, seq, blob });
            await transactionDone(tx);
        },

        async getChunks(sessionId) {
            const tx = (await open()).transaction('chunks', 'readonly');
            const rows = await request(
                tx
                    .objectStore('chunks')
                    .getAll(
                        IDBKeyRange.bound(
                            [sessionId, 0],
                            [sessionId, Number.MAX_SAFE_INTEGER],
                        ),
                    ),
            );

            return rows.map((row: { blob: Blob }) => row.blob);
        },

        async deleteSession(sessionId) {
            const tx = (await open()).transaction(
                ['sessions', 'chunks'],
                'readwrite',
            );
            tx.objectStore('sessions').delete(sessionId);
            tx.objectStore('chunks').delete(
                IDBKeyRange.bound(
                    [sessionId, 0],
                    [sessionId, Number.MAX_SAFE_INTEGER],
                ),
            );
            await transactionDone(tx);
        },
    };
}

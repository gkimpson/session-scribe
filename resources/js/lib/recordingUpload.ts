import { show, store, upload } from '@/routes/recordings';
import { show as showSummary } from '@/routes/summaries';
import { store as storeSummary } from '@/routes/transcripts/summaries';

interface CreatedRecording {
    id: string;
    s3_key: string;
    upload: { url: string; headers: Record<string, string | string[]> };
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson<T>(url: string, body?: object): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return (await response.json()) as T;
}

function putWithProgress(
    url: string,
    headers: CreatedRecording['upload']['headers'],
    blob: Blob,
    contentType: string,
    onProgress: (percent: number) => void,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);
        Object.entries(headers).forEach(([name, value]) => {
            if (name.toLowerCase() === 'host') {
                return;
            }
            xhr.setRequestHeader(
                name,
                Array.isArray(value) ? value.join(', ') : value,
            );
        });
        xhr.setRequestHeader('Content-Type', contentType);
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        xhr.onload = () =>
            xhr.status >= 200 && xhr.status < 300
                ? resolve()
                : reject(new Error(`Upload failed with status ${xhr.status}`));
        xhr.onerror = () => reject(new Error('Network error during upload'));
        xhr.send(blob);
    });
}

export async function uploadRecording(
    audio: { blob: Blob; mimeType: string; durationSeconds: number },
    onProgress: (percent: number) => void,
): Promise<{ id: string; s3Key: string }> {
    const contentType = audio.mimeType.split(';')[0];
    const created = await postJson<CreatedRecording>(store().url, {
        mime_type: contentType,
        size_bytes: audio.blob.size,
        duration_seconds: Math.max(1, audio.durationSeconds),
        consent: true,
    });

    await putWithProgress(
        created.upload.url,
        created.upload.headers,
        audio.blob,
        contentType,
        onProgress,
    );
    await postJson(upload(created.id).url);

    return { id: created.id, s3Key: created.s3_key };
}

export interface TranscriptTurn {
    speaker: string;
    text: string;
}

export interface RecordingStatus {
    state: string;
    failureReason: string | null;
    turns: TranscriptTurn[] | null;
    redacted: boolean | null;
    transcriptId: number | null;
}

export async function fetchRecording(id: string): Promise<RecordingStatus> {
    const response = await fetch(show(id).url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    const data = (await response.json()) as {
        state: string;
        failure_reason: string | null;
        turns: TranscriptTurn[] | null;
        redacted: boolean | null;
        transcript_id: number | null;
    };

    return {
        state: data.state,
        failureReason: data.failure_reason,
        turns: data.turns,
        redacted: data.redacted,
        transcriptId: data.transcript_id,
    };
}

export type SummaryLevel = 'brief' | 'normal' | 'detailed';

export interface SummarySection {
    heading: string;
    body: string;
}

export interface SummaryView {
    id: number;
    level: SummaryLevel;
    state: string;
    sections: SummarySection[] | null;
    failureReason: string | null;
}

interface SummaryPayload {
    id: number;
    level: SummaryLevel;
    state: string;
    sections: SummarySection[] | null;
    failure_reason: string | null;
}

export function toSummaryView(payload: SummaryPayload): SummaryView {
    return {
        id: payload.id,
        level: payload.level,
        state: payload.state,
        sections: payload.sections,
        failureReason: payload.failure_reason,
    };
}

export async function requestSummary(
    transcriptId: number,
    level: SummaryLevel,
): Promise<SummaryView> {
    return toSummaryView(
        await postJson<SummaryPayload>(storeSummary(transcriptId).url, {
            level,
        }),
    );
}

export async function fetchSummary(id: number): Promise<SummaryView> {
    const response = await fetch(showSummary(id).url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return toSummaryView((await response.json()) as SummaryPayload);
}

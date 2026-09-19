import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import {
    fetchRecording,
    fetchSummary,
    requestSummary,
    toSummaryView,
    uploadRecording,
} from '@/lib/recordingUpload';
import type {
    SummaryLevel,
    SummaryView,
    TranscriptTurn,
} from '@/lib/recordingUpload';

export type RecorderStatus =
    | 'idle'
    | 'recording'
    | 'uploading'
    | 'transcribing'
    | 'ready'
    | 'failed'
    | 'blocked';

export interface CapturedAudio {
    blob: Blob;
    url: string;
    mimeType: string;
    extension: string;
    sizeBytes: number;
    durationSeconds: number;
}

export interface StoredRecording {
    id: string;
    s3Key: string;
}

export type FailStage = 'record' | 'upload' | 'transcribe';

const POLL_MS = 3000;
const SUMMARY_POLL_MS = 2000;
const MAX_POLL_ERRORS = 5;

export const MAX_SECONDS = 60 * 60;
export const MAX_BYTES = 250 * 1024 * 1024;

const mimeCandidates: { mimeType: string; extension: string }[] = [
    { mimeType: 'audio/webm;codecs=opus', extension: 'webm' },
    { mimeType: 'audio/webm', extension: 'webm' },
    { mimeType: 'audio/mp4', extension: 'm4a' },
    { mimeType: 'audio/ogg;codecs=opus', extension: 'ogg' },
];

function pickMimeType(): { mimeType: string; extension: string } | null {
    return (
        mimeCandidates.find((candidate) =>
            MediaRecorder.isTypeSupported(candidate.mimeType),
        ) ?? null
    );
}

export const statusLabels: Record<RecorderStatus, string> = {
    idle: 'Not started',
    recording: 'Recording',
    uploading: 'Uploading',
    transcribing: 'Transcribing',
    ready: 'Transcript ready',
    failed: 'Failed',
    blocked: 'Failed',
};

export const stepOrder: RecorderStatus[] = [
    'recording',
    'uploading',
    'transcribing',
    'ready',
];

/**
 * Recorder state machine. The microphone capture and the upload to S3 are
 * real. Transcription runs as a queued Transcribe job and is polled until it
 * finishes. Summaries come in later.
 */
export function useRecorderFlow(initial?: {
    id: number;
    recordingId: string;
    s3Key: string;
    turns: TranscriptTurn[];
    redacted: boolean;
    summaries: Parameters<typeof toSummaryView>[0][];
}) {
    const status = ref<RecorderStatus>('idle');
    const consent = ref(false);
    const elapsed = ref(0);
    const progress = ref(0);
    const failStage = ref<FailStage | null>(null);
    const failReason = ref('');
    const audio = ref<CapturedAudio | null>(null);
    const stored = ref<StoredRecording | null>(null);
    const turns = ref<TranscriptTurn[]>([]);
    const waitedSeconds = ref(0);
    const redacted = ref(true);
    const transcriptId = ref<number | null>(null);
    const detail = ref<SummaryLevel>('normal');
    const summaries = ref<Partial<Record<SummaryLevel, SummaryView>>>({});
    const currentSummary = computed(
        () => summaries.value[detail.value] ?? null,
    );
    const summaryTimers: Partial<
        Record<SummaryLevel, ReturnType<typeof setTimeout>>
    > = {};

    let elapsedTimer: ReturnType<typeof setInterval> | null = null;
    let pollTimer: ReturnType<typeof setTimeout> | null = null;
    let waitTimer: ReturnType<typeof setInterval> | null = null;
    let pollErrors = 0;
    let stream: MediaStream | null = null;
    let recorder: MediaRecorder | null = null;
    let chunks: Blob[] = [];
    let extension = 'webm';

    function clearElapsedTimer(): void {
        if (elapsedTimer) {
            clearInterval(elapsedTimer);
            elapsedTimer = null;
        }
    }

    function clearPollTimer(): void {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        if (waitTimer) {
            clearInterval(waitTimer);
            waitTimer = null;
        }
    }

    function clearSummaryTimers(): void {
        (Object.keys(summaryTimers) as SummaryLevel[]).forEach((level) => {
            clearTimeout(summaryTimers[level]);
            delete summaryTimers[level];
        });
    }

    function failSummary(level: SummaryLevel, reason: string): void {
        summaries.value[level] = {
            id: summaries.value[level]?.id ?? 0,
            level,
            state: 'failed',
            sections: null,
            failureReason: reason,
        };
    }

    function pollSummary(level: SummaryLevel, errors = 0): void {
        summaryTimers[level] = setTimeout(async () => {
            const current = summaries.value[level];
            if (!current || ['complete', 'failed'].includes(current.state)) {
                return;
            }

            try {
                summaries.value[level] = await fetchSummary(current.id);
            } catch {
                if (errors + 1 >= MAX_POLL_ERRORS) {
                    failSummary(
                        level,
                        'Lost contact with the server while waiting for the summary.',
                    );

                    return;
                }
                pollSummary(level, errors + 1);

                return;
            }

            if (
                !['complete', 'failed'].includes(
                    summaries.value[level]?.state ?? '',
                )
            ) {
                pollSummary(level);
            }
        }, SUMMARY_POLL_MS);
    }

    async function summarise(): Promise<void> {
        const level = detail.value;
        const existing = summaries.value[level];

        if (
            transcriptId.value === null ||
            existing?.state === 'complete' ||
            existing?.state === 'queued' ||
            existing?.state === 'summarising'
        ) {
            return;
        }

        summaries.value[level] = {
            id: existing?.id ?? 0,
            level,
            state: 'queued',
            sections: null,
            failureReason: null,
        };

        try {
            const view = await requestSummary(transcriptId.value, level);
            summaries.value[level] = view;
            if (!['complete', 'failed'].includes(view.state)) {
                pollSummary(level);
            }
        } catch {
            failSummary(level, 'The summary could not be started.');
        }
    }

    function clearAudio(): void {
        if (audio.value) {
            URL.revokeObjectURL(audio.value.url);
        }
        audio.value = null;
    }

    function stopStream(): void {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        recorder = null;
    }

    function fail(stage: FailStage, reason: string): void {
        clearElapsedTimer();
        clearPollTimer();
        stopStream();
        status.value = 'failed';
        failStage.value = stage;
        failReason.value = reason;
    }

    async function record(): Promise<void> {
        clearElapsedTimer();
        elapsed.value = 0;
        progress.value = 0;
        failStage.value = null;
        failReason.value = '';
        stored.value = null;
        turns.value = [];
        status.value = 'recording';
        clearAudio();

        if (typeof MediaRecorder === 'undefined') {
            fail('record', 'This browser cannot record audio.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            fail(
                'record',
                'The microphone is only available on a secure (HTTPS) page. Open this site over HTTPS and try again.',
            );
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (error) {
            const name = (error as DOMException | undefined)?.name;
            if (
                name === 'NotAllowedError' ||
                name === 'SecurityError' ||
                name === 'PermissionDeniedError'
            ) {
                status.value = 'blocked';
            } else if (name === 'NotReadableError') {
                fail(
                    'record',
                    'The microphone is in use by another app or tab. Close it and try again.',
                );
            } else {
                fail(
                    'record',
                    'No microphone was found, so nothing was recorded.',
                );
            }
            return;
        }

        const format = pickMimeType();
        if (!format) {
            fail(
                'record',
                'This browser cannot record audio in a supported format.',
            );
            return;
        }

        chunks = [];
        extension = format.extension;
        recorder = new MediaRecorder(stream, { mimeType: format.mimeType });
        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        recorder.onerror = () =>
            fail(
                'record',
                'The recording stopped unexpectedly. Nothing was saved.',
            );
        recorder.start(1000);
        elapsedTimer = setInterval(() => {
            elapsed.value += 1;
            if (elapsed.value >= MAX_SECONDS) {
                stop();
            }
        }, 1000);
    }

    function stop(): void {
        clearElapsedTimer();
        const active = recorder;
        if (!active || active.state === 'inactive') {
            return;
        }

        active.onstop = () => {
            const blob = new Blob(chunks, { type: active.mimeType });
            chunks = [];
            if (blob.size === 0) {
                fail('record', 'No audio was captured, so nothing was saved.');
            } else if (blob.size > MAX_BYTES) {
                fail(
                    'record',
                    'The recording is over the 250 MB limit and was not saved.',
                );
            } else {
                clearAudio();
                audio.value = {
                    blob,
                    url: URL.createObjectURL(blob),
                    mimeType: active.mimeType,
                    extension,
                    sizeBytes: blob.size,
                    durationSeconds: elapsed.value,
                };
                void upload();
            }
        };
        active.stop();
        stopStream();
    }

    async function upload(): Promise<void> {
        const captured = audio.value;
        if (!captured) {
            return;
        }

        status.value = 'uploading';
        progress.value = 0;
        failStage.value = null;
        failReason.value = '';

        try {
            stored.value = await uploadRecording(captured, (percent) => {
                progress.value = percent;
            });
            progress.value = 100;
            beginPolling();
        } catch {
            fail(
                'upload',
                'The upload did not finish. The audio is still on this device.',
            );
        }
    }

    function beginPolling(): void {
        status.value = 'transcribing';
        pollErrors = 0;
        waitedSeconds.value = 0;
        clearPollTimer();
        waitTimer = setInterval(() => (waitedSeconds.value += 1), 1000);
        pollTimer = setTimeout(() => void poll(), POLL_MS);
    }

    async function poll(): Promise<void> {
        if (!stored.value || status.value !== 'transcribing') {
            return;
        }

        try {
            const result = await fetchRecording(stored.value.id);
            pollErrors = 0;

            if (result.state === 'ready') {
                clearPollTimer();
                turns.value = result.turns ?? [];
                redacted.value = result.redacted ?? true;
                transcriptId.value = result.transcriptId;
                status.value = 'ready';

                return;
            }

            if (result.state === 'failed') {
                fail(
                    'transcribe',
                    result.failureReason ?? 'The transcription failed.',
                );

                return;
            }
        } catch {
            pollErrors += 1;
            if (pollErrors >= MAX_POLL_ERRORS) {
                fail(
                    'transcribe',
                    'Lost contact with the server while waiting for the transcript. The audio is safely stored.',
                );

                return;
            }
        }

        pollTimer = setTimeout(() => void poll(), POLL_MS);
    }

    function retry(): void {
        if (failStage.value === 'upload') {
            void upload();
        } else {
            status.value = 'idle';
            elapsed.value = 0;
            progress.value = 0;
            failStage.value = null;
            failReason.value = '';
        }
    }

    function reset(): void {
        clearSummaryTimers();
        summaries.value = {};
        transcriptId.value = null;
        detail.value = 'normal';
        clearElapsedTimer();
        clearPollTimer();
        stopStream();
        clearAudio();
        status.value = 'idle';
        consent.value = false;
        elapsed.value = 0;
        progress.value = 0;
        stored.value = null;
        turns.value = [];
        failStage.value = null;
        failReason.value = '';
    }

    if (initial) {
        stored.value = { id: initial.recordingId, s3Key: initial.s3Key };
        turns.value = initial.turns;
        redacted.value = initial.redacted;
        transcriptId.value = initial.id;
        initial.summaries.forEach((payload) => {
            const view = toSummaryView(payload);
            summaries.value[view.level] = view;
            if (!['complete', 'failed'].includes(view.state)) {
                pollSummary(view.level);
            }
        });
        status.value = 'ready';
    }

    onBeforeUnmount(() => {
        clearSummaryTimers();
        clearElapsedTimer();
        clearPollTimer();
        stopStream();
        clearAudio();
    });

    return reactive({
        status,
        consent,
        elapsed,
        progress,
        failStage,
        failReason,
        audio,
        stored,
        turns,
        redacted,
        transcriptId,
        detail,
        summaries,
        currentSummary,
        summarise,
        waitedSeconds,
        record,
        stop,
        retry,
        reset,
    });
}

import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { fakeSummaries, fakeTranscript } from '@/lib/fakeConsultation';
import type {
    DetailLevel,
    SummarySection,
    TranscriptTurn,
} from '@/lib/fakeConsultation';

export type RecorderStatus =
    | 'idle'
    | 'recording'
    | 'uploading'
    | 'transcribing'
    | 'ready'
    | 'summarising'
    | 'summary'
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

export type FailStage = 'record' | 'upload' | 'summary';

export const statusLabels: Record<RecorderStatus, string> = {
    idle: 'Not started',
    recording: 'Recording',
    uploading: 'Uploading',
    transcribing: 'Transcribing',
    ready: 'Transcript ready',
    summarising: 'Generating summary',
    summary: 'Transcript ready',
    failed: 'Failed',
    blocked: 'Failed',
};

export const stepOrder: RecorderStatus[] = [
    'recording',
    'uploading',
    'transcribing',
    'ready',
    'summarising',
];

/**
 * Simulated recorder state machine. The microphone is real. Upload,
 * transcription and summary are timers until the AWS pipeline is wired in.
 */
export function useRecorderFlow(options: { simulateFailure?: boolean } = {}) {
    const status = ref<RecorderStatus>('idle');
    const consent = ref(false);
    const elapsed = ref(0);
    const progress = ref(0);
    const detail = ref<DetailLevel>('Normal');
    const sections = ref<SummarySection[]>([]);
    const generatedAt = ref('');
    const failStage = ref<FailStage | null>(null);
    const failReason = ref('');

    const transcript = computed<TranscriptTurn[]>(() =>
        ['ready', 'summarising', 'summary'].includes(status.value)
            ? fakeTranscript
            : [],
    );

    const timers: Record<string, ReturnType<typeof setInterval>> = {};
    let stream: MediaStream | null = null;
    let recorder: MediaRecorder | null = null;
    let chunks: Blob[] = [];
    let extension = 'webm';
    const audio = ref<CapturedAudio | null>(null);

    function clearAudio(): void {
        if (audio.value) {
            URL.revokeObjectURL(audio.value.url);
        }
        audio.value = null;
    }

    function clearTimers(): void {
        Object.values(timers).forEach((id) => {
            clearInterval(id);
            clearTimeout(id);
        });
        Object.keys(timers).forEach((key) => delete timers[key]);
    }

    function stopStream(): void {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        recorder = null;
    }

    function fail(stage: FailStage, reason: string): void {
        clearTimers();
        stopStream();
        status.value = 'failed';
        failStage.value = stage;
        failReason.value = reason;
    }

    async function record(): Promise<void> {
        clearTimers();
        elapsed.value = 0;
        progress.value = 0;
        failStage.value = null;
        failReason.value = '';
        sections.value = [];
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
        timers.elapsed = setInterval(() => {
            elapsed.value += 1;
            if (elapsed.value >= MAX_SECONDS) {
                stop();
            }
        }, 1000);
    }

    function stop(): void {
        clearInterval(timers.elapsed);
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
                beginUpload(0);
            }
        };
        active.stop();
        stopStream();
    }

    function beginUpload(from: number): void {
        clearInterval(timers.upload);
        status.value = 'uploading';
        progress.value = from;
        failStage.value = null;
        failReason.value = '';

        timers.upload = setInterval(() => {
            progress.value = Math.min(100, progress.value + 6);
            if (progress.value < 100) {
                return;
            }
            clearInterval(timers.upload);
            if (options.simulateFailure) {
                fail(
                    'upload',
                    'The upload stopped before it finished because the connection dropped. The audio is still on this device.',
                );
            } else {
                beginTranscribe();
            }
        }, 180);
    }

    function beginTranscribe(): void {
        status.value = 'transcribing';
        timers.transcribe = setTimeout(() => (status.value = 'ready'), 3000);
    }

    function summarise(): void {
        status.value = 'summarising';
        timers.summarise = setTimeout(() => {
            sections.value = fakeSummaries[detail.value].map((section) => ({
                ...section,
            }));
            generatedAt.value = new Intl.DateTimeFormat('en-GB', {
                dateStyle: 'long',
                timeStyle: 'short',
            }).format(new Date());
            status.value = 'summary';
        }, 2400);
    }

    function retry(): void {
        if (failStage.value === 'upload') {
            beginUpload(0);
        } else if (failStage.value === 'summary') {
            summarise();
        } else {
            status.value = 'idle';
            elapsed.value = 0;
            progress.value = 0;
            failStage.value = null;
            failReason.value = '';
        }
    }

    function reset(): void {
        clearTimers();
        stopStream();
        clearAudio();
        status.value = 'idle';
        consent.value = false;
        elapsed.value = 0;
        progress.value = 0;
        sections.value = [];
        generatedAt.value = '';
        failStage.value = null;
        failReason.value = '';
    }

    onBeforeUnmount(() => {
        clearTimers();
        stopStream();
        clearAudio();
    });

    return reactive({
        status,
        consent,
        elapsed,
        progress,
        detail,
        sections,
        generatedAt,
        failStage,
        failReason,
        transcript,
        audio,
        record,
        stop,
        summarise,
        retry,
        reset,
    });
}

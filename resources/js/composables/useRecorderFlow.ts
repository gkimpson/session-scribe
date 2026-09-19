import { onBeforeUnmount, reactive, ref } from 'vue';
import { uploadRecording } from '@/lib/recordingUpload';

export type RecorderStatus =
    | 'idle'
    | 'recording'
    | 'uploading'
    | 'uploaded'
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

export type FailStage = 'record' | 'upload';

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
    uploaded: 'Saved to S3',
    failed: 'Failed',
    blocked: 'Failed',
};

export const stepOrder: RecorderStatus[] = [
    'recording',
    'uploading',
    'uploaded',
];

/**
 * Recorder state machine. The microphone capture and the upload to S3 are
 * real. Transcription and summaries come in later as real jobs.
 */
export function useRecorderFlow() {
    const status = ref<RecorderStatus>('idle');
    const consent = ref(false);
    const elapsed = ref(0);
    const progress = ref(0);
    const failStage = ref<FailStage | null>(null);
    const failReason = ref('');
    const audio = ref<CapturedAudio | null>(null);
    const stored = ref<StoredRecording | null>(null);

    let elapsedTimer: ReturnType<typeof setInterval> | null = null;
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
            status.value = 'uploaded';
        } catch {
            fail(
                'upload',
                'The upload did not finish. The audio is still on this device.',
            );
        }
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
        clearElapsedTimer();
        stopStream();
        clearAudio();
        status.value = 'idle';
        consent.value = false;
        elapsed.value = 0;
        progress.value = 0;
        stored.value = null;
        failStage.value = null;
        failReason.value = '';
    }

    onBeforeUnmount(() => {
        clearElapsedTimer();
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
        record,
        stop,
        retry,
        reset,
    });
}

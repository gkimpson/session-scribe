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
            } else {
                fail(
                    'record',
                    'No microphone was found, so the consultation was not recorded.',
                );
            }
            return;
        }

        recorder = new MediaRecorder(stream);
        recorder.start();
        timers.elapsed = setInterval(() => (elapsed.value += 1), 1000);
    }

    function stop(): void {
        clearInterval(timers.elapsed);
        recorder?.stop();
        stopStream();
        beginUpload(0);
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
        record,
        stop,
        summarise,
        retry,
        reset,
    });
}

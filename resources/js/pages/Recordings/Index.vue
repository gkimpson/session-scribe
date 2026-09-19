<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { FwbBadge, FwbButton } from 'flowbite-vue';
import { computed, ref, watch } from 'vue';
import { home } from '@/routes';
import { destroy } from '@/routes/recordings';

interface RecordingRow {
    id: string;
    createdAt: string;
    durationSeconds: number;
    sizeBytes: number;
    state: string;
    failureReason: string | null;
    transcriptId: string | null;
    snippet: string | null;
    redacted: boolean | null;
    summariesComplete: number;
}

const props = defineProps<{
    recordings: RecordingRow[];
    pagination: {
        currentPage: number;
        lastPage: number;
        total: number;
        previousUrl: string | null;
        nextUrl: string | null;
    };
}>();

const stateLabels: Record<string, string> = {
    pending_upload: 'Uploading',
    uploaded: 'Queued',
    transcription_queued: 'Queued',
    transcribing: 'Transcribing',
    ready: 'Transcript ready',
    failed: 'Failed',
};

const waitingStates = ['uploaded', 'transcription_queued', 'transcribing'];
const STALE_UPLOAD_MS = 15 * 60 * 1000;

/**
 * An upload that has sat unfinished for a while was abandoned, so it isn't
 * worth waiting for or refreshing the list over.
 */
function isStaleUpload(recording: RecordingRow): boolean {
    return (
        recording.state === 'pending_upload' &&
        Date.now() - new Date(recording.createdAt).getTime() > STALE_UPLOAD_MS
    );
}

function isActive(recording: RecordingRow): boolean {
    return (
        waitingStates.includes(recording.state) ||
        (recording.state === 'pending_upload' && !isStaleUpload(recording))
    );
}

function labelFor(recording: RecordingRow): string {
    return isStaleUpload(recording)
        ? 'Upload not finished'
        : (stateLabels[recording.state] ?? recording.state);
}

// Removing is refused while a recording is queued or transcribing.
function canRemove(recording: RecordingRow): boolean {
    return !waitingStates.includes(recording.state);
}

const confirmingId = ref<string | null>(null);
const removing = ref(false);
const removeError = ref<string | null>(null);

function askToRemove(recording: RecordingRow): void {
    confirmingId.value = recording.id;
    removeError.value = null;
}

function cancelRemove(): void {
    confirmingId.value = null;
    removeError.value = null;
}

function remove(recording: RecordingRow): void {
    router.delete(destroy(recording.id).url, {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onSuccess: () => (confirmingId.value = null),
        onError: (errors) =>
            (removeError.value = errors.delete ?? 'It could not be removed.'),
        onFinish: () => (removing.value = false),
    });
}

const hasInProgress = computed(() => props.recordings.some(isActive));

// Refresh the list while something is still being transcribed, then stop.
const { start, stop } = usePoll(
    5000,
    { only: ['recordings', 'pagination'] },
    { autoStart: false },
);

watch(hasInProgress, (active) => (active ? start() : stop()), {
    immediate: true,
});

function formatWhen(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

function formatDuration(seconds: number): string {
    const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');

    return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function summaryText(count: number): string {
    return count === 1 ? '1 summary' : `${count} summaries`;
}

const kicker =
    'text-[11px] font-bold tracking-[0.14em] text-ink-muted uppercase';
</script>

<template>
    <Head title="Recordings" />

    <div class="bg-paper text-ink flex min-h-screen flex-col md:flex-row">
        <aside
            class="border-ink flex flex-col gap-6 border-b-2 p-6 md:w-75 md:flex-none md:border-r-2 md:border-b-0"
        >
            <div>
                <div :class="kicker">Session notes</div>
                <h1 class="mt-1.5 text-2xl leading-tight font-extrabold">
                    Recordings
                </h1>
            </div>

            <div class="border-ink flex flex-col gap-2.5 border-t-2 pt-4">
                <Link :href="home().url">
                    <FwbButton size="sm" class="w-full">
                        New recording
                    </FwbButton>
                </Link>
            </div>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col">
            <div
                class="border-ink flex flex-wrap items-baseline justify-between gap-4 border-b-2 px-8 py-5"
            >
                <div :class="kicker">All recordings</div>
                <span class="text-ink-muted text-[13px] font-semibold"
                    >{{ pagination.total }} in total</span
                >
            </div>

            <div class="flex max-w-225 flex-col gap-6 p-8">
                <div
                    v-if="recordings.length === 0"
                    class="border-ink bg-panel flex flex-col items-start gap-4 border-2 p-6"
                >
                    <h2 class="text-2xl font-extrabold">No recordings yet</h2>
                    <p class="text-ink-soft max-w-[62ch]">
                        Recordings you make will appear here, with their
                        transcript and summaries.
                    </p>
                    <Link :href="home().url">
                        <FwbButton size="lg">Make a recording</FwbButton>
                    </Link>
                </div>

                <ul v-else class="border-ink flex flex-col border-2">
                    <li
                        v-for="recording in recordings"
                        :key="recording.id"
                        class="border-line border-b last:border-b-0"
                    >
                        <div class="flex items-start">
                            <component
                                :is="recording.transcriptId ? Link : 'div'"
                                :href="
                                    recording.transcriptId
                                        ? home(recording.transcriptId).url
                                        : undefined
                                "
                                class="flex min-w-0 flex-1 flex-col gap-2 px-5 py-4"
                                :class="
                                    recording.transcriptId
                                        ? 'hover:bg-panel focus-visible:bg-panel'
                                        : ''
                                "
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <span class="text-[15px] font-bold">{{
                                        formatWhen(recording.createdAt)
                                    }}</span>
                                    <FwbBadge
                                        :type="
                                            recording.state === 'failed' ||
                                            isStaleUpload(recording)
                                                ? 'red'
                                                : 'default'
                                        "
                                        class="text-xs font-extrabold tracking-widest uppercase"
                                    >
                                        {{ labelFor(recording) }}
                                    </FwbBadge>
                                </div>

                                <p
                                    v-if="recording.snippet"
                                    class="text-ink-soft line-clamp-2 max-w-[68ch] text-[15px] leading-relaxed"
                                >
                                    {{ recording.snippet }}
                                </p>
                                <p
                                    v-else-if="recording.failureReason"
                                    class="text-accent-strong max-w-[68ch] text-[15px]"
                                >
                                    {{ recording.failureReason }}
                                </p>
                                <p
                                    v-else-if="isActive(recording)"
                                    class="text-ink-muted text-[15px]"
                                >
                                    The transcript will appear here when it is
                                    ready.
                                </p>

                                <div
                                    class="text-ink-muted flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-semibold"
                                >
                                    <span>{{
                                        formatDuration(
                                            recording.durationSeconds,
                                        )
                                    }}</span>
                                    <span>{{
                                        formatSize(recording.sizeBytes)
                                    }}</span>
                                    <span
                                        v-if="recording.summariesComplete > 0"
                                        >{{
                                            summaryText(
                                                recording.summariesComplete,
                                            )
                                        }}</span
                                    >
                                    <span v-if="recording.redacted === false"
                                        >Not redacted</span
                                    >
                                </div>
                            </component>
                            <div class="flex-none px-4 py-4">
                                <FwbButton
                                    v-if="canRemove(recording)"
                                    color="light"
                                    size="xs"
                                    :disabled="removing"
                                    @click="askToRemove(recording)"
                                >
                                    Remove
                                </FwbButton>
                                <span
                                    v-else
                                    class="text-ink-muted text-xs font-semibold"
                                    >Processing</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="confirmingId === recording.id"
                            class="border-accent bg-accent-wash flex flex-col gap-3 border-t-2 px-5 py-4"
                            role="alertdialog"
                            aria-label="Confirm removal"
                        >
                            <p class="max-w-[62ch] text-[15px] font-semibold">
                                Remove this recording permanently?
                            </p>
                            <p class="max-w-[62ch] text-sm">
                                This deletes the audio, the transcript and any
                                summaries. It cannot be undone.
                            </p>
                            <p
                                v-if="removeError"
                                class="text-accent-strong text-sm font-semibold"
                            >
                                {{ removeError }}
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <FwbButton
                                    :disabled="removing"
                                    @click="remove(recording)"
                                >
                                    {{
                                        removing
                                            ? 'Removing…'
                                            : 'Remove permanently'
                                    }}
                                </FwbButton>
                                <FwbButton
                                    color="light"
                                    :disabled="removing"
                                    @click="cancelRemove()"
                                >
                                    Cancel
                                </FwbButton>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav
                    v-if="pagination.lastPage > 1"
                    class="flex items-center justify-between gap-4"
                    aria-label="Pagination"
                >
                    <Link
                        v-if="pagination.previousUrl"
                        :href="pagination.previousUrl"
                    >
                        <FwbButton color="light">Newer</FwbButton>
                    </Link>
                    <span v-else></span>
                    <span class="text-ink-muted text-[13px] font-semibold"
                        >Page {{ pagination.currentPage }} of
                        {{ pagination.lastPage }}</span
                    >
                    <Link v-if="pagination.nextUrl" :href="pagination.nextUrl">
                        <FwbButton color="light">Older</FwbButton>
                    </Link>
                    <span v-else></span>
                </nav>
            </div>
        </main>
    </div>
</template>

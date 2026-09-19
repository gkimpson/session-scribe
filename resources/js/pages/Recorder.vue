<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FwbAlert,
    FwbBadge,
    FwbButton,
    FwbCard,
    FwbCheckbox,
    FwbProgress,
} from 'flowbite-vue';
import { computed } from 'vue';
import {
    stepOrder,
    statusLabels,
    useRecorderFlow,
} from '@/composables/useRecorderFlow';

const flow = useRecorderFlow();

const isFailure = computed(
    () => flow.status === 'failed' || flow.status === 'blocked',
);

const elapsedText = computed(() => formatDuration(flow.elapsed));

const steps = computed(() => {
    const activeIndex = stepOrder.indexOf(flow.status);
    const failIndex =
        flow.status === 'blocked'
            ? 0
            : flow.status === 'failed'
              ? flow.failStage === 'upload'
                  ? 1
                  : 0
              : -1;

    return stepOrder.map((key, index) => {
        let mark = '–';
        if (index === failIndex) {
            mark = '✕';
        } else if (flow.status === 'uploaded' || index < activeIndex) {
            mark = '✓';
        } else if (index === activeIndex) {
            mark = '●';
        }

        return {
            key,
            mark,
            label: statusLabels[key],
            danger: index === failIndex,
            strong: index === failIndex || index === activeIndex,
        };
    });
});

const failTitle = computed(() =>
    flow.failStage === 'upload'
        ? 'The upload did not finish'
        : 'The recording was not saved',
);

const failNext = computed(() =>
    flow.failStage === 'upload'
        ? 'Choose Retry to send it again. Do not close this page until it has uploaded.'
        : 'Choose Retry to start a new recording.',
);

const announce = computed(() => {
    if (flow.status === 'uploading') {
        return `Uploading, ${flow.progress} per cent`;
    }
    if (flow.status === 'failed') {
        return `Failed. ${flow.failReason}`;
    }
    if (flow.status === 'blocked') {
        return 'Failed. The microphone is blocked.';
    }

    return statusLabels[flow.status];
});

function formatDuration(seconds: number): string {
    const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');

    return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const kicker =
    'text-[11px] font-bold tracking-[0.14em] text-ink-muted uppercase';
const card = 'min-w-0 border-2 border-ink shadow-none';
</script>

<template>
    <Head title="Recorder" />

    <div class="bg-paper text-ink flex min-h-screen flex-col md:flex-row">
        <aside
            class="border-ink flex flex-col gap-6 border-b-2 p-6 md:w-75 md:flex-none md:border-r-2 md:border-b-0"
        >
            <div>
                <div :class="kicker">Session notes</div>
                <h1 class="mt-1.5 text-2xl leading-tight font-extrabold">
                    Recorder
                </h1>
            </div>

            <div class="border-ink border-t-2 pt-4">
                <div :class="[kicker, 'mb-2']">Progress</div>
                <ol aria-label="Progress">
                    <li
                        v-for="step in steps"
                        :key="step.key"
                        class="border-line flex items-start gap-2.5 border-b py-2.5 text-sm"
                        :class="
                            step.danger
                                ? 'text-accent-strong font-bold'
                                : step.strong
                                  ? 'text-ink font-bold'
                                  : 'text-ink-muted font-medium'
                        "
                    >
                        <span aria-hidden="true" class="w-3.5 flex-none">{{
                            step.mark
                        }}</span>
                        <span>{{ step.label }}</span>
                    </li>
                </ol>
            </div>

            <div class="border-ink flex flex-col gap-2.5 border-t-2 pt-4">
                <FwbButton color="light" size="sm" @click="flow.reset()">
                    New recording
                </FwbButton>
            </div>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col">
            <div
                class="border-ink flex flex-wrap items-baseline justify-between gap-4 border-b-2 px-8 py-5"
            >
                <div :class="kicker">Session recording</div>
                <FwbBadge
                    :type="isFailure ? 'red' : 'default'"
                    class="text-[13px] font-extrabold tracking-widest uppercase"
                >
                    {{ statusLabels[flow.status] }}
                </FwbBadge>
            </div>

            <div class="sr-only" aria-live="polite">{{ announce }}</div>

            <div class="flex max-w-225 flex-col gap-8 p-8">
                <section
                    v-if="flow.status === 'idle'"
                    class="flex flex-col gap-5"
                >
                    <div>
                        <h2
                            class="mb-2.5 text-3xl leading-tight font-extrabold"
                        >
                            Before you record
                        </h2>
                        <p class="max-w-[62ch] text-base leading-relaxed">
                            This records the session and stores the audio in a
                            private S3 bucket. Tell everyone taking part that it
                            is being recorded and that they can ask you to stop
                            at any time.
                        </p>
                    </div>
                    <label
                        class="border-ink bg-panel flex max-w-[62ch] cursor-pointer items-start gap-3 border-2 p-4"
                    >
                        <FwbCheckbox
                            v-model="flow.consent"
                            label="I have told everyone taking part about the recording and they agree to it."
                        />
                    </label>
                    <div>
                        <FwbButton
                            size="lg"
                            :disabled="!flow.consent"
                            @click="flow.record()"
                        >
                            Record
                        </FwbButton>
                        <p class="text-ink-muted mt-2.5 text-[13px]">
                            {{
                                flow.consent
                                    ? 'Your browser will ask for permission to use the microphone.'
                                    : 'Tick the box above to enable Record.'
                            }}
                        </p>
                    </div>
                </section>

                <FwbCard
                    v-if="flow.status === 'recording'"
                    :class="`${card} bg-panel`"
                >
                    <div
                        class="border-ink flex items-center gap-3.5 border-b-2 px-6 py-5"
                    >
                        <span
                            aria-hidden="true"
                            class="animate-recorder-pulse bg-accent size-3.5 flex-none"
                        ></span>
                        <span
                            class="text-lg font-extrabold tracking-wide uppercase"
                            >Recording</span
                        >
                        <span
                            class="ml-auto text-3xl font-bold tracking-wide tabular-nums"
                            >{{ elapsedText }}</span
                        >
                    </div>
                    <div class="flex flex-col gap-4 px-6 py-5">
                        <p class="text-ink-soft max-w-[62ch] text-[15px]">
                            The microphone is live. Stop when the session ends.
                            The audio uploads straight away.
                        </p>
                        <div>
                            <FwbButton
                                color="dark"
                                size="lg"
                                @click="flow.stop()"
                            >
                                Stop
                            </FwbButton>
                        </div>
                    </div>
                </FwbCard>

                <FwbCard
                    v-if="flow.status === 'uploading'"
                    :class="`${card} flex flex-col gap-3.5 px-6 py-5`"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-lg font-extrabold">Uploading</span>
                        <span class="font-bold tabular-nums"
                            >{{ flow.progress }}%</span
                        >
                    </div>
                    <FwbProgress :progress="flow.progress" size="lg" />
                    <p class="text-ink-muted max-w-[62ch] text-sm">
                        Keep this page open until the upload finishes.
                    </p>
                </FwbCard>

                <FwbCard
                    v-if="flow.status === 'uploaded' && flow.stored"
                    :class="`${card} flex flex-col gap-3 px-6 py-5`"
                >
                    <h2 class="text-xl font-extrabold">Saved to S3</h2>
                    <dl
                        class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-1.5 text-[15px]"
                    >
                        <dt :class="kicker">Object key</dt>
                        <dd class="font-mono break-all">
                            {{ flow.stored.s3Key }}
                        </dd>
                        <dt :class="kicker">Recording</dt>
                        <dd class="font-mono break-all">
                            {{ flow.stored.id }}
                        </dd>
                    </dl>
                </FwbCard>

                <FwbAlert
                    v-if="flow.status === 'blocked'"
                    type="danger"
                    class="border-accent border-2"
                >
                    <h2 class="text-accent-strong mb-2 text-xl font-extrabold">
                        The microphone is blocked
                    </h2>
                    <p class="mb-3 max-w-[62ch]">
                        Your browser is not letting this page use the
                        microphone, so nothing was recorded.
                    </p>
                    <ol class="mb-4 max-w-[62ch] list-decimal pl-5 leading-7">
                        <li>
                            Select the padlock or microphone icon in the address
                            bar.
                        </li>
                        <li>Set the microphone permission to Allow.</li>
                        <li>Reload this page, then choose Retry.</li>
                    </ol>
                    <FwbButton @click="flow.retry()">Retry</FwbButton>
                </FwbAlert>

                <FwbAlert
                    v-if="flow.status === 'failed'"
                    type="danger"
                    class="border-accent border-2"
                >
                    <h2 class="text-accent-strong mb-2 text-xl font-extrabold">
                        {{ failTitle }}
                    </h2>
                    <p class="mb-2 max-w-[62ch]">{{ flow.failReason }}</p>
                    <p class="mb-4 max-w-[62ch]">{{ failNext }}</p>
                    <FwbButton @click="flow.retry()">Retry</FwbButton>
                </FwbAlert>

                <section
                    v-if="flow.audio && flow.status !== 'recording'"
                    class="flex flex-col gap-3"
                >
                    <div
                        class="border-ink flex flex-wrap items-baseline justify-between gap-4 border-b-2 pb-3"
                    >
                        <h2 class="text-2xl font-extrabold">Audio</h2>
                        <span class="text-ink-muted text-[13px] font-semibold"
                            >{{ flow.audio.extension.toUpperCase() }} ·
                            {{ formatSize(flow.audio.sizeBytes) }} ·
                            {{
                                formatDuration(flow.audio.durationSeconds)
                            }}</span
                        >
                    </div>
                    <audio
                        controls
                        :src="flow.audio.url"
                        class="w-full"
                    ></audio>
                </section>
            </div>
        </main>
    </div>
</template>

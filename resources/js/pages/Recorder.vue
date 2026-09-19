<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FwbAlert,
    FwbBadge,
    FwbButton,
    FwbButtonGroup,
    FwbCheckbox,
    FwbProgress,
    FwbTextarea,
} from 'flowbite-vue';
import { computed } from 'vue';
import {
    stepOrder,
    statusLabels,
    useRecorderFlow,
} from '@/composables/useRecorderFlow';
import type { RecorderStatus } from '@/composables/useRecorderFlow';
import { detailLevels, levelHints } from '@/lib/fakeConsultation';

const flow = useRecorderFlow();

const isFailure = computed(
    () => flow.status === 'failed' || flow.status === 'blocked',
);
const hasTranscript = computed(() => flow.transcript.length > 0);
const showSummarise = computed(
    () => flow.status === 'ready' || flow.status === 'summary',
);
const elapsedText = computed(() => {
    const minutes = String(Math.floor(flow.elapsed / 60)).padStart(2, '0');
    const seconds = String(flow.elapsed % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
});

const steps = computed(() => {
    const current: RecorderStatus =
        flow.status === 'summary' ? 'summarising' : flow.status;
    const activeIndex = stepOrder.indexOf(current);
    const failIndex =
        flow.status === 'blocked'
            ? 0
            : flow.status === 'failed'
              ? { record: 0, upload: 1, summary: 4 }[flow.failStage ?? 'record']
              : -1;

    return stepOrder.map((key, index) => {
        let mark = '–';
        if (index === failIndex) {
            mark = '✕';
        } else if (flow.status === 'summary' || index < activeIndex) {
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

const failTitle = computed(
    () =>
        ({
            upload: 'The upload did not finish',
            summary: 'The summary was not generated',
            record: 'The recording was not saved',
        })[flow.failStage ?? 'record'],
);

const failNext = computed(
    () =>
        ({
            upload: 'Choose Retry to send it again. Do not close this page until it has uploaded.',
            summary:
                'The transcript is safe. Choose Retry to generate the summary again.',
            record: 'Choose Retry to start a new recording.',
        })[flow.failStage ?? 'record'],
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
    if (flow.status === 'summary') {
        return 'Draft summary ready';
    }

    return statusLabels[flow.status];
});

const kicker =
    'text-[11px] font-bold tracking-[0.14em] text-ink-muted uppercase';
const panel = 'border-2 border-ink';
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
                            This records the session to produce a draft summary.
                            Tell everyone taking part that it is being recorded
                            and that they can ask you to stop at any time. The
                            audio and transcript are stored and can be deleted
                            on request.
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

                <section
                    v-if="flow.status === 'recording'"
                    :class="[panel, 'bg-panel']"
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
                </section>

                <section
                    v-if="flow.status === 'uploading'"
                    :class="[panel, 'flex flex-col gap-3.5 px-6 py-5']"
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
                </section>

                <section
                    v-if="flow.status === 'transcribing'"
                    :class="[panel, 'flex flex-col gap-4 px-6 py-5']"
                >
                    <span class="text-lg font-extrabold">Transcribing</span>
                    <div class="flex flex-col gap-2.5" aria-hidden="true">
                        <div
                            v-for="width in ['82%', '94%', '64%', '88%', '48%']"
                            :key="width"
                            class="bg-line h-3.5 animate-pulse"
                            :style="{ width }"
                        ></div>
                    </div>
                    <p class="text-ink-muted text-sm">
                        This usually takes under a minute. You can leave the
                        page and come back.
                    </p>
                </section>

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

                <section v-if="hasTranscript" class="flex flex-col">
                    <div
                        class="border-ink flex flex-wrap items-baseline justify-between gap-4 border-b-2 pb-3"
                    >
                        <h2 class="text-2xl font-extrabold">Transcript</h2>
                        <span class="text-ink-muted text-[13px] font-semibold"
                            >Automatic transcript ·
                            {{ flow.transcript.length }} turns</span
                        >
                    </div>
                    <div
                        class="border-accent bg-accent-wash flex items-start gap-3 border-2 border-t-0 px-4.5 py-3.5"
                    >
                        <span
                            aria-hidden="true"
                            class="text-accent-strong font-extrabold"
                            >!</span
                        >
                        <p class="max-w-[62ch] text-sm">
                            Check names, terms and omissions against the
                            recording.
                        </p>
                    </div>
                    <div
                        class="border-ink bg-well flex max-h-85 flex-col gap-3.5 overflow-auto border-2 border-t-0 px-6 py-5"
                    >
                        <p
                            v-for="(turn, index) in flow.transcript"
                            :key="index"
                            class="grid grid-cols-[96px_1fr] items-baseline gap-3 text-[15px] leading-relaxed"
                        >
                            <span
                                class="text-ink-muted text-xs font-bold tracking-wider uppercase"
                                >{{ turn.who }}</span
                            >
                            <span>{{ turn.text }}</span>
                        </p>
                    </div>
                </section>

                <section
                    v-if="showSummarise"
                    class="border-ink flex flex-col gap-4 border-t-2 pt-6"
                >
                    <div>
                        <div :class="[kicker, 'mb-2.5']">Detail level</div>
                        <FwbButtonGroup
                            role="radiogroup"
                            aria-label="Detail level"
                        >
                            <FwbButton
                                v-for="level in detailLevels"
                                :key="level"
                                role="radio"
                                :aria-checked="flow.detail === level"
                                :color="
                                    flow.detail === level ? 'dark' : 'light'
                                "
                                @click="flow.detail = level"
                            >
                                {{ level }}
                            </FwbButton>
                        </FwbButtonGroup>
                        <p
                            class="text-ink-muted mt-2.5 max-w-[62ch] text-[13px]"
                        >
                            {{ levelHints[flow.detail] }}
                        </p>
                    </div>
                    <div>
                        <FwbButton size="lg" @click="flow.summarise()">
                            Summary
                        </FwbButton>
                    </div>
                </section>

                <section
                    v-if="flow.status === 'summarising'"
                    class="border-ink flex flex-col gap-4 border-t-2 pt-6"
                >
                    <span class="text-lg font-extrabold"
                        >Generating summary</span
                    >
                    <div
                        :class="[panel, 'flex flex-col gap-4 px-6 py-5']"
                        aria-hidden="true"
                    >
                        <div class="bg-line h-3 w-1/3 animate-pulse"></div>
                        <div class="bg-line h-3 w-11/12 animate-pulse"></div>
                        <div class="bg-line h-3 w-3/4 animate-pulse"></div>
                        <div class="bg-line h-3 w-1/4 animate-pulse"></div>
                        <div class="bg-line h-3 w-5/6 animate-pulse"></div>
                    </div>
                </section>

                <section
                    v-if="flow.status === 'summary' && flow.sections.length"
                    class="border-ink flex flex-col border-t-2 pt-6"
                >
                    <div
                        class="border-ink bg-panel flex flex-wrap items-baseline justify-between gap-4 border-2 px-5 py-4"
                    >
                        <div class="flex flex-wrap items-baseline gap-3">
                            <span
                                class="bg-accent px-2 py-1 text-xs font-extrabold tracking-widest text-white uppercase"
                                >Draft</span
                            >
                            <h2 class="text-[22px] font-extrabold">
                                Session recap
                            </h2>
                        </div>
                        <span class="text-ink-soft text-[13px] font-semibold"
                            >{{ flow.detail }} detail · generated
                            {{ flow.generatedAt }}</span
                        >
                    </div>
                    <div
                        class="border-ink bg-well border-2 border-t-0 px-5 pt-2 pb-5"
                    >
                        <div
                            v-for="(section, index) in flow.sections"
                            :key="section.heading"
                            class="border-line border-b py-5"
                        >
                            <FwbTextarea
                                v-model="flow.sections[index].body"
                                :label="section.heading"
                                :rows="4"
                            />
                        </div>
                        <p class="text-ink-muted mt-4 max-w-[62ch] text-[13px]">
                            Edits are kept on this page. Read the draft against
                            the transcript before you use it.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>

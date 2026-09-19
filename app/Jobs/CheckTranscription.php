<?php

namespace App\Jobs;

use App\Actions\StoreTranscript;
use App\Contracts\TranscriptionProvider;
use App\Enums\RecordingState;
use App\Enums\TranscriptionJobStatus;
use App\Models\Recording;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckTranscription implements ShouldQueue
{
    use Queueable;

    private const GIVE_UP_AFTER_MINUTES = 60;

    public function __construct(public string $recordingId) {}

    public int $tries = 3;

    public int $backoff = 5;

    public function handle(TranscriptionProvider $transcription, StoreTranscript $storeTranscript): void
    {
        $recording = Recording::findOrFail($this->recordingId);

        if ($recording->state !== RecordingState::Transcribing) {
            return;
        }

        $result = $transcription->status($recording->provider_job_id);

        match ($result->status) {
            TranscriptionJobStatus::Completed => $this->complete($recording, $result->transcriptKey, $result->redacted, $storeTranscript),
            TranscriptionJobStatus::Failed => $recording->update([
                'state' => RecordingState::Failed,
                'failure_reason' => $result->failureReason ?? 'The transcription failed.',
            ]),
            default => $this->checkAgain($recording),
        };
    }

    private function complete(Recording $recording, ?string $transcriptKey, bool $redacted, StoreTranscript $storeTranscript): void
    {
        if ($transcriptKey === null) {
            $recording->update([
                'state' => RecordingState::Failed,
                'failure_reason' => 'The transcript was not found.',
            ]);

            return;
        }

        $storeTranscript->handle($recording, $transcriptKey, $redacted);
        $recording->update(['state' => RecordingState::Ready]);
    }

    private function checkAgain(Recording $recording): void
    {
        if ($recording->updated_at->diffInMinutes(now(), absolute: true) >= self::GIVE_UP_AFTER_MINUTES) {
            $recording->update([
                'state' => RecordingState::Failed,
                'failure_reason' => 'The transcription took too long and was stopped.',
            ]);

            return;
        }

        self::dispatch($recording->id)->delay(now()->addSeconds(5));
    }

    public function failed(?\Throwable $exception): void
    {
        Recording::whereKey($this->recordingId)->update([
            'state' => RecordingState::Failed,
            'failure_reason' => 'The transcript could not be saved.',
        ]);
    }
}

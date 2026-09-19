<?php

namespace App\Jobs;

use App\Contracts\TranscriptionProvider;
use App\Enums\RecordingState;
use App\Models\Recording;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartTranscription implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $recordingId) {}

    public function handle(TranscriptionProvider $transcription): void
    {
        $recording = Recording::findOrFail($this->recordingId);

        if ($recording->state !== RecordingState::Uploaded || $recording->provider_job_id !== null) {
            return;
        }

        $recording->update([
            'provider_job_id' => $transcription->start($recording),
            'state' => RecordingState::Transcribing,
        ]);

        CheckTranscription::dispatch($recording->id)->delay(now()->addSeconds(5));
    }

    public function failed(?\Throwable $exception): void
    {
        Recording::whereKey($this->recordingId)->update([
            'state' => RecordingState::Failed,
            'failure_reason' => 'The transcription could not be started.',
        ]);
    }
}

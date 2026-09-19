<?php

namespace App\Actions;

use App\Models\Recording;
use App\Models\Transcript;
use App\Support\TranscribeTranscriptFormatter;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the finished Transcribe JSON from S3 and saves the speaker turns and
 * a speaker-labelled text version (the input for summaries) to the database.
 * The JSON stays in S3 as the raw source.
 */
class StoreTranscript
{
    public function handle(Recording $recording, string $s3Key, bool $redacted): Transcript
    {
        $json = json_decode(Storage::disk(config('recordings.disk'))->get($s3Key) ?? '', true);

        if (! is_array($json)) {
            throw new \RuntimeException("Could not read the transcript at {$s3Key}.");
        }

        $turns = TranscribeTranscriptFormatter::turns($json);

        return $recording->transcripts()->create([
            's3_key' => $s3Key,
            'redacted' => $redacted,
            'turns' => $turns,
            'text' => collect($turns)
                ->map(fn (array $turn) => "{$turn['speaker']}: {$turn['text']}")
                ->implode("\n"),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Exceptions\StorageFailed;
use App\Http\Requests\StoreRecordingRequest;
use App\Models\Recording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class RecordingController extends Controller
{
    public function store(StoreRecordingRequest $request, RecordingStorage $storage): JsonResponse
    {
        $data = $request->validated();
        $id = (string) Str::uuid();
        $extension = config('recordings.mime_types')[$data['mime_type']];

        $recording = Recording::create([
            'id' => $id,
            'user_id' => $request->user()?->id,
            's3_key' => config('recordings.key_prefix')."/{$id}.{$extension}",
            'mime_type' => $data['mime_type'],
            'extension' => $extension,
            'size_bytes' => $data['size_bytes'],
            'duration_seconds' => $data['duration_seconds'],
            'state' => RecordingState::PendingUpload,
            'consent_confirmed_at' => now(),
        ]);

        $upload = $storage->presignedUpload($recording);

        return response()->json([
            'id' => $recording->id,
            's3_key' => $recording->s3_key,
            'upload' => [
                'url' => $upload->url,
                'headers' => $upload->headers,
            ],
        ], 201);
    }

    public function show(Recording $recording): JsonResponse
    {
        $transcript = $recording->state === RecordingState::Ready
            ? $recording->transcripts()->latest('version')->first()
            : null;

        return response()->json([
            'id' => $recording->id,
            'state' => $recording->state->value,
            'failure_reason' => $recording->failure_reason,
            'turns' => $transcript?->turns,
            'redacted' => $transcript?->redacted,
            'transcript_id' => $transcript?->uuid,
        ]);
    }

    /**
     * Permanently removes a recording, its transcripts, its summaries and
     * every stored file. Storage is cleared first, so a failure there leaves
     * the records in place for another try instead of orphaning files.
     */
    public function destroy(Recording $recording, RecordingStorage $storage): RedirectResponse
    {
        $processing = [
            RecordingState::Uploaded,
            RecordingState::TranscriptionQueued,
            RecordingState::Transcribing,
        ];

        if (in_array($recording->state, $processing, true)) {
            return back()->withErrors(['delete' => 'This recording is still being processed. Try again when it has finished.']);
        }

        try {
            $storage->purge($recording);
        } catch (StorageFailed) {
            return back()->withErrors(['delete' => 'The stored files could not be removed, so nothing was deleted. Try again.']);
        }

        $recording->delete();

        return to_route('recordings.index');
    }
}

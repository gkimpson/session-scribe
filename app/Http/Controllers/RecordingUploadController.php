<?php

namespace App\Http\Controllers;

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Jobs\StartTranscription;
use App\Models\Recording;
use Illuminate\Http\JsonResponse;

class RecordingUploadController extends Controller
{
    public function __invoke(Recording $recording, RecordingStorage $storage): JsonResponse
    {
        if ($recording->state !== RecordingState::PendingUpload) {
            return response()->json(['state' => $recording->state->value]);
        }

        $storedSize = $storage->storedSize($recording);

        if ($storedSize === null) {
            return response()->json(['message' => 'The uploaded audio was not found.'], 422);
        }

        if ($storedSize !== $recording->size_bytes) {
            $storage->delete($recording);
            $recording->update([
                'state' => RecordingState::Failed,
                'failure_reason' => 'The uploaded audio did not match the size that was declared.',
            ]);

            return response()->json(['message' => 'The uploaded audio is the wrong size and was removed.'], 422);
        }

        $recording->update(['state' => RecordingState::Uploaded]);

        StartTranscription::dispatch($recording->id);

        return response()->json(['state' => $recording->state->value]);
    }
}

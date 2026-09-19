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

        if ($storage->storedSize($recording) !== $recording->size_bytes) {
            return response()->json(['message' => 'The uploaded audio was not found or is the wrong size.'], 422);
        }

        $recording->update(['state' => RecordingState::Uploaded]);

        StartTranscription::dispatch($recording->id);

        return response()->json(['state' => $recording->state->value]);
    }
}

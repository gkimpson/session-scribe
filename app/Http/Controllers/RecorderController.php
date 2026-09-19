<?php

namespace App\Http\Controllers;

use App\Models\Transcript;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecorderController extends Controller
{
    public function __invoke(Request $request, ?string $transcriptId = null): Response
    {
        $requestedId = $transcriptId ?? $request->query('transcript_id');
        $transcript = ctype_digit((string) $requestedId)
            ? Transcript::find((int) $requestedId)
            : null;

        return Inertia::render('Recorder', [
            'transcript' => $transcript ? [
                'id' => $transcript->id,
                'recordingId' => $transcript->recording_id,
                's3Key' => $transcript->s3_key,
                'turns' => $transcript->turns ?? [],
                'redacted' => $transcript->redacted,
                'summaries' => $transcript->summaries()
                    ->where('model_id', config('recordings.summary.model_id'))
                    ->where('prompt_version', config('recordings.summary.prompt_version'))
                    ->get()
                    ->map(fn ($summary) => SummaryController::payload($summary))
                    ->all(),
            ] : null,
            'missingTranscriptId' => $requestedId !== null && $transcript === null
                ? (string) $requestedId
                : null,
        ]);
    }
}

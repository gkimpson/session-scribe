<?php

namespace App\Http\Controllers;

use App\Enums\SummaryState;
use App\Models\Recording;
use App\Models\Transcript;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecordingIndexController extends Controller
{
    private const PER_PAGE = 15;

    private const SNIPPET_CHARACTERS = 140;

    public function __invoke(): Response
    {
        $page = Recording::latest()->paginate(self::PER_PAGE)->withQueryString();

        // Only the newest transcript per recording, and only the start of its
        // text, so a page of long transcripts stays cheap to load.
        $transcripts = Transcript::whereIn('recording_id', $page->pluck('id'))
            ->select(['id', 'uuid', 'recording_id', 'redacted', DB::raw('substr(text, 1, '.self::SNIPPET_CHARACTERS.') as snippet')])
            ->withCount(['summaries as summaries_complete_count' => fn ($query) => $query->where('state', SummaryState::Complete)])
            ->latest('id')
            ->get()
            ->unique('recording_id')
            ->keyBy('recording_id');

        return Inertia::render('Recordings/Index', [
            'recordings' => $page->map(fn (Recording $recording) => [
                'id' => $recording->id,
                'createdAt' => $recording->created_at->toIso8601String(),
                'durationSeconds' => $recording->duration_seconds,
                'sizeBytes' => $recording->size_bytes,
                'state' => $recording->state->value,
                'failureReason' => $recording->failure_reason,
                'transcriptId' => $transcripts[$recording->id]->uuid ?? null,
                'snippet' => $transcripts[$recording->id]->snippet ?? null,
                'redacted' => $transcripts[$recording->id]->redacted ?? null,
                'summariesComplete' => $transcripts[$recording->id]->summaries_complete_count ?? 0,
            ])->all(),
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
        ]);
    }
}

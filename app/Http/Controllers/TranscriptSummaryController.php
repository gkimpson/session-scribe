<?php

namespace App\Http\Controllers;

use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use App\Jobs\GenerateSummary;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranscriptSummaryController extends Controller
{
    /**
     * Ask for a summary of the whole transcript at one level. Asking again for
     * a summary that already exists returns it instead of paying for another.
     */
    public function store(Request $request, Transcript $transcript): JsonResponse
    {
        $level = SummaryLevel::from($request->validate([
            'level' => ['required', Rule::enum(SummaryLevel::class)],
        ])['level']);

        $summary = Summary::firstOrCreate([
            'transcript_id' => $transcript->id,
            'level' => $level,
            'model_id' => config('recordings.summary.model_id'),
            'prompt_version' => config('recordings.summary.prompt_version'),
        ], ['state' => SummaryState::Queued]);

        if ($summary->state === SummaryState::Failed) {
            $summary->update(['state' => SummaryState::Queued, 'failure_reason' => null]);
        }

        if ($summary->wasRecentlyCreated || $summary->state === SummaryState::Queued) {
            GenerateSummary::dispatch($summary->id);
        }

        return response()->json(SummaryController::payload($summary), 202);
    }
}

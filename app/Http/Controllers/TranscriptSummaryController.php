<?php

namespace App\Http\Controllers;

use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use App\Jobs\GenerateSummary;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $attributes = [
            'transcript_id' => $transcript->id,
            'level' => $level,
            'model_id' => config('recordings.summary.model_id'),
            'prompt_version' => config('recordings.summary.prompt_version'),
        ];

        try {
            $summary = Summary::firstOrCreate($attributes, ['state' => SummaryState::Queued]);
        } catch (UniqueConstraintViolationException) {
            // A second click landed at the same moment. Use the row that won.
            $summary = Summary::where($attributes)->firstOrFail();
        }

        $shouldDispatch = $summary->wasRecentlyCreated;

        if ($summary->state === SummaryState::Failed) {
            $retried = Summary::whereKey($summary->id)
                ->where('state', SummaryState::Failed)
                ->update(['state' => SummaryState::Queued, 'failure_reason' => null]);

            $shouldDispatch = $retried === 1;
            $summary->refresh();
        }

        if ($shouldDispatch) {
            GenerateSummary::dispatch($summary->id);
        }

        return response()->json(SummaryController::payload($summary), 202);
    }
}

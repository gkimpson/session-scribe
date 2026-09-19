<?php

namespace App\Jobs;

use App\Contracts\SummaryGenerator;
use App\Enums\SummaryState;
use App\Models\Summary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $summaryId) {}

    public function handle(SummaryGenerator $generator): void
    {
        $summary = Summary::with('transcript')->findOrFail($this->summaryId);

        if ($summary->state !== SummaryState::Queued) {
            return;
        }

        $summary->update(['state' => SummaryState::Summarising]);

        $sections = $generator->generate($summary->transcript, $summary->level);

        $summary->update([
            'state' => SummaryState::Complete,
            'sections' => $sections,
            'failure_reason' => null,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Summary::whereKey($this->summaryId)->update([
            'state' => SummaryState::Failed,
            'failure_reason' => 'The summary could not be generated.',
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Contracts\SummaryGenerator;
use App\Enums\SummaryState;
use App\Exceptions\SummaryUnavailable;
use App\Models\Summary;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSummary implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $summaryId) {}

    public function uniqueId(): string
    {
        return (string) $this->summaryId;
    }

    public function handle(SummaryGenerator $generator): void
    {
        $summary = Summary::with('transcript')->findOrFail($this->summaryId);

        if ($summary->state !== SummaryState::Queued) {
            return;
        }

        $summary->update(['state' => SummaryState::Summarising]);

        try {
            $sections = $generator->generate($summary->transcript, $summary->level);
        } catch (SummaryUnavailable $exception) {
            $summary->update([
                'state' => SummaryState::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);

            return;
        }

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

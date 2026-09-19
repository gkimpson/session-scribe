<?php

namespace App\Console\Commands;

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Enums\SummaryState;
use App\Jobs\CheckTranscription;
use App\Jobs\GenerateSummary;
use App\Jobs\StartTranscription;
use App\Models\Recording;
use App\Models\Summary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Signature('recordings:reconcile {--dry-run : Show what would change without doing it}')]
#[Description('Restart stuck transcriptions and summaries and remove abandoned uploads')]
class ReconcileRecordings extends Command
{
    public function handle(RecordingStorage $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $notStarted = Recording::where('state', RecordingState::Uploaded)
            ->whereNull('provider_job_id')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get();
        $this->report('Uploaded but never started', $notStarted, $dryRun, fn (Recording $r) => StartTranscription::dispatch($r->id));

        $stalled = Recording::where('state', RecordingState::Transcribing)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();
        $this->report('Transcribing with no recent check', $stalled, $dryRun, fn (Recording $r) => CheckTranscription::dispatch($r->id));

        $abandoned = Recording::where('state', RecordingState::PendingUpload)
            ->where('created_at', '<', now()->subDay())
            ->get();
        $this->report('Abandoned uploads (older than a day)', $abandoned, $dryRun, function (Recording $r) use ($storage) {
            $storage->delete($r);
            $r->delete();
        });

        $unqueued = Summary::where('state', SummaryState::Queued)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get();
        $this->report('Summaries queued but never run', $unqueued, $dryRun, fn (Summary $s) => GenerateSummary::dispatch($s->id));

        $hung = Summary::where('state', SummaryState::Summarising)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();
        $this->report('Summaries stuck generating', $hung, $dryRun, fn (Summary $s) => $s->update([
            'state' => SummaryState::Failed,
            'failure_reason' => 'The summary took too long and was stopped.',
        ]));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Model>  $items
     */
    private function report(string $label, $items, bool $dryRun, callable $action): void
    {
        $this->line(sprintf('%s: %d%s', $label, $items->count(), $dryRun && $items->isNotEmpty() ? ' (dry run, no changes)' : ''));

        if ($dryRun) {
            return;
        }

        $items->each($action);
    }
}

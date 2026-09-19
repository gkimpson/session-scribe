<?php

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Enums\SummaryState;
use App\Jobs\CheckTranscription;
use App\Jobs\GenerateSummary;
use App\Jobs\StartTranscription;
use App\Models\Recording;
use App\Models\Summary;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->storage = Mockery::mock(RecordingStorage::class);
    $this->app->instance(RecordingStorage::class, $this->storage);
});

function ageBy(Model $model, string $interval): void
{
    $model->forceFill(['updated_at' => now()->sub($interval), 'created_at' => now()->sub($interval)])->saveQuietly();
}

it('restarts an upload that never got a transcription job', function () {
    $stuck = Recording::factory()->uploaded()->create();
    ageBy($stuck, '10 minutes');
    Recording::factory()->uploaded()->create();

    $this->artisan('recordings:reconcile')->assertSuccessful();

    Queue::assertPushed(StartTranscription::class, 1);
    Queue::assertPushed(StartTranscription::class, fn ($job) => $job->recordingId === $stuck->id);
});

it('does not restart an upload that already has a job', function () {
    $recording = Recording::factory()->uploaded()->create(['provider_job_id' => 'case-event-x']);
    ageBy($recording, '10 minutes');

    $this->artisan('recordings:reconcile')->assertSuccessful();

    Queue::assertNotPushed(StartTranscription::class);
});

it('checks a transcription that has gone quiet', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    ageBy($recording, '20 minutes');

    $this->artisan('recordings:reconcile')->assertSuccessful();

    Queue::assertPushed(CheckTranscription::class, fn ($job) => $job->recordingId === $recording->id);
});

it('removes abandoned uploads and their objects', function () {
    $abandoned = Recording::factory()->create();
    ageBy($abandoned, '2 days');
    $fresh = Recording::factory()->create();
    $this->storage->shouldReceive('delete')->once()->with(Mockery::on(fn ($r) => $r->is($abandoned)));

    $this->artisan('recordings:reconcile')->assertSuccessful();

    expect(Recording::find($abandoned->id))->toBeNull()
        ->and(Recording::find($fresh->id))->not->toBeNull();
});

it('requeues summaries that never ran and fails ones that hung', function () {
    $unqueued = Summary::factory()->create(['state' => SummaryState::Queued]);
    ageBy($unqueued, '10 minutes');
    $hung = Summary::factory()->create(['state' => SummaryState::Summarising, 'level' => 'brief']);
    ageBy($hung, '20 minutes');

    $this->artisan('recordings:reconcile')->assertSuccessful();

    Queue::assertPushed(GenerateSummary::class, fn ($job) => $job->summaryId === $unqueued->id);
    expect($hung->fresh())
        ->state->toBe(SummaryState::Failed)
        ->failure_reason->toContain('too long');
});

it('changes nothing on a dry run', function () {
    $stuck = Recording::factory()->uploaded()->create();
    ageBy($stuck, '10 minutes');
    $abandoned = Recording::factory()->create();
    ageBy($abandoned, '2 days');
    $this->storage->shouldNotReceive('delete');

    $this->artisan('recordings:reconcile --dry-run')
        ->expectsOutputToContain('Uploaded but never started: 1 (dry run, no changes)')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect(Recording::find($abandoned->id))->not->toBeNull();
});

it('is scheduled every fifteen minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'recordings:reconcile'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});

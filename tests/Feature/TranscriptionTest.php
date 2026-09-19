<?php

use App\Actions\StoreTranscript;
use App\Contracts\RecordingStorage;
use App\Contracts\TranscriptionProvider;
use App\Enums\RecordingState;
use App\Enums\TranscriptionJobStatus;
use App\Jobs\CheckTranscription;
use App\Jobs\StartTranscription;
use App\Models\Recording;
use App\Models\Transcript;
use App\Support\TranscriptionJobResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->provider = Mockery::mock(TranscriptionProvider::class);
    $this->app->instance(TranscriptionProvider::class, $this->provider);
});

it('queues transcription once the upload is confirmed', function () {
    Queue::fake();
    $recording = Recording::factory()->create(['size_bytes' => 5000]);
    $storage = Mockery::mock(RecordingStorage::class);
    $storage->shouldReceive('storedSize')->andReturn(5000);
    $this->app->instance(RecordingStorage::class, $storage);

    $this->postJson(route('recordings.upload', $recording))->assertOk();
    $this->postJson(route('recordings.upload', $recording))->assertOk();

    Queue::assertPushed(StartTranscription::class, 1);
});

it('starts a transcribe job and schedules a status check', function () {
    Queue::fake();
    $recording = Recording::factory()->uploaded()->create();
    $this->provider->shouldReceive('start')->once()->andReturn("case-event-{$recording->id}");

    (new StartTranscription($recording->id))->handle($this->provider);

    expect($recording->fresh())
        ->state->toBe(RecordingState::Transcribing)
        ->provider_job_id->toBe("case-event-{$recording->id}");
    Queue::assertPushed(CheckTranscription::class);
});

it('does not start a second job for the same recording', function () {
    $recording = Recording::factory()->uploaded()->create(['provider_job_id' => 'case-event-x']);
    $this->provider->shouldNotReceive('start');

    (new StartTranscription($recording->id))->handle($this->provider);
});

it('checks again while the job is running', function (TranscriptionJobStatus $status) {
    Queue::fake();
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult($status));

    (new CheckTranscription($recording->id))->handle($this->provider, app(StoreTranscript::class));

    expect($recording->fresh()->state)->toBe(RecordingState::Transcribing);
    Queue::assertPushed(CheckTranscription::class);
})->with([TranscriptionJobStatus::Queued, TranscriptionJobStatus::InProgress]);

it('stores the transcript text and turns when the job completes', function () {
    Queue::fake();
    Storage::fake('s3');
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult(TranscriptionJobStatus::Completed, null, "case-event-transcripts/redacted-{$recording->id}.json", true));

    Storage::disk('s3')->put("case-event-transcripts/redacted-{$recording->id}.json", json_encode(['results' => [
        'transcripts' => [['transcript' => 'Hello there.']],
        'items' => [],
    ]]));

    (new CheckTranscription($recording->id))->handle($this->provider, app(StoreTranscript::class));

    $transcript = $recording->transcripts()->firstOrFail();
    expect($recording->fresh()->state)->toBe(RecordingState::Ready)
        ->and($transcript->s3_key)->toBe("case-event-transcripts/redacted-{$recording->id}.json")
        ->and($transcript->turns)->toBe([['speaker' => 'Speaker 1', 'text' => 'Hello there.']])
        ->and($transcript->text)->toBe('Speaker 1: Hello there.')
        ->and($transcript->redacted)->toBeTrue();
    Queue::assertNotPushed(CheckTranscription::class);
});

it('fails a completed job that has no transcript location', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult(TranscriptionJobStatus::Completed));

    (new CheckTranscription($recording->id))->handle($this->provider, app(StoreTranscript::class));

    expect($recording->fresh()->state)->toBe(RecordingState::Failed);
});

it('records the reason when the job fails', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult(TranscriptionJobStatus::Failed, 'Unsupported format'));

    (new CheckTranscription($recording->id))->handle($this->provider, app(StoreTranscript::class));

    expect($recording->fresh())
        ->state->toBe(RecordingState::Failed)
        ->failure_reason->toBe('Unsupported format');
});

it('gives up on a job that runs far too long', function () {
    Queue::fake();
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $recording->forceFill(['updated_at' => now()->subHours(2)])->saveQuietly();
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult(TranscriptionJobStatus::InProgress));

    (new CheckTranscription($recording->id))->handle($this->provider, app(StoreTranscript::class));

    expect($recording->fresh()->state)->toBe(RecordingState::Failed);
    Queue::assertNotPushed(CheckTranscription::class);
});

it('reports the state while transcribing', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing]);

    $this->getJson(route('recordings.show', $recording))
        ->assertOk()
        ->assertJson(['state' => 'transcribing', 'turns' => null]);
});

it('returns speaker turns from the database once the transcript is ready', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Ready]);
    Transcript::factory()->for($recording)->create(['turns' => [['speaker' => 'Speaker 1', 'text' => 'Hello there.']]]);

    $this->getJson(route('recordings.show', $recording))
        ->assertOk()
        ->assertJson(['state' => 'ready', 'redacted' => true, 'turns' => [['speaker' => 'Speaker 1', 'text' => 'Hello there.']]]);
});

it('marks the recording failed when the transcript cannot be read', function () {
    Storage::fake('s3');
    $recording = Recording::factory()->create(['state' => RecordingState::Transcribing, 'provider_job_id' => 'case-event-x']);
    $this->provider->shouldReceive('status')->andReturn(new TranscriptionJobResult(TranscriptionJobStatus::Completed, null, 'case-event-transcripts/missing.json'));

    $job = new CheckTranscription($recording->id);
    expect(fn () => $job->handle($this->provider, app(StoreTranscript::class)))->toThrow(RuntimeException::class);

    $job->failed(null);
    expect($recording->fresh()->state)->toBe(RecordingState::Failed);
});

it('reports the failure reason', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Failed, 'failure_reason' => 'Nope']);

    $this->getJson(route('recordings.show', $recording))
        ->assertOk()
        ->assertJson(['state' => 'failed', 'failure_reason' => 'Nope']);
});

<?php

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Models\Recording;
use App\Support\PresignedUpload;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->storage = Mockery::mock(RecordingStorage::class);
    $this->app->instance(RecordingStorage::class, $this->storage);
});

function validPayload(array $overrides = []): array
{
    return array_merge([
        'mime_type' => 'audio/webm',
        'size_bytes' => 1_200_000,
        'duration_seconds' => 95,
        'consent' => true,
    ], $overrides);
}

it('creates a recording and returns a presigned upload url', function () {
    $this->storage->shouldReceive('presignedUpload')
        ->once()
        ->andReturn(new PresignedUpload('https://bucket.example/audio/x.webm?sig=1', ['Content-Type' => 'audio/webm']));

    $response = $this->postJson(route('recordings.store'), validPayload());

    $response->assertCreated()
        ->assertJsonPath('s3_key', 'voice-transcripts/'.$response->json('id').'.webm')
        ->assertJsonPath('upload.url', 'https://bucket.example/audio/x.webm?sig=1')
        ->assertJsonPath('upload.headers.Content-Type', 'audio/webm');

    $recording = Recording::findOrFail($response->json('id'));
    expect($recording->state)->toBe(RecordingState::PendingUpload)
        ->and($recording->s3_key)->toBe("voice-transcripts/{$recording->id}.webm")
        ->and($recording->extension)->toBe('webm')
        ->and($recording->consent_confirmed_at)->not->toBeNull();
});

it('maps safari mp4 audio to an m4a key', function () {
    $this->storage->shouldReceive('presignedUpload')
        ->andReturn(new PresignedUpload('https://bucket.example/u', []));

    $response = $this->postJson(route('recordings.store'), validPayload(['mime_type' => 'audio/mp4']));

    $response->assertCreated();
    expect(Recording::findOrFail($response->json('id'))->s3_key)->toEndWith('.m4a');
});

it('rejects invalid recordings', function (array $overrides, string $field) {
    $this->postJson(route('recordings.store'), validPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no consent' => [['consent' => false], 'consent'],
    'wrong type' => [['mime_type' => 'video/mp4'], 'mime_type'],
    'too big' => [['size_bytes' => 250 * 1024 * 1024 + 1], 'size_bytes'],
    'too long' => [['duration_seconds' => 3601], 'duration_seconds'],
    'empty' => [['size_bytes' => 0], 'size_bytes'],
]);

it('marks a recording uploaded when the object exists at the right size', function () {
    $recording = Recording::factory()->create(['size_bytes' => 5000]);
    $this->storage->shouldReceive('storedSize')->once()->andReturn(5000);

    $this->postJson(route('recordings.upload', $recording))
        ->assertOk()
        ->assertJson(['state' => 'uploaded']);

    expect($recording->fresh()->state)->toBe(RecordingState::Uploaded);
});

it('leaves the recording pending when the object is not there yet', function () {
    $recording = Recording::factory()->create(['size_bytes' => 5000]);
    $this->storage->shouldReceive('storedSize')->andReturn(null);
    $this->storage->shouldNotReceive('delete');

    $this->postJson(route('recordings.upload', $recording))->assertUnprocessable();

    expect($recording->fresh()->state)->toBe(RecordingState::PendingUpload);
});

it('deletes the object and fails the recording when the size is wrong', function () {
    $recording = Recording::factory()->create(['size_bytes' => 5000]);
    $this->storage->shouldReceive('storedSize')->andReturn(4999);
    $this->storage->shouldReceive('delete')->once()->with(Mockery::on(fn ($r) => $r->is($recording)));

    $this->postJson(route('recordings.upload', $recording))->assertUnprocessable();

    expect($recording->fresh())
        ->state->toBe(RecordingState::Failed)
        ->failure_reason->toContain('size');
});

it('rate limits recording requests', function () {
    $this->storage->shouldReceive('presignedUpload')->andReturn(new PresignedUpload('https://bucket.example/u', []));

    foreach (range(1, 20) as $ignored) {
        $this->postJson(route('recordings.store'), validPayload())->assertCreated();
    }

    $this->postJson(route('recordings.store'), validPayload())->assertStatus(429);
});

it('does not re-check a recording that is already uploaded', function () {
    $recording = Recording::factory()->uploaded()->create();
    $this->storage->shouldNotReceive('storedSize');

    $this->postJson(route('recordings.upload', $recording))
        ->assertOk()
        ->assertJson(['state' => 'uploaded']);
});

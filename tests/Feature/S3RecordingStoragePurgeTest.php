<?php

use App\Exceptions\StorageFailed;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\S3RecordingStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('removes the audio and every transcript file for a recording', function () {
    Storage::fake('s3');
    $recording = Recording::factory()->create();
    $transcript = Transcript::factory()->for($recording)->create(['s3_key' => "case-event-transcripts/redacted-{$recording->id}.json"]);
    $other = Recording::factory()->create();
    foreach ([$recording->s3_key, $transcript->s3_key, "case-event-transcripts/{$recording->id}.json", $other->s3_key] as $key) {
        Storage::disk('s3')->put($key, 'x');
    }

    (new S3RecordingStorage)->purge($recording);

    Storage::disk('s3')->assertMissing([$recording->s3_key, $transcript->s3_key, "case-event-transcripts/{$recording->id}.json"]);
    Storage::disk('s3')->assertExists($other->s3_key);
});

it('is fine when the files are already gone', function () {
    Storage::fake('s3');

    (new S3RecordingStorage)->purge(Recording::factory()->create());

    expect(true)->toBeTrue();
});

it('throws when storage refuses to delete', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('delete')->andReturn(false);
    Storage::shouldReceive('disk')->andReturn($disk);

    (new S3RecordingStorage)->purge(Recording::factory()->create());
})->throws(StorageFailed::class);

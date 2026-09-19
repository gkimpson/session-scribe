<?php

use App\Contracts\RecordingStorage;
use App\Enums\RecordingState;
use App\Enums\SummaryState;
use App\Exceptions\StorageFailed;
use App\Models\Recording;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('shows an empty list when there are no recordings', function () {
    $this->get(route('recordings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Recordings/Index')
            ->where('recordings', [])
            ->where('pagination.total', 0));
});

it('lists recordings newest first', function () {
    $older = Recording::factory()->create(['created_at' => now()->subDay()]);
    $newer = Recording::factory()->create(['created_at' => now()]);

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recordings.0.id', $newer->id)
            ->where('recordings.1.id', $older->id));
});

it('links a recording to its transcript with a snippet and summary count', function () {
    $recording = Recording::factory()->create(['state' => RecordingState::Ready, 'duration_seconds' => 95, 'size_bytes' => 12345]);
    $transcript = Transcript::factory()->for($recording)->create(['text' => str_repeat('a', 300), 'redacted' => false]);
    Summary::factory()->complete()->for($transcript)->create();
    Summary::factory()->for($transcript)->create(['level' => 'brief', 'state' => SummaryState::Queued]);

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recordings.0.transcriptId', $transcript->uuid)
            ->where('recordings.0.state', 'ready')
            ->where('recordings.0.durationSeconds', 95)
            ->where('recordings.0.sizeBytes', 12345)
            ->where('recordings.0.redacted', false)
            ->where('recordings.0.summariesComplete', 1)
            ->where('recordings.0.snippet', str_repeat('a', 140)));
});

it('never exposes the numeric transcript id', function () {
    $transcript = Transcript::factory()->create();

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recordings.0.transcriptId', fn ($id) => is_string($id) && $id !== (string) $transcript->id));
});

it('shows recordings that have no transcript yet, including failures', function () {
    $waiting = Recording::factory()->create(['state' => RecordingState::Transcribing, 'created_at' => now()]);
    $failed = Recording::factory()->create(['state' => RecordingState::Failed, 'failure_reason' => 'Unsupported format', 'created_at' => now()->subMinute()]);

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recordings.0.id', $waiting->id)
            ->where('recordings.0.transcriptId', null)
            ->where('recordings.0.snippet', null)
            ->where('recordings.1.id', $failed->id)
            ->where('recordings.1.failureReason', 'Unsupported format'));
});

it('only uses the newest transcript when a recording has more than one', function () {
    $recording = Recording::factory()->create();
    Transcript::factory()->for($recording)->create(['text' => 'old text']);
    $newest = Transcript::factory()->for($recording)->create(['text' => 'new text']);

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recordings', 1)
            ->where('recordings.0.transcriptId', $newest->uuid)
            ->where('recordings.0.snippet', 'new text'));
});

it('paginates fifteen at a time', function () {
    Recording::factory()->count(16)->create();

    $this->get(route('recordings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recordings', 15)
            ->where('pagination.total', 16)
            ->where('pagination.lastPage', 2)
            ->where('pagination.previousUrl', null)
            ->where('pagination.nextUrl', fn ($url) => str_contains($url, 'page=2')));

    $this->get(route('recordings.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recordings', 1)
            ->where('pagination.nextUrl', null));
});

it('does not run a query per row', function () {
    Recording::factory()->count(10)->create()->each(fn ($recording) => Transcript::factory()->for($recording)->create());

    DB::enableQueryLog();
    $this->get(route('recordings.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(6);
});

describe('removing a recording', function () {
    beforeEach(function () {
        $this->storage = Mockery::mock(RecordingStorage::class);
        $this->app->instance(RecordingStorage::class, $this->storage);
    });

    it('deletes the recording, its transcripts, its summaries and its stored files', function () {
        $recording = Recording::factory()->create(['state' => RecordingState::Ready]);
        $transcript = Transcript::factory()->for($recording)->create();
        Summary::factory()->complete()->for($transcript)->create();
        $other = Recording::factory()->create(['state' => RecordingState::Ready]);
        $otherTranscript = Transcript::factory()->for($other)->create();
        $this->storage->shouldReceive('purge')->once()->with(Mockery::on(fn ($r) => $r->is($recording)));

        $this->delete(route('recordings.destroy', $recording))->assertRedirect(route('recordings.index'));

        expect(Recording::find($recording->id))->toBeNull()
            ->and(Transcript::find($transcript->id))->toBeNull()
            ->and(Summary::count())->toBe(0)
            ->and(Recording::find($other->id))->not->toBeNull()
            ->and(Transcript::find($otherTranscript->id))->not->toBeNull();
    });

    it('can remove failed and unfinished recordings', function (RecordingState $state) {
        $recording = Recording::factory()->create(['state' => $state]);
        $this->storage->shouldReceive('purge')->once();

        $this->delete(route('recordings.destroy', $recording))->assertRedirect(route('recordings.index'));

        expect(Recording::find($recording->id))->toBeNull();
    })->with([RecordingState::Failed, RecordingState::PendingUpload, RecordingState::Ready]);

    it('refuses while the recording is still being processed', function (RecordingState $state) {
        $recording = Recording::factory()->create(['state' => $state]);
        $this->storage->shouldNotReceive('purge');

        $this->delete(route('recordings.destroy', $recording))->assertSessionHasErrors('delete');

        expect(Recording::find($recording->id))->not->toBeNull();
    })->with([RecordingState::Uploaded, RecordingState::TranscriptionQueued, RecordingState::Transcribing]);

    it('keeps everything when storage cannot be cleared', function () {
        $recording = Recording::factory()->create(['state' => RecordingState::Ready]);
        $transcript = Transcript::factory()->for($recording)->create();
        $this->storage->shouldReceive('purge')->andThrow(new StorageFailed('nope'));

        $this->delete(route('recordings.destroy', $recording))
            ->assertSessionHasErrors(['delete' => 'The stored files could not be removed, so nothing was deleted. Try again.']);

        expect(Recording::find($recording->id))->not->toBeNull()
            ->and(Transcript::find($transcript->id))->not->toBeNull();
    });

    it('returns 404 for a recording that does not exist', function () {
        $this->delete(route('recordings.destroy', (string) Str::uuid()))->assertNotFound();
    });
});

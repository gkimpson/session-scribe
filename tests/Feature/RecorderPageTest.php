<?php

use App\Models\Transcript;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('opens an empty recorder without a transcript id', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Recorder')
            ->where('transcript', null)
            ->where('missingTranscriptId', null));
});

it('loads the transcript for a uuid in the path', function () {
    $transcript = Transcript::factory()->create(['turns' => [['speaker' => 'Speaker 1', 'text' => 'Hello.']]]);

    $this->get("/{$transcript->uuid}/")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript.id', $transcript->uuid)
            ->where('transcript.recordingId', $transcript->recording_id)
            ->where('transcript.turns', [['speaker' => 'Speaker 1', 'text' => 'Hello.']])
            ->where('missingTranscriptId', null));
});

it('still loads a transcript from the transcript_id query string', function () {
    $transcript = Transcript::factory()->create();

    $this->get(route('home', ['transcript_id' => $transcript->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transcript.id', $transcript->uuid));
});

it('does not expose the numeric id anywhere in the transcript page data', function () {
    $transcript = Transcript::factory()->create();

    $this->get("/{$transcript->uuid}")
        ->assertInertia(fn (Assert $page) => $page->where('transcript.id', fn ($id) => $id !== $transcript->id && is_string($id)));
});

it('no longer opens a transcript by its numeric id', function () {
    $transcript = Transcript::factory()->create();

    $this->get("/{$transcript->id}")->assertNotFound();
});

it('reports a uuid that does not exist', function () {
    $uuid = (string) Str::uuid();

    $this->get("/{$uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript', null)
            ->where('missingTranscriptId', $uuid));
});

it('reports a query string id that is not a valid uuid', function (string $id) {
    $this->get(route('home', ['transcript_id' => $id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript', null)
            ->where('missingTranscriptId', $id));
})->with(['a number' => '1', 'text' => 'abc']);

it('does not treat a non-uuid path as a transcript', function () {
    $this->get('/abc')->assertNotFound();
});

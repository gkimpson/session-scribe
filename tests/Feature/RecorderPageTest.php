<?php

use App\Models\Transcript;
use Inertia\Testing\AssertableInertia as Assert;

it('opens an empty recorder without a transcript id', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Recorder')
            ->where('transcript', null)
            ->where('missingTranscriptId', null));
});

it('loads the transcript for an id in the path', function () {
    $transcript = Transcript::factory()->create();

    $this->get("/{$transcript->id}/")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript.id', $transcript->id)
            ->where('missingTranscriptId', null));
});

it('still loads a transcript from the transcript_id query string', function () {
    $transcript = Transcript::factory()->create(['turns' => [['speaker' => 'Speaker 1', 'text' => 'Hello.']]]);

    $this->get(route('home', ['transcript_id' => $transcript->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript.id', $transcript->id)
            ->where('transcript.recordingId', $transcript->recording_id)
            ->where('transcript.turns', [['speaker' => 'Speaker 1', 'text' => 'Hello.']])
            ->where('missingTranscriptId', null));
});

it('reports a path id that does not exist', function () {
    $this->get('/99999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript', null)
            ->where('missingTranscriptId', '99999'));
});

it('reports a query string id that does not exist', function (string $id) {
    $this->get(route('home', ['transcript_id' => $id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transcript', null)
            ->where('missingTranscriptId', $id));
})->with(['unknown' => '99999', 'not a number' => 'abc']);

it('does not treat a non-numeric path as a transcript', function () {
    $this->get('/abc')->assertNotFound();
});

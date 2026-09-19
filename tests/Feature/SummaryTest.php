<?php

use App\Contracts\SummaryGenerator;
use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use App\Jobs\GenerateSummary;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Support\Facades\Queue;

it('queues a summary for a level', function (string $level) {
    Queue::fake();
    $transcript = Transcript::factory()->create();

    $this->postJson(route('transcripts.summaries.store', $transcript), ['level' => $level])
        ->assertStatus(202)
        ->assertJson(['level' => $level, 'state' => 'queued']);

    $summary = Summary::firstOrFail();
    expect($summary->transcript_id)->toBe($transcript->id)
        ->and($summary->model_id)->toBe('amazon.nova-lite-v1:0')
        ->and($summary->prompt_version)->toBe(1);
    Queue::assertPushed(GenerateSummary::class, fn ($job) => $job->summaryId === $summary->id);
})->with(['brief', 'normal', 'detailed']);

it('rejects an unknown level', function () {
    $transcript = Transcript::factory()->create();

    $this->postJson(route('transcripts.summaries.store', $transcript), ['level' => 'huge'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('level');
});

it('returns an existing summary instead of making another', function () {
    Queue::fake();
    $summary = Summary::factory()->complete()->create(['level' => SummaryLevel::Brief]);

    $this->postJson(route('transcripts.summaries.store', $summary->transcript), ['level' => 'brief'])
        ->assertStatus(202)
        ->assertJson(['id' => $summary->id, 'state' => 'complete']);

    expect(Summary::count())->toBe(1);
    Queue::assertNothingPushed();
});

it('retries a failed summary', function () {
    Queue::fake();
    $summary = Summary::factory()->create(['state' => SummaryState::Failed, 'failure_reason' => 'Nope']);

    $this->postJson(route('transcripts.summaries.store', $summary->transcript), ['level' => 'normal'])
        ->assertStatus(202)
        ->assertJson(['id' => $summary->id, 'state' => 'queued', 'failure_reason' => null]);

    Queue::assertPushed(GenerateSummary::class);
});

it('keeps separate summaries for each level', function () {
    Queue::fake();
    $transcript = Transcript::factory()->create();

    foreach (['brief', 'normal', 'detailed'] as $level) {
        $this->postJson(route('transcripts.summaries.store', $transcript), ['level' => $level])->assertStatus(202);
    }

    expect($transcript->summaries()->count())->toBe(3);
});

it('reports a summary for polling', function () {
    $summary = Summary::factory()->complete()->create();

    $this->getJson(route('summaries.show', $summary))
        ->assertOk()
        ->assertJsonPath('state', 'complete')
        ->assertJsonCount(3, 'sections');
});

it('generates and stores the sections', function () {
    $summary = Summary::factory()->create(['level' => SummaryLevel::Brief]);
    $generator = Mockery::mock(SummaryGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn([['heading' => 'Decisions', 'body' => 'Keep it.']]);

    (new GenerateSummary($summary->id))->handle($generator);

    expect($summary->fresh())
        ->state->toBe(SummaryState::Complete)
        ->sections->toBe([['heading' => 'Decisions', 'body' => 'Keep it.']]);
});

it('does not generate twice for a summary already underway', function () {
    $summary = Summary::factory()->create(['state' => SummaryState::Summarising]);
    $generator = Mockery::mock(SummaryGenerator::class);
    $generator->shouldNotReceive('generate');

    (new GenerateSummary($summary->id))->handle($generator);
});

it('marks the summary failed when generation throws', function () {
    $summary = Summary::factory()->create();

    (new GenerateSummary($summary->id))->failed(new RuntimeException('boom'));

    expect($summary->fresh())
        ->state->toBe(SummaryState::Failed)
        ->failure_reason->toBe('The summary could not be generated.');
});

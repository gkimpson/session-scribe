<?php

use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores structured sections against a transcript level', function () {
    $summary = Summary::factory()->complete()->create(['level' => SummaryLevel::Brief]);

    expect($summary->fresh())
        ->level->toBe(SummaryLevel::Brief)
        ->state->toBe(SummaryState::Complete)
        ->sections->toHaveCount(3)
        ->and($summary->transcript->summaries)->toHaveCount(1);
});

it('allows one summary per transcript, level, prompt version and model', function () {
    $transcript = Transcript::factory()->create();
    Summary::factory()->for($transcript)->create();

    Summary::factory()->for($transcript)->create();
})->throws(UniqueConstraintViolationException::class);

it('allows the same level again for a new prompt version', function () {
    $transcript = Transcript::factory()->create();
    Summary::factory()->for($transcript)->create(['prompt_version' => 1]);
    Summary::factory()->for($transcript)->create(['prompt_version' => 2]);

    expect($transcript->summaries()->count())->toBe(2);
});

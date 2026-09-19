<?php

namespace Database\Factories;

use App\Models\Recording;
use App\Models\Transcript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcript>
 */
class TranscriptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $turns = [
            ['speaker' => 'Speaker 1', 'text' => fake()->sentence()],
            ['speaker' => 'Speaker 2', 'text' => fake()->sentence()],
        ];

        return [
            'recording_id' => Recording::factory(),
            's3_key' => 'case-event-transcripts/redacted-'.fake()->uuid().'.json',
            'version' => 1,
            'redacted' => true,
            'turns' => $turns,
            'text' => collect($turns)->map(fn (array $turn) => "{$turn['speaker']}: {$turn['text']}")->implode("\n"),
        ];
    }
}

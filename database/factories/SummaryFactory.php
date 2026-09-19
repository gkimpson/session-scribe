<?php

namespace Database\Factories;

use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use App\Models\Summary;
use App\Models\Transcript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Summary>
 */
class SummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transcript_id' => Transcript::factory(),
            'level' => SummaryLevel::Normal,
            'model_id' => 'amazon.nova-lite-v1:0',
            'prompt_version' => 1,
            'state' => SummaryState::Queued,
            'sections' => null,
        ];
    }

    public function complete(): static
    {
        return $this->state([
            'state' => SummaryState::Complete,
            'sections' => [
                ['heading' => 'What we discussed', 'body' => fake()->sentence()],
                ['heading' => 'Decisions', 'body' => fake()->sentence()],
                ['heading' => 'Next steps', 'body' => fake()->sentence()],
            ],
        ]);
    }
}

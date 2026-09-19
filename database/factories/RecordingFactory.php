<?php

namespace Database\Factories;

use App\Enums\RecordingState;
use App\Models\Recording;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recording>
 */
class RecordingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'id' => $id,
            's3_key' => config('recordings.key_prefix')."/{$id}.webm",
            'mime_type' => 'audio/webm',
            'extension' => 'webm',
            'size_bytes' => fake()->numberBetween(50_000, 5_000_000),
            'duration_seconds' => fake()->numberBetween(5, 600),
            'state' => RecordingState::PendingUpload,
            'consent_confirmed_at' => now(),
        ];
    }

    public function uploaded(): static
    {
        return $this->state(['state' => RecordingState::Uploaded]);
    }
}

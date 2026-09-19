<?php

namespace App\Models;

use App\Enums\RecordingState;
use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'user_id',
    's3_key',
    'mime_type',
    'extension',
    'size_bytes',
    'duration_seconds',
    'state',
    'provider_job_id',
    'failure_reason',
    'consent_confirmed_at',
])]
class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => RecordingState::class,
            'consent_confirmed_at' => 'datetime',
        ];
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\TranscriptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['recording_id', 's3_key', 'version', 'turns', 'text', 'redacted'])]
class Transcript extends Model
{
    /** @use HasFactory<TranscriptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['turns' => 'array', 'redacted' => 'boolean'];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }
}

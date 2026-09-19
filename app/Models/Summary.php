<?php

namespace App\Models;

use App\Enums\SummaryLevel;
use App\Enums\SummaryState;
use Database\Factories\SummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transcript_id',
    'level',
    'model_id',
    'prompt_version',
    'state',
    'sections',
    'failure_reason',
])]
class Summary extends Model
{
    /** @use HasFactory<SummaryFactory> */
    use HasFactory, HasUuids;

    /**
     * The numeric id is the primary key. URLs use the uuid column instead, so
     * they can't be guessed by counting.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'level' => SummaryLevel::class,
            'state' => SummaryState::class,
            'sections' => 'array',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}

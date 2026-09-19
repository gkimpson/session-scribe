<?php

namespace App\Support;

use App\Enums\TranscriptionJobStatus;

final readonly class TranscriptionJobResult
{
    public function __construct(
        public TranscriptionJobStatus $status,
        public ?string $failureReason = null,
        public ?string $transcriptKey = null,
        public bool $redacted = false,
    ) {}
}

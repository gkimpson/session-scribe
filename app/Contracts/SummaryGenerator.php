<?php

namespace App\Contracts;

use App\Enums\SummaryLevel;
use App\Models\Transcript;

interface SummaryGenerator
{
    /**
     * Summarise the whole conversation at the given level.
     *
     * @return list<array{heading: string, body: string}>
     */
    public function generate(Transcript $transcript, SummaryLevel $level): array;
}

<?php

namespace App\Contracts;

use App\Models\Recording;
use App\Support\TranscriptionJobResult;

interface TranscriptionProvider
{
    /**
     * Start a batch job for the recording's audio and return the job name.
     */
    public function start(Recording $recording): string;

    /**
     * A finished job's result carries the S3 key of its transcript JSON.
     */
    public function status(string $jobName): TranscriptionJobResult;
}

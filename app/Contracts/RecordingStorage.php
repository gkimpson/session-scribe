<?php

namespace App\Contracts;

use App\Models\Recording;
use App\Support\PresignedUpload;

interface RecordingStorage
{
    /**
     * A short-lived PUT URL scoped to the recording's single object key.
     */
    public function presignedUpload(Recording $recording): PresignedUpload;

    /**
     * Size in bytes of the stored object, or null if it isn't there.
     */
    public function storedSize(Recording $recording): ?int;
}

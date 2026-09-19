<?php

namespace App\Contracts;

use App\Exceptions\StorageFailed;
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

    /**
     * Remove the stored object. Safe to call when it isn't there.
     */
    public function delete(Recording $recording): void;

    /**
     * Remove everything stored for the recording: its audio and every
     * transcript file. Missing files are fine. Throws if storage refuses, so
     * the caller can keep the records and try again.
     *
     * @throws StorageFailed
     */
    public function purge(Recording $recording): void;
}

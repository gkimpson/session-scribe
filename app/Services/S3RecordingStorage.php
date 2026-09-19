<?php

namespace App\Services;

use App\Contracts\RecordingStorage;
use App\Exceptions\StorageFailed;
use App\Models\Recording;
use App\Support\PresignedUpload;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

class S3RecordingStorage implements RecordingStorage
{
    public function presignedUpload(Recording $recording): PresignedUpload
    {
        $upload = Storage::disk(config('recordings.disk'))->temporaryUploadUrl(
            $recording->s3_key,
            now()->addMinutes(config('recordings.presign_minutes')),
            ['ContentType' => $recording->mime_type],
        );

        return new PresignedUpload($upload['url'], $upload['headers']);
    }

    /**
     * The IAM user has no s3:ListBucket, so a missing object comes back as a
     * 403 rather than a 404. Either way, treat it as "not stored".
     */
    public function storedSize(Recording $recording): ?int
    {
        try {
            return Storage::disk(config('recordings.disk'))->size($recording->s3_key);
        } catch (FilesystemException) {
            return null;
        }
    }

    public function delete(Recording $recording): void
    {
        try {
            Storage::disk(config('recordings.disk'))->delete($recording->s3_key);
        } catch (FilesystemException) {
            // Nothing to do. The reconcile command tries again later.
        }
    }

    /**
     * Includes the two names Transcribe uses for its output, so a file is
     * still found when a failed job left no transcript row behind.
     */
    public function purge(Recording $recording): void
    {
        $keys = collect([
            $recording->s3_key,
            "case-event-transcripts/{$recording->id}.json",
            "case-event-transcripts/redacted-{$recording->id}.json",
        ])
            ->merge($recording->transcripts()->pluck('s3_key'))
            ->unique()
            ->values()
            ->all();

        try {
            $deleted = Storage::disk(config('recordings.disk'))->delete($keys);
        } catch (FilesystemException $exception) {
            throw new StorageFailed('The stored files could not be removed.', previous: $exception);
        }

        if (! $deleted) {
            throw new StorageFailed('The stored files could not be removed.');
        }
    }
}

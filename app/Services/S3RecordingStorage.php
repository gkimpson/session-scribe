<?php

namespace App\Services;

use App\Contracts\RecordingStorage;
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
}

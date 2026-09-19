<?php

namespace App\Services;

use App\Contracts\TranscriptionProvider;
use App\Enums\TranscriptionJobStatus;
use App\Models\Recording;
use App\Support\TranscriptionJobResult;
use Aws\Exception\AwsException;
use Aws\TranscribeService\TranscribeServiceClient;
use Illuminate\Support\Str;

/**
 * The IAM policy only allows jobs named case-event-* and output keys that
 * start with case-event-, so both are built from those prefixes.
 */
class AwsTranscribeProvider implements TranscriptionProvider
{
    public function __construct(private TranscribeServiceClient $client) {}

    public function start(Recording $recording): string
    {
        $jobName = "case-event-{$recording->id}";
        $bucket = config('filesystems.disks.s3.bucket');

        $job = [
            'TranscriptionJobName' => $jobName,
            'LanguageCode' => 'en-GB',
            'MediaFormat' => $recording->extension,
            'Media' => ['MediaFileUri' => "s3://{$bucket}/{$recording->s3_key}"],
            'OutputBucketName' => $bucket,
            'OutputKey' => "case-event-transcripts/{$recording->id}.json",
            'Settings' => [
                'ShowSpeakerLabels' => true,
                'MaxSpeakerLabels' => 2,
            ],
        ];

        if (config('recordings.redact_pii')) {
            $job['ContentRedaction'] = [
                'RedactionType' => 'PII',
                'RedactionOutput' => 'redacted',
            ];
        }

        try {
            $this->client->startTranscriptionJob($job);
        } catch (AwsException $exception) {
            // A retry after the job already started. The name is deterministic.
            if ($exception->getAwsErrorCode() !== 'ConflictException') {
                throw $exception;
            }
        }

        return $jobName;
    }

    public function status(string $jobName): TranscriptionJobResult
    {
        $job = $this->client->getTranscriptionJob(['TranscriptionJobName' => $jobName])['TranscriptionJob'];

        return new TranscriptionJobResult(
            TranscriptionJobStatus::from($job['TranscriptionJobStatus']),
            $job['FailureReason'] ?? null,
            $this->transcriptKey($job['Transcript'] ?? []),
            isset($job['Transcript']['RedactedTranscriptFileUri']),
        );
    }

    /**
     * With PII redaction on, Transcribe writes redacted-{job}.json next to the
     * requested output key, so read the real location from the job itself.
     *
     * @param  array<string, string>  $transcript
     */
    private function transcriptKey(array $transcript): ?string
    {
        $uri = $transcript['RedactedTranscriptFileUri'] ?? $transcript['TranscriptFileUri'] ?? null;

        if ($uri === null) {
            return null;
        }

        $bucket = config('filesystems.disks.s3.bucket');

        return ltrim(Str::after(parse_url($uri, PHP_URL_PATH), "/{$bucket}/"), '/');
    }
}

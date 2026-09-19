<?php

use App\Models\Recording;
use App\Services\AwsTranscribeProvider;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\TranscribeService\TranscribeServiceClient;
use GuzzleHttp\Promise\Create;

function providerWith(array &$sent, array $results = []): AwsTranscribeProvider
{
    $client = new TranscribeServiceClient([
        'version' => 'latest',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'k', 'secret' => 's'],
        'handler' => function ($command) use (&$sent, $results) {
            $sent[] = $command;

            return Create::promiseFor(new Result($results[$command->getName()] ?? []));
        },
    ]);

    return new AwsTranscribeProvider($client);
}

it('asks Transcribe to redact PII when the flag is on', function () {
    config(['recordings.redact_pii' => true]);
    $sent = [];

    providerWith($sent)->start(Recording::factory()->make());

    expect($sent[0]['ContentRedaction'])->toBe(['RedactionType' => 'PII', 'RedactionOutput' => 'redacted'])
        ->and($sent[0]['LanguageCode'])->toBe('en-GB')
        ->and($sent[0]['Settings'])->toBe(['ShowSpeakerLabels' => true, 'MaxSpeakerLabels' => 2]);
});

it('leaves redaction out when the flag is off', function () {
    config(['recordings.redact_pii' => false]);
    $sent = [];

    providerWith($sent)->start(Recording::factory()->make());

    expect($sent[0]->hasParam('ContentRedaction'))->toBeFalse();
});

it('names jobs and output keys with the case-event prefix', function () {
    $sent = [];
    $recording = Recording::factory()->make();

    $name = providerWith($sent)->start($recording);

    expect($name)->toBe("case-event-{$recording->id}")
        ->and($sent[0]['OutputKey'])->toStartWith('case-event-');
});

it('reads the redacted transcript location and flags it as redacted', function () {
    config(['filesystems.disks.s3.bucket' => 'my-bucket']);
    $sent = [];
    $provider = providerWith($sent, ['GetTranscriptionJob' => ['TranscriptionJob' => [
        'TranscriptionJobStatus' => 'COMPLETED',
        'Transcript' => ['RedactedTranscriptFileUri' => 'https://s3.eu-west-2.amazonaws.com/my-bucket/case-event-transcripts/redacted-x.json'],
    ]]]);

    $result = $provider->status('case-event-x');

    expect($result->transcriptKey)->toBe('case-event-transcripts/redacted-x.json')
        ->and($result->redacted)->toBeTrue();
});

it('reads the plain transcript location when redaction was off', function () {
    config(['filesystems.disks.s3.bucket' => 'my-bucket']);
    $sent = [];
    $provider = providerWith($sent, ['GetTranscriptionJob' => ['TranscriptionJob' => [
        'TranscriptionJobStatus' => 'COMPLETED',
        'Transcript' => ['TranscriptFileUri' => 'https://s3.eu-west-2.amazonaws.com/my-bucket/case-event-transcripts/x.json'],
    ]]]);

    $result = $provider->status('case-event-x');

    expect($result->transcriptKey)->toBe('case-event-transcripts/x.json')
        ->and($result->redacted)->toBeFalse();
});

it('treats a job that already exists as started', function () {
    $recording = Recording::factory()->make();
    $client = new TranscribeServiceClient([
        'version' => 'latest',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'k', 'secret' => 's'],
        'handler' => fn ($command) => Create::rejectionFor(new AwsException('conflict', $command, ['code' => 'ConflictException'])),
    ]);

    expect((new AwsTranscribeProvider($client))->start($recording))->toBe("case-event-{$recording->id}");
});

it('still throws for other Transcribe errors', function () {
    $client = new TranscribeServiceClient([
        'version' => 'latest',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'k', 'secret' => 's'],
        'handler' => fn ($command) => Create::rejectionFor(new AwsException('bad', $command, ['code' => 'BadRequestException'])),
    ]);

    (new AwsTranscribeProvider($client))->start(Recording::factory()->make());
})->throws(AwsException::class);

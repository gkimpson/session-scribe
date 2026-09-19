<?php

use App\Enums\SummaryLevel;
use App\Models\Transcript;
use App\Services\BedrockSummaryGenerator;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use GuzzleHttp\Promise\Create;

function generatorReplying(string $text, array &$sent): BedrockSummaryGenerator
{
    $client = new BedrockRuntimeClient([
        'version' => 'latest',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'k', 'secret' => 's'],
        'handler' => function ($command) use (&$sent, $text) {
            $sent[] = $command;

            return Create::promiseFor(new Result(['output' => ['message' => ['content' => [['text' => $text]]]]]));
        },
    ]);

    return new BedrockSummaryGenerator($client);
}

$sections = json_encode(['sections' => [
    ['heading' => 'What we discussed', 'body' => 'Asthma review.'],
    ['heading' => 'Decisions', 'body' => 'Same inhaler.'],
    ['heading' => 'Next steps', 'body' => 'Review in six weeks.'],
]]);

it('sends the transcript to the configured model and returns the sections', function () use ($sections) {
    $sent = [];
    $transcript = Transcript::factory()->make(['text' => "Speaker 1: Hello.\nSpeaker 2: Hi."]);

    $result = generatorReplying($sections, $sent)->generate($transcript, SummaryLevel::Normal);

    expect($result)->toHaveCount(3)
        ->and($result[0])->toBe(['heading' => 'What we discussed', 'body' => 'Asthma review.'])
        ->and($sent[0]['modelId'])->toBe('amazon.nova-lite-v1:0')
        ->and($sent[0]['messages'][0]['content'][0]['text'])->toContain('Speaker 1: Hello.');
});

it('asks for a different length at each level', function () use ($sections) {
    $prompts = [];
    foreach (SummaryLevel::cases() as $level) {
        $sent = [];
        generatorReplying($sections, $sent)->generate(Transcript::factory()->make(), $level);
        $prompts[$level->value] = $sent[0]['system'][0]['text'];
    }

    expect($prompts['brief'])->toContain('one or two short sentences')
        ->and($prompts['brief'])->toContain('Never assume roles')
        ->and($prompts['normal'])->toContain('short paragraph')
        ->and($prompts['detailed'])->toContain('in detail')
        ->and(array_unique($prompts))->toHaveCount(3);
});

it('accepts JSON wrapped in a code fence', function () use ($sections) {
    $sent = [];

    $result = generatorReplying("```json\n{$sections}\n```", $sent)->generate(Transcript::factory()->make(), SummaryLevel::Brief);

    expect($result)->toHaveCount(3);
});

it('throws when the model returns something unusable', function (string $reply) {
    $sent = [];

    generatorReplying($reply, $sent)->generate(Transcript::factory()->make(), SummaryLevel::Brief);
})->with(['plain text' => ['Sorry, I cannot do that.'], 'empty sections' => ['{"sections": []}']])
    ->throws(RuntimeException::class);

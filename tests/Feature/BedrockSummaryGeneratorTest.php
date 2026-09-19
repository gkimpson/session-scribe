<?php

use App\Enums\SummaryLevel;
use App\Exceptions\SummaryUnavailable;
use App\Models\Transcript;
use App\Services\BedrockSummaryGenerator;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use GuzzleHttp\Promise\Create;

function generatorReplying(string|array $reply, array &$sent): BedrockSummaryGenerator
{
    $content = is_array($reply) ? $reply : [['text' => $reply]];
    $client = new BedrockRuntimeClient([
        'version' => 'latest',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'k', 'secret' => 's'],
        'handler' => function ($command) use (&$sent, $content) {
            $sent[] = $command;

            return Create::promiseFor(new Result(['output' => ['message' => ['content' => $content]]]));
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

it('reads the summary from the tool call', function () {
    $sent = [];
    $reply = [['toolUse' => ['name' => 'save_summary', 'input' => ['sections' => [
        ['heading' => 'What we discussed', 'body' => 'Asthma review.'],
        ['heading' => 'Decisions', 'body' => 'Same inhaler.'],
    ]]]]];

    $result = generatorReplying($reply, $sent)->generate(Transcript::factory()->make(), SummaryLevel::Brief);

    expect($result)->toBe([
        ['heading' => 'What we discussed', 'body' => 'Asthma review.'],
        ['heading' => 'Decisions', 'body' => 'Same inhaler.'],
    ])
        ->and($sent[0]['toolConfig']['toolChoice'])->toBe(['tool' => ['name' => 'save_summary']])
        ->and($sent[0]['toolConfig']['tools'][0]['toolSpec']['inputSchema']['json']['required'])->toBe(['sections']);
});

it('refuses a transcript that is too long, without calling the model', function () {
    config(['recordings.summary.max_transcript_characters' => 100]);
    $sent = [];

    expect(fn () => generatorReplying('{}', $sent)->generate(Transcript::factory()->make(['text' => str_repeat('a', 101)]), SummaryLevel::Brief))
        ->toThrow(SummaryUnavailable::class, 'too long');
    expect($sent)->toBe([]);
});

it('refuses a transcript with no text, without calling the model', function () {
    $sent = [];

    expect(fn () => generatorReplying('{}', $sent)->generate(Transcript::factory()->make(['text' => '  ']), SummaryLevel::Brief))
        ->toThrow(SummaryUnavailable::class, 'no text');
    expect($sent)->toBe([]);
});

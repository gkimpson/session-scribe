<?php

use App\Support\TranscribeTranscriptFormatter;

function word(string $content, string $start): array
{
    return ['type' => 'pronunciation', 'start_time' => $start, 'end_time' => $start, 'alternatives' => [['content' => $content]]];
}

it('groups consecutive words by speaker and attaches punctuation', function () {
    $json = ['results' => [
        'speaker_labels' => ['segments' => [
            ['speaker_label' => 'spk_1', 'items' => [['start_time' => '0.1'], ['start_time' => '0.5']]],
            ['speaker_label' => 'spk_0', 'items' => [['start_time' => '1.0'], ['start_time' => '1.4']]],
        ]],
        'items' => [
            word('Hello', '0.1'),
            word('there', '0.5'),
            ['type' => 'punctuation', 'alternatives' => [['content' => '.']]],
            word('Hi', '1.0'),
            word('doctor', '1.4'),
            ['type' => 'punctuation', 'alternatives' => [['content' => '?']]],
        ],
    ]];

    expect(TranscribeTranscriptFormatter::turns($json))->toBe([
        ['speaker' => 'Speaker 1', 'text' => 'Hello there.'],
        ['speaker' => 'Speaker 2', 'text' => 'Hi doctor?'],
    ]);
});

it('falls back to a single turn when there are no speaker labels', function () {
    $json = ['results' => ['transcripts' => [['transcript' => 'Just some words.']], 'items' => []]];

    expect(TranscribeTranscriptFormatter::turns($json))->toBe([
        ['speaker' => 'Speaker 1', 'text' => 'Just some words.'],
    ]);
});

it('returns no turns for an empty transcript', function () {
    expect(TranscribeTranscriptFormatter::turns(['results' => ['transcripts' => [['transcript' => '']]]]))->toBe([]);
});

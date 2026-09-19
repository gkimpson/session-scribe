<?php

namespace App\Support;

/**
 * Turns Amazon Transcribe's JSON into speaker turns.
 */
class TranscribeTranscriptFormatter
{
    /**
     * @param  array<string, mixed>  $json
     * @return list<array{speaker: string, text: string}>
     */
    public static function turns(array $json): array
    {
        $results = $json['results'] ?? [];
        $speakerAt = [];

        foreach ($results['speaker_labels']['segments'] ?? [] as $segment) {
            foreach ($segment['items'] ?? [] as $item) {
                $speakerAt[$item['start_time']] = $segment['speaker_label'];
            }
        }

        if ($speakerAt === []) {
            $text = trim($results['transcripts'][0]['transcript'] ?? '');

            return $text === '' ? [] : [['speaker' => 'Speaker 1', 'text' => $text]];
        }

        $labels = [];
        $turns = [];
        $current = null;

        foreach ($results['items'] ?? [] as $item) {
            $content = $item['alternatives'][0]['content'] ?? '';

            if ($item['type'] === 'punctuation') {
                if ($current !== null) {
                    $turns[$current]['text'] .= $content;
                }

                continue;
            }

            $label = $speakerAt[$item['start_time']] ?? ($current !== null ? $turns[$current]['label'] : 'spk_0');

            if ($current === null || $turns[$current]['label'] !== $label) {
                $labels[$label] ??= 'Speaker '.(count($labels) + 1);
                $turns[] = ['label' => $label, 'speaker' => $labels[$label], 'text' => ''];
                $current = array_key_last($turns);
            }

            $turns[$current]['text'] .= ($turns[$current]['text'] === '' ? '' : ' ').$content;
        }

        return array_map(
            fn (array $turn) => ['speaker' => $turn['speaker'], 'text' => $turn['text']],
            $turns,
        );
    }
}

<?php

namespace App\Services;

use App\Contracts\SummaryGenerator;
use App\Enums\SummaryLevel;
use App\Models\Transcript;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use RuntimeException;

class BedrockSummaryGenerator implements SummaryGenerator
{
    public const HEADINGS = ['What we discussed', 'Decisions', 'Next steps'];

    public function __construct(private BedrockRuntimeClient $client) {}

    public function generate(Transcript $transcript, SummaryLevel $level): array
    {
        $response = $this->client->converse([
            'modelId' => config('recordings.summary.model_id'),
            'system' => [['text' => $this->systemPrompt($level)]],
            'messages' => [[
                'role' => 'user',
                'content' => [['text' => "Transcript:\n\n{$transcript->text}"]],
            ]],
            'inferenceConfig' => ['maxTokens' => $this->maxTokens($level), 'temperature' => 0.2],
        ]);

        $text = $response['output']['message']['content'][0]['text'] ?? '';

        return $this->parse($text);
    }

    private function systemPrompt(SummaryLevel $level): string
    {
        $length = match ($level) {
            SummaryLevel::Brief => 'Keep each section to one or two short sentences. Include only the key points.',
            SummaryLevel::Normal => 'Write each section as a short paragraph of full sentences covering the main points.',
            SummaryLevel::Detailed => 'Write each section in detail. Keep specifics such as numbers, names, reasons and any safety or follow-up wording that was said.',
        };

        $headings = implode('", "', self::HEADINGS);

        return <<<PROMPT
        You summarise a transcript of a conversation between two speakers.
        Use only what is in the transcript. Do not invent facts, names or numbers. If a section has nothing to report, say "Nothing was discussed."
        Refer to the people as "Speaker 1" and "Speaker 2" unless the transcript itself gives a name or role. Never assume roles such as doctor or patient.
        Write in British English.
        {$length}

        Reply with JSON only, no other text, in exactly this shape:
        {"sections": [{"heading": "{$this->firstHeading()}", "body": "..."}, ...]}
        The sections must be, in order: "{$headings}".
        PROMPT;
    }

    private function firstHeading(): string
    {
        return self::HEADINGS[0];
    }

    private function maxTokens(SummaryLevel $level): int
    {
        return match ($level) {
            SummaryLevel::Brief => 500,
            SummaryLevel::Normal => 900,
            SummaryLevel::Detailed => 1800,
        };
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    private function parse(string $text): array
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)));
        $data = json_decode($json, true);

        $sections = collect($data['sections'] ?? [])
            ->filter(fn ($section) => is_array($section) && filled($section['heading'] ?? null) && filled($section['body'] ?? null))
            ->map(fn (array $section) => [
                'heading' => (string) $section['heading'],
                'body' => trim((string) $section['body']),
            ])
            ->values()
            ->all();

        if ($sections === []) {
            throw new RuntimeException('The model did not return a usable summary.');
        }

        return $sections;
    }
}

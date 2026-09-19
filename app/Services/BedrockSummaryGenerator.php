<?php

namespace App\Services;

use App\Contracts\SummaryGenerator;
use App\Enums\SummaryLevel;
use App\Exceptions\SummaryUnavailable;
use App\Models\Transcript;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use RuntimeException;

class BedrockSummaryGenerator implements SummaryGenerator
{
    public const HEADINGS = ['What we discussed', 'Decisions', 'Next steps'];

    private const TOOL_NAME = 'save_summary';

    public function __construct(private BedrockRuntimeClient $client) {}

    public function generate(Transcript $transcript, SummaryLevel $level): array
    {
        $text = trim((string) $transcript->text);

        if ($text === '') {
            throw new SummaryUnavailable('There is no text in this transcript to summarise.');
        }

        if (mb_strlen($text) > config('recordings.summary.max_transcript_characters')) {
            throw new SummaryUnavailable('This transcript is too long to summarise.');
        }

        $response = $this->client->converse([
            'modelId' => config('recordings.summary.model_id'),
            'system' => [['text' => $this->systemPrompt($level)]],
            'messages' => [[
                'role' => 'user',
                'content' => [['text' => "Transcript:\n\n{$text}"]],
            ]],
            'toolConfig' => [
                'tools' => [['toolSpec' => $this->toolSpec()]],
                'toolChoice' => ['tool' => ['name' => self::TOOL_NAME]],
            ],
            'inferenceConfig' => ['maxTokens' => $this->maxTokens($level), 'temperature' => 0.2],
        ]);

        return $this->sectionsFrom($response['output']['message']['content'] ?? []);
    }

    /**
     * The model has to answer by calling this tool, so the reply always
     * arrives as structured data and never as text we have to parse.
     *
     * @return array<string, mixed>
     */
    private function toolSpec(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Save the finished summary of the conversation.',
            'inputSchema' => ['json' => [
                'type' => 'object',
                'properties' => [
                    'sections' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'heading' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                            ],
                            'required' => ['heading', 'body'],
                        ],
                    ],
                ],
                'required' => ['sections'],
            ]],
        ];
    }

    private function systemPrompt(SummaryLevel $level): string
    {
        $length = match ($level) {
            SummaryLevel::Brief => 'Keep each section to one or two short sentences. Include only the key points.',
            SummaryLevel::Normal => 'Write each section as a short paragraph of full sentences covering the main points.',
            SummaryLevel::Detailed => 'Write each section in detail. Keep specifics such as numbers, names, reasons and any safety or follow-up wording that was said.',
        };

        $headings = implode('", "', self::HEADINGS);
        $tool = self::TOOL_NAME;

        return <<<PROMPT
        You summarise a transcript of a conversation between two speakers.
        Use only what is in the transcript. Do not invent facts, names or numbers. If a section has nothing to report, say "Nothing was discussed."
        Refer to the people as "Speaker 1" and "Speaker 2" unless the transcript itself gives a name or role. Never assume roles such as doctor or patient.
        Write in British English.
        {$length}

        Give your answer by calling the {$tool} tool. The sections must be, in order: "{$headings}".
        PROMPT;
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
     * @param  array<int, array<string, mixed>>  $content
     * @return list<array{heading: string, body: string}>
     */
    private function sectionsFrom(array $content): array
    {
        $input = collect($content)->pluck('toolUse.input')->filter()->first();

        if ($input === null) {
            $input = $this->decodeText(collect($content)->pluck('text')->filter()->implode("\n"));
        }

        $sections = collect($input['sections'] ?? [])
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

    /**
     * Fallback for a model that answers in plain text instead of the tool.
     *
     * @return array<string, mixed>
     */
    private function decodeText(string $text): array
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)));

        return json_decode($json, true) ?? [];
    }
}

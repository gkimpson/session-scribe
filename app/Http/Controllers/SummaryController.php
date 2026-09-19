<?php

namespace App\Http\Controllers;

use App\Models\Summary;
use Illuminate\Http\JsonResponse;

class SummaryController extends Controller
{
    public function show(Summary $summary): JsonResponse
    {
        return response()->json(self::payload($summary));
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Summary $summary): array
    {
        return [
            'id' => $summary->uuid,
            'level' => $summary->level->value,
            'state' => $summary->state->value,
            'sections' => $summary->sections,
            'failure_reason' => $summary->failure_reason,
        ];
    }
}

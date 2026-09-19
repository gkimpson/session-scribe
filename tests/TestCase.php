<?php

namespace Tests;

use App\Contracts\RecordingStorage;
use App\Contracts\SummaryGenerator;
use App\Contracts\TranscriptionProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests must never reach AWS. A test that needs these fakes them itself.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([RecordingStorage::class, TranscriptionProvider::class, SummaryGenerator::class] as $contract) {
            $this->app->bind($contract, fn () => throw new LogicException("Tests must fake {$contract} instead of calling AWS."));
        }
    }
}

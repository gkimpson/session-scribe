<?php

namespace App\Providers;

use App\Contracts\RecordingStorage;
use App\Contracts\SummaryGenerator;
use App\Contracts\TranscriptionProvider;
use App\Services\AwsTranscribeProvider;
use App\Services\BedrockSummaryGenerator;
use App\Services\S3RecordingStorage;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\TranscribeService\TranscribeServiceClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RecordingStorage::class, S3RecordingStorage::class);

        $this->app->bind(TranscriptionProvider::class, fn () => new AwsTranscribeProvider(
            new TranscribeServiceClient([
                'version' => 'latest',
                'region' => config('services.aws.region'),
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]),
        ));

        $this->app->bind(SummaryGenerator::class, fn () => new BedrockSummaryGenerator(
            new BedrockRuntimeClient([
                'version' => 'latest',
                'region' => config('recordings.summary.region'),
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

<?php

namespace App\Support;

final readonly class PresignedUpload
{
    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function __construct(
        public string $url,
        public array $headers,
    ) {}
}

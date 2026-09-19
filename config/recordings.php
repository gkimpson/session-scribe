<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recording storage
    |--------------------------------------------------------------------------
    |
    | Audio goes straight from the browser to a private bucket using a
    | short-lived presigned PUT. The limits below match _docs/architecture.md.
    |
    */

    'disk' => env('RECORDINGS_DISK', 's3'),

    'key_prefix' => 'voice-transcripts',

    /*
    | Ask Transcribe to redact personal information (PII) from the transcript.
    | Each transcript records whether it was redacted, so changing this only
    | affects new recordings.
    */

    'redact_pii' => (bool) env('RECORDINGS_REDACT_PII', true),

    'max_bytes' => 250 * 1024 * 1024,

    'max_seconds' => 60 * 60,

    'presign_minutes' => 15,

    'mime_types' => [
        'audio/webm' => 'webm',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
    ],

];

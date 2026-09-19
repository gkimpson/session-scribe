<?php

namespace App\Enums;

enum RecordingState: string
{
    case PendingUpload = 'pending_upload';
    case Uploaded = 'uploaded';
    case TranscriptionQueued = 'transcription_queued';
    case Transcribing = 'transcribing';
    case Ready = 'ready';
    case Failed = 'failed';
}

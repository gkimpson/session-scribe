<?php

namespace App\Enums;

enum TranscriptionJobStatus: string
{
    case Queued = 'QUEUED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}

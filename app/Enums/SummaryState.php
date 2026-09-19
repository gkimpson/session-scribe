<?php

namespace App\Enums;

enum SummaryState: string
{
    case Queued = 'queued';
    case Summarising = 'summarising';
    case Complete = 'complete';
    case Failed = 'failed';
}

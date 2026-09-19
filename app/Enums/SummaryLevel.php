<?php

namespace App\Enums;

enum SummaryLevel: string
{
    case Brief = 'brief';
    case Normal = 'normal';
    case Detailed = 'detailed';
}

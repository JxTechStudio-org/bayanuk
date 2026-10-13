<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case UPLOADED = 'uploaded';
    case SCANNING = 'scanning';
    case EXTRACTING = 'extracting';
    case CLASSIFYING = 'classifying';
    case ANALYZING = 'analyzing';
    case INDEXING = 'indexing';
    case READY = 'ready';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
}

<?php

namespace App\Enums;

enum ExtractionMethod: string
{
    case TEXT_LAYER = 'text_layer';
    case OCR = 'ocr';
    case VISION = 'vision';
}
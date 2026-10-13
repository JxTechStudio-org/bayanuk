<?php

namespace App\Enums;

enum DocumentType: string
{
    case JOB_OFFER = 'job_offer';
    case EMPLOYMENT_CONTRACT = 'employment_contract';
    case OTHER = 'other';
}
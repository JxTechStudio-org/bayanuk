<?php

namespace App\Enums;

enum FindingCategory: string
{
    case NON_COMPETE = 'non_compete';
    case LONG_NOTICE = 'long_notice';
    case LONG_PROBATION = 'long_probation';
    case PENALTIES = 'penalties';
    case SALARY_MISMATCH = 'salary_mismatch';
    case MISSING_INFO = 'missing_info';
    case ONE_SIDED_CHANGE = 'one_sided_change';
    case WORKING_TIME = 'working_time';
    case OTHER = 'other';
}

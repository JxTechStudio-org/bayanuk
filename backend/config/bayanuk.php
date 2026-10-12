<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document File & Upload Limits
    |--------------------------------------------------------------------------
    |
    | Limits for document uploads based on Bayanuk MVP requirements.
    |
    */
    'limits' => [
        'max_file_size_mb' => (int) env('BAYANUK_MAX_FILE_SIZE_MB', 10),
        'max_pages' => (int) env('BAYANUK_MAX_PAGES', 30),
        'allowed_mimes' => ['pdf', 'docx'],
        'supported_document_types' => [
            'job_offer',
            'employment_contract',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Data Retention Periods
    |--------------------------------------------------------------------------
    |
    | Automatic deletion thresholds for documents, analyses, and chat logs.
    |
    */
    'retention' => [
        'guest_hours' => (int) env('BAYANUK_GUEST_RETENTION_HOURS', 24),
        'user_days' => (int) env('BAYANUK_USER_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | "Ask Your Document" Chat Limits
    |--------------------------------------------------------------------------
    |
    | Max questions allowed per document based on user authentication state.
    |
    */
    'chat' => [
        'guest_max_questions' => (int) env('BAYANUK_GUEST_MAX_QUESTIONS', 3),
        'user_max_questions' => (int) env('BAYANUK_USER_MAX_QUESTIONS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attention Point Rule Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds used by deterministic rule checks based on Saudi Labor Law.
    |
    */
    'thresholds' => [
        'long_probation_days' => (int) env('BAYANUK_THRESHOLD_PROBATION_DAYS', 90),
        'long_notice_period_days' => (int) env('BAYANUK_THRESHOLD_NOTICE_DAYS', 60),
        'max_weekly_working_hours' => (int) env('BAYANUK_THRESHOLD_WORKING_HOURS', 48),
        'default_non_compete_max_months' => (int) env('BAYANUK_THRESHOLD_NON_COMPETE_MONTHS', 12),
    ],

];

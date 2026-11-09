<?php

// Arabic validation messages for the fields the API validates today.
// Any rule not listed here falls back to the English message (fallback locale).
return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يرجى إدخال :attribute صحيح.',
    'max' => [
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
    ],
    'string' => 'يجب أن يكون :attribute نصاً.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'in' => 'قيمة :attribute المحددة غير صالحة.',

    'attributes' => [
        'email' => 'البريد الإلكتروني',
        'token' => 'الرمز',
        'locale' => 'اللغة',
        'keep_documents' => 'الاحتفاظ بالمستندات',
    ],
];

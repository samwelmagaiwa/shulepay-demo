<?php

/*
 * Display labels for backend enums. These reach the user in JSON resources
 * (e.g. PaymentResource.method_label) and on printed PDFs, so they follow the
 * language chosen in the SPA header — see App\Http\Middleware\SetLocale.
 */
return [
    'payment_method' => [
        'cash' => 'Taslimu',
        'mpesa' => 'M-Pesa',
        'bank' => 'Benki',
        'cheque' => 'Hundi',
        'sponsor' => 'Mfadhili',
    ],

    'invoice_status' => [
        'paid' => 'Amelipa',
        'partial' => 'Amelipa kiasi',
        'unpaid' => 'Hajalipa',
    ],

    'school_level' => [
        'primary' => 'Msingi',
        'secondary' => 'Sekondari',
    ],

    'user_role' => [
        'superadmin' => 'Msimamizi Mkuu',
        'owner' => 'Mkurugenzi',
        'accountant' => 'Muhasibu',
        'head_teacher' => 'Mwalimu Mkuu',
        'headmaster' => 'Mkuu wa Shule',
        'academic_pri' => 'Mratibu wa Masomo (Msingi)',
        'academic_sec' => 'Mratibu wa Masomo (Sekondari)',
        'teacher_pri' => 'Mwalimu wa Darasa (Msingi)',
        'teacher_sec' => 'Mwalimu wa Darasa (Sekondari)',
        'parent' => 'Mzazi / Mlezi',
    ],
];

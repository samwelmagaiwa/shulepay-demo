<?php

/*
 * Display labels for backend enums. These reach the user in JSON resources
 * (e.g. PaymentResource.method_label) and on printed PDFs, so they follow the
 * language chosen in the SPA header — see App\Http\Middleware\SetLocale.
 */
return [
    'payment_method' => [
        'cash' => 'Cash',
        'mpesa' => 'M-Pesa',
        'bank' => 'Bank',
        'cheque' => 'Cheque',
        'sponsor' => 'Sponsor',
    ],

    'invoice_status' => [
        'paid' => 'Paid',
        'partial' => 'Partially paid',
        'unpaid' => 'Unpaid',
    ],

    'school_level' => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
    ],

    'user_role' => [
        'superadmin' => 'Super Admin',
        'owner' => 'Owner',
        'accountant' => 'Accountant',
        'head_teacher' => 'Head Teacher',
        'headmaster' => 'Headmaster',
        'academic_pri' => 'Academic Coordinator (Primary)',
        'academic_sec' => 'Academic Coordinator (Secondary)',
        'teacher_pri' => 'Class Teacher (Primary)',
        'teacher_sec' => 'Class Teacher (Secondary)',
        'parent' => 'Parent / Guardian',
    ],
];

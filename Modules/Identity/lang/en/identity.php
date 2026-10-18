<?php

return [
    'account_disabled' => 'This account has been disabled. Please contact the academy administration.',
    'last_system_administrator' => 'This change would leave the system without an active System Administrator.',
    'immutable_role_permissions' => 'The System Administrator role permissions are fixed and cannot be changed.',
    'reset_link_requested' => 'If the email is registered, you will receive a password reset link.',

    'validation' => [
        'phone' => 'The phone number is invalid. Use the international format, e.g. +201001234567.',
    ],

    'reset_password' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello :name,',
        'intro' => 'We received a request to reset the password for your academy account.',
        'action' => 'Reset password',
        'expiry' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not request this, no further action is required.',
    ],
];

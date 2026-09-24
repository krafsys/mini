<?php

return [
    'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
    'port' => env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'), // 'tls' (587), 'ssl' (465), or '' (25/none)
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
    'from_name' => env('MAIL_FROM_NAME', 'Tiny MVC'),
];

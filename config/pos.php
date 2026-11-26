<?php

return [
    'currency' => env('POS_CURRENCY', 'PHP'),
    'display_timezone' => env('POS_DISPLAY_TIMEZONE', 'Asia/Manila'),
    'initial_username' => env('INITIAL_STAFF_USERNAME', 'admin'),
    'initial_name' => env('INITIAL_STAFF_NAME', 'Store Administrator'),
    'initial_password' => env('INITIAL_STAFF_PASSWORD'),
];

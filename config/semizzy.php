<?php

return [
    'finance_enabled' => filter_var(env('FINANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'admin/login'),
];

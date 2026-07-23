<?php
declare(strict_types=1);

return [
    'app_env' => getenv('ENV') ?: (getenv('APP_ENV') ?: 'production'),
    'app_name' => getenv('APP_NAME') ?: 'Sietelsa Online',
    'app_url' => getenv('APP_URL') ?: 'https://sietelsaonline.com',
    'app_timezone' => getenv('APP_TIMEZONE') ?: 'America/El_Salvador',
    'log_path' => getenv('APP_LOG_PATH') ?: (__DIR__ . '/../logs/app.log'),
];

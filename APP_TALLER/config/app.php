<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'APP_TALLER',
    'version' => getenv('APP_VERSION') ?: '1.0.0',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
];

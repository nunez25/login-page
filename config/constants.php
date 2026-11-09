<?php
declare(strict_types=1);

/**
 * Read an environment variable, falling back to $default when unset or empty.
 */
function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

// School identity: the ONLY place these are defined (values come from .env).
define('SCHOOL_NAME', env('SCHOOL_NAME', 'Silverwood Academy'));
define('SCHOOL_SHORT_NAME', env('SCHOOL_SHORT_NAME', 'Silverwood Academy'));
define('APP_NAME', env('APP_NAME', 'Silverwood Academy Student Information System'));

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

define('SESSION_NAME', 'HCCISESSID');

// Captcha
define('CAPTCHA_CHARSET', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'); // no 0/O, 1/I/l
define('CAPTCHA_TTL_SECONDS', 120);
define('CAPTCHA_FONT', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');

<?php

/*
 * Talk backend configuration.
 *
 * Values are read from environment variables first. If you do not use env vars,
 * you can edit the fallback values in this file directly.
 *
 * start.sh injects environment variables into PHP through Nginx FastCGI params.
 * To avoid Nginx template issues with special characters in passwords, start.sh
 * passes NAME_B64 values. This file also accepts normal NAME values.
 */

function talk_env(string $name, string $default = ''): string
{
    $encoded = getenv($name . '_B64');
    if ($encoded !== false && $encoded !== '') {
        $decoded = base64_decode($encoded, true);
        if ($decoded !== false) {
            return $decoded;
        }
    }

    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

function talk_env_int(string $name, int $default): int
{
    $value = talk_env($name, '');
    if ($value === '' || !is_numeric($value)) {
        return $default;
    }
    return (int)$value;
}

function talk_env_bool(string $name, bool $default = false): bool
{
    $value = talk_env($name, $default ? 'true' : 'false');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

//****************************************
// PLEASE SPECIFY THE VARIABLES BELOW
//****************************************

// Database host.
$DB_HOST = talk_env('DB_HOST', '');

// Database port.
$DB_PORT = talk_env_int('DB_PORT', 3306);

// Database name for Talk.
$DB_NAME = talk_env('DB_NAME', '');

// Database username.
$DB_USER = talk_env('DB_USER', '');

// Database password.
$DB_PASSWORD = talk_env('DB_PASSWORD', '');

/*
 * Trusted static frontend URL.
 *
 * This may contain a path. CORS origin will be derived from this URL.
 *
 * Example:
 *   https://abc.github.io/talk/
 *
 * Derived origin:
 *   https://abc.github.io
 */
$FRONTEND_URL = talk_env('FRONTEND_URL', 'https://abc.github.io/talk/');

/*
 * Keep false in production.
 *
 * Set true only for temporary CLI testing without an Origin header.
 */
$ALLOW_NO_ORIGIN_REQUESTS = talk_env_bool('ALLOW_NO_ORIGIN_REQUESTS', false);

// Default timezone.
date_default_timezone_set(talk_env('TZ', 'America/Los_Angeles'));

/*
 * Server-side pepper only.
 *
 * Do not change after you start using Talk. If you change this, existing links
 * cannot be opened because access-token HMAC verification will fail.
 */
$GLOBAL_SALT_3 = talk_env('GLOBAL_SALT_3', '*&Kjnskjnaucibiqb9298hv9sHIUWNiukJNIusfbic897*(^)');

//********************************************************************
// ADVANCED SETTINGS
//********************************************************************

// Maximum accepted encrypted payload size in bytes.
$MAX_CIPHERTEXT_BYTES = talk_env_int('MAX_CIPHERTEXT_BYTES', 1048576);

// Default message lifetime in days.
$DEFAULT_EXPIRE_DAYS = talk_env_int('DEFAULT_EXPIRE_DAYS', 5);

// Maximum message lifetime in days.
$MAX_EXPIRE_DAYS = talk_env_int('MAX_EXPIRE_DAYS', 30);

// KDF iteration bounds accepted from the frontend.
$MIN_PBKDF2_ITERATIONS = talk_env_int('MIN_PBKDF2_ITERATIONS', 100000);
$MAX_PBKDF2_ITERATIONS = talk_env_int('MAX_PBKDF2_ITERATIONS', 2000000);

// Rate limit window in seconds.
$RATE_LIMIT_WINDOW = talk_env_int('RATE_LIMIT_WINDOW', 300);

// Request limits per RATE_LIMIT_WINDOW.
$RATE_LIMIT_CREATE = talk_env_int('RATE_LIMIT_CREATE', 20);
$RATE_LIMIT_META = talk_env_int('RATE_LIMIT_META', 120);
$RATE_LIMIT_OPEN = talk_env_int('RATE_LIMIT_OPEN', 60);
$RATE_LIMIT_OPEN_CODE = talk_env_int('RATE_LIMIT_OPEN_CODE', 10);

// How many expired records can be removed during one cleanup pass.
$CLEANUP_LIMIT = talk_env_int('CLEANUP_LIMIT', 1000);

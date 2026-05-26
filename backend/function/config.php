<?php

//****************************************
// PLEASE SPECIFY THE VARIABLES BELOW
//****************************************

function talk_config_value($name, $default = '') {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    $b64_name = $name . '_B64';
    $b64_value = getenv($b64_name);
    if ($b64_value === false || $b64_value === '') {
        $b64_value = isset($_SERVER[$b64_name]) ? $_SERVER[$b64_name] : '';
    }
    if ($b64_value !== '') {
        $decoded = base64_decode($b64_value, true);
        if ($decoded !== false) {
            return $decoded;
        }
    }

    return $default;
}

// Database host.
$DB_HOST = talk_config_value('DB_HOST', '');

// Database port.
$DB_PORT = talk_config_value('DB_PORT', '3306');

// Database name for Talk.
$DB_NAME = talk_config_value('DB_NAME', '');

// Database username.
$DB_USER = talk_config_value('DB_USER', '');

// Database password.
$DB_PASSWORD = talk_config_value('DB_PASSWORD', '');

/*
 * Trusted static frontend URL.
 *
 * This may contain a path. CORS origin will be derived from this URL.
 *
 * Example:
 *   https://example.pages.dev/talk/
 *
 * Derived origin:
 *   https://example.pages.dev
 */
$FRONTEND_URL = talk_config_value('FRONTEND_URL', 'https://abc.pages.dev/');

/*
 * Keep false in production.
 *
 * Set true only for temporary CLI testing without an Origin header.
 */
$ALLOW_NO_ORIGIN_REQUESTS = filter_var(talk_config_value('ALLOW_NO_ORIGIN_REQUESTS', ''), FILTER_VALIDATE_BOOLEAN);

// Default timezone.
date_default_timezone_set(talk_config_value('TZ', 'America/Los_Angeles'));

/*
 * Server-side salt only.
 *
 * Do not change after you start using Talk, or existing messages cannot be opened.
 */
$GLOBAL_SALT_3 = talk_config_value('GLOBAL_SALT_3', '*&Kjnskjnaucibiqb9298hv9sHIUWNiukJNIusfbic897*(^)');

//********************************************************************
// ADVANCED SETTINGS
//********************************************************************

// Maximum encrypted ciphertext stored in MEDIUMBLOB. MySQL MEDIUMBLOB limit is 16777215 bytes.
$MAX_CIPHERTEXT_BYTES = talk_config_value('MAX_CIPHERTEXT_BYTES', '') !== '' ? (int)talk_config_value('MAX_CIPHERTEXT_BYTES') : 16777215;

// Short link code length.
$CODE_LENGTH = talk_config_value('CODE_LENGTH', '') !== '' ? (int)talk_config_value('CODE_LENGTH') : 8;

// Expire messages after this many days when the client does not send a value.
$DEFAULT_EXPIRE_DAYS = talk_config_value('DEFAULT_EXPIRE_DAYS', '') !== '' ? (int)talk_config_value('DEFAULT_EXPIRE_DAYS') : 1;

// Maximum expire days accepted from the client.
$MAX_EXPIRE_DAYS = talk_config_value('MAX_EXPIRE_DAYS', '') !== '' ? (int)talk_config_value('MAX_EXPIRE_DAYS') : 7;

// Simple application-level rate limits.
$CREATE_LIMIT_PER_HOUR = talk_config_value('CREATE_LIMIT_PER_HOUR', '') !== '' ? (int)talk_config_value('CREATE_LIMIT_PER_HOUR') : 120;
$CHECK_LIMIT_PER_HOUR = talk_config_value('CHECK_LIMIT_PER_HOUR', '') !== '' ? (int)talk_config_value('CHECK_LIMIT_PER_HOUR') : 600;

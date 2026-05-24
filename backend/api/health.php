<?php
require_once __DIR__ . '/../function/common.php';
api_bootstrap(false);

$warnings = [];
if ($GLOBALS['GLOBAL_SALT_3'] === '*&Kjnskjnaucibiqb9298hv9sHIUWNiukJNIusfbic897*(^)') {
    $warnings[] = 'GLOBAL_SALT_3 is still using the example fallback value.';
}

json_response([
    'success' => true,
    'service' => 'Talk backend',
    'time' => gmdate('c'),
    'warnings' => $warnings,
]);

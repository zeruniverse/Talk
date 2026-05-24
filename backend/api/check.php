<?php
require_once __DIR__ . '/../function/bootstrap.php';

global $CHECK_LIMIT_PER_HOUR;

talk_require_method('POST');
talk_rate_limit('check', $CHECK_LIMIT_PER_HOUR, 3600);

$body = talk_read_json_body();
$code = isset($body['code']) ? trim((string)$body['code']) : '';
$token = isset($body['token']) ? trim((string)$body['token']) : '';
if (!talk_validate_code($code) || $token === '') {
    talk_json(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$pdo = talk_db();
$stmt = $pdo->prepare('SELECT access_token_hmac FROM talk_messages WHERE code = ? AND expire_at > UTC_TIMESTAMP()');
$stmt->execute([$code]);
$row = $stmt->fetch();
if (!$row) {
    talk_json(['ok' => false, 'error' => 'Message not found or expired.'], 404);
}

if (!hash_equals($row['access_token_hmac'], talk_hmac_token($token))) {
    talk_json(['ok' => false, 'error' => 'Wrong passphrase.'], 403);
}

talk_json(['ok' => true]);

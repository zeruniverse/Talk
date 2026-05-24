<?php
require_once __DIR__ . '/../function/bootstrap.php';

talk_require_method('POST');
$body = talk_read_json_body();
$code = isset($body['code']) ? trim((string)$body['code']) : '';
$token = isset($body['token']) ? trim((string)$body['token']) : '';
if (!talk_validate_code($code) || $token === '') {
    talk_json(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$pdo = talk_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT ciphertext, ciphertext_bytes, access_token_hmac FROM talk_messages WHERE code = ? AND expire_at > UTC_TIMESTAMP() FOR UPDATE');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->rollBack();
        talk_json(['ok' => false, 'error' => 'Message not found, expired, or already opened.'], 404);
    }
    if (!hash_equals($row['access_token_hmac'], talk_hmac_token($token))) {
        $pdo->rollBack();
        talk_json(['ok' => false, 'error' => 'Wrong passphrase.'], 403);
    }

    $ciphertext = $row['ciphertext'];
    $bytes = (int)$row['ciphertext_bytes'];
    $delete = $pdo->prepare('DELETE FROM talk_messages WHERE code = ?');
    $delete->execute([$code]);
    $pdo->commit();

    http_response_code(200);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $bytes);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $ciphertext;
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    talk_json(['ok' => false, 'error' => 'Failed to open message.'], 500);
}

<?php
require_once __DIR__ . '/../function/bootstrap.php';

talk_require_method('GET');
talk_cleanup_expired();

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if (!talk_validate_code($code)) {
    talk_json(['ok' => false, 'error' => 'Invalid code.'], 400);
}

$pdo = talk_db();
$stmt = $pdo->prepare('SELECT code, salt, hint, kdf, pbkdf2_iterations, ciphertext_bytes, created_at, expire_at, multi_view FROM talk_messages WHERE code = ? AND expire_at > UTC_TIMESTAMP()');
$stmt->execute([$code]);
$row = $stmt->fetch();
if (!$row) {
    talk_json(['ok' => false, 'error' => 'Message not found or expired.'], 404);
}

talk_json([
    'ok' => true,
    'code' => $row['code'],
    'salt' => $row['salt'],
    'hint' => $row['hint'],
    'kdf' => $row['kdf'],
    'iterations' => (int)$row['pbkdf2_iterations'],
    'ciphertext_bytes' => (int)$row['ciphertext_bytes'],
    'created_at' => $row['created_at'],
    'expires_at' => $row['expire_at'],
    'multi_view' => (bool)$row['multi_view'],
]);

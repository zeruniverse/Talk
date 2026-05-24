<?php
require_once __DIR__ . '/../function/common.php';
api_bootstrap(true);

try {
    $pdo = db();
    cleanup_expired($pdo);
    rate_limit_named('meta');

    $code = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $code = clean_string($_GET['code'] ?? '', 24);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $data = read_json_body();
        $code = clean_string($data['code'] ?? '', 24);
    } else {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    }

    if (!validate_code($code)) {
        json_response(['success' => false, 'error' => 'Invalid code.'], 400);
    }

    $stmt = $pdo->prepare('SELECT code, hint, one_time, kdf, iterations, salt, expires_at FROM talk_messages WHERE code = ? AND expires_at > UTC_TIMESTAMP()');
    $stmt->execute([$code]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['success' => false, 'error' => 'Message not found or expired.'], 404);
    }

    json_response(['success' => true, 'message' => public_message_row($row)]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Server error.'], 500);
}

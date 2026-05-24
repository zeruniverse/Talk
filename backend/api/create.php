<?php
require_once __DIR__ . '/../function/common.php';
api_bootstrap(true);
require_method('POST');

try {
    $pdo = db();
    cleanup_expired($pdo);
    rate_limit_named('create');

    global $MAX_CIPHERTEXT_BYTES, $DEFAULT_EXPIRE_DAYS, $MAX_EXPIRE_DAYS, $MIN_PBKDF2_ITERATIONS, $MAX_PBKDF2_ITERATIONS;

    $data = read_json_body();
    $ciphertext = clean_string($data['ciphertext'] ?? '', $MAX_CIPHERTEXT_BYTES);
    $iv = clean_string($data['iv'] ?? '', 128);
    $salt = clean_string($data['salt'] ?? '', 128);
    $accessToken = clean_string($data['accessToken'] ?? '', 256);
    $hint = clean_string($data['hint'] ?? '', 255);
    $kdf = clean_string($data['kdf'] ?? 'PBKDF2-SHA256', 32);
    $iterations = isset($data['iterations']) ? (int)$data['iterations'] : 0;
    $oneTime = !empty($data['oneTime']) ? 1 : 0;
    $expireDays = isset($data['expireDays']) ? (int)$data['expireDays'] : (int)$DEFAULT_EXPIRE_DAYS;

    if ($kdf !== 'PBKDF2-SHA256') {
        json_response(['success' => false, 'error' => 'Unsupported KDF.'], 400);
    }
    if ($iterations < $MIN_PBKDF2_ITERATIONS || $iterations > $MAX_PBKDF2_ITERATIONS) {
        json_response(['success' => false, 'error' => 'Invalid PBKDF2 iteration count.'], 400);
    }
    if (!validate_base64url_string($ciphertext, 16, (int)$MAX_CIPHERTEXT_BYTES)) {
        json_response(['success' => false, 'error' => 'Invalid ciphertext.'], 400);
    }
    if (!validate_base64url_string($iv, 8, 128) || !validate_base64url_string($salt, 8, 128)) {
        json_response(['success' => false, 'error' => 'Invalid encryption metadata.'], 400);
    }
    if (!validate_base64url_string($accessToken, 16, 256)) {
        json_response(['success' => false, 'error' => 'Invalid access token.'], 400);
    }

    $expireDays = max(1, min((int)$MAX_EXPIRE_DAYS, $expireDays));
    $accessHmac = access_token_hmac($accessToken);

    $code = '';
    for ($i = 0; $i < 10; $i++) {
        $candidate = random_code(12);
        $check = $pdo->prepare('SELECT 1 FROM talk_messages WHERE code = ?');
        $check->execute([$candidate]);
        if (!$check->fetch()) {
            $code = $candidate;
            break;
        }
    }

    if ($code === '') {
        json_response(['success' => false, 'error' => 'Unable to allocate a message code.'], 500);
    }

    $stmt = $pdo->prepare('INSERT INTO talk_messages (code, ciphertext, iv, salt, kdf, iterations, access_token_hmac, hint, one_time, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY))');
    $stmt->execute([$code, $ciphertext, $iv, $salt, $kdf, $iterations, $accessHmac, $hint, $oneTime, $expireDays]);

    json_response([
        'success' => true,
        'code' => $code,
        'expiresInDays' => $expireDays,
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Server error.'], 500);
}

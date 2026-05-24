<?php
require_once __DIR__ . '/../function/common.php';
api_bootstrap(true);
require_method('POST');

try {
    $pdo = db();
    cleanup_expired($pdo);
    rate_limit_named('open');

    $data = read_json_body();
    $code = clean_string($data['code'] ?? '', 24);
    $accessToken = clean_string($data['accessToken'] ?? '', 256);

    if (!validate_code($code)) {
        json_response(['success' => false, 'error' => 'Invalid code.'], 400);
    }
    if (!validate_base64url_string($accessToken, 16, 256)) {
        json_response(['success' => false, 'error' => 'Invalid access token.'], 400);
    }

    rate_limit_named('open_code', $code);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM talk_messages WHERE code = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->commit();
            json_response(['success' => false, 'error' => 'Message not found or expired.'], 404);
        }

        $expected = $row['access_token_hmac'];
        $actual = access_token_hmac($accessToken);
        if (!hash_equals($expected, $actual)) {
            $pdo->commit();
            json_response(['success' => false, 'error' => 'Wrong passphrase or message not found.'], 403);
        }

        if ((int)$row['one_time'] === 1) {
            $delete = $pdo->prepare('DELETE FROM talk_messages WHERE code = ?');
            $delete->execute([$code]);
        } else {
            $update = $pdo->prepare('UPDATE talk_messages SET opened_at = UTC_TIMESTAMP() WHERE code = ?');
            $update->execute([$code]);
        }

        $pdo->commit();

        json_response([
            'success' => true,
            'message' => [
                'code' => $row['code'],
                'ciphertext' => $row['ciphertext'],
                'iv' => $row['iv'],
                'salt' => $row['salt'],
                'kdf' => $row['kdf'],
                'iterations' => (int)$row['iterations'],
                'oneTime' => ((int)$row['one_time']) === 1,
            ],
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Server error.'], 500);
}

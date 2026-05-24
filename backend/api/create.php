<?php
require_once __DIR__ . '/../function/bootstrap.php';

global $MAX_CIPHERTEXT_BYTES, $PBKDF2_ITERATIONS, $CODE_LENGTH, $DEFAULT_EXPIRE_DAYS, $MAX_EXPIRE_DAYS, $CREATE_LIMIT_PER_HOUR;

talk_require_method('POST');
talk_rate_limit('create', $CREATE_LIMIT_PER_HOUR, 3600);
talk_cleanup_expired();

$salt = isset($_POST['salt']) ? trim((string)$_POST['salt']) : '';
$token = isset($_POST['token']) ? trim((string)$_POST['token']) : '';
$hint = isset($_POST['hint']) ? (string)$_POST['hint'] : '';
$kdf = isset($_POST['kdf']) ? trim((string)$_POST['kdf']) : 'PBKDF2-SHA256';
$iterations = isset($_POST['iterations']) ? (int)$_POST['iterations'] : (int)$PBKDF2_ITERATIONS;
$expire_days = isset($_POST['expires_days']) ? (int)$_POST['expires_days'] : (int)$DEFAULT_EXPIRE_DAYS;
$multi_view = isset($_POST['multi_view']) && in_array((string)$_POST['multi_view'], ['1', 'true', 'yes', 'on'], true) ? 1 : 0;

if ($salt === '' || strlen($salt) > 128 || !preg_match('/^[A-Za-z0-9_\-]+$/', $salt)) {
    talk_json(['ok' => false, 'error' => 'Invalid salt.'], 400);
}
if ($token === '' || strlen($token) > 256 || !preg_match('/^[A-Za-z0-9_\-]+$/', $token)) {
    talk_json(['ok' => false, 'error' => 'Invalid access token.'], 400);
}
if ($kdf !== 'PBKDF2-SHA256') {
    talk_json(['ok' => false, 'error' => 'Unsupported KDF.'], 400);
}
if ($iterations < 100000 || $iterations > 2000000) {
    talk_json(['ok' => false, 'error' => 'Invalid PBKDF2 iterations.'], 400);
}
if ($expire_days < 1) {
    $expire_days = 1;
}
if ($expire_days > $MAX_EXPIRE_DAYS) {
    $expire_days = (int)$MAX_EXPIRE_DAYS;
}
if (strlen($hint) > 255) {
    $hint = function_exists('mb_substr') ? mb_substr($hint, 0, 255, 'UTF-8') : substr($hint, 0, 255);
}

if (!isset($_FILES['ciphertext']) || !is_uploaded_file($_FILES['ciphertext']['tmp_name'])) {
    talk_json(['ok' => false, 'error' => 'Missing ciphertext blob.'], 400);
}
$size = (int)$_FILES['ciphertext']['size'];
if ($size <= 0 || $size > $MAX_CIPHERTEXT_BYTES) {
    talk_json(['ok' => false, 'error' => 'Ciphertext is too large.'], 413);
}
$ciphertext = file_get_contents($_FILES['ciphertext']['tmp_name']);
if ($ciphertext === false || strlen($ciphertext) !== $size) {
    talk_json(['ok' => false, 'error' => 'Failed to read ciphertext blob.'], 400);
}

$pdo = talk_db();
$expire_at = gmdate('Y-m-d H:i:s', time() + $expire_days * 86400);
$token_hmac = talk_hmac_token($token);
$length = max(3, min(24, (int)$CODE_LENGTH));

try {
    for ($i = 0; $i < 16; $i++) {
        $code = talk_make_code($length);
        $stmt = $pdo->prepare('SELECT 1 FROM talk_messages WHERE code = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetchColumn()) {
            break;
        }
        $code = '';
    }
    if ($code === '') {
        talk_json(['ok' => false, 'error' => 'Failed to allocate a message code.'], 500);
    }

    $stmt = $pdo->prepare('INSERT INTO talk_messages (code, ciphertext, ciphertext_bytes, salt, access_token_hmac, hint, kdf, pbkdf2_iterations, expire_at, multi_view) VALUES (:code, :ciphertext, :ciphertext_bytes, :salt, :access_token_hmac, :hint, :kdf, :pbkdf2_iterations, :expire_at, :multi_view)');
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
    $stmt->bindValue(':ciphertext', $ciphertext, PDO::PARAM_LOB);
    $stmt->bindValue(':ciphertext_bytes', $size, PDO::PARAM_INT);
    $stmt->bindValue(':salt', $salt, PDO::PARAM_STR);
    $stmt->bindValue(':access_token_hmac', $token_hmac, PDO::PARAM_STR);
    $stmt->bindValue(':hint', $hint, PDO::PARAM_STR);
    $stmt->bindValue(':kdf', $kdf, PDO::PARAM_STR);
    $stmt->bindValue(':pbkdf2_iterations', $iterations, PDO::PARAM_INT);
    $stmt->bindValue(':expire_at', $expire_at, PDO::PARAM_STR);
    $stmt->bindValue(':multi_view', $multi_view, PDO::PARAM_INT);
    $stmt->execute();

    talk_json(['ok' => true, 'code' => $code, 'expires_at' => $expire_at, 'ciphertext_bytes' => $size, 'multi_view' => $multi_view === 1]);
} catch (Throwable $e) {
    talk_json(['ok' => false, 'error' => 'Failed to save message.'], 500);
}

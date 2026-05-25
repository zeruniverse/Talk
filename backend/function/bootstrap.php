<?php
require_once __DIR__ . '/config.php';

function talk_frontend_origin() {
    global $FRONTEND_URL;
    $parts = parse_url($FRONTEND_URL);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (isset($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }
    return $origin;
}

function talk_apply_cors() {
    global $ALLOW_NO_ORIGIN_REQUESTS;
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $trusted = talk_frontend_origin();

    if ($origin !== '' && $trusted !== '' && strtolower($origin) === strtolower($trusted)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } elseif ($ALLOW_NO_ORIGIN_REQUESTS) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Origin is not allowed.']);
        exit;
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

talk_apply_cors();

function talk_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function talk_require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        talk_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

function talk_read_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        talk_json(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }
    return $data;
}

function talk_db() {
    static $pdo = null;
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($DB_HOST === '' || $DB_NAME === '' || $DB_USER === '') {
        talk_json(['ok' => false, 'error' => 'Database is not configured.'], 500);
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $DB_HOST, (int)$DB_PORT, $DB_NAME);
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (Throwable $e) {
        talk_json(['ok' => false, 'error' => 'Database connection failed.'], 500);
    }
}

function talk_validate_code($code) {
    return is_string($code) && preg_match('/^[a-z0-9]{3,24}$/', $code);
}

function talk_make_code($length) {
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function talk_hmac_token($token) {
    global $GLOBAL_SALT_3;
    return hash_hmac('sha256', (string)$token, $GLOBAL_SALT_3);
}

function talk_client_ip_bucket() {
    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
    $ip = explode(',', $ip)[0];
    return hash('sha256', trim($ip));
}

function talk_rate_limit($scope, $limit, $seconds) {
    if ($limit <= 0) {
        return;
    }
    $pdo = talk_db();
    $bucket = talk_client_ip_bucket();
    $now = gmdate('Y-m-d H:i:s');
    $reset = gmdate('Y-m-d H:i:s', time() + $seconds);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT hits, reset_at FROM talk_rate_limits WHERE scope = ? AND bucket = ? FOR UPDATE');
        $stmt->execute([$scope, $bucket]);
        $row = $stmt->fetch();
        if (!$row || $row['reset_at'] <= $now) {
            $stmt = $pdo->prepare('REPLACE INTO talk_rate_limits (scope, bucket, hits, reset_at) VALUES (?, ?, 1, ?)');
            $stmt->execute([$scope, $bucket, $reset]);
            $pdo->commit();
            return;
        }
        if ((int)$row['hits'] >= $limit) {
            $pdo->commit();
            talk_json(['ok' => false, 'error' => 'Too many requests. Try again later.'], 429);
        }
        $stmt = $pdo->prepare('UPDATE talk_rate_limits SET hits = hits + 1 WHERE scope = ? AND bucket = ?');
        $stmt->execute([$scope, $bucket]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Do not make the service unavailable only because the rate-limit table failed.
    }
}

function talk_cleanup_expired($probability = 20) {
    if (random_int(1, $probability) !== 1) {
        return;
    }
    try {
        $pdo = talk_db();
        $pdo->prepare('DELETE FROM talk_messages WHERE expire_at <= UTC_TIMESTAMP()')->execute();
        $pdo->prepare('DELETE FROM talk_rate_limits WHERE reset_at <= UTC_TIMESTAMP()')->execute();
    } catch (Throwable $e) {
        // Best-effort cleanup only.
    }
}

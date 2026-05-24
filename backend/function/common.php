<?php
require_once __DIR__ . '/db.php';

function apply_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    header('Cache-Control: no-store, max-age=0');
}

function frontend_origin(): string
{
    global $FRONTEND_URL;
    $parts = parse_url($FRONTEND_URL);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (!empty($parts['port'])) {
        $origin .= ':' . (int)$parts['port'];
    }
    return $origin;
}

function apply_cors_headers(): void
{
    $trusted = frontend_origin();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '' && $trusted !== '' && hash_equals($trusted, $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
    }
}

function require_trusted_origin(): void
{
    global $ALLOW_NO_ORIGIN_REQUESTS;

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $trusted = frontend_origin();

    if ($method === 'OPTIONS') {
        if ($origin !== '' && $trusted !== '' && hash_equals($trusted, $origin)) {
            http_response_code(204);
            exit;
        }
        json_response(['success' => false, 'error' => 'Origin is not allowed.'], 403);
    }

    if ($origin === '') {
        if ($ALLOW_NO_ORIGIN_REQUESTS) {
            return;
        }
        json_response(['success' => false, 'error' => 'Missing Origin header.'], 403);
    }

    if ($trusted === '' || !hash_equals($trusted, $origin)) {
        json_response(['success' => false, 'error' => 'Origin is not allowed.'], 403);
    }
}

function api_bootstrap(bool $require_origin = true): void
{
    apply_security_headers();
    apply_cors_headers();
    if ($require_origin) {
        require_trusted_origin();
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['success' => false, 'error' => 'Invalid JSON body.'], 400);
    }
    return $data;
}

function require_method(string $method): void
{
    $actual = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($actual !== $method) {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    }
}

function clean_string($value, int $maxLen): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen, 'UTF-8');
    }
    return substr($value, 0, $maxLen);
}

function validate_code(string $code): bool
{
    return preg_match('/^[A-Za-z0-9_-]{8,24}$/', $code) === 1;
}

function validate_base64url_string(string $value, int $minLen, int $maxLen): bool
{
    $len = strlen($value);
    if ($len < $minLen || $len > $maxLen) {
        return false;
    }
    return preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1;
}

function random_code(int $bytes = 12): string
{
    $raw = random_bytes($bytes);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function client_ip(): string
{
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if ($first !== '') {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function cleanup_expired(?PDO $pdo = null): void
{
    global $CLEANUP_LIMIT;
    $pdo = $pdo ?: db();
    $limit = max(1, min(10000, (int)$CLEANUP_LIMIT));
    $pdo->exec('DELETE FROM talk_messages WHERE expires_at <= UTC_TIMESTAMP() LIMIT ' . $limit);
    $pdo->exec('DELETE FROM talk_rate_limits WHERE reset_at <= UTC_TIMESTAMP() LIMIT ' . $limit);
}

function rate_limit_named(string $scope, string $extra = ''): void
{
    global $RATE_LIMIT_CREATE, $RATE_LIMIT_META, $RATE_LIMIT_OPEN, $RATE_LIMIT_OPEN_CODE, $RATE_LIMIT_WINDOW;

    $limits = [
        'create' => $RATE_LIMIT_CREATE,
        'meta' => $RATE_LIMIT_META,
        'open' => $RATE_LIMIT_OPEN,
        'open_code' => $RATE_LIMIT_OPEN_CODE,
    ];

    $limit = $limits[$scope] ?? 60;
    rate_limit($scope, $extra, (int)$limit, (int)$RATE_LIMIT_WINDOW);
}

function rate_limit(string $scope, string $extra, int $limit, int $windowSeconds): void
{
    global $GLOBAL_SALT_3;

    if ($limit <= 0 || $windowSeconds <= 0) {
        return;
    }

    $pdo = db();
    $bucketInput = $scope . '|' . client_ip() . '|' . $extra;
    $bucket = hash_hmac('sha256', $bucketInput, $GLOBAL_SALT_3);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT hits, reset_at FROM talk_rate_limits WHERE bucket = ? FOR UPDATE');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $pdo->prepare('INSERT INTO talk_rate_limits (bucket, hits, reset_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))');
            $insert->execute([$bucket, $windowSeconds]);
            $pdo->commit();
            return;
        }

        $expired = strtotime($row['reset_at'] . ' UTC') <= time();
        if ($expired) {
            $update = $pdo->prepare('UPDATE talk_rate_limits SET hits = 1, reset_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND) WHERE bucket = ?');
            $update->execute([$windowSeconds, $bucket]);
            $pdo->commit();
            return;
        }

        if ((int)$row['hits'] >= $limit) {
            $pdo->commit();
            json_response(['success' => false, 'error' => 'Too many requests. Please try again later.'], 429);
        }

        $update = $pdo->prepare('UPDATE talk_rate_limits SET hits = hits + 1 WHERE bucket = ?');
        $update->execute([$bucket]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function access_token_hmac(string $accessToken): string
{
    global $GLOBAL_SALT_3;
    return hash_hmac('sha256', $accessToken, $GLOBAL_SALT_3);
}

function public_message_row(array $row): array
{
    return [
        'code' => $row['code'],
        'hint' => $row['hint'],
        'oneTime' => ((int)$row['one_time']) === 1,
        'kdf' => $row['kdf'],
        'iterations' => (int)$row['iterations'],
        'salt' => $row['salt'],
        'expiresAt' => $row['expires_at'],
    ];
}

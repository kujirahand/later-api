<?php

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const USERS_DIR = DATA_DIR . '/users';
const USERS_DB = DATA_DIR . '/users.db';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensureStorage(): void
{
    ensureDir(DATA_DIR, 'Failed to create data directory');
    ensureDir(USERS_DIR, 'Failed to create users directory');
}

function ensureDir(string $path, string $errorMessage): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        respond(['error' => $errorMessage], 500);
    }
}

function usersDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ensureStorage();

    $pdo = new PDO('sqlite:' . USERS_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            api_key TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function bucketIndex(int $userId): int
{
    if ($userId < 1) {
        respond(['error' => 'Invalid user id'], 500);
    }

    return intdiv($userId - 1, 10);
}

function userDataDb(int $userId): PDO
{
    static $connections = [];
    $bucket = bucketIndex($userId);

    if (isset($connections[$bucket]) && $connections[$bucket] instanceof PDO) {
        return $connections[$bucket];
    }

    $path = USERS_DIR . '/user-' . $bucket . '.db';

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sync_data (
            user_id INTEGER PRIMARY KEY,
            data TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $connections[$bucket] = $pdo;

    return $connections[$bucket];
}

function normalizeEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

function parseRequestBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            respond(['error' => 'Invalid JSON body'], 400);
        }

        return $decoded;
    }

    return $_POST;
}

function getAuthorizationHeader(): string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (mb_strtolower((string) $name) === 'authorization') {
                    return (string) $value;
                }
            }
        }
    }

    return '';
}

function apiKeyFromRequest(array $body): string
{
    $apiKey = trim((string) ($body['api_key'] ?? ''));
    if ($apiKey !== '') {
        return $apiKey;
    }

    $apiKeyHeader = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($apiKeyHeader !== '') {
        return $apiKeyHeader;
    }

    $authorization = getAuthorizationHeader();
    if (preg_match('/^Bearer\\s+(\\S+)$/i', $authorization, $matches) === 1) {
        return trim($matches[1]);
    }

    return '';
}

function issueApiKey(): string
{
    return bin2hex(random_bytes(24));
}

function findOrCreateUser(string $email): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Invalid email address'], 400);
    }

    $pdo = usersDb();
    $now = gmdate(DATE_ATOM);
    $email = normalizeEmail($email);

    $select = $pdo->prepare('SELECT id, email, api_key FROM users WHERE email = :email');
    $select->execute([':email' => $email]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);
    if ($existing !== false) {
        return $existing;
    }

    $apiKey = issueApiKey();
    $insert = $pdo->prepare(
        'INSERT INTO users (email, api_key, created_at, updated_at) VALUES (:email, :api_key, :created_at, :updated_at)'
    );
    $insert->execute([
        ':email' => $email,
        ':api_key' => $apiKey,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'email' => $email,
        'api_key' => $apiKey,
    ];
}

function userByApiKey(string $apiKey): array
{
    $stmt = usersDb()->prepare('SELECT id, email, api_key FROM users WHERE api_key = :api_key');
    $stmt->execute([':api_key' => trim($apiKey)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        respond(['error' => 'Invalid API key'], 401);
    }

    return $user;
}

function upsertUserData(int $userId, array $data): string
{
    $pdo = userDataDb($userId);
    $now = gmdate(DATE_ATOM);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        respond(['error' => 'Failed to encode data'], 500);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sync_data (user_id, data, updated_at)
         VALUES (:user_id, :data, :updated_at)
         ON CONFLICT(user_id) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':data' => $json,
        ':updated_at' => $now,
    ]);

    return $now;
}

function readUserData(int $userId): array
{
    $stmt = userDataDb($userId)->prepare('SELECT data, updated_at FROM sync_data WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return ['data' => [], 'updated_at' => null];
    }

    $decoded = json_decode((string) $row['data'], true);
    return [
        'data' => is_array($decoded) ? $decoded : [],
        'updated_at' => $row['updated_at'],
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'status';
    $body = parseRequestBody();

    if ($action === 'status') {
        respond(['ok' => true, 'service' => 'later-api']);
    }

    if ($action === 'register') {
        if ($method !== 'POST') {
            respond(['error' => 'Use POST for register'], 405);
        }

        $email = (string) ($body['email'] ?? '');
        $user = findOrCreateUser($email);

        respond([
            'ok' => true,
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'api_key' => $user['api_key'],
            'bucket' => bucketIndex((int) $user['id']),
        ]);
    }

    if ($action === 'sync') {
        $apiKey = apiKeyFromRequest($body);
        if ($apiKey === '') {
            respond(['error' => 'api_key is required'], 400);
        }

        $user = userByApiKey($apiKey);
        $userId = (int) $user['id'];

        if ($method === 'GET') {
            $stored = readUserData($userId);
            respond([
                'ok' => true,
                'user_id' => $userId,
                'email' => $user['email'],
                'bucket' => bucketIndex($userId),
                'data' => $stored['data'],
                'updated_at' => $stored['updated_at'],
            ]);
        }

        if ($method !== 'POST') {
            respond(['error' => 'Use GET or POST for sync'], 405);
        }

        $payload = $body['data'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                respond(['error' => 'data must be valid JSON object/array'], 400);
            }
            $payload = $decoded;
        }

        if (!is_array($payload)) {
            respond(['error' => 'data is required and must be object/array'], 400);
        }

        $updatedAt = upsertUserData($userId, $payload);
        respond([
            'ok' => true,
            'user_id' => $userId,
            'bucket' => bucketIndex($userId),
            'updated_at' => $updatedAt,
        ]);
    }

    respond(['error' => 'Unknown action'], 404);
} catch (Throwable $e) {
    error_log((string) $e);
    respond(['error' => 'Server error'], 500);
}

<?php

declare(strict_types=1);

session_start();

const USERS_DB_PATH = __DIR__ . '/data/users.db';

function ensureWebStorage(): void
{
    $dataDir = __DIR__ . '/data';
    $usersDir = $dataDir . '/users';

    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    if (!is_dir($usersDir)) {
        mkdir($usersDir, 0775, true);
    }
}

function usersWebDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ensureWebStorage();

    $pdo = new PDO('sqlite:' . USERS_DB_PATH);
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

function normalizeEmailWeb(string $email): string
{
    return mb_strtolower(trim($email));
}

function createApiKeyWeb(): string
{
    return bin2hex(random_bytes(24));
}

function getOrCreateWebUser(string $email): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('有効なメールアドレスを入力してください。');
    }

    $email = normalizeEmailWeb($email);
    $pdo = usersWebDb();

    $find = $pdo->prepare('SELECT id, email, api_key FROM users WHERE email = :email');
    $find->execute([':email' => $email]);
    $user = $find->fetch(PDO::FETCH_ASSOC);

    if ($user !== false) {
        return $user;
    }

    $now = gmdate(DATE_ATOM);
    $apiKey = createApiKeyWeb();
    $insert = $pdo->prepare(
        'INSERT INTO users (email, api_key, created_at, updated_at) VALUES (:email, :api_key, :created_at, :updated_at)'
    );
    $insert->execute([
        ':email' => $email,
        ':api_key' => $apiKey,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return ['id' => (int) $pdo->lastInsertId(), 'email' => $email, 'api_key' => $apiKey];
}

$user = null;
$error = null;
$csrfToken = (string) ($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(24));
    $_SESSION['csrf_token'] = $csrfToken;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $error = '不正なリクエストです。';
    }

    $email = (string) ($_POST['email'] ?? '');
    if ($error === null) {
        try {
            $user = getOrCreateWebUser($email);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>later-api web</title>
</head>
<body>
  <h1>later-api Webサービス</h1>
  <p>メールアドレス認証でAPIキーを発行し、<code>/api.php</code> でlater-cli同期に利用します。</p>

  <form method="post" action="/index.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label for="email">メールアドレス</label>
    <input id="email" name="email" type="email" required>
    <button type="submit">APIキーを発行/表示</button>
  </form>

  <?php if ($error !== null): ?>
    <p style="color: #b00020;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <?php if (is_array($user)): ?>
    <h2>認証情報</h2>
    <ul>
      <li>User ID: <?= htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8') ?></li>
      <li>Email: <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></li>
      <li>API Key: <code><?= htmlspecialchars((string) $user['api_key'], ENT_QUOTES, 'UTF-8') ?></code></li>
    </ul>
  <?php endif; ?>
</body>
</html>

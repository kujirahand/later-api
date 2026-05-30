<?php
/**
 * Later API Shared Helper Functions & Database Connections
 */

declare(strict_types=1);

require_once __DIR__ . '/lang.php';

// Load configuration
function get_config(): array {
    static $config = null;
    if ($config === null) {
        $configPath = __DIR__ . '/config.inc.php';
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/config.tpl.php';
        }
        $config = require $configPath;
    }
    return $config;
}

// Get main users DB PDO connection
function get_db_connection(): PDO {
    $config = get_config();
    $dbPath = $config['db']['users_db'];
    
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $exists = file_exists($dbPath);
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable Write-Ahead Logging for better SQLite concurrency
    $pdo->exec('PRAGMA journal_mode = WAL;');
    
    if (!$exists) {
        $sql = file_get_contents(__DIR__ . '/users.sql');
        $pdo->exec($sql);
    }
    migrate_main_db($pdo);
    
    return $pdo;
}

function migrate_main_db(PDO $pdo): void {
    $userColumns = get_table_columns($pdo, 'users');
    if (!isset($userColumns['passcode_expires_at'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN passcode_expires_at DATETIME DEFAULT NULL");
    }
    if (!isset($userColumns['passcode_attempts'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN passcode_attempts INTEGER NOT NULL DEFAULT 0");
    }

    $apiKeyColumns = get_table_columns($pdo, 'api_keys');
    if (isset($apiKeyColumns['api_key']) && !isset($apiKeyColumns['api_key_hash'])) {
        $pdo->beginTransaction();
        try {
            $pdo->exec("
                CREATE TABLE api_keys_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    api_key_hash TEXT NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            $rows = $pdo->query("SELECT id, user_id, api_key, created_at, expires_at FROM api_keys")->fetchAll();
            $stmt = $pdo->prepare("
                INSERT INTO api_keys_new (id, user_id, api_key_hash, created_at, expires_at)
                VALUES (:id, :user_id, :api_key_hash, :created_at, :expires_at)
            ");
            foreach ($rows as $row) {
                $stmt->execute([
                    ':id' => $row['id'],
                    ':user_id' => $row['user_id'],
                    ':api_key_hash' => hash_api_key($row['api_key']),
                    ':created_at' => $row['created_at'],
                    ':expires_at' => $row['expires_at'],
                ]);
            }

            $pdo->exec("DROP TABLE api_keys");
            $pdo->exec("ALTER TABLE api_keys_new RENAME TO api_keys");
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

function get_table_columns(PDO $pdo, string $tableName): array {
    $stmt = $pdo->query("PRAGMA table_info(" . $tableName . ")");
    $columns = [];
    foreach ($stmt->fetchAll() as $column) {
        $columns[$column['name']] = $column;
    }
    return $columns;
}

// Get sharded user DB PDO connection (10 users per DB file)
function get_user_db_connection(int $userId): PDO {
    if ($userId <= 0) {
        throw new InvalidArgumentException("User ID must be positive.");
    }
    
    $config = get_config();
    $usersDir = $config['db']['users_dir'];
    
    if (!is_dir($usersDir)) {
        mkdir($usersDir, 0755, true);
    }
    
    // Shard formula: user-0.db for user_id 1-10, user-1.db for user_id 11-20, etc.
    $dbIndex = intval(($userId - 1) / 10);
    $dbPath = $usersDir . "/user-{$dbIndex}.db";
    
    $exists = file_exists($dbPath);
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $pdo->exec('PRAGMA journal_mode = WAL;');
    
    if (!$exists) {
        $sql = file_get_contents(__DIR__ . '/user_db.sql');
        $pdo->exec($sql);
    }
    
    return $pdo;
}

// Generate API key based on specification:
// format: laterapi::{user_id}::{hash}{token}
// - hash: sha256(current_datetime + email + random) encoded in base64
// - token: secure random alphanumeric string 16 chars
function generate_api_key(int $userId, string $email): string {
    $datetime = date('Y-m-d H:i:s');
    $random = bin2hex(random_bytes(16));
    
    // Hash is base64 encoded SHA256 binary hash
    $hashBinary = hash('sha256', $datetime . $email . $random, true);
    // Base64 encode and clean to prevent double-colons or weird characters in url
    $hash = base64_encode($hashBinary);
    $hash = str_replace(['+', '/', '=', ':'], ['', '', '', ''], $hash);
    
    // Security random token 16 chars
    $tokenBytes = random_bytes(8);
    $token = bin2hex($tokenBytes);
    
    return "laterapi::{$userId}::{$hash}{$token}";
}

function hash_api_key(string $apiKey): string {
    return hash('sha256', $apiKey);
}

// Parse API key and return [user_id, token_part]
function parse_api_key(string $apiKey): ?array {
    $parts = explode('::', $apiKey);
    if (count($parts) !== 3 || $parts[0] !== 'laterapi') {
        return null;
    }
    return [
        'user_id' => intval($parts[1]),
        'key_part' => $parts[2]
    ];
}

function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function issue_otp_for_user(PDO $db, int $userId, string $token): void {
    $stmt = $db->prepare("
        UPDATE users
        SET passcode = :passcode,
            passcode_expires_at = :expires_at,
            passcode_attempts = 0,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([
        ':passcode' => password_hash($token, PASSWORD_DEFAULT),
        ':expires_at' => date('Y-m-d H:i:s', time() + 15 * 60),
        ':id' => $userId
    ]);
}

function verify_user_otp(PDO $db, string $email, string $token): ?array {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    if (intval($user['passcode_attempts'] ?? 0) >= 5) {
        return null;
    }

    $expiresAt = $user['passcode_expires_at'] ?? null;
    if (empty($expiresAt) || strtotime((string)$expiresAt) < time()) {
        return null;
    }

    $storedPasscode = (string)($user['passcode'] ?? '');
    $verified = password_get_info($storedPasscode)['algo'] !== null
        ? password_verify($token, $storedPasscode)
        : hash_equals($storedPasscode, $token);

    if (!$verified) {
        $updateStmt = $db->prepare("
            UPDATE users
            SET passcode_attempts = passcode_attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $updateStmt->execute([':id' => $user['id']]);
        return null;
    }

    return $user;
}

function clear_user_otp(PDO $db, int $userId): void {
    $stmt = $db->prepare("
        UPDATE users
        SET passcode = '',
            passcode_expires_at = NULL,
            passcode_attempts = 0,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([':id' => $userId]);
}

// Send verification email
function send_verification_email(string $email, string $token): bool {
    $config = get_config();
    $lang = get_current_lang();
    
    // Japanese template
    $subjectJa = "Later APIの認証メール";
    $messageJa = "Later APIをご利用いただきありがとうございます。\n"
               . "以下のトークンをLater APIの認証ページに入力して、認証を完了してください。\n"
               . "---\n"
               . $token . "\n"
               . "---\n"
               . "もし、このメールに心当たりがない場合は、削除してください。\n"
               . "申し訳ありませんが、このメールに返信しても対応できませんので、ご了承ください。";
               
    // English template
    $subjectEn = "Later API Verification Code";
    $messageEn = "Thank you for using Later API.\n"
               . "Please enter the following token on the Later API verification page to complete your login/registration:\n"
               . "---\n"
               . $token . "\n"
               . "---\n"
               . "If you did not request this email, please delete it.\n"
               . "Please note that you cannot reply to this automated email.";
               
    // Assemble bilingual message prioritizing the active language
    if ($lang === 'ja') {
        $subject = "{$subjectJa} / {$subjectEn}";
        $message = $messageJa . "\n\n" . str_repeat('=', 40) . "\n\n" . $messageEn;
    } else {
        $subject = "{$subjectEn} / {$subjectJa}";
        $message = $messageEn . "\n\n" . str_repeat('=', 40) . "\n\n" . $messageJa;
    }
             
    $driver = $config['mail']['driver'] ?? 'log';
    $fromAddress = $config['mail']['from_address'] ?? 'no-reply@example.com';
    $fromName = $config['mail']['from_name'] ?? 'Later API';

    if ($driver === 'log') {
        // Log locally for debugging
        $logPath = __DIR__ . '/data/mail_debug.log';
        $logContent = "[" . date('Y-m-d H:i:s') . "] To: $email | Subject: $subject\n$message\n" . str_repeat('=', 40) . "\n";
        file_put_contents($logPath, $logContent, FILE_APPEND);
        
        // Save to session so we can display a developer helper toast/banner on the webpage
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['dev_last_email'] = [
            'to' => $email,
            'subject' => $subject,
            'body' => $message,
            'token' => $token,
            'time' => date('H:i:s')
        ];
        return true;
    } elseif ($driver === 'smtp') {
        return send_smtp_mail($email, $subject, $message, $config['mail']);
    } else {
        // Standard PHP mail()
        $headers = [
            'From' => "$fromName <$fromAddress>",
            'Reply-To' => $fromAddress,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];
        // Convert headers array to string for compatibility with older PHP if needed
        $headerStr = "";
        foreach ($headers as $k => $v) {
            $headerStr .= "$k: $v\r\n";
        }
        return mail($email, $subject, $message, $headerStr);
    }
}

// SMTP mail sender fallback
function send_smtp_mail(string $to, string $subject, string $message, array $mailConfig): bool {
    $host = $mailConfig['smtp_host'] ?? '';
    $port = $mailConfig['smtp_port'] ?? 587;
    $user = $mailConfig['smtp_username'] ?? '';
    $pass = $mailConfig['smtp_password'] ?? '';
    $secure = $mailConfig['smtp_secure'] ?? 'tls';
    $from = $mailConfig['from_address'] ?? 'no-reply@example.com';
    $fromName = $mailConfig['from_name'] ?? 'Later API';

    // If SMTP details are empty, fall back to PHP mail()
    if (empty($host) || empty($user)) {
        $headers = "From: $fromName <$from>\r\n" .
                   "Reply-To: $from\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n" .
                   "X-Mailer: PHP/" . phpversion();
        return mail($to, $subject, $message, $headers);
    }

    try {
        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        
        // Handle certificate verification issues common in local development environments
        $verifyPeer = $mailConfig['smtp_verify_peer'] ?? false; // Default to false for maximum out-of-the-box compatibility
        
        $contextOpts = [
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer
            ]
        ];
        $context = stream_context_create($contextOpts);
        
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }

        $getResponse = function($socket) {
            $response = "";
            while (($line = fgets($socket, 512)) !== false) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            return $response;
        };

        $sendCmd = function($socket, $cmd) use ($getResponse) {
            fputs($socket, $cmd . "\r\n");
            return $getResponse($socket);
        };

        $getResponse($socket); // Read connection greeting
        $sendCmd($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        if ($secure === 'tls') {
            $res = $sendCmd($socket, "STARTTLS");
            if (strpos($res, '220') === false) {
                throw new Exception("STARTTLS failed: " . $res);
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Crypto enable failed");
            }
            $sendCmd($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        // Authenticate
        $res = $sendCmd($socket, "AUTH LOGIN");
        if (strpos($res, '334') === false) {
            throw new Exception("AUTH LOGIN failed: " . $res);
        }
        $sendCmd($socket, base64_encode($user));
        $res = $sendCmd($socket, base64_encode($pass));
        if (strpos($res, '235') === false) {
            throw new Exception("SMTP Authentication failed: " . $res);
        }

        // Send envelope
        $sendCmd($socket, "MAIL FROM: <$from>");
        $sendCmd($socket, "RCPT TO: <$to>");
        
        $res = $sendCmd($socket, "DATA");
        if (strpos($res, '354') === false) {
            throw new Exception("DATA command failed: " . $res);
        }

        // Send headers and body
        $headers = "To: $to\r\n" .
                   "From: $fromName <$from>\r\n" .
                   "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n" .
                   "Content-Transfer-Encoding: 8bit\r\n\r\n";
                   
        fputs($socket, $headers . $message . "\r\n.\r\n");
        $res = $getResponse($socket);
        if (strpos($res, '250') === false) {
            throw new Exception("Sending message failed: " . $res);
        }

        $sendCmd($socket, "QUIT");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        // Fall back to mail() in case of SMTP failure, logging it
        $logPath = __DIR__ . '/data/mail_debug.log';
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] SMTP Error: " . $e->getMessage() . ". Falling back to mail().\n", FILE_APPEND);
        
        $headers = "From: $fromName <$from>\r\n" .
                   "Reply-To: $from\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($to, $subject, $message, $headers);
    }
}

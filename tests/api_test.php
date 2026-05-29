<?php
/**
 * API Integration Test Suite
 * Programmatically starts a local PHP server, performs end-to-end HTTP requests,
 * and validates responses under clean isolated conditions.
 */

declare(strict_types=1);

// Ensure we run from CLI
if (PHP_SAPI !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "==================================================\n";
echo "🧪 LATER API - INTEGRATION TEST RUNNER\n";
echo "==================================================\n\n";

$baseDir = dirname(__DIR__);
$configPath = $baseDir . '/config.inc.php';
$backupConfigPath = $baseDir . '/config.inc.php.bak';

// 1. Isolate Environment (Backup config)
$hasBackup = false;
if (file_exists($configPath)) {
    rename($configPath, $backupConfigPath);
    $hasBackup = true;
    echo "• Backed up active configuration.\n";
}

// 2. Write Test Configuration
$testDbFile = $baseDir . '/data/test_users.db';
$testUsersDir = $baseDir . '/data/test_users';

// Clean any leftover test databases
if (file_exists($testDbFile)) {
    unlink($testDbFile);
}
if (is_dir($testUsersDir)) {
    foreach (glob("$testUsersDir/*.db") as $file) {
        unlink($file);
    }
    rmdir($testUsersDir);
}

$testConfig = [
    'db' => [
        'users_db' => $testDbFile,
        'users_dir' => $testUsersDir,
    ],
    'mail' => [
        'driver' => 'log',
        'from_address' => 'test-reply@example.com',
        'from_name' => 'Later API Test',
    ],
    'session' => [
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],
    'debug' => true
];

$configContent = "<?php\nreturn " . var_export($testConfig, true) . ";\n";
file_put_contents($configPath, $configContent);
echo "• Initialized clean test configuration.\n";

// 3. Start local PHP Server in background
$port = 8999;
$cmd = sprintf("php -S 127.0.0.1:%d -t %s", $port, escapeshellarg($baseDir));
echo "• Starting background test server on 127.0.0.1:{$port}...\n";

$descriptors = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"]  // stderr
];

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    echo "❌ [FAIL] Could not start the test server.\n";
    cleanup();
    exit(1);
}

// Allow time for the server to bind and start
usleep(600000); // 600ms

// 4. Initialize Test DB and Dummy user directly so we have a valid user and API key
require_once $baseDir . '/db.php';
$db = get_db_connection();

// Create test user
$stmt = $db->prepare("INSERT INTO users (nickname, email, passcode) VALUES (:nickname, :email, :passcode)");
$stmt->execute([
    ':nickname' => 'Tester',
    ':email' => 'tester@example.com',
    ':passcode' => ''
]);
$userId = intval($db->lastInsertId());

// Generate valid API key
$validApiKey = generate_api_key($userId, 'tester@example.com');
$expiresAt = date('Y-m-d H:i:s', strtotime("+1 year"));
$stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key_hash, expires_at) VALUES (:user_id, :api_key_hash, :expires_at)");
$stmt->execute([
    ':user_id' => $userId,
    ':api_key_hash' => hash_api_key($validApiKey),
    ':expires_at' => $expiresAt
]);

// Generate an expired API key
$expiredApiKey = generate_api_key($userId, 'tester@example.com');
$expiredAt = date('Y-m-d H:i:s', strtotime("-1 day"));
$stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key_hash, expires_at) VALUES (:user_id, :api_key_hash, :expires_at)");
$stmt->execute([
    ':user_id' => $userId,
    ':api_key_hash' => hash_api_key($expiredApiKey),
    ':expires_at' => $expiredAt
]);

echo "• Test user created (ID: {$userId}).\n";
echo "• API Keys prepared.\n\n";

// Helper to make HTTP POST requests to the test API
function api_request(string $method, ?string $apiKey, array $payload): array {
    global $port;
    $url = "http://127.0.0.1:{$port}/api.php?method=" . urlencode($method);
    
    $jsonData = json_encode($payload);
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ];
    
    if ($apiKey !== null) {
        $headers[] = "Authorization: Bearer {$apiKey}";
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    $data = null;
    if ($response !== false) {
        $data = json_decode((string)$response, true);
    }
    
    return [
        'status' => $httpCode,
        'content_type' => $contentType,
        'data' => $data,
        'raw' => $response
    ];
}

$testsRun = 0;
$testsPassed = 0;

function assert_test(string $name, bool $expression, ?string $info = null) {
    global $testsRun, $testsPassed;
    $testsRun++;
    if ($expression) {
        $testsPassed++;
        echo "✅ [PASS] {$name}\n";
    } else {
        echo "❌ [FAIL] {$name}\n";
        if ($info !== null) {
            echo "   👉 Info: {$info}\n";
        }
    }
}

// ==========================================
// TEST CASES
// ==========================================

echo "--- RUNNING TESTS ---\n\n";

// 1. Missing Authorization Header
$res = api_request('post', null, ['events' => []]);
assert_test(
    "1. Request without Authorization header returns 401",
    $res['status'] === 401 && ($res['data']['success'] ?? null) === false,
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// 2. Invalid Token Format
$res = api_request('post', 'invalid-format-token', ['events' => []]);
assert_test(
    "2. Request with malformed Authorization token returns 401",
    $res['status'] === 401 && strpos($res['data']['message'] ?? '', 'Invalid API key format') !== false,
    "Status: {$res['status']}, Message: " . ($res['data']['message'] ?? 'none')
);

// 3. Unregistered Token
$unregisteredKey = generate_api_key(99, 'stranger@example.com');
$res = api_request('post', $unregisteredKey, ['events' => []]);
assert_test(
    "3. Request with unregistered API key returns 401",
    $res['status'] === 401 && strpos($res['data']['message'] ?? '', 'API key is not registered') !== false,
    "Status: {$res['status']}, Message: " . ($res['data']['message'] ?? 'none')
);

// 4. Expired Token
$res = api_request('post', $expiredApiKey, ['events' => []]);
assert_test(
    "4. Request with expired API key returns 401",
    $res['status'] === 401 && strpos($res['data']['message'] ?? '', 'expired') !== false,
    "Status: {$res['status']}, Message: " . ($res['data']['message'] ?? 'none')
);

// 5. Valid Auth, Empty Payload
$res = api_request('post', $validApiKey, []);
assert_test(
    "5. Request with valid key but missing 'events' returns 400",
    $res['status'] === 400 && strpos($res['data']['message'] ?? '', 'events') !== false,
    "Status: {$res['status']}, Message: " . ($res['data']['message'] ?? 'none')
);

// 6. Record Events (POST method)
$guid1 = "123e4567-e89b-12d3-a456-426614174001";
$guid2 = "123e4567-e89b-12d3-a456-426614174002";
$timestamp = "2026-05-29 14:00:00";

$eventsPayload = [
    'events' => [
        [
            'event' => 'add',
            'guid' => $guid1,
            'timestamp' => $timestamp,
            'task' => 'テスト用タスク 1',
            'status' => 'done',
            'date' => '2026-05-30 08:00:00'
        ],
        [
            'event' => 'add',
            'guid' => $guid2,
            'timestamp' => $timestamp,
            'task' => 'テスト用タスク 2',
            'status' => 'pending',
            'date' => '2026-05-30 09:00:00'
        ]
    ]
];

$res = api_request('post', $validApiKey, $eventsPayload);
assert_test(
    "6. Valid method=post creates events inside user sharded DB",
    $res['status'] === 200 && ($res['data']['success'] ?? false) === true && ($res['data']['inserted'] ?? 0) === 2,
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// 7. Duplicate/Deduplication Test
$res = api_request('post', $validApiKey, $eventsPayload);
assert_test(
    "7. Re-sending identical events triggers deduplication (0 inserted, 2 skipped)",
    $res['status'] === 200 && ($res['data']['inserted'] ?? 0) === 0 && ($res['data']['skipped'] ?? 0) === 2,
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// 8. Event Retrieval (GET method)
$getPayload = [
    'date_from' => '2026-05-29 00:00:00',
    'date_to' => '2026-05-29 23:59:59'
];
$res = api_request('get', $validApiKey, $getPayload);
assert_test(
    "8. Valid method=get retrieves events matching date range",
    $res['status'] === 200 && is_array($res['data']) && count($res['data']) === 2,
    "Status: {$res['status']}, Response count: " . (is_array($res['data']) ? count($res['data']) : 'none')
);

// 9. Event Retrieval filtering
$getEmptyPayload = [
    'date_from' => '2026-05-30 00:00:00',
    'date_to' => '2026-05-30 23:59:59'
];
$res = api_request('get', $validApiKey, $getEmptyPayload);
assert_test(
    "9. Event retrieval filters out non-matching ranges correctly",
    $res['status'] === 200 && is_array($res['data']) && count($res['data']) === 0,
    "Status: {$res['status']}, Count: " . (is_array($res['data']) ? count($res['data']) : 'none')
);

// 10. Non-existent method routing
$res = api_request('unknown_method', $validApiKey, []);
assert_test(
    "10. Invalid method parameter returns 400 Bad Request",
    $res['status'] === 400 && strpos($res['data']['message'] ?? '', 'Invalid or missing') !== false,
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// 11. Hello Endpoint - Valid API Key
$res = api_request('hello', $validApiKey, ['message' => 'Hello, Later API!']);
assert_test(
    "11. Valid API key hello request echoes the message with status 200",
    $res['status'] === 200 && ($res['data']['message'] ?? '') === 'Hello, Later API!',
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// 12. Hello Endpoint - Invalid API Key
$res = api_request('hello', $expiredApiKey, ['message' => 'Hello, Later API!']);
assert_test(
    "12. Expired API key hello request fails with status 401",
    $res['status'] === 401 && ($res['data']['success'] ?? null) === false,
    "Status: {$res['status']}, Response: " . print_r($res['data'], true)
);

// ==========================================
// CLEANUP & SUMMARY
// ==========================================

echo "\n--- SUMMARY ---\n";
echo "Tests Run: {$testsRun}\n";
echo "Tests Passed: {$testsPassed} / {$testsRun}\n";

$allPassed = ($testsRun === $testsPassed);
if ($allPassed) {
    echo "\n🎉 ALL API TESTS PASSED SUCCESSFULLY! 🎉\n";
} else {
    echo "\n❌ SOME API TESTS FAILED.\n";
}

cleanup();

// Cleanup environment
function cleanup() {
    global $baseDir, $process, $pipes, $testDbFile, $testUsersDir, $configPath, $backupConfigPath, $hasBackup;
    
    echo "\n• Cleaning up background test server...\n";
    // Close handles and terminate process
    if (is_resource($process)) {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($process);
        proc_close($process);
    }
    
    echo "• Removing test databases...\n";
    if (file_exists($testDbFile)) {
        unlink($testDbFile);
    }
    // Remove journal/WAL files if any
    foreach (glob($testDbFile . "*") as $f) {
        unlink($f);
    }
    if (is_dir($testUsersDir)) {
        foreach (glob("$testUsersDir/*") as $file) {
            unlink($file);
        }
        rmdir($testUsersDir);
    }
    
    echo "• Restoring configuration...\n";
    if (file_exists($configPath)) {
        unlink($configPath);
    }
    if ($hasBackup && file_exists($backupConfigPath)) {
        rename($backupConfigPath, $configPath);
    }
    
    echo "• Cleanup completed.\n";
}

exit($allPassed ? 0 : 1);

<?php
/**
 * Later API - API Entrypoint
 */

declare(strict_types=1);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Include DB configurations and helpers
require_once __DIR__ . '/db.php';

// Helper to send JSON responses
function send_json_response($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Helper to extract bearer token from headers
function get_bearer_token(): ?string {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('strtolower', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['authorization'])) {
            $headers = trim($requestHeaders['authorization']);
        }
    }
    
    if (!empty($headers) && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

try {
    // 1. Authenticate Request
    $apiKey = get_bearer_token();
    if (empty($apiKey)) {
        send_json_response(['success' => false, 'message' => 'Authorization header (Bearer token) is missing or invalid'], 401);
    }
    
    // Parse key format: laterapi::{user_id}::{hash_token}
    $parsedKey = parse_api_key($apiKey);
    if (!$parsedKey) {
        send_json_response(['success' => false, 'message' => 'Invalid API key format'], 401);
    }
    
    $userId = $parsedKey['user_id'];
    $db = get_db_connection();
    
    // Validate key against database and check expiry
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key_hash = :api_key_hash AND user_id = :user_id");
    $stmt->execute([
        ':api_key_hash' => hash_api_key($apiKey),
        ':user_id' => $userId
    ]);
    $keyInfo = $stmt->fetch();
    
    if (!$keyInfo) {
        send_json_response(['success' => false, 'message' => 'API key is not registered'], 401);
    }
    
    // Check expiration
    if (strtotime($keyInfo['expires_at']) < time()) {
        send_json_response(['success' => false, 'message' => 'API key has expired'], 401);
    }
    
    // Fetch user details to ensure user is valid
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        send_json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // 2. Routing sub-methods: method=post or method=get
    $method = $_GET['method'] ?? '';
    
    // All API requests must be POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'message' => 'API requests must be sent via POST method'], 405);
    }
    
    // Read input JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE && !empty($rawInput)) {
        send_json_response(['success' => false, 'message' => 'Invalid JSON body'], 400);
    }
    
    // Open the sharded user database
    $userDb = get_user_db_connection($userId);
    
    switch ($method) {
        case 'post':
            // Record events
            if (!isset($input['events']) || !is_array($input['events'])) {
                send_json_response(['success' => false, 'message' => 'Missing or invalid "events" array in payload'], 400);
            }
            
            $events = $input['events'];
            $insertedCount = 0;
            $skippedCount = 0;
            
            // Start transaction for efficiency
            $userDb->beginTransaction();
            
            try {
                // Prepared statements
                $checkStmt = $userDb->prepare("SELECT COUNT(*) as count FROM events WHERE user_id = :user_id AND task_id = :task_id AND created_at = :created_at");
                $insertStmt = $userDb->prepare("INSERT INTO events (user_id, task_id, json_str, created_at) VALUES (:user_id, :task_id, :json_str, :created_at)");
                
                foreach ($events as $event) {
                    $guid = $event['guid'] ?? null;
                    $timestamp = $event['timestamp'] ?? date('Y-m-d H:i:s');
                    
                    if (empty($guid)) {
                        continue; // Skip invalid event with no guid
                    }
                    
                    // Deduplicate based on user_id, task_id (guid) and created_at (timestamp)
                    $checkStmt->execute([
                        ':user_id' => $userId,
                        ':task_id' => $guid,
                        ':created_at' => $timestamp
                    ]);
                    $exists = intval($checkStmt->fetch()['count'] ?? 0) > 0;
                    
                    if ($exists) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Serialized JSON event string
                    $jsonStr = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    $insertStmt->execute([
                        ':user_id' => $userId,
                        ':task_id' => $guid,
                        ':json_str' => $jsonStr,
                        ':created_at' => $timestamp
                    ]);
                    $insertedCount++;
                }
                
                // Commit sharded DB
                $userDb->commit();
                
                // Update the last sync time in main DB
                $mainDb = get_db_connection();
                $updateStmt = $mainDb->prepare("UPDATE users SET sync_at = CURRENT_TIMESTAMP WHERE id = :id");
                $updateStmt->execute([':id' => $userId]);
                
                send_json_response([
                    'success' => true,
                    'message' => 'Events processed successfully',
                    'inserted' => $insertedCount,
                    'skipped' => $skippedCount
                ]);
            } catch (Exception $e) {
                $userDb->rollBack();
                throw $e;
            }
            break;
            
        case 'get':
            // Retrieve events
            $dateTo = $input['date_to'] ?? null;
            if (empty($dateTo)) {
                $dateTo = date('Y-m-d H:i:s');
            }
            
            $dateFrom = $input['date_from'] ?? null;
            if (empty($dateFrom)) {
                // Default is 7 days before date_to
                $dateFrom = date('Y-m-d H:i:s', strtotime($dateTo . ' -7 days'));
            }
            
            // Query sharded DB
            $stmt = $userDb->prepare("SELECT json_str FROM events WHERE user_id = :user_id AND created_at BETWEEN :date_from AND :date_to ORDER BY created_at ASC");
            $stmt->execute([
                ':user_id' => $userId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo
            ]);
            $rows = $stmt->fetchAll();
            
            $events = [];
            foreach ($rows as $row) {
                $event = json_decode($row['json_str'], true);
                if ($event) {
                    $events[] = $event;
                }
            }
            
            send_json_response($events);
            break;
            
        case 'hello':
            // Simple test endpoint to verify API key validity
            $msg = $input['message'] ?? 'Hello, Later API!';
            send_json_response([
                'message' => $msg
            ]);
            break;
            
        default:
            send_json_response(['success' => false, 'message' => 'Invalid or missing "method" parameter'], 400);
            break;
    }
    
} catch (Exception $e) {
    $config = get_config();
    $isDebug = $config['debug'] ?? false;
    
    send_json_response([
        'success' => false,
        'message' => 'An internal server error occurred',
        'error' => $isDebug ? $e->getMessage() : 'Internal Server Error'
    ], 500);
}

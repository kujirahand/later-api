<?php
/**
 * Later API - Web Service Entrypoint
 */

declare(strict_types=1);

// Include database and helper functions
require_once __DIR__ . '/db.php';

// Start secure session
$config = get_config();
$sessionConfig = $config['session'] ?? [];
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool)($sessionConfig['cookie_secure'] ?? false),
    'httponly' => (bool)($sessionConfig['cookie_httponly'] ?? true),
    'samesite' => (string)($sessionConfig['cookie_samesite'] ?? 'Lax'),
]);
session_start();

// Setup routing/actions
$action = $_GET['action'] ?? 'dashboard';

// Helper to render pages within the header/footer layout
function render_view(string $templateName, array $vars = []): void {
    extract($vars);
    $pageTitle = match ($templateName) {
        'login' => __('login_title'),
        'register' => __('register_title'),
        'verify' => __('verify_title'),
        'dashboard' => __('dash_title'),
        default => 'Later API'
    };
    
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . "/templates/{$templateName}.php";
    require_once __DIR__ . '/templates/footer.php';
}

// Redirect helper
function redirect(string $url): void {
    header("Location: $url");
    exit();
}

try {
    // Define public routes (no login required)
    $publicActions = ['login', 'register', 'verify', 'set_lang'];
    
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['user_id']);
    
    if (!$isLoggedIn && !in_array($action, $publicActions, true)) {
        // Redirect to login if trying to access dashboard/restricted actions
        redirect('index.php?action=login');
    }
    
    switch ($action) {
        case 'set_lang':
            $newLang = $_GET['lang'] ?? 'en';
            set_current_lang($newLang);
            $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (empty($host) || strpos($referer, $host) !== false) {
                redirect($referer);
            } else {
                redirect('index.php');
            }
            break;
            
        case 'login':
            if ($isLoggedIn) {
                redirect('index.php');
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                    $_SESSION['flash_error'] = 'flash_err_invalid_request';
                    redirect('index.php?action=login');
                }

                $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
                if (!$email) {
                    $_SESSION['flash_error'] = 'flash_err_valid_email';
                    redirect('index.php?action=login');
                }
                
                $email = strtolower(trim($email));
                $db = get_db_connection();
                
                // Check if user exists
                $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $_SESSION['flash_error'] = 'flash_err_email_not_found';
                    redirect('index.php?action=login');
                }
                
                // User exists, generate 6-digit numeric token
                $token = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                
                issue_otp_for_user($db, intval($user['id']), $token);
                
                // Send verification email
                if (send_verification_email($email, $token)) {
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['auth_flow'] = 'login';
                    $_SESSION['flash_success'] = 'flash_success_login_mail';
                    redirect('index.php?action=verify');
                } else {
                    $_SESSION['flash_error'] = 'flash_err_mail_fail';
                    redirect('index.php?action=login');
                }
            } else {
                render_view('login');
            }
            break;
            
        case 'register':
            if ($isLoggedIn) {
                redirect('index.php');
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                    $_SESSION['flash_error'] = 'flash_err_invalid_request';
                    redirect('index.php?action=register');
                }

                $nickname = trim((string)($_POST['nickname'] ?? ''));
                $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
                
                if (empty($nickname)) {
                    $_SESSION['flash_error'] = 'flash_err_nickname';
                    redirect('index.php?action=register');
                }
                
                if (!$email) {
                    $_SESSION['flash_error'] = 'flash_err_valid_email';
                    redirect('index.php?action=register');
                }
                
                $email = strtolower(trim($email));
                $db = get_db_connection();
                
                // Check if email already registered
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    $_SESSION['flash_error'] = 'flash_err_email_registered';
                    redirect('index.php?action=login');
                }
                
                // Generate 6-digit numeric token
                $token = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (nickname, email, passcode) VALUES (:nickname, :email, :passcode)");
                $stmt->execute([
                    ':nickname' => $nickname,
                    ':email' => $email,
                    ':passcode' => ''
                ]);
                $newUserId = intval($db->lastInsertId());
                issue_otp_for_user($db, $newUserId, $token);
                
                // Send verification email
                if (send_verification_email($email, $token)) {
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['auth_flow'] = 'register';
                    $_SESSION['flash_success'] = 'flash_success_register_mail';
                    redirect('index.php?action=verify');
                } else {
                    $_SESSION['flash_error'] = 'flash_err_mail_fail';
                    redirect('index.php?action=register');
                }
            } else {
                render_view('register');
            }
            break;
            
        case 'verify':
            // Check for direct email/token link verification (GET request)
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['email'], $_GET['token'])) {
                $email = strtolower(trim((string)$_GET['email']));
                $token = trim((string)$_GET['token']);
                
                $db = get_db_connection();
                $user = verify_user_otp($db, $email, $token);
                
                if ($user) {
                    // Log the user in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nickname'] = $user['nickname'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Invalidate passcode so it cannot be used again
                    clear_user_otp($db, intval($user['id']));
                    
                    // Clean temp sessions
                    unset($_SESSION['pending_email'], $_SESSION['auth_flow'], $_SESSION['dev_last_email']);
                    
                    $_SESSION['flash_success'] = 'flash_success_verify_link';
                    redirect('index.php');
                } else {
                    $_SESSION['flash_error'] = 'flash_err_link_invalid';
                    redirect('index.php?action=login');
                }
            }
            
            // Standard form-based code entry (POST request)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                    $_SESSION['flash_error'] = 'flash_err_invalid_request';
                    redirect('index.php?action=verify');
                }

                $pendingEmail = $_SESSION['pending_email'] ?? '';
                if (empty($pendingEmail)) {
                    $_SESSION['flash_error'] = 'flash_err_no_auth_session';
                    redirect('index.php?action=login');
                }
                
                $passcode = trim((string)($_POST['passcode'] ?? ''));
                
                $db = get_db_connection();
                $user = verify_user_otp($db, $pendingEmail, $passcode);
                
                if ($user) {
                    // Success! Authenticate
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nickname'] = $user['nickname'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Invalidate passcode
                    clear_user_otp($db, intval($user['id']));
                    
                    // Clean temp sessions
                    unset($_SESSION['pending_email'], $_SESSION['auth_flow'], $_SESSION['dev_last_email']);
                    
                    $_SESSION['flash_success'] = 'flash_success_login';
                    redirect('index.php');
                } else {
                    $_SESSION['flash_error'] = 'flash_err_invalid_code';
                    redirect('index.php?action=verify');
                }
            } else {
                if (empty($_SESSION['pending_email'])) {
                    redirect('index.php?action=login');
                }
                render_view('verify');
            }
            break;
            
        case 'issue_key':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                    $_SESSION['flash_error'] = 'flash_err_invalid_request';
                    redirect('index.php');
                }

                $userId = $_SESSION['user_id'];
                $email = $_SESSION['email'];
                $durationYears = intval($_POST['duration'] ?? 1);
                
                if (!in_array($durationYears, [1, 3, 5, 10], true)) {
                    $durationYears = 1;
                }
                
                $db = get_db_connection();
                
                // Count current active keys
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM api_keys WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $count = intval($stmt->fetch()['count'] ?? 0);
                
                if ($count >= 10) {
                    $_SESSION['flash_error'] = 'flash_err_limit_reached';
                    redirect('index.php');
                }
                
                // Generate secure API key based on format
                $apiKey = generate_api_key($userId, $email);
                
                // Expiry date
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationYears} year"));
                
                // Insert key
                $stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key_hash, expires_at) VALUES (:user_id, :api_key_hash, :expires_at)");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':api_key_hash' => hash_api_key($apiKey),
                    ':expires_at' => $expiresAt
                ]);
                
                $_SESSION['new_api_key'] = $apiKey;
                $_SESSION['flash_success'] = 'flash_success_key_issued';
                redirect('index.php');
            }
            break;
            
        case 'delete_key':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                    $_SESSION['flash_error'] = 'flash_err_invalid_request';
                    redirect('index.php');
                }

                $userId = $_SESSION['user_id'];
                $keyId = intval($_POST['key_id'] ?? 0);
                
                $db = get_db_connection();
                $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    ':id' => $keyId,
                    ':user_id' => $userId
                ]);
                
                $_SESSION['flash_success'] = 'flash_success_key_deleted';
                redirect('index.php');
            }
            break;
            
        case 'logout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
                $_SESSION['flash_error'] = 'flash_err_invalid_request';
                redirect('index.php');
            }

            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            
            // Start a new session just for the flash message
            session_start();
            $_SESSION['flash_success'] = 'flash_success_logout';
            redirect('index.php?action=login');
            break;
            
        case 'dashboard':
        default:
            $userId = $_SESSION['user_id'];
            $db = get_db_connection();
            
            // Fetch all keys
            $stmt = $db->prepare("SELECT * FROM api_keys WHERE user_id = :user_id ORDER BY id DESC");
            $stmt->execute([':user_id' => $userId]);
            $apiKeys = $stmt->fetchAll();
            
            render_view('dashboard', ['apiKeys' => $apiKeys]);
            break;
    }
} catch (Exception $e) {
    $config = get_config();
    $isDebug = $config['debug'] ?? false;
    
    $_SESSION['flash_error'] = 'エラーが発生しました: ' . ($isDebug ? $e->getMessage() : 'システムエラーが発生しました。');
    
    // In case of error in action, fall back to login/dashboard
    if (isset($_SESSION['user_id'])) {
        render_view('dashboard', ['apiKeys' => []]);
    } else {
        render_view('login');
    }
}

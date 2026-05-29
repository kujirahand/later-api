<?php
/**
 * Later API - Localization & Multi-language Helper (English default, Japanese optional)
 */

declare(strict_types=1);

// Initialize translation catalog
const TRANSLATIONS = [
    'en' => [
        // Navigation / Header
        'nav_login' => 'Login',
        'nav_register' => 'Register',
        'nav_logout' => 'Logout',
        'footer_rights' => 'Later API. Created for syncing later-cli tasks. All rights reserved.',
        
        // Login Page
        'login_title' => 'Login',
        'login_desc' => 'Enter your registered email to receive a verification code. No password is required.',
        'label_email' => 'Email Address',
        'btn_send_login' => 'Send Login Code',
        'link_to_register' => 'New here? Register an account',
        
        // Register Page
        'register_title' => 'Create Account',
        'register_desc' => 'Create a new account to start using Later API and sync your tasks.',
        'label_nickname' => 'Nickname',
        'btn_send_register' => 'Send Verification Email',
        'link_to_login' => 'Already have an account? Login',
        
        // Verification Page
        'verify_title' => 'Enter Verification Code',
        'verify_desc' => 'Please enter the 6-digit verification code sent to',
        'verify_spam_alert' => 'If you did not receive the email, please check your <strong>spam/junk folder</strong>.',
        'btn_verify' => 'Complete Verification',
        'link_restart' => 'Need to restart? Click here',
        'js_verify_error' => 'Please enter all 6 digits.',
        
        // Dashboard Page
        'dash_title' => 'API Key Management',
        'dash_desc' => 'Generate and manage API keys for syncing later-cli tasks. Maximum 10 keys allowed.',
        'dash_keys_list' => 'Registered API Keys',
        'dash_empty_state' => 'No API keys yet',
        'dash_empty_desc' => 'Generate a new API key from the right panel to use with later-cli.',
        'dash_created_at' => 'Created: ',
        'dash_expires_at' => 'Expires: ',
        'dash_status_active' => 'Active',
        'dash_status_expired' => 'Expired',
        'dash_btn_delete' => 'Delete',
        'dash_delete_confirm' => 'Are you sure you want to delete this API key? Sync clients using this key will lose access immediately.',
        
        'dash_new_key' => 'Issue New API Key',
        'dash_limit_alert' => 'Maximum limit (10 keys) reached. Please delete an old key to create a new one.',
        'dash_duration' => 'Validity Period',
        'dash_duration_1' => '1 Year',
        'dash_duration_3' => '3 Years',
        'dash_duration_5' => '5 Years',
        'dash_duration_10' => '10 Years',
        'dash_notice' => '* The key is shown only once upon generation. Please copy and store it securely.',
        'dash_btn_generate' => 'Generate API Key',
        'dash_copy_fail' => 'Failed to copy. Please show the key and copy it manually.',
        'dash_new_key_once_title' => 'Copy this API key now. It will not be shown again.',
        'dash_new_key_once_desc' => 'For security, only a hash is stored on the server after this page is loaded.',
        'dash_key_fingerprint' => 'Key fingerprint: ',
        
        // Alert Messages
        'flash_err_valid_email' => 'Please enter a valid email address.',
        'flash_err_nickname' => 'Please enter a nickname.',
        'flash_err_email_registered' => 'This email is already registered. Please log in.',
        'flash_err_email_not_found' => 'This email is not registered. Please sign up first.',
        'flash_err_mail_fail' => 'Failed to send verification email. Please check your settings.',
        'flash_err_no_auth_session' => 'Verification session expired. Please log in again.',
        'flash_err_invalid_code' => 'The entered verification code is incorrect.',
        'flash_err_link_invalid' => 'Verification link is invalid or expired.',
        'flash_err_limit_reached' => 'You cannot generate more than 10 API keys.',
        'flash_err_invalid_request' => 'Invalid request. Please reload the page and try again.',
        'flash_success_login_mail' => 'Verification code sent. Please check your email inbox.',
        'flash_success_register_mail' => 'Account pre-created and verification email sent.',
        'flash_success_login' => 'Successfully authenticated and logged in.',
        'flash_success_verify_link' => 'Verification successful via email link!',
        'flash_success_key_issued' => 'New API key generated successfully. Please copy it now.',
        'flash_success_key_deleted' => 'API key deleted successfully.',
        'flash_success_logout' => 'Logged out successfully.',
        
        'dev_debugger_title' => 'Developer Mail Debugger',
        'dev_debugger_desc' => 'Simulated email sent locally. Verification code:',
        'dev_debugger_footer' => '(Not shown in production)',
        'dev_debugger_to' => 'To:',
        'dev_debugger_sent' => 'Sent:'
    ],
    'ja' => [
        // Navigation / Header
        'nav_login' => 'ログイン',
        'nav_register' => '新規登録',
        'nav_logout' => 'ログアウト',
        'footer_rights' => 'Later API. later-cli同期用のAPIおよび管理サービス。All rights reserved.',
        
        // Login Page
        'login_title' => 'ログイン',
        'login_desc' => '登録しているメールアドレスを入力して、認証コードを送信してください。パスワードは不要です。',
        'label_email' => 'メールアドレス',
        'btn_send_login' => 'ログインコードを送信',
        'link_to_register' => '初めてのご利用ですか？ アカウント登録',
        
        // Register Page
        'register_title' => 'アカウント登録',
        'register_desc' => 'Later APIをご利用いただくために、アカウントを作成してください。',
        'label_nickname' => 'ニックネーム',
        'btn_send_register' => '認証メールを送信',
        'link_to_login' => '既にアカウントをお持ちですか？ ログイン',
        
        // Verification Page
        'verify_title' => '認証コードの入力',
        'verify_desc' => '宛てに送信した6桁の認証コードを入力してください：',
        'verify_spam_alert' => 'メールが届かない場合は、<strong>迷惑メールフォルダ</strong>をご確認ください。',
        'btn_verify' => '認証を完了する',
        'link_restart' => 'やり直す場合はこちらをクリック',
        'js_verify_error' => '6桁の認証コードをすべて入力してください。',
        
        // Dashboard Page
        'dash_title' => 'APIキーの管理',
        'dash_desc' => 'Later APIを同期するために必要なAPIキーを生成・管理します。最大10個まで発行可能です。',
        'dash_keys_list' => '登録済みのAPIキー',
        'dash_empty_state' => 'APIキーがありません',
        'dash_empty_desc' => '右側のフォームから新しくAPIキーを発行してください。later-cliとの同期に使用できます。',
        'dash_created_at' => '作成: ',
        'dash_expires_at' => '有効期限: ',
        'dash_status_active' => 'アクティブ',
        'dash_status_expired' => '期限切れ',
        'dash_btn_delete' => '削除',
        'dash_delete_confirm' => '本当にこのAPIキーを削除しますか？このキーを使用している同期クライアントはアクセスできなくなります。',
        
        'dash_new_key' => '新規APIキー発行',
        'dash_limit_alert' => 'APIキーの上限数 (10個) に達しています。新しいキーを発行するには、古い不要なキーを削除してください。',
        'dash_duration' => '有効期間',
        'dash_duration_1' => '1年間有効',
        'dash_duration_3' => '3年間有効',
        'dash_duration_5' => '5年間有効',
        'dash_duration_10' => '10年間有効',
        'dash_notice' => '※キーは生成時に一度だけ全桁が表示されます。安全な場所にコピーして保存してください。',
        'dash_btn_generate' => 'APIキーを新規発行する',
        'dash_copy_fail' => 'コピーに失敗しました。キーを表示して手動でコピーしてください。',
        'dash_new_key_once_title' => 'このAPIキーを今すぐコピーしてください。再表示はできません。',
        'dash_new_key_once_desc' => 'セキュリティのため、このページ表示後はサーバーにはハッシュのみ保存されます。',
        'dash_key_fingerprint' => 'キー指紋: ',
        
        // Alert Messages
        'flash_err_valid_email' => '有効なメールアドレスを入力してください。',
        'flash_err_nickname' => 'ニックネームを入力してください。',
        'flash_err_email_registered' => 'このメールアドレスは既に登録されています。ログインを行ってください。',
        'flash_err_email_not_found' => 'このメールアドレスは登録されていません。新規登録を行ってください。',
        'flash_err_mail_fail' => 'メールの送信に失敗しました。管理者にお問い合わせいただくか、設定をご確認ください。',
        'flash_err_no_auth_session' => '認証情報が見つかりません。もう一度ログインしてください。',
        'flash_err_invalid_code' => '入力された認証コードが正しくありません。',
        'flash_err_link_invalid' => '認証リンクが無効か、有効期限が切れています。',
        'flash_err_limit_reached' => 'APIキーは最大10個までしか作成できません。',
        'flash_err_invalid_request' => '不正なリクエストです。ページを再読み込みして、もう一度お試しください。',
        'flash_success_login_mail' => 'ログイン用の認証メールを送信しました。メールフォルダを確認してください。',
        'flash_success_register_mail' => 'アカウントを仮作成し、認証メールを送信しました。',
        'flash_success_login' => '認証に成功し、ログインしました。',
        'flash_success_verify_link' => 'メールリンクより認証が完了しました！',
        'flash_success_key_issued' => '新しいAPIキーを発行しました。大切に保管してください。',
        'flash_success_key_deleted' => 'APIキーを削除しました。',
        'flash_success_logout' => 'ログアウトしました。',
        
        'dev_debugger_title' => '開発用メールデバッガー',
        'dev_debugger_desc' => 'メール送信をシミュレートしました。認証コード：',
        'dev_debugger_footer' => '(本番環境ではこのトーストは表示されません)',
        'dev_debugger_to' => '宛先:',
        'dev_debugger_sent' => '送信時刻:'
    ]
];

// Get current language ('en' by default)
function get_current_lang(): string {
    // Check session first
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'] === 'ja' ? 'ja' : 'en';
    }
    
    // Check active config default lang if defined
    $configPath = __DIR__ . '/config.inc.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        if (isset($config['lang'])) {
            return $config['lang'] === 'ja' ? 'ja' : 'en';
        }
    }
    
    return 'en';
}

// Set/Toggle language
function set_current_lang(string $lang): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['lang'] = ($lang === 'ja') ? 'ja' : 'en';
}

// Localized string helper: __($key)
function __(string $key): string {
    $lang = get_current_lang();
    return TRANSLATIONS[$lang][$key] ?? TRANSLATIONS['en'][$key] ?? $key;
}

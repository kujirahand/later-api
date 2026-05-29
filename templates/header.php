<?php
/**
 * Header template
 */
declare(strict_types=1);

$config = get_config();
$isDebug = $config['debug'] ?? false;
$isLoggedIn = isset($_SESSION['user_id']);
$nickname = $_SESSION['nickname'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Later API</title>
    <meta name="description" content="later-cliのデータを同期するためのAPIとWeb管理サービス。">
    <link rel="stylesheet" href="templates/style.css">
</head>
<body>
    <header>
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <span class="logo-icon"></span>
                Later API
            </a>
            <div class="nav-links">
                <!-- Language Switcher -->
                <div style="display: flex; gap: 8px; margin-right: 12px; font-size: 0.85rem; border-right: 1px solid var(--border-color); padding-right: 12px; align-items: center;">
                    <a href="index.php?action=set_lang&lang=en" style="<?= get_current_lang() === 'en' ? 'font-weight: 700; color: var(--accent-primary);' : 'color: var(--text-secondary);' ?>">EN</a>
                    <span style="color: var(--text-muted); font-size: 0.75rem;">|</span>
                    <a href="index.php?action=set_lang&lang=ja" style="<?= get_current_lang() === 'ja' ? 'font-weight: 700; color: var(--accent-primary);' : 'color: var(--text-secondary);' ?>">JA</a>
                </div>
                
                <?php if ($isLoggedIn): ?>
                    <span class="user-badge"><?= htmlspecialchars($nickname) ?></span>
                    <form action="index.php?action=logout" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <button type="submit" class="btn-logout" style="border: 0; cursor: pointer;"><?= htmlspecialchars(__('nav_logout')) ?></button>
                    </form>
                <?php else: ?>
                    <a href="index.php?action=login" style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars(__('nav_login')) ?></a>
                    <a href="index.php?action=register" class="btn btn-secondary" style="width: auto; padding: 6px 12px; font-size: 0.9rem; border-radius: 6px;"><?= htmlspecialchars(__('nav_register')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="main-content">
        <div class="container" style="display: flex; flex-direction: column; align-items: center; text-align: center; width: 100%;">
            
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success" style="max-width: 480px; width: 100%; margin: 0 auto 20px;">
                    <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div><?= htmlspecialchars(__($_SESSION['flash_success'])) ?></div>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger" style="max-width: 480px; width: 100%; margin: 0 auto 20px;">
                    <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div><?= htmlspecialchars(__($_SESSION['flash_error'])) ?></div>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

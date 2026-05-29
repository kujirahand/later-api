<?php
/**
 * Login form template
 */
declare(strict_types=1);
?>
<div class="glass-card" style="margin: 40px auto;">
    <h2 class="card-title"><?= htmlspecialchars(__('login_title')) ?></h2>
    <p class="card-desc"><?= htmlspecialchars(__('login_desc')) ?></p>
    
    <form action="index.php?action=login" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <div class="form-group">
            <label class="form-label" for="email"><?= htmlspecialchars(__('label_email')) ?></label>
            <input class="form-control" type="email" id="email" name="email" required placeholder="example@domain.com" autocomplete="email">
        </div>
        
        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
            <?= htmlspecialchars(__('btn_send_login')) ?>
        </button>
    </form>
    
    <div style="margin-top: 24px; font-size: 0.9rem; color: var(--text-secondary);">
        <?php if (get_current_lang() === 'ja'): ?>
            初めてのご利用ですか？ <a href="index.php?action=register" style="font-weight: 600;">アカウント登録</a>
        <?php else: ?>
            New here? <a href="index.php?action=register" style="font-weight: 600;">Register an account</a>
        <?php endif; ?>
    </div>
</div>

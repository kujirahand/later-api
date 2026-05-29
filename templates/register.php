<?php
/**
 * Register form template
 */
declare(strict_types=1);
?>
<div class="glass-card" style="margin: 40px auto;">
    <h2 class="card-title"><?= htmlspecialchars(__('register_title')) ?></h2>
    <p class="card-desc"><?= htmlspecialchars(__('register_desc')) ?></p>
    
    <form action="index.php?action=register" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <div class="form-group">
            <label class="form-label" for="nickname"><?= htmlspecialchars(__('label_nickname')) ?></label>
            <input class="form-control" type="text" id="nickname" name="nickname" required placeholder="kujirahand" autocomplete="nickname">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="email"><?= htmlspecialchars(__('label_email')) ?></label>
            <input class="form-control" type="email" id="email" name="email" required placeholder="example@domain.com" autocomplete="email">
        </div>
        
        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            <?= htmlspecialchars(__('btn_send_register')) ?>
        </button>
    </form>
    
    <div style="margin-top: 24px; font-size: 0.9rem; color: var(--text-secondary);">
        <?php if (get_current_lang() === 'ja'): ?>
            既にアカウントをお持ちですか？ <a href="index.php?action=login" style="font-weight: 600;">ログイン</a>
        <?php else: ?>
            Already have an account? <a href="index.php?action=login" style="font-weight: 600;">Login</a>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Dashboard template - API Key Management
 */
declare(strict_types=1);

$nickname = $_SESSION['nickname'] ?? 'User';
$keysCount = count($apiKeys);
$maxKeys = 10;
$canGenerate = $keysCount < $maxKeys;
$newApiKey = $_SESSION['new_api_key'] ?? null;
unset($_SESSION['new_api_key']);
?>
<div class="hero">
    <h1><?= htmlspecialchars(__('dash_title')) ?></h1>
    <p><?= htmlspecialchars(__('dash_desc')) ?></p>
</div>

<?php if (is_string($newApiKey) && $newApiKey !== ''): ?>
    <div class="alert alert-info" style="max-width: 960px; width: 100%; margin: 0 auto 20px; align-items: flex-start;">
        <div style="width: 100%;">
            <div style="font-weight: 700; margin-bottom: 8px;"><?= htmlspecialchars(__('dash_new_key_once_title')) ?></div>
            <div class="key-value-wrapper" style="justify-content: space-between;">
                <div class="key-value" id="newApiKey" style="font-family: monospace; word-break: break-all; text-align: left;">
                    <?= htmlspecialchars($newApiKey) ?>
                </div>
                <button class="btn-icon" onclick="copyNewApiKey()" title="コピー" id="copyNewApiKeyBtn" type="button">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                </button>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 8px; text-align: left;">
                <?= htmlspecialchars(__('dash_new_key_once_desc')) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- Left Column: Active API Keys List -->
    <div class="dashboard-card">
        <h2>
            <svg style="width: 24px; height: 24px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
            <span><?= htmlspecialchars(__('dash_keys_list')) ?> (<?= $keysCount ?>/<?= $maxKeys ?>)</span>
        </h2>
        
        <?php if ($keysCount === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔑</div>
                <h3><?= htmlspecialchars(__('dash_empty_state')) ?></h3>
                <p style="margin-top: 8px;"><?= htmlspecialchars(__('dash_empty_desc')) ?></p>
            </div>
        <?php else: ?>
            <div class="key-list">
                <?php foreach ($apiKeys as $index => $key): 
                    $isExpired = strtotime($key['expires_at']) < time();
                    $badgeClass = $isExpired ? 'key-badge-expired' : 'key-badge-active';
                    $badgeText = $isExpired ? __('dash_status_expired') : __('dash_status_active');
                    $fingerprint = substr((string)$key['api_key_hash'], 0, 12);
                ?>
                    <div class="key-item">
                        <div class="key-info">
                            <div class="key-value-wrapper">
                                <div class="key-value">
                                    <?= htmlspecialchars(__('dash_key_fingerprint')) ?><?= htmlspecialchars($fingerprint) ?>
                                </div>
                            </div>
                            <div class="key-meta">
                                <span class="key-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
                                <span><?= htmlspecialchars(__('dash_created_at')) ?><?= htmlspecialchars(date('Y/m/d H:i', strtotime($key['created_at']))) ?></span>
                                <span><?= htmlspecialchars(__('dash_expires_at')) ?><strong style="color: var(--text-primary);"><?= htmlspecialchars(date('Y/m/d H:i', strtotime($key['expires_at']))) ?></strong></span>
                            </div>
                        </div>
                        <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: flex-end;">
                            <form action="index.php?action=delete_key" method="POST" onsubmit="return confirm('<?= htmlspecialchars(__('dash_delete_confirm'), ENT_QUOTES) ?>');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                <button class="btn btn-danger" type="submit">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    <?= htmlspecialchars(__('dash_btn_delete')) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Issue New API Key -->
    <div class="dashboard-card" style="height: fit-content;">
        <h2>
            <svg style="width: 24px; height: 24px; color: var(--accent-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= htmlspecialchars(__('dash_new_key')) ?></span>
        </h2>
        
        <?php if (!$canGenerate): ?>
            <div class="alert alert-danger" style="margin-bottom: 0;">
                <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div><?= htmlspecialchars(__('dash_limit_alert')) ?></div>
            </div>
        <?php else: ?>
            <form action="index.php?action=issue_key" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <div class="form-group">
                    <label class="form-label" for="duration"><?= htmlspecialchars(__('dash_duration')) ?></label>
                    <select class="form-control form-select" id="duration" name="duration" required>
                        <option value="1"><?= htmlspecialchars(__('dash_duration_1')) ?></option>
                        <option value="3"><?= htmlspecialchars(__('dash_duration_3')) ?></option>
                        <option value="5"><?= htmlspecialchars(__('dash_duration_5')) ?></option>
                        <option value="10"><?= htmlspecialchars(__('dash_duration_10')) ?></option>
                    </select>
                </div>
                
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.4;">
                    <?= htmlspecialchars(__('dash_notice')) ?>
                </p>
                
                <button class="btn btn-primary" type="submit">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <?= htmlspecialchars(__('dash_btn_generate')) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function copyNewApiKey() {
    const el = document.getElementById('newApiKey');
    const btn = document.getElementById('copyNewApiKeyBtn');
    if (!el || !btn) {
        return;
    }
    
    navigator.clipboard.writeText(el.textContent.trim()).then(() => {
        // Simple visual feedback
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<svg style="width: 16px; height: 16px; color: var(--accent-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        btn.style.background = 'rgba(16, 185, 129, 0.1)';
        
        setTimeout(() => {
            btn.innerHTML = origHtml;
            btn.style.background = '';
        }, 1500);
    }).catch(err => {
        alert('<?= htmlspecialchars(__('dash_copy_fail'), ENT_QUOTES) ?>');
    });
}
</script>

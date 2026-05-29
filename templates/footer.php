<?php
/**
 * Footer template
 */
declare(strict_types=1);

$config = get_config();
$isDebug = $config['debug'] ?? false;
?>
        </div>
    </div>
    
    <?php if ($isDebug && isset($_SESSION['dev_last_email'])): ?>
        <!-- Developer Local Email Debug Assistant -->
        <div class="dev-helper" id="devHelper">
            <div class="dev-helper-header">
                <div class="dev-helper-title">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span><?= htmlspecialchars(__('dev_debugger_title')) ?></span>
                </div>
                <button class="btn-icon" onclick="document.getElementById('devHelper').style.display='none'" style="font-size: 1rem; line-height: 1;">&times;</button>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 6px;">
                <strong><?= htmlspecialchars(__('dev_debugger_to')) ?></strong> <?= htmlspecialchars($_SESSION['dev_last_email']['to']) ?><br>
                <strong><?= htmlspecialchars(__('dev_debugger_sent')) ?></strong> <?= htmlspecialchars($_SESSION['dev_last_email']['time']) ?>
            </div>
            <div style="font-size: 0.85rem; margin-bottom: 8px;">
                <?= htmlspecialchars(__('dev_debugger_desc')) ?>
            </div>
            <div class="dev-helper-code">
                <?= htmlspecialchars($_SESSION['dev_last_email']['token']) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); text-align: right;">
                <?= htmlspecialchars(__('dev_debugger_footer')) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(__('footer_rights')) ?></p>
        </div>
    </footer>
</body>
</html>

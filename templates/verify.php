<?php
/**
 * OTP verification form template
 */
declare(strict_types=1);

$email = $_SESSION['pending_email'] ?? '';
?>
<div class="glass-card" style="margin: 40px auto;">
    <h2 class="card-title"><?= htmlspecialchars(__('verify_title')) ?></h2>
    <p class="card-desc">
        <?php if (get_current_lang() === 'ja'): ?>
            <span style="color: var(--accent-primary); font-weight: 600;"><?= htmlspecialchars($email) ?></span> 宛てに送信した6桁の認証コードを入力してください。
        <?php else: ?>
            Please enter the 6-digit verification code sent to <span style="color: var(--accent-primary); font-weight: 600;"><?= htmlspecialchars($email) ?></span>.
        <?php endif; ?>
    </p>
    
    <div class="alert alert-info" style="margin-bottom: 24px;">
        <svg style="width: 24px; height: 24px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <div><?= __('verify_spam_alert') ?></div>
    </div>
    
    <form action="index.php?action=verify" method="POST" id="otpForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="passcode" id="hiddenPasscode">
        
        <div class="otp-container">
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="one-time-code">
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input class="otp-input" type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
        </div>
        
        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">
            <?= htmlspecialchars(__('btn_verify')) ?>
        </button>
    </form>
    
    <div style="margin-top: 24px; font-size: 0.9rem; color: var(--text-secondary);">
        <?php if (get_current_lang() === 'ja'): ?>
            やり直す場合は <a href="index.php?action=login" style="font-weight: 600;">こちらをクリック</a>
        <?php else: ?>
            Need to restart? <a href="index.php?action=login" style="font-weight: 600;">Click here</a>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.otp-input');
    const form = document.getElementById('otpForm');
    const hiddenInput = document.getElementById('hiddenPasscode');
    
    // Focus first input automatically
    inputs[0].focus();
    
    // Handle typing and auto-focusing
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            // Clean non-digits
            e.target.value = value.replace(/[^0-9]/g, '');
            
            if (e.target.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            updateHiddenField();
        });
        
        // Handle backspace key
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace') {
                if (!e.target.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                } else {
                    e.target.value = '';
                }
                updateHiddenField();
            }
        });
        
        // Handle paste event
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData('text');
            const cleanDigits = pastedData.replace(/[^0-9]/g, '').substring(0, 6);
            
            cleanDigits.split('').forEach((char, i) => {
                if (inputs[i]) {
                    inputs[i].value = char;
                    if (inputs[i+1]) {
                        inputs[i+1].focus();
                    }
                }
            });
            updateHiddenField();
            
            // If we pasted a full 6-digit code, submit automatically
            if (cleanDigits.length === 6) {
                form.submit();
            }
        });
    });
    
    function updateHiddenField() {
        let code = '';
        inputs.forEach(input => {
            code += input.value;
        });
        hiddenInput.value = code;
    }
    
    form.addEventListener('submit', (e) => {
        updateHiddenField();
        if (hiddenInput.value.length !== 6) {
            e.preventDefault();
            alert('<?= htmlspecialchars(__('js_verify_error'), ENT_QUOTES) ?>');
        }
    });
});
</script>

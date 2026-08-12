<?php
/**
 * includes/credentials_card.php
 *
 * A beautiful, printable card showing a newly-created (or reset) login's
 * username/temporary password, with the school's name & logo on it.
 * Include this file wherever $__cred is set to an array with keys:
 *   full_name, role_label, username, password, and optionally roll_no, class_name.
 * Password is only ever shown ONCE, right after creation/reset — it is never
 * stored in plaintext, so this is the only chance to see/print it.
 */
if (!isset($__cred) || !is_array($__cred)) return;

$__settings  = function_exists('get_settings') ? get_settings() : [];
$__brandName = !empty($__settings['school_name']) ? $__settings['school_name'] : APP_NAME;
$__brandLogo = !empty($__settings['logo']) ? BASE_URL . 'assets/uploads/branding/' . $__settings['logo'] : null;
$__cardId    = 'credCard_' . substr(md5(($__cred['username'] ?? '') . microtime()), 0, 8);
?>
<div class="cred-card" id="<?php echo $__cardId; ?>">
    <div class="cred-card-inner">
        <div class="cred-card-header">
            <?php if ($__brandLogo): ?>
                <img src="<?php echo e($__brandLogo); ?>" alt="logo" class="cred-card-logo">
            <?php else: ?>
                <div class="cred-card-logo cred-card-logo-fallback">🏫</div>
            <?php endif; ?>
            <div>
                <div class="cred-card-school"><?php echo e($__brandName); ?></div>
                <div class="cred-card-subtitle">Login Credentials</div>
            </div>
        </div>

        <div class="cred-card-person">
            <div class="cred-card-person-name"><?php echo e($__cred['full_name'] ?? ''); ?></div>
            <?php if (!empty($__cred['roll_no']) || !empty($__cred['class_name'])): ?>
                <div class="cred-card-person-meta">
                    <?php if (!empty($__cred['roll_no'])): ?>Roll No: <?php echo e($__cred['roll_no']); ?><?php endif; ?>
                    <?php if (!empty($__cred['roll_no']) && !empty($__cred['class_name'])): ?> &nbsp;|&nbsp; <?php endif; ?>
                    <?php if (!empty($__cred['class_name'])): ?>Class: <?php echo e($__cred['class_name']); ?><?php endif; ?>
                </div>
            <?php elseif (!empty($__cred['role_label'])): ?>
                <div class="cred-card-person-meta"><?php echo e($__cred['role_label']); ?></div>
            <?php endif; ?>
        </div>

        <div class="cred-card-fields">
            <div class="cred-card-field">
                <span class="cred-card-label">Username</span>
                <span class="cred-card-value"><?php echo e($__cred['username'] ?? ''); ?></span>
            </div>
            <div class="cred-card-field">
                <span class="cred-card-label">Temporary Password</span>
                <span class="cred-card-value"><?php echo e($__cred['password'] ?? ''); ?></span>
            </div>
        </div>

        <div class="cred-card-note">
            ⚠️ This password is shown only once and cannot be recovered later — please save or print it now.
            The user must change it on first login.
        </div>

        <div class="cred-card-actions no-print">
            <button type="button" class="btn btn-sm" onclick="window.print()">🖨️ Print</button>
            <button type="button" class="btn btn-sm btn-secondary cred-copy-btn"
                    data-cred-text="<?php echo e(($__cred['username'] ?? '') . ' / ' . ($__cred['password'] ?? '')); ?>">📋 Copy</button>
        </div>
    </div>
</div>
<script>
(function() {
    var btn = document.currentScript.previousElementSibling.querySelector('.cred-copy-btn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var text = btn.getAttribute('data-cred-text');
        navigator.clipboard.writeText(text).then(function() {
            var original = btn.textContent;
            btn.textContent = '✅ Copied';
            setTimeout(function() { btn.textContent = '📋 Copy'; }, 1500);
        });
    });
})();
</script>

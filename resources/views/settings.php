<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$currentTab = $_GET['tab'] ?? 'updates';
$role = $_SESSION['role'] ?? 'user';
$isAdmin = in_array($role, ['super_admin', 'admin']);
$settings = $settings ?? [];
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Settings</h1>
    <p style="font-size: 13px; color: var(--text-secondary);">Manage your instance, version lifecycle, and system settings.</p>
</div>

<?php if ($msg = flash('success')): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 20px;">
        <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i><?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 20px;">
        ✓ LinkForge successfully updated to the latest release!
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="table-tabs" style="margin-bottom: 24px;">
    <a href="<?= $baseURL ?>/settings?tab=updates" class="tab-item <?= $currentTab === 'updates' ? 'active' : '' ?>">Updates</a>
    <?php if ($isAdmin): ?>
        <a href="<?= $baseURL ?>/settings?tab=email" class="tab-item <?= $currentTab === 'email' ? 'active' : '' ?>">Email</a>
        <a href="<?= $baseURL ?>/settings?tab=security" class="tab-item <?= $currentTab === 'security' ? 'active' : '' ?>">Security</a>
        <a href="<?= $baseURL ?>/domains" class="tab-item <?= strpos($currentURI ?? '', '/domains') !== false ? 'active' : '' ?>">Domains</a>
        <a href="<?= $baseURL ?>/settings/health" class="tab-item <?= strpos($currentURI ?? '', '/settings/health') !== false ? 'active' : '' ?>">Health</a>
    <?php endif; ?>
</div>

<?php if ($currentTab === 'updates'): ?>
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; max-width: 680px; margin-bottom: 24px;">
        <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">System Updates</div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">Keep your LinkForge core, database schemas, and dependencies current.</p>

        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);">
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Current version</div>
                <div class="font-mono" style="font-size: 16px; font-weight: 600; margin-top: 2px;">v<?= htmlspecialchars($current_version) ?></div>
            </div>

            <?php if (!$update_available): ?>
                <span class="badge badge-active" style="padding: 4px 10px; font-size: 12px;">Up to date</span>
            <?php else: ?>
                <span class="badge badge-expired" style="padding: 4px 10px; font-size: 12px;">Update Available</span>
            <?php endif; ?>
        </div>

        <?php if ($update_available): ?>
            <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-top: 20px;">
                <div style="color: var(--status-active-text); font-weight: 600; font-size: 14px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: var(--status-active-text); border-radius: 50%;"></span>
                    Release available: v<?= htmlspecialchars($latest_version) ?>
                </div>
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                    Includes recent features, security patches, and database migrations.
                </p>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <form method="POST" action="<?= $baseURL ?>/settings/update" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Apply Update</button>
                    </form>
                    <a href="https://github.com/sushantkumar-web/linkforge/releases/latest" target="_blank" class="btn btn-secondary btn-sm">View Release Notes</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div style="font-size: 12px; color: var(--text-muted); line-height: 1.6;">
        LinkForge Telemetry is currently disabled.<br>
        Self-hosted instance running on Apache + PHP.
    </div>

<?php elseif ($currentTab === 'email' && $isAdmin): ?>

    <!-- Email configuration -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; max-width: 680px; margin-bottom: 24px;">
        <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">Email Configuration</div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            How LinkForge sends password resets, welcome emails, and notifications.
        </p>

        <form method="POST" action="<?= $baseURL ?>/settings/email">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label class="form-label">Delivery Method</label>
                <select name="mail_driver" id="mailDriver" class="form-input" onchange="toggleDriverFields()">
                    <option value="log"   <?= ($settings['mail_driver'] ?? 'log') === 'log'   ? 'selected' : '' ?>>Log (development) — writes emails to storage/logs/mail/</option>
                    <option value="smtp"  <?= ($settings['mail_driver'] ?? '') === 'smtp'  ? 'selected' : '' ?>>SMTP (production) — recommended for real delivery</option>
                    <option value="mail"  <?= ($settings['mail_driver'] ?? '') === 'mail'  ? 'selected' : '' ?>>Native mail() — legacy, only use if your host requires it</option>
                </select>
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 6px;">
                    Use <strong>Log</strong> during development. Switch to <strong>SMTP</strong> when you deploy to production.
                </span>
            </div>

            <div id="smtpFields" style="display: none;">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" placeholder="587" class="form-input font-mono">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Encryption</label>
                    <select name="smtp_secure" class="form-input">
                        <option value="tls"  <?= ($settings['smtp_secure'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS (port 587)</option>
                        <option value="ssl"  <?= ($settings['smtp_secure'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL (port 465)</option>
                        <option value="none" <?= ($settings['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>None (local Mailpit / unencrypted)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" placeholder="you@example.com" autocomplete="off" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="smtp_pass" placeholder="<?= !empty($settings['smtp_pass']) ? '•••••••• (leave blank to keep current)' : 'Enter SMTP password' ?>" autocomplete="new-password" class="form-input">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                        Stored in your database. Never displayed again.
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-top: 8px;">
                <div class="form-group">
                    <label class="form-label">From Email</label>
                    <input type="email" name="from_email" value="<?= htmlspecialchars($settings['from_email'] ?? '') ?>" placeholder="hello@yourdomain.com" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">From Name</label>
                    <input type="text" name="from_name" value="<?= htmlspecialchars($settings['from_name'] ?? 'LinkForge') ?>" placeholder="LinkForge" class="form-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Test email card -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; max-width: 680px;">
        <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">Send a Test Email</div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
            Verify your configuration by sending a real email to any address.
        </p>

        <div style="display: flex; gap: 8px; max-width: 480px;">
            <input type="email" id="testEmailInput" placeholder="you@example.com" class="form-input" style="flex: 1;">
            <button type="button" onclick="sendTestEmail()" class="btn btn-secondary" id="testEmailBtn">Send Test</button>
        </div>

        <div id="testEmailResult" style="margin-top: 12px; font-size: 13px; display: none;"></div>
    </div>

    <?php elseif ($currentTab === 'security' && $isAdmin): ?>

    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; max-width: 680px;">
        <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">Captcha Protection</div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            Require a captcha challenge on login and password reset forms to block bot attacks.
        </p>

        <form method="POST" action="<?= $baseURL ?>/settings/security">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="captcha_enabled" value="1" <?= ($settings['captcha_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    Enable captcha on login and password reset
                </label>
            </div>

            <div class="form-group">
                <label class="form-label">Provider</label>
                <select name="captcha_provider" class="form-input">
                    <option value="turnstile" <?= ($settings['captcha_provider'] ?? 'turnstile') === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile (recommended — free, privacy-first, invisible)</option>
                    <option value="recaptcha" <?= ($settings['captcha_provider'] ?? '') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA v3</option>
                    <option value="hcaptcha"  <?= ($settings['captcha_provider'] ?? '') === 'hcaptcha'  ? 'selected' : '' ?>>hCaptcha</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Site Key</label>
                <input type="text" name="captcha_site_key" value="<?= htmlspecialchars($settings['captcha_site_key'] ?? '') ?>" placeholder="Public site key" class="form-input font-mono">
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                    Get this from your captcha provider dashboard.
                </span>
            </div>

            <div class="form-group">
                <label class="form-label">Secret Key</label>
                <input type="password" name="captcha_secret_key" placeholder="<?= !empty($settings['captcha_secret_key']) ? '•••••••• (leave blank to keep current)' : 'Private secret key' ?>" class="form-input font-mono" autocomplete="new-password">
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                    Stored in your database. Never displayed again.
                </span>
            </div>

            <div style="background: rgba(110,123,242,0.08); border: 1px solid var(--accent-border); border-radius: var(--radius-sm); padding: 12px 14px; margin-top: 16px; font-size: 12px; color: var(--text-secondary);">
                <strong style="color: var(--accent-text);">Setup guide for Turnstile:</strong>
                <ol style="margin: 6px 0 0 20px; padding: 0; line-height: 1.8;">
                    <li>Sign in at <a href="https://dash.cloudflare.com" target="_blank" style="color: var(--accent);">dash.cloudflare.com</a></li>
                    <li>Go to <strong>Turnstile</strong> in the sidebar</li>
                    <li>Click <strong>Add site</strong>, enter your hostname (e.g. <code>links.yourdomain.com</code>)</li>
                    <li>Copy the Site Key and Secret Key into the fields above</li>
                </ol>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php if ($currentTab === 'email' && $isAdmin): ?>
<script>
function toggleDriverFields() {
    var driver = document.getElementById('mailDriver');
    var smtp = document.getElementById('smtpFields');
    if (!driver || !smtp) return;
    smtp.style.display = (driver.value === 'smtp') ? 'block' : 'none';
}
toggleDriverFields();

function sendTestEmail() {
    var btn = document.getElementById('testEmailBtn');
    var input = document.getElementById('testEmailInput');
    var result = document.getElementById('testEmailResult');
    var baseURL = '<?= $baseURL ?>';

    var email = input.value.trim();
    if (!email) {
        result.style.display = 'block';
        result.style.color = '#EF4444';
        result.textContent = 'Enter an email address first.';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Sending…';
    result.style.display = 'none';

    var fd = new FormData();
    fd.append('test_email', email);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    fetch(baseURL + '/settings/email/test', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        result.style.display = 'block';
        if (data.ok) {
            result.style.color = '#10B981';
            result.textContent = '✓ ' + data.message;
        } else {
            result.style.color = '#EF4444';
            result.textContent = '✕ ' + (data.error || 'Unknown error');
        }
    })
    .catch(function (err) {
        result.style.display = 'block';
        result.style.color = '#EF4444';
        result.textContent = '✕ Request failed: ' + err.message;
    })
    .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Send Test';
    });
}
</script>
<?php endif; ?>
<?php
$slot = ob_get_clean();
$pageTitle = "Settings - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
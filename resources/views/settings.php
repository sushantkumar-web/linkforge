<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Settings</h1>
    <p style="font-size: 13px; color: var(--text-secondary);">Manage your instance, version lifecycle, and system settings.</p>
</div>

<?php if(isset($_GET['updated'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 20px;">
        ✓ LinkForge successfully updated to the latest release!
    </div>
<?php endif; ?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; max-width: 680px; margin-bottom: 24px;">
    <div style="font-size: 15px; font-weight: 600; margin-bottom: 4px;">System Updates</div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">Keep your LinkForge core, database schemas, and dependencies current.</p>

    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Current version</div>
            <div class="font-mono" style="font-size: 16px; font-weight: 600; margin-top: 2px;">v<?= htmlspecialchars($current_version) ?></div>
        </div>

        <?php if(!$update_available): ?>
            <span class="badge badge-active" style="padding: 4px 10px; font-size: 12px;">Up to date</span>
        <?php else: ?>
            <span class="badge badge-expired" style="padding: 4px 10px; font-size: 12px;">Update Available</span>
        <?php endif; ?>
    </div>

    <?php if($update_available): ?>
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

<?php
$slot = ob_get_clean();
$pageTitle = "Settings - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
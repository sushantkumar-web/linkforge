<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">API Keys</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Manage developer access tokens for the REST API.</p>
    </div>
    <form method="POST" action="<?= $baseURL ?>/api-keys/create" style="margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" class="btn btn-primary">+ Create key</button>
    </form>
</div>

<?php if(empty($api_keys)): ?>
    <div class="table-card" style="padding: 48px; text-align: center; color: var(--text-muted);">
        <p style="margin-bottom: 12px;">No API keys yet. Create one to start using LinkForge programmatically.</p>
        <form method="POST" action="<?= $baseURL ?>/api-keys/create" style="margin: 0; display: inline-block;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm">+ Create your first key</button>
        </form>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach($api_keys as $key): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Production API Key</div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="font-mono" style="font-size: 13px; color: var(--text-secondary); background: var(--bg-app); padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <?= substr($key['api_key'], 0, 12) ?>••••••••••••••••••
                        </span>
                        <button onclick="copyToClipboard('<?= $key['api_key'] ?>')" class="btn btn-secondary btn-sm">Copy</button>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                        Last used: <?= $key['last_used_at'] ? date('M j, Y H:i', strtotime($key['last_used_at'])) : 'Never' ?>
                    </div>
                </div>

                <div>
                    <form method="POST" action="<?= $baseURL ?>/api-keys/revoke" onsubmit="return confirm('Revoke this API key? Applications using it will immediately lose access.');" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
$pageTitle = "API Keys - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
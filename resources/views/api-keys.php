<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<!-- LinkForge Modal System CSS (Matched to links.php) -->
<style>
.lf-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.lf-modal-backdrop.active {
    display: flex !important;
}
.lf-modal-card {
    background: #16191F;
    border: 1px solid #282C34;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
}
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">API Keys</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Manage programmatic access tokens, scopes, and inspect API activity.</p>
    </div>
    <button type="button" onclick="openModal('createKeyModal')" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Generate New Key
    </button>
</div>

<!-- Flash One-Time Token Banner -->
<?php if (!empty($newKeyPlain)): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
        <span style="font-size: 14px; font-weight: 600; color: #10B981;">
            <i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i> API Key Generated for <?= htmlspecialchars($newKeyPlain['name']) ?>
        </span>
        <span style="font-size: 12px; color: #EF4444; font-weight: 500;">Copy it now. It will never be shown again.</span>
    </div>
    <div style="display: flex; gap: 8px;">
        <input type="text" id="plainTokenInput" readonly value="<?= htmlspecialchars($newKeyPlain['token']) ?>" class="form-input font-mono" style="background: #0B0D11; border-color: #10B981; color: #F3F4F6;">
        <button type="button" class="btn btn-primary" onclick="copyPlainToken()">Copy</button>
    </div>
</div>
<script>
function copyPlainToken() {
    const input = document.getElementById('plainTokenInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        // Toast notification instead of an alert
        const toast = document.createElement('div');
        toast.innerText = 'API Key copied to clipboard!';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#10B981;color:white;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}
</script>
<?php endif; ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total API Calls</div>
        <div class="value font-mono"><?= number_format((int)($metrics['total_calls'] ?? 0)) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Active Keys</div>
        <div class="value font-mono"><?= number_format((int)($metrics['active_keys'] ?? 0)) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Total Generated</div>
        <div class="value font-mono"><?= number_format((int)($metrics['total_keys'] ?? 0)) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Default Limit</div>
        <div class="value font-mono">60 <span style="font-size: 12px; font-weight: 400; color: var(--text-muted);">req/min</span></div>
    </div>
</div>

<!-- API Keys Table Card -->
<div class="table-card" style="margin-bottom: 32px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 14px;">
        Active Tokens
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Key Name</th>
                <th>Token Prefix</th>
                <th>Permissions</th>
                <th>Rate Limit</th>
                <th>Usage</th>
                <th>Last Active</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No API keys found. Generate a key to begin integrating programmatically.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($keys as $k): ?>
            <tr style="<?= $k['status'] !== 'active' ? 'opacity: 0.6;' : '' ?>">
                <td>
                    <div style="font-weight: 600;">
                        <?= htmlspecialchars($k['name']) ?>
                        <?php if ($k['status'] !== 'active'): ?>
                            <span class="badge" style="background: rgba(239,68,68,0.15); color: #EF4444; font-size: 10px; margin-left: 6px;">Revoked</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted);">Created <?= date('M j, Y', strtotime($k['created_at'])) ?></div>
                </td>
                <td class="font-mono" style="color: var(--accent);"><?= htmlspecialchars($k['key_prefix']) ?></td>
                <td>
                    <?php foreach (explode(',', $k['scopes']) as $scope): ?>
                        <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; margin-right: 4px; font-size: 10px;">
                            <?= htmlspecialchars(trim($scope)) ?>
                        </span>
                    <?php endforeach; ?>
                </td>
                <td class="font-mono" style="font-size: 12px;"><?= (int)$k['rate_limit_rpm'] ?>/min</td>
                <td class="font-mono"><?= number_format((int)$k['total_requests']) ?></td>
                <td style="font-size: 12px; color: var(--text-muted);">
                    <?= !empty($k['last_used_at']) ? date('M j, H:i', strtotime($k['last_used_at'])) : 'Never' ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <form method="POST" action="<?= $baseURL ?>/api-keys/revoke" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?= $k['status'] === 'active' ? 'Revoke' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= $baseURL ?>/api-keys/delete" style="display:inline;" onsubmit="return confirm('Delete this API key permanently?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- API Request Telemetry Logs -->
<div class="table-card">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 14px;">
        Live Request Telemetry (Last 15 Hits)
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Key Used</th>
                <th>Client IP</th>
                <th style="text-align: right;">Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentLogs)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 32px; color: var(--text-muted);">
                        No requests logged yet. Calls using Bearer tokens will populate here in real-time.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td>
                    <span class="badge" style="background: <?= $log['status_code'] < 400 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)' ?>; color: <?= $log['status_code'] < 400 ? '#10B981' : '#EF4444' ?>; font-size: 11px;">
                        <?= (int)$log['status_code'] ?>
                    </span>
                </td>
                <td class="font-mono" style="font-weight: 600; font-size: 12px;"><?= htmlspecialchars($log['http_method']) ?></td>
                <td class="font-mono" style="color: var(--text-primary);"><?= htmlspecialchars($log['endpoint']) ?></td>
                <td>
                    <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($log['key_name']) ?></span>
                    <span class="font-mono" style="font-size: 11px; color: var(--text-muted);">(<?= htmlspecialchars($log['key_prefix']) ?>)</span>
                </td>
                <td class="font-mono" style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($log['ip_address']) ?></td>
                <td style="text-align: right; font-size: 12px; color: var(--text-muted);">
                    <?= date('M j, H:i:s', strtotime($log['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Generate Key -->
<div id="createKeyModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Generate New API Key</h3>
        <form method="POST" action="<?= $baseURL ?>/api-keys/generate">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label class="form-label">Key Name / Description</label>
                <input type="text" name="name" placeholder="e.g. CI/CD Deployment or Mobile App" required class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Permissions (Scopes)</label>
                <div style="display: flex; gap: 16px; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" name="scopes[]" value="links:read" checked> Read Links
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" name="scopes[]" value="links:write" checked> Create / Edit Links
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Rate Limit (Requests / Minute)</label>
                <input type="number" name="rate_limit_rpm" value="60" min="10" max="1000" class="form-input font-mono">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('createKeyModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Key</button>
            </div>
        </form>
    </div>
</div>

<script>
// Backdrop click to close (Matched to links.php)
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) {
        window.closeModal(e.target.id);
    }
});

window.openModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
    }
};

window.closeModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
    }
};
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "API Keys - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
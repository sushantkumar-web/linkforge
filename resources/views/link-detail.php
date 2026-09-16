<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$isSuperAdmin = ($_SESSION['role'] ?? 'user') === 'super_admin';
$activeTab = $_GET['tab'] ?? 'overview';

function renderBar($items, $total, $color = '#5B5CE2') {
    if (empty($items) || $total <= 0) {
        echo '<div style="color: var(--text-muted); font-size: 13px; padding: 12px 0;">No data yet.</div>';
        return;
    }
    foreach ($items as $item) {
        $count = (int)$item['count'];
        $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        $name = htmlspecialchars($item['name'] ?? 'Unknown');
        echo '<div style="margin-bottom: 12px;">';
        echo '  <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">';
        echo "    <span style=\"color: var(--text-primary);\">{$name}</span>";
        echo "    <span style=\"color: var(--text-muted); font-family: monospace;\">{$count} <span style=\"color: var(--text-secondary);\">({$pct}%)</span></span>";
        echo '  </div>';
        echo '  <div style="height: 6px; background: var(--bg-app); border-radius: 3px; overflow: hidden;">';
        echo "    <div style=\"height: 100%; width: {$pct}%; background: {$color}; border-radius: 3px;\"></div>";
        echo '  </div>';
        echo '</div>';
    }
}

// Decode targeting rules once for reuse
$targetingRules = [];
if (!empty($link['targeting'])) {
    $decoded = json_decode($link['targeting'], true);
    if (is_array($decoded)) $targetingRules = $decoded;
}
?>

<style>
.link-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.link-detail-title {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 6px;
    word-break: break-word;
}
.link-detail-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.link-detail-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.link-detail-tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.link-detail-tabs::-webkit-scrollbar { display: none; }
.link-detail-tabs .tab-item {
    flex-shrink: 0;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    border-bottom: 2px solid transparent;
    text-decoration: none;
    white-space: nowrap;
}
.link-detail-tabs .tab-item:hover { color: var(--text-primary); }
.link-detail-tabs .tab-item.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.detail-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.breakdown-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 20px;
}
.breakdown-card-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 16px;
}
.chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 160px;
    padding: 0 4px;
}
.chart-bar-col {
    flex: 1;
    height: 100%;
    display: flex;
    align-items: flex-end;
}
.chart-bar {
    width: 100%;
    min-height: 2px;
    border-radius: 2px 2px 0 0;
    transition: background 0.15s;
}
.chart-bar.empty { background: var(--border-subtle); }
.chart-bar.filled { background: var(--accent); }

@media (max-width: 768px) {
    .link-detail-title { font-size: 18px; }
    .link-detail-actions { width: 100%; }
    .link-detail-actions > * { flex: 1; }
    .detail-grid-2 { grid-template-columns: 1fr; }
    .chart-bars { height: 120px; }
}
</style>

<!-- Breadcrumb -->
<div style="margin-bottom: 20px; font-size: 12px; color: var(--text-muted);">
    <a href="<?= $baseURL ?>/links" style="color: var(--text-muted); text-decoration: none;">← Back to links</a>
</div>

<!-- Header -->
<div class="link-detail-header">
    <div style="min-width: 0; flex: 1;">
        <h1 class="link-detail-title"><?= htmlspecialchars($link['title'] ?: 'Untitled link') ?></h1>
        <div class="link-detail-meta">
            <a href="<?= htmlspecialchars($shortUrl) ?>" target="_blank" class="font-mono" style="color: var(--accent); font-weight: 600; text-decoration: none; font-size: 14px; word-break: break-all;">
                <?= htmlspecialchars($shortUrl) ?>
            </a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($shortUrl) ?>')">
                <i class="fa-regular fa-copy"></i>
            </button>
            <?php if ($isExpired): ?>
                <span class="badge badge-expired">EXPIRED</span>
            <?php elseif ($link['status'] === 'active'): ?>
                <span class="badge badge-active">active</span>
            <?php else: ?>
                <span class="badge badge-disabled">disabled</span>
            <?php endif; ?>
            <?php if (!empty($link['pass_hash'])): ?>
                <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8;">
                    <i class="fa-solid fa-lock" style="margin-right: 3px;"></i> Password
                </span>
            <?php endif; ?>
            <?php if (!empty($targetingRules)): ?>
                <span class="badge" style="background: rgba(245,158,11,0.15); color: #F59E0B;">
                    <i class="fa-solid fa-shuffle" style="margin-right: 3px;"></i> <?= count($targetingRules) ?> rule<?= count($targetingRules) === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
            <?php if ($isSuperAdmin && !empty($link['owner_email'])): ?>
                <span style="font-size: 11px; color: var(--text-muted);">Owner: <?= htmlspecialchars($link['owner_email']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="link-detail-actions">
        <form method="POST" action="<?= $baseURL ?>/links/toggle" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?= $link['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="POST" action="<?= $baseURL ?>/links/delete" style="display:inline;" onsubmit="return confirm('Delete this link permanently? All analytics will be lost.')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
    </div>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total clicks</div>
        <div class="value font-mono"><?= number_format((int)$kpis['total_clicks']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Unique visitors</div>
        <div class="value font-mono"><?= number_format((int)$kpis['unique_visitors']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Created</div>
        <div class="value" style="font-size: 16px;"><?= date('M j, Y', strtotime($link['created_at'])) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Expires</div>
        <div class="value" style="font-size: 16px;"><?= !empty($link['expires_at']) ? date('M j, Y', strtotime($link['expires_at'])) : 'Never' ?></div>
    </div>
</div>

<!-- Tabs -->
<div class="link-detail-tabs">
    <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>&tab=overview" class="tab-item <?= $activeTab === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>&tab=analytics" class="tab-item <?= $activeTab === 'analytics' ? 'active' : '' ?>">Analytics</a>
    <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>&tab=qr" class="tab-item <?= $activeTab === 'qr' ? 'active' : '' ?>">QR Code</a>
    <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>&tab=activity" class="tab-item <?= $activeTab === 'activity' ? 'active' : '' ?>">Activity</a>
    <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>&tab=edit" class="tab-item <?= $activeTab === 'edit' ? 'active' : '' ?>">Edit</a>
</div>

<?php if ($activeTab === 'overview'): ?>

    <div class="breakdown-card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="font-size: 14px; font-weight: 600;">Clicks — last 30 days</div>
            <div style="font-size: 12px; color: var(--text-muted);">Peak: <span class="font-mono" style="color: var(--text-primary);"><?= number_format($maxTimeline) ?></span></div>
        </div>
        <?php if (array_sum(array_column($timeline, 'count')) === 0): ?>
            <div style="padding: 60px 20px; text-align: center; color: var(--text-muted); font-size: 13px;">
                No clicks yet. Share your link to start collecting data.
            </div>
        <?php else: ?>
            <div class="chart-bars">
                <?php foreach ($timeline as $t):
                    $h = $maxTimeline > 0 ? max(2, round(($t['count'] / $maxTimeline) * 100)) : 2;
                ?>
                    <div class="chart-bar-col" title="<?= htmlspecialchars($t['label']) ?>: <?= $t['count'] ?> clicks">
                        <div class="chart-bar <?= $t['count'] === 0 ? 'empty' : 'filled' ?>" style="height: <?= $h ?>%;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; color: var(--text-muted); font-family: monospace;">
                <span><?= htmlspecialchars($timeline[0]['label']) ?></span>
                <span><?= htmlspecialchars($timeline[count($timeline) - 1]['label']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-grid-2">
        <div class="breakdown-card">
            <div class="breakdown-card-title">Traffic sources</div>
            <?php renderBar($referrers, (int)$kpis['total_clicks'], '#5B5CE2'); ?>
        </div>
        <div class="breakdown-card">
            <div class="breakdown-card-title">Devices</div>
            <?php renderBar($devices, (int)$kpis['total_clicks'], '#10B981'); ?>
        </div>
    </div>

<?php elseif ($activeTab === 'analytics'): ?>

    <div class="detail-grid-2">
        <div class="breakdown-card">
            <div class="breakdown-card-title">Browsers</div>
            <?php renderBar($browsers, (int)$kpis['total_clicks'], '#F59E0B'); ?>
        </div>
        <div class="breakdown-card">
            <div class="breakdown-card-title">Operating systems</div>
            <?php renderBar($oses, (int)$kpis['total_clicks'], '#EF4444'); ?>
        </div>
        <div class="breakdown-card">
            <div class="breakdown-card-title">Referrers</div>
            <?php renderBar($referrers, (int)$kpis['total_clicks'], '#8B5CF6'); ?>
        </div>
        <div class="breakdown-card">
            <div class="breakdown-card-title">Devices</div>
            <?php renderBar($devices, (int)$kpis['total_clicks'], '#10B981'); ?>
        </div>
    </div>

    <div style="text-align: right; margin-top: 20px;">
        <a href="<?= $baseURL ?>/analytics/export?id=<?= (int)$link['id'] ?>" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-download" style="margin-right: 4px; font-size: 11px;"></i> Export CSV
        </a>
    </div>

<?php elseif ($activeTab === 'qr'): ?>

    <div class="breakdown-card" style="padding: 32px; text-align: center;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 20px;">QR Code</div>
        <div style="display: inline-block; background: white; padding: 16px; border-radius: 8px;">
            <img src="<?= $baseURL ?>/qr?id=<?= (int)$link['id'] ?>" alt="QR Code" style="display: block; width: 220px; height: 220px; max-width: 100%;">
        </div>
        <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <a href="<?= $baseURL ?>/qr?id=<?= (int)$link['id'] ?>&dl=1" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-download" style="margin-right: 4px; font-size: 11px;"></i> Download PNG
            </a>
            <a href="<?= $baseURL ?>/qr?id=<?= (int)$link['id'] ?>&size=15" target="_blank" class="btn btn-secondary btn-sm">Preview large</a>
        </div>
        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 16px;">
            The QR code points to the short URL, so changing the destination doesn't invalidate printed codes.
        </p>
    </div>

<?php elseif ($activeTab === 'activity'): ?>

    <div class="table-card">
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Referrer</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>OS</th>
                        <th style="text-align: right;">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activity)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">No clicks recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($activity as $a): ?>
                    <tr>
                        <td style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($a['referrer'] ?: 'Direct') ?></td>
                        <td><span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; font-size: 11px;"><?= htmlspecialchars($a['device_type']) ?></span></td>
                        <td style="font-size: 12px;"><?= htmlspecialchars($a['browser']) ?></td>
                        <td style="font-size: 12px;"><?= htmlspecialchars($a['os']) ?></td>
                        <td style="text-align: right; font-size: 12px; color: var(--text-muted);"><?= date('M j, H:i:s', strtotime($a['clicked_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'edit'): ?>

    <div class="breakdown-card" style="padding: 24px; max-width: 720px;">
        <h3 style="font-size: 15px; margin-bottom: 20px;">Edit link</h3>
        <form method="POST" action="<?= $baseURL ?>/links/update" onsubmit="syncDetailTargeting()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">

            <div class="form-group">
                <label class="form-label">Destination URL</label>
                <input type="url" name="url" value="<?= htmlspecialchars($link['destination_url']) ?>" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($link['title'] ?? '') ?>" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Tags (comma-separated)</label>
                <input type="text" name="tags" value="<?= htmlspecialchars($link['tags'] ?? '') ?>" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration</label>
                <input type="text" name="expires_at" value="<?= htmlspecialchars($link['expires_at'] ?? '') ?>" placeholder="YYYY-MM-DD HH:MM:SS (leave blank for never)" class="form-input font-mono">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration Fallback URL</label>
                <input type="url" name="fallback_url" value="<?= htmlspecialchars($link['fallback_url'] ?? '') ?>" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" placeholder="<?= !empty($link['pass_hash']) ? 'Enter new password to change' : 'Leave blank for public access' ?>" class="form-input" autocomplete="new-password">
                <?php if (!empty($link['pass_hash'])): ?>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; margin-top: 8px; color: var(--status-expired-text); cursor: pointer;">
                        <input type="checkbox" name="remove_password" value="1">
                        Remove password protection
                    </label>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;" onclick="toggleDetailTargeting()">
                    <span>Targeting Rules</span>
                    <i class="fa-solid fa-chevron-down" id="detail-targeting-arrow" style="font-size: 11px; transition: transform 0.15s; <?= !empty($targetingRules) ? 'transform: rotate(180deg);' : '' ?>"></i>
                </label>
                <div id="detail-targeting-section" style="margin-top: 10px; <?= empty($targetingRules) ? 'display: none;' : '' ?>">
                    <div id="detail-targeting-rules"></div>
                    <button type="button" class="add-rule-btn" onclick="addDetailRule()">
                        <i class="fa-solid fa-plus" style="margin-right: 4px;"></i> Add Rule
                    </button>
                    <div class="targeting-help">
                        Route visitors to different destinations based on device or country. First matching rule wins.
                    </div>
                    <input type="hidden" name="targeting_json" id="detail-targeting-json" value="<?= htmlspecialchars($link['targeting'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
                <a href="<?= $baseURL ?>/link?id=<?= (int)$link['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>

<style>
.targeting-row {
    display: grid;
    grid-template-columns: 100px 1fr 1.5fr 32px;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
}
.targeting-row select,
.targeting-row input {
    font-size: 12px;
    padding: 6px 8px;
    background: var(--bg-app);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    width: 100%;
}
.targeting-row .remove-rule {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: #EF4444;
    cursor: pointer;
    padding: 4px;
    font-size: 12px;
    line-height: 1;
}
.targeting-row .remove-rule:hover {
    background: rgba(239,68,68,0.1);
}
.targeting-help {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 6px;
}
.add-rule-btn {
    background: transparent;
    border: 1px dashed var(--border-color);
    border-radius: 6px;
    color: var(--text-secondary);
    padding: 6px 12px;
    font-size: 12px;
    cursor: pointer;
    margin-top: 6px;
}
.add-rule-btn:hover {
    color: var(--accent);
    border-color: var(--accent);
}
@media (max-width: 600px) {
    .targeting-row {
        grid-template-columns: 1fr;
        gap: 6px;
        padding: 10px;
        background: var(--bg-app);
        border-radius: 8px;
        margin-bottom: 12px;
    }
    .targeting-row .remove-rule {
        position: absolute;
        top: 8px;
        right: 8px;
    }
    .targeting-row { position: relative; }
}

<script>
// Detail-page targeting engine (self-contained, no dependency on links.php)
window.toggleDetailTargeting = function() {
    var section = document.getElementById('detail-targeting-section');
    var arrow = document.getElementById('detail-targeting-arrow');
    var isHidden = section.style.display === 'none';
    section.style.display = isHidden ? 'block' : 'none';
    if (arrow) arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0)';
};

window.addDetailRule = function(type, match, url) {
    var container = document.getElementById('detail-targeting-rules');
    if (!container) return;

    var row = document.createElement('div');
    row.className = 'targeting-row';

    var select = document.createElement('select');
    select.innerHTML = '<option value="device">Device</option><option value="country">Country</option>';
    select.value = type || 'device';

    var matchInput = document.createElement('input');
    matchInput.placeholder = select.value === 'device' ? 'mobile / desktop / tablet' : 'ISO code (IN, US, GB)';
    matchInput.value = match || '';
    matchInput.className = 'target-match';

    var urlInput = document.createElement('input');
    urlInput.type = 'url';
    urlInput.placeholder = 'https://special-page.com';
    urlInput.value = url || '';
    urlInput.className = 'target-url';

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'remove-rule';
    removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    removeBtn.onclick = function() { row.remove(); window.syncDetailTargeting(); };

    select.onchange = function() {
        matchInput.placeholder = select.value === 'device' ? 'mobile / desktop / tablet' : 'ISO code (IN, US, GB)';
        matchInput.value = '';
    };

    [select, matchInput, urlInput].forEach(function(el) {
        el.addEventListener('input', window.syncDetailTargeting);
        el.addEventListener('change', window.syncDetailTargeting);
    });

    row.appendChild(select);
    row.appendChild(matchInput);
    row.appendChild(urlInput);
    row.appendChild(removeBtn);
    container.appendChild(row);
    window.syncDetailTargeting();
};

window.syncDetailTargeting = function() {
    var container = document.getElementById('detail-targeting-rules');
    var hidden = document.getElementById('detail-targeting-json');
    if (!container || !hidden) return;

    var rules = [];
    container.querySelectorAll('.targeting-row').forEach(function(row) {
        var type = row.querySelector('select').value;
        var match = row.querySelector('.target-match').value.trim();
        var url = row.querySelector('.target-url').value.trim();
        if (type && match && url) rules.push({ type: type, match: match, url: url });
    });

    hidden.value = rules.length > 0 ? JSON.stringify(rules) : '';
};

// Initialize existing rules on load
(function() {
    var existing = <?= json_encode($targetingRules) ?>;
    if (Array.isArray(existing)) {
        existing.forEach(function(r) { window.addDetailRule(r.type, r.match, r.url); });
    }
})();
</script>

<?php endif; ?>

<?php
$slot = ob_get_clean();
$pageTitle = htmlspecialchars($link['title'] ?: 'Link') . " - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$isSuperAdmin = ($_SESSION['role'] ?? 'user') === 'super_admin';

// Helper for rendering breakdown bars
function renderBreakdown($items, $total, $accentColor = '#5B5CE2') {
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
        echo "    <div style=\"height: 100%; width: {$pct}%; background: {$accentColor}; border-radius: 3px;\"></div>";
        echo '  </div>';
        echo '</div>';
    }
}
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Analytics</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">
            <?= $isSuperAdmin ? 'Instance-wide performance across all users.' : 'Cross-link performance for your links.' ?>
        </p>
    </div>

    <!-- Time range switcher -->
    <div style="display: flex; gap: 4px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 4px;">
        <?php foreach (['24h' => '24h', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time'] as $key => $label): ?>
            <a href="<?= $baseURL ?>/analytics?range=<?= $key ?>"
               style="padding: 6px 14px; font-size: 12px; font-weight: 500; border-radius: 6px; text-decoration: none;
                      <?= ($range === $key)
                          ? 'background: var(--accent); color: white;'
                          : 'color: var(--text-secondary);' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total clicks</div>
        <div class="value font-mono"><?= number_format($total_clicks) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Unique visitors</div>
        <div class="value font-mono"><?= number_format($unique_visitors) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label"><?= $isSuperAdmin ? 'Total links (all users)' : 'Your links' ?></div>
        <div class="value font-mono"><?= number_format($total_links) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Avg clicks / link</div>
        <div class="value font-mono"><?= number_format($avg_clicks) ?></div>
    </div>
</div>

<!-- Timeline chart -->
<div class="table-card" style="padding: 24px; margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="font-size: 14px; font-weight: 600;">Clicks over time</div>
        <div style="font-size: 12px; color: var(--text-muted);">
            Peak: <span class="font-mono" style="color: var(--text-primary);"><?= number_format($maxBucket) ?></span> clicks
        </div>
    </div>

    <?php if (array_sum(array_column($timeline, 'count')) === 0): ?>
        <div style="padding: 60px 20px; text-align: center; color: var(--text-muted); font-size: 13px;">
            No clicks in this period. Try a wider time range.
        </div>
    <?php else: ?>
        <div style="display: flex; align-items: flex-end; gap: 2px; height: 180px; padding: 0 4px;">
            <?php foreach ($timeline as $t):
                $heightPct = $maxBucket > 0 ? max(2, round(($t['count'] / $maxBucket) * 100)) : 2;
                $isEmpty = $t['count'] === 0;
            ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end;"
                     title="<?= htmlspecialchars($t['label']) ?>: <?= $t['count'] ?> clicks">
                    <div style="width: 100%; height: <?= $heightPct ?>%; min-height: 2px; background: <?= $isEmpty ? 'var(--border-subtle)' : 'var(--accent)' ?>; border-radius: 2px 2px 0 0; transition: background 0.15s;"
                         onmouseover="this.style.background='<?= $isEmpty ? 'var(--border-subtle)' : '#8B8DF8' ?>'"
                         onmouseout="this.style.background='<?= $isEmpty ? 'var(--border-subtle)' : 'var(--accent)' ?>'"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; color: var(--text-muted); font-family: monospace;">
            <span><?= htmlspecialchars($timeline[0]['label'] ?? '') ?></span>
            <span><?= htmlspecialchars($timeline[count($timeline) - 1]['label'] ?? '') ?></span>
        </div>
    <?php endif; ?>
</div>

<!-- Top links (full width) -->
<div class="table-card" style="margin-bottom: 24px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 14px;">
        Top performing links
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Link</th>
                <th>Destination</th>
                <th style="text-align: right;">Clicks in range</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topLinks)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--text-muted);">No links yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($topLinks as $i => $l): ?>
                <tr>
                    <td class="font-mono" style="color: var(--text-muted); font-size: 12px;"><?= $i + 1 ?></td>
                    <td>
                        <div>
                            <a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" class="font-mono" style="color: var(--accent); font-weight: 600;">/<?= htmlspecialchars($l['short_code']) ?></a>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($l['title'] ?? 'Untitled') ?></div>
                    </td>
                    <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary); font-size: 12px;">
                        <?= htmlspecialchars($l['destination_url']) ?>
                    </td>
                    <td class="font-mono" style="text-align: right; font-weight: 600;"><?= number_format((int)$l['range_clicks']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Breakdown grid: 2x2 -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Traffic sources</div>
        <?php renderBreakdown($referrers, $total_clicks, '#5B5CE2'); ?>
    </div>
    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Devices</div>
        <?php renderBreakdown($devices, $total_clicks, '#10B981'); ?>
    </div>
    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Browsers</div>
        <?php renderBreakdown($browsers, $total_clicks, '#F59E0B'); ?>
    </div>
    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Operating systems</div>
        <?php renderBreakdown($oses, $total_clicks, '#EF4444'); ?>
    </div>
</div>

<!-- Responsive: stack on mobile -->
<style>
@media (max-width: 768px) {
    .kpi-grid { grid-template-columns: 1fr 1fr !important; }
    div[style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>

<?php
$slot = ob_get_clean();
$pageTitle = "Analytics - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
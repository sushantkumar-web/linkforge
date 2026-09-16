<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$isSuperAdmin = ($_SESSION['role'] ?? 'user') === 'super_admin';
$firstName = explode('@', $_SESSION['user_email'] ?? 'there')[0];

// Greeting based on time of day
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

// Helper: render a horizontal breakdown bar
function renderBreakdownRow($name, $count, $total, $color = '#6E7BF2') {
    $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
    $nameEsc = htmlspecialchars($name);
    echo '<div style="margin-bottom: 12px;">';
    echo '  <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">';
    echo "    <span style=\"color: var(--text-primary);\">{$nameEsc}</span>";
    echo "    <span style=\"color: var(--text-muted); font-family: monospace;\">{$count} <span style=\"color: var(--text-secondary);\">({$pct}%)</span></span>";
    echo '  </div>';
    echo '  <div style="height: 6px; background: var(--bg-app); border-radius: 3px; overflow: hidden;">';
    echo "    <div style=\"height: 100%; width: {$pct}%; background: {$color}; border-radius: 3px;\"></div>";
    echo '  </div>';
    echo '</div>';
}
?>

<style>
.dash-greeting {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 4px;
}
.dash-subtitle {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 24px;
}
.dash-kpi-trend {
    font-size: 11px;
    font-weight: 600;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.dash-kpi-trend.up { color: #34D399; }
.dash-kpi-trend.down { color: #F87171; }
.dash-kpi-trend.neutral { color: var(--text-muted); }

.dash-chart-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 24px;
    margin-bottom: 24px;
}
.dash-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 8px;
}
.dash-chart-title { font-size: 14px; font-weight: 600; }
.dash-chart-peak { font-size: 12px; color: var(--text-muted); }

.dash-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 180px;
    padding: 0 4px;
}
.dash-bar-col {
    flex: 1;
    height: 100%;
    display: flex;
    align-items: flex-end;
}
.dash-bar {
    width: 100%;
    min-height: 2px;
    border-radius: 2px 2px 0 0;
    transition: background 0.15s;
}
.dash-bar.empty { background: var(--border-subtle); }
.dash-bar.filled { background: var(--accent); }
.dash-bar.filled:hover { background: var(--accent-hover); }

.dash-chart-axis {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 11px;
    color: var(--text-muted);
    font-family: var(--font-mono);
}

.dash-grid-2 {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.dash-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.dash-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dash-card-title {
    font-size: 14px;
    font-weight: 600;
}
.dash-card-link {
    font-size: 12px;
    color: var(--accent);
    text-decoration: none;
    font-weight: 500;
}
.dash-card-link:hover { color: var(--accent-hover); }
.dash-card-body { padding: 16px 20px; }

/* Top link rows */
.top-link-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-subtle);
}
.top-link-row:last-child { border-bottom: none; }
.top-link-rank {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--text-muted);
    min-width: 20px;
}
.top-link-body { flex: 1; min-width: 0; }
.top-link-code {
    font-family: var(--font-mono);
    color: var(--accent);
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    display: block;
}
.top-link-code:hover { text-decoration: underline; }
.top-link-title {
    font-size: 11px;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 2px;
}
.top-link-bar-wrap {
    height: 4px;
    background: var(--bg-app);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 6px;
}
.top-link-bar {
    height: 100%;
    background: var(--accent);
    border-radius: 2px;
}
.top-link-clicks {
    font-family: var(--font-mono);
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    flex-shrink: 0;
}

/* Activity feed */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-subtle);
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--accent);
    margin-top: 6px;
    flex-shrink: 0;
}
.activity-body { flex: 1; min-width: 0; }
.activity-line1 {
    font-size: 13px;
    color: var(--text-primary);
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: baseline;
}
.activity-code {
    font-family: var(--font-mono);
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
}
.activity-code:hover { text-decoration: underline; }
.activity-meta {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 3px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.activity-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    background: var(--bg-app);
    border: 1px solid var(--border-color);
    font-size: 10px;
    font-family: var(--font-mono);
    color: var(--text-secondary);
}
.activity-time {
    font-size: 11px;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-left: auto;
    font-family: var(--font-mono);
}

.dash-empty {
    padding: 40px 20px;
    text-align: center;
    color: var(--text-muted);
    font-size: 13px;
}

@media (max-width: 900px) {
    .dash-grid-2 { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .dash-bars { height: 130px; }
    .dash-chart-card { padding: 16px; }
    .dash-card-body { padding: 12px 16px; }
}
</style>

<!-- Greeting header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <div class="dash-greeting"><?= $greeting ?>, <?= htmlspecialchars(ucfirst($firstName)) ?></div>
        <div class="dash-subtitle">
            <?php if ($isSuperAdmin): ?>
                Instance-wide overview across all users.
            <?php else: ?>
                Here's how your links are performing.
            <?php endif; ?>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= $baseURL ?>/links" class="btn btn-primary">
            <i class="fa-solid fa-plus" style="margin-right: 6px; font-size: 11px;"></i> Create link
        </a>
    </div>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total clicks</div>
        <div class="value font-mono"><?= number_format($total_clicks) ?></div>
        <?php if ($weekTrend != 0): ?>
            <div class="dash-kpi-trend <?= $weekTrend > 0 ? 'up' : 'down' ?>">
                <i class="fa-solid fa-arrow-<?= $weekTrend > 0 ? 'up' : 'down' ?>" style="font-size: 9px;"></i>
                <?= abs($weekTrend) ?>% vs last week
            </div>
        <?php else: ?>
            <div class="dash-kpi-trend neutral"><?= number_format($week_clicks) ?> this week</div>
        <?php endif; ?>
    </div>

    <div class="kpi-card">
        <div class="label">Unique visitors</div>
        <div class="value font-mono"><?= number_format($unique_visitors) ?></div>
        <div class="dash-kpi-trend neutral">all time</div>
    </div>

    <div class="kpi-card">
        <div class="label">Active links</div>
        <div class="value font-mono"><?= number_format($active_links) ?></div>
        <div class="dash-kpi-trend neutral"><?= number_format($total_links) ?> total</div>
    </div>

    <div class="kpi-card">
        <div class="label">Today</div>
        <div class="value font-mono"><?= number_format($today_clicks) ?></div>
        <div class="dash-kpi-trend neutral"><?= date('D, M j') ?></div>
    </div>
</div>

<!-- 30-day chart -->
<div class="dash-chart-card">
    <div class="dash-chart-header">
        <div class="dash-chart-title">Clicks — last 30 days</div>
        <div class="dash-chart-peak">Peak: <span class="font-mono" style="color: var(--text-primary);"><?= number_format($maxTimeline) ?></span></div>
    </div>
    <?php if (array_sum(array_column($timeline, 'count')) === 0): ?>
        <div class="dash-empty">No clicks in the last 30 days. Share your links to start collecting data.</div>
    <?php else: ?>
        <div class="dash-bars">
            <?php foreach ($timeline as $t):
                $h = $maxTimeline > 0 ? max(2, round(($t['count'] / $maxTimeline) * 100)) : 2;
            ?>
                <div class="dash-bar-col" title="<?= htmlspecialchars($t['label']) ?>: <?= $t['count'] ?> clicks">
                    <div class="dash-bar <?= $t['count'] === 0 ? 'empty' : 'filled' ?>" style="height: <?= $h ?>%;"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="dash-chart-axis">
            <span><?= htmlspecialchars($timeline[0]['label']) ?></span>
            <span><?= htmlspecialchars($timeline[14]['label']) ?></span>
            <span><?= htmlspecialchars($timeline[29]['label']) ?></span>
        </div>
    <?php endif; ?>
</div>

<!-- Two-column grid: Top links + Recent activity -->
<div class="dash-grid-2">

    <!-- Top performing links -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">Top performing links</div>
            <a href="<?= $baseURL ?>/links" class="dash-card-link">View all →</a>
        </div>
        <div class="dash-card-body">
            <?php if (empty($top_links)): ?>
                <div class="dash-empty">No links yet. Create your first one to see performance here.</div>
            <?php else: ?>
                <?php foreach ($top_links as $i => $tl):
                    $barPct = $topMax > 0 ? max(2, round(((int)$tl['clicks'] / $topMax) * 100)) : 2;
                ?>
                    <div class="top-link-row">
                        <div class="top-link-rank"><?= $i + 1 ?></div>
                        <div class="top-link-body">
                            <a href="<?= $baseURL ?>/link?id=<?= (int)$tl['id'] ?>" class="top-link-code">/<?= htmlspecialchars($tl['short_code']) ?></a>
                            <div class="top-link-title"><?= htmlspecialchars($tl['title'] ?: 'Untitled') ?></div>
                            <div class="top-link-bar-wrap">
                                <div class="top-link-bar" style="width: <?= $barPct ?>%;"></div>
                            </div>
                        </div>
                        <div class="top-link-clicks"><?= number_format((int)$tl['clicks']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">Recent activity</div>
            <span style="font-size: 11px; color: var(--text-muted);">Last <?= count($recent_activity) ?></span>
        </div>
        <div class="dash-card-body">
            <?php if (empty($recent_activity)): ?>
                <div class="dash-empty">No clicks recorded yet.</div>
            <?php else: ?>
                <?php foreach ($recent_activity as $a):
                    $ago = time() - strtotime($a['clicked_at']);
                    if ($ago < 60)      $rel = 'just now';
                    elseif ($ago < 3600) $rel = floor($ago / 60) . 'm ago';
                    elseif ($ago < 86400) $rel = floor($ago / 3600) . 'h ago';
                    else                 $rel = floor($ago / 86400) . 'd ago';
                ?>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div class="activity-body">
                            <div class="activity-line1">
                                <a href="<?= $baseURL ?>/link?id=<?= (int)$a['link_id'] ?>" class="activity-code">/<?= htmlspecialchars($a['short_code']) ?></a>
                                <span style="color: var(--text-secondary); font-size: 12px;">clicked</span>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-badge"><?= htmlspecialchars($a['device_type']) ?></span>
                                <span class="activity-badge"><?= htmlspecialchars($a['browser']) ?></span>
                                <?php if (!empty($a['referrer']) && $a['referrer'] !== 'Direct'): ?>
                                    <span>from <?= htmlspecialchars(mb_substr($a['referrer'], 0, 24)) ?></span>
                                <?php else: ?>
                                    <span>direct</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-time"><?= $rel ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom row: Traffic sources + Devices -->
<div class="dash-grid-2" style="grid-template-columns: 1fr 1fr;">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">Top traffic sources</div>
            <a href="<?= $baseURL ?>/analytics" class="dash-card-link">Analytics →</a>
        </div>
        <div class="dash-card-body">
            <?php if (empty($top_referrers)): ?>
                <div class="dash-empty">No referrer data yet.</div>
            <?php else: ?>
                <?php foreach ($top_referrers as $r):
                    renderBreakdownRow($r['name'], (int)$r['count'], $total_clicks, '#6E7BF2');
                endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">Devices</div>
        </div>
        <div class="dash-card-body">
            <?php if (empty($devices)): ?>
                <div class="dash-empty">No device data yet.</div>
            <?php else: ?>
                <?php foreach ($devices as $d):
                    renderBreakdownRow($d['name'], (int)$d['count'], $total_clicks, '#34D399');
                endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// What's New modal partial (renders only when $whatsNew is set)
require BASE_PATH . '/resources/views/partials/whats-new-modal.php';

$slot = ob_get_clean();
$pageTitle = "Overview - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
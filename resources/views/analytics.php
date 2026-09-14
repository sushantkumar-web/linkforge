<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<div style="margin-bottom: 20px;">
    <a href="<?= $baseURL ?>/" style="font-size: 13px; color: var(--text-secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
        ← Back to Links
    </a>
</div>

<!-- Header Card -->
<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <div class="font-mono" style="font-size: 22px; font-weight: 700; color: var(--accent); margin-bottom: 6px;">
            /<?= htmlspecialchars((string)$link['short_code']) ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary); max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            <?= htmlspecialchars((string)$link['destination_url']) ?>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 24px;">
        <a href="<?= $baseURL ?>/analytics/export?id=<?= $link['id'] ?>" class="btn btn-secondary btn-sm">Export CSV</a>
        <div style="text-align: right;">
            <div class="font-mono" style="font-size: 30px; font-weight: 700; line-height: 1.1;"><?= number_format((int)$link['clicks']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Clicks</div>
        </div>
    </div>
</div>

<!-- 7-Day Performance Timeline -->
<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px;">
    <h3 style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
        Activity (Last 7 Days)
    </h3>
    <div style="display: flex; align-items: flex-end; gap: 16px; height: 140px; padding-top: 10px;">
        <?php foreach($timeline as $day): 
            $heightPercent = max(6, round(($day['count'] / $maxClicks) * 100));
        ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 8px;">
                <span class="font-mono" style="font-size: 11px; color: var(--text-secondary);"><?= $day['count'] ?></span>
                <div style="width: 100%; max-width: 44px; background: <?= $day['count'] > 0 ? 'var(--accent)' : 'var(--border-color)' ?>; height: <?= $heightPercent ?>%; border-radius: 4px 4px 0 0; transition: height 0.3s ease;"></div>
                <span style="font-size: 11px; color: var(--text-muted);"><?= explode(' ', $day['label'])[0] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Breakdown Grid -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
    <!-- Referrers -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px;">
        <h4 style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
            Traffic Sources
        </h4>
        <?php if(empty($referrers)): ?>
            <div style="color: var(--text-muted); font-size: 13px;">No referral data yet.</div>
        <?php else: ?>
            <?php foreach($referrers as $r): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;">
                    <span style="color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;"><?= htmlspecialchars($r['name'] ?: 'Direct') ?></span>
                    <span class="font-mono" style="color: var(--text-secondary);"><?= $r['count'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Devices -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px;">
        <h4 style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
            Devices
        </h4>
        <?php if(empty($devices)): ?>
            <div style="color: var(--text-muted); font-size: 13px;">No device data yet.</div>
        <?php else: ?>
            <?php foreach($devices as $d): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;">
                    <span style="color: var(--text-primary);"><?= htmlspecialchars($d['name']) ?></span>
                    <span class="font-mono" style="color: var(--text-secondary);"><?= $d['count'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Browsers -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px;">
        <h4 style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
            Browsers
        </h4>
        <?php if(empty($browsers)): ?>
            <div style="color: var(--text-muted); font-size: 13px;">No browser data yet.</div>
        <?php else: ?>
            <?php foreach($browsers as $b): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;">
                    <span style="color: var(--text-primary);"><?= htmlspecialchars($b['name']) ?></span>
                    <span class="font-mono" style="color: var(--text-secondary);"><?= $b['count'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$slot = ob_get_clean();
$pageTitle = "Analytics - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
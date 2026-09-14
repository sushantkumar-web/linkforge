<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; }
        .back-link { color: #9CA3AF; text-decoration: none; font-size: 14px; margin-bottom: 24px; display: inline-block; transition: color 0.2s; }
        .back-link:hover { color: #F5F5F5; }
        
        .header-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 24px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; }
        .link-title { font-size: 20px; font-weight: 600; margin-bottom: 8px; font-family: 'JetBrains Mono', monospace; }
        .link-dest { font-size: 14px; color: #9CA3AF; }
        .link-clicks { text-align: right; }
        .link-clicks h2 { font-size: 32px; font-weight: 600; }
        .link-clicks span { font-size: 12px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
        .data-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 24px; }
        .data-card h3 { font-size: 14px; color: #9CA3AF; margin-bottom: 16px; font-weight: 500; border-bottom: 1px solid #282C34; padding-bottom: 12px; }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
        .data-row .name { color: #F5F5F5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
        .data-row .val { color: #9CA3AF; }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= $baseURL ?>/" class="back-link">← Back to Dashboard</a>
        
        <div class="header-card">
    <div>
        <div class="link-title">/<?= htmlspecialchars((string)$link['short_code']) ?></div>
        <div class="link-dest"><?= htmlspecialchars((string)$link['destination_url']) ?></div>
    </div>
    <div style="display: flex; align-items: center; gap: 24px;">
        <a href="<?= $baseURL ?>/analytics/export?id=<?= $link['id'] ?>" class="btn-primary" style="text-decoration: none; padding: 8px 14px; font-size: 13px;">Export CSV</a>
        <div class="link-clicks">
            <h2><?= number_format((int)$link['clicks']) ?></h2>
            <span>Total Clicks</span>
        </div>
    </div>
</div>
        
<!-- 7-Day Performance Timeline -->
            <div style="background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <h3 style="font-size: 14px; color: #9CA3AF; margin-bottom: 20px; font-weight: 500;">Activity (Last 7 Days)</h3>
                <div style="display: flex; align-items: flex-end; gap: 16px; height: 140px; padding-top: 10px;">
                    <?php foreach($timeline as $day): 
                        $heightPercent = max(6, round(($day['count'] / $maxClicks) * 100));
                    ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 8px;">
                            <span style="font-size: 11px; color: #9CA3AF; font-family: 'JetBrains Mono', monospace;"><?= $day['count'] ?></span>
                            <div style="width: 100%; max-width: 42px; background: <?= $day['count'] > 0 ? '#5B5CE2' : '#282C34' ?>; height: <?= $heightPercent ?>%; border-radius: 4px 4px 0 0; transition: height 0.3s;"></div>
                            <span style="font-size: 11px; color: #6B7280;"><?= explode(' ', $day['label'])[0] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <div class="grid">
            <!-- Referrers -->
            <div class="data-card">
                <h3>Traffic Sources</h3>
                <?php if(empty($referrers)): ?>
                    <div class="data-row"><span class="name">No data</span></div>
                <?php else: ?>
                    <?php foreach($referrers as $r): ?>
                        <div class="data-row"><span class="name"><?= htmlspecialchars($r['name'] ?: 'Direct') ?></span> <span class="val"><?= $r['count'] ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Devices -->
            <div class="data-card">
                <h3>Devices</h3>
                <?php if(empty($devices)): ?>
                    <div class="data-row"><span class="name">No data</span></div>
                <?php else: ?>
                    <?php foreach($devices as $d): ?>
                        <div class="data-row"><span class="name"><?= htmlspecialchars($d['name']) ?></span> <span class="val"><?= $d['count'] ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Browsers -->
            <div class="data-card">
                <h3>Browsers</h3>
                <?php if(empty($browsers)): ?>
                    <div class="data-row"><span class="name">No data</span></div>
                <?php else: ?>
                    <?php foreach($browsers as $b): ?>
                        <div class="data-row"><span class="name"><?= htmlspecialchars($b['name']) ?></span> <span class="val"><?= $b['count'] ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
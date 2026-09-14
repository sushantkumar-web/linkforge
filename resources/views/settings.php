<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; }
        .back-link { color: #9CA3AF; text-decoration: none; font-size: 14px; margin-bottom: 24px; display: inline-block; transition: color 0.2s; }
        .back-link:hover { color: #F5F5F5; }
        
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 32px; }
        h2 { font-size: 14px; color: #9CA3AF; margin-bottom: 16px; font-weight: 500; border-bottom: 1px solid #282C34; padding-bottom: 8px; }
        
        .update-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .version-label { font-size: 14px; color: #9CA3AF; }
        .version-value { font-size: 16px; font-weight: 600; }
        
        .update-alert { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 16px; margin-top: 16px; }
        .update-alert-title { color: #10B981; font-weight: 600; font-size: 14px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .update-alert-desc { color: #9CA3AF; font-size: 13px; margin-bottom: 16px; }
        
        .btn-primary { background-color: #5B5CE2; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background-color: transparent; color: #9CA3AF; border: 1px solid #282C34; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= $baseURL ?>/" class="back-link">← Back to Dashboard</a>
        
        <h1>Settings</h1>
        
        <h2>System Updates</h2>
        
        <div class="update-card">
            <div class="flex-between">
                <div>
                    <div class="version-label">Current version</div>
                    <div class="version-value">v<?= htmlspecialchars($current_version) ?></div>
                </div>
                <?php if(!$update_available): ?>
                    <span style="background: rgba(255,255,255,0.05); padding: 4px 12px; border-radius: 12px; font-size: 12px; color: #9CA3AF;">Up to date</span>
                <?php endif; ?>
            </div>
            
            <?php if($update_available): ?>
            <div class="update-alert">
                <div class="update-alert-title">
                    <span style="display:inline-block; width:8px; height:8px; background:#10B981; border-radius:50%;"></span>
                    Update available: v<?= htmlspecialchars($latest_version) ?>
                </div>
                <div class="update-alert-desc">Security fixes + performance improvements</div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/settings/update" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn-primary">Apply Database Update</button>
                    </form>
                    <button class="btn-secondary">View changes</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; font-size: 12px; color: #6B7280; margin-top: 48px;">
            LinkForge Telemetry is currently disabled.<br>
            Self-hosted with LinkForge.
        </div>
    </div>
</body>
</html>
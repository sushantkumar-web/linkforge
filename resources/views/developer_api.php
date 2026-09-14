<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; }
        .back-link { color: #9CA3AF; text-decoration: none; font-size: 14px; margin-bottom: 24px; display: inline-block; transition: color 0.2s; }
        .back-link:hover { color: #F5F5F5; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .header-bar h1 { font-size: 20px; font-weight: 600; }
        .btn-primary { background-color: #5B5CE2; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        
        .key-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 24px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .key-info { display: flex; flex-direction: column; gap: 8px; }
        .key-title { font-size: 14px; font-weight: 600; }
        .key-value { font-family: 'JetBrains Mono', monospace; font-size: 14px; color: #9CA3AF; background: #0F1115; padding: 8px 12px; border-radius: 6px; border: 1px solid #282C34; cursor: pointer; }
        .key-meta { font-size: 12px; color: #6B7280; }
        
        .actions form { margin: 0; }
        .btn-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= $baseURL ?>/" class="back-link">← Back to Dashboard</a>
        
        <div class="header-bar">
            <h1>API Keys</h1>
            <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/api-keys/create" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn-primary">+ Create key</button>
            </form>
        </div>

        <?php if(empty($api_keys)): ?>
            <div style="background: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 48px; text-align: center; color: #9CA3AF; font-size: 14px;">
                No API keys yet. Create one to start using LinkForge programmatically.
            </div>
        <?php else: ?>
            <?php foreach($api_keys as $key): ?>
                <div class="key-card">
                    <div class="key-info">
                        <div class="key-title">Production API Key</div>
                        <div class="key-value" onclick="navigator.clipboard.writeText('<?= $key['api_key'] ?>'); alert('API Key copied!');">
                            <?= substr($key['api_key'], 0, 12) ?>••••••••••••••••••
                        </div>
                        <div class="key-meta">
                            Last used: <?= $key['last_used_at'] ? date('M j, Y H:i', strtotime($key['last_used_at'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="actions">
                        <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/api-keys/revoke" onsubmit="return confirm('Revoke this API key? Applications using it will immediately lose access.');">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button type="submit" class="btn-danger">Revoke</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
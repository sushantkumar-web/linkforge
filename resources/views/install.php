<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - LinkForge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 32px 16px;
        }
        .install-card {
            width: 100%;
            max-width: 460px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px var(--space-6);
        }
        .step-divider {
            height: 1px;
            background: var(--border-color);
            margin: 24px 0 20px;
            position: relative;
        }
        .step-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-surface);
            padding: 0 10px;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="install-card">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="font-size: 20px; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 4px;">
                <span style="color: var(--accent);"><i class="fa-solid fa-cubes"></i></span> LINKFORGE
            </div>
            <div style="font-size: 13px; color: var(--text-secondary);">Installation and Environment Setup</div>
        </div>

        <?php if(!empty($error)): ?>
            <div style="background: var(--status-expired-bg); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--status-expired-text); padding: 10px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 20px; text-align: center;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseURL ?>/install">
            <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 12px;">
                <i class="fa-solid fa-database" style="margin-right: 6px; color: var(--accent);"></i> Database Configuration
            </div>

            <div class="form-group">
                <label class="form-label">Database Host</label>
                <input type="text" name="db_host" value="localhost" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_name" required placeholder="linkforge_db" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Database User</label>
                <input type="text" name="db_user" required placeholder="root" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Database Password</label>
                <input type="password" name="db_pass" placeholder="••••••••" class="form-input">
            </div>

            <div class="step-divider">
                <span class="step-badge">Administrator</span>
            </div>

            <div class="form-group">
                <label class="form-label">Admin Email</label>
                <input type="email" name="admin_email" required placeholder="admin@domain.com" class="form-input">
            </div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Admin Password</label>
                <input type="password" name="admin_pass" required placeholder="Strong password" class="form-input">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px;">
                Complete Installation <i class="fa-solid fa-circle-check" style="margin-left: 6px;"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle); font-size: 12px; color: var(--text-muted);">
            Self-hosted with LinkForge
        </div>
    </div>
</body>
</html>
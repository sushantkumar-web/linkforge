<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Protected - LinkForge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-card {
            width: 100%;
            max-width: 380px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px var(--space-6);
            text-align: center;
        }
        .lock-icon-badge {
            width: 48px;
            height: 48px;
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            color: var(--accent);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="lock-icon-badge">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 6px;">Link Protected</h2>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px;">Enter the password to access this destination.</p>

        <?php if (!empty($error)): ?>
            <div style="background: var(--status-expired-bg); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--status-expired-text); padding: 10px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group" style="text-align: left;">
                <input type="password" name="link_password" placeholder="Enter link password" required autofocus class="form-input">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">
                Access Link <i class="fa-solid fa-arrow-right" style="margin-left: 6px; font-size: 12px;"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle); font-size: 12px; color: var(--text-muted);">
            Protected with LinkForge
        </div>
    </div>
</body>
</html>
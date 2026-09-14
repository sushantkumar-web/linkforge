<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - LinkForge</title>
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px var(--space-6);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="font-size: 20px; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 4px;">
                <span style="color: var(--accent);">⬡</span> LINKFORGE
            </div>
            <div style="font-size: 13px; color: var(--text-secondary);">Sign in to your account</div>
        </div>

        <?php if(!empty($error)): ?>
            <div style="background: var(--status-expired-bg); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--status-expired-text); padding: 10px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 16px; text-align: center;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseURL ?>/login">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label class="form-label">Email address</label>
                <input type="email" name="email" required autofocus class="form-input">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Password</label>
                <input type="password" name="password" required class="form-input">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Sign in</button>
        </form>

        <div style="text-align: center; margin-top: 28px; padding-top: 16px; border-top: 1px solid var(--border-subtle); font-size: 12px; color: var(--text-muted);">
            Self-hosted with LinkForge
        </div>
    </div>
</body>
</html>
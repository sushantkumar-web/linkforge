<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$captcha = \App\Core\Captcha::getActiveProvider();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - LinkForge</title>
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
    <?php if ($captcha === 'turnstile'): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php elseif ($captcha === 'recaptcha'): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php elseif ($captcha === 'hcaptcha'): ?>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <?php endif; ?>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px var(--space-6);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }
        .login-logo-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .login-error {
            background: var(--status-expired-bg);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--status-expired-text);
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
        }
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .login-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        .login-options a {
            color: var(--accent);
            text-decoration: none;
        }
        .login-options a:hover { text-decoration: underline; }
        .login-submit {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
        }
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
            font-size: 12px;
            color: var(--text-muted);
        }
        .captcha-wrap {
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-title">LINKFORGE</div>
            <div class="login-logo-subtitle">Sign in to your account</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseURL ?>/login" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label class="form-label">Email address</label>
                <input type="email" name="email" required autofocus autocomplete="email" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" required autocomplete="current-password" class="form-input">
            </div>

            <?php if ($captcha): ?>
                <div class="captcha-wrap">
                    <?php if ($captcha === 'turnstile'): ?>
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(\App\Core\Captcha::getSiteKey()) ?>" data-theme="dark"></div>
                    <?php elseif ($captcha === 'recaptcha'): ?>
                        <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(\App\Core\Captcha::getSiteKey()) ?>" data-theme="dark"></div>
                    <?php elseif ($captcha === 'hcaptcha'): ?>
                        <div class="h-captcha" data-sitekey="<?= htmlspecialchars(\App\Core\Captcha::getSiteKey()) ?>" data-theme="dark"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="login-options">
                <label>
                    <input type="checkbox" name="remember_me" value="1">
                    Stay signed in
                </label>
                <a href="<?= $baseURL ?>/forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary login-submit">Sign in</button>
        </form>

        <div class="login-footer">
            Self-hosted with LinkForge
        </div>
    </div>
</body>
</html>
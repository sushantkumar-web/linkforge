<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$err = flash('error');
$ok = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - LinkForge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
</head>
<body style="background:#0F1115;color:#F5F5F5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:sans-serif;">
    <div style="width:100%;max-width:400px;padding:32px;background:#16191F;border:1px solid #282C34;border-radius:12px;">
        <h1 style="font-size:20px;margin-bottom:8px;">Forgot your password?</h1>
        <p style="font-size:13px;color:#9CA3AF;margin-bottom:24px;">Enter your email and we'll send a reset link.</p>

        <?php if ($err): ?>
            <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:12px;margin-bottom:16px;color:#EF4444;font-size:13px;"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if ($ok): ?>
            <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:12px;margin-bottom:16px;color:#10B981;font-size:13px;"><?= htmlspecialchars($ok) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseURL ?>/forgot-password">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label style="display:block;font-size:12px;color:#9CA3AF;margin-bottom:6px;">Email Address</label>
            <input type="email" name="email" required autofocus
                style="width:100%;padding:10px 12px;background:#0F1115;border:1px solid #282C34;border-radius:8px;color:#F5F5F5;font-size:14px;box-sizing:border-box;">
            <button type="submit" style="width:100%;margin-top:20px;padding:12px;background:#5B5CE2;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Reset Link</button>
        </form>

        <p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:20px;">
            <a href="<?= $baseURL ?>/login" style="color:#8B8DF8;text-decoration:none;">← Back to sign in</a>
        </p>
    </div>
</body>
</html>
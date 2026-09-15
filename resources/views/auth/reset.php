<?php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$err = flash('error');
$token = $token ?? ($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LinkForge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
</head>
<body style="background:#0F1115;color:#F5F5F5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:sans-serif;">
    <div style="width:100%;max-width:400px;padding:32px;background:#16191F;border:1px solid #282C34;border-radius:12px;">
        <h1 style="font-size:20px;margin-bottom:8px;">Set a new password</h1>
        <p style="font-size:13px;color:#9CA3AF;margin-bottom:24px;">Choose a strong password. Minimum 8 characters.</p>

        <?php if ($err): ?>
            <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:12px;margin-bottom:16px;color:#EF4444;font-size:13px;">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseURL ?>/reset-password">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label style="display:block;font-size:12px;color:#9CA3AF;margin-bottom:6px;">New Password</label>
            <input type="password" name="password" required autofocus minlength="8" autocomplete="new-password"
                style="width:100%;padding:10px 12px;background:#0F1115;border:1px solid #282C34;border-radius:8px;color:#F5F5F5;font-size:14px;box-sizing:border-box;">

            <label style="display:block;font-size:12px;color:#9CA3AF;margin:16px 0 6px;">Confirm Password</label>
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                style="width:100%;padding:10px 12px;background:#0F1115;border:1px solid #282C34;border-radius:8px;color:#F5F5F5;font-size:14px;box-sizing:border-box;">

            <button type="submit" style="width:100%;margin-top:20px;padding:12px;background:#5B5CE2;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
                Update Password
            </button>
        </form>

        <p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:20px;">
            <a href="<?= $baseURL ?>/login" style="color:#8B8DF8;text-decoration:none;">← Back to sign in</a>
        </p>
    </div>
</body>
</html>
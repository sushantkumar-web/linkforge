<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { width: 100%; max-width: 380px; padding: 32px; }
        .brand { text-align: center; font-size: 24px; font-weight: 600; margin-bottom: 8px; }
        h1 { font-size: 16px; font-weight: 400; color: #9CA3AF; margin-bottom: 32px; text-align: center; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #9CA3AF; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 12px; background-color: #0F1115; border: 1px solid #282C34; border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus { border-color: #5B5CE2; }
        button { width: 100%; background-color: #5B5CE2; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; margin-top: 8px; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .error { background: rgba(239, 68, 68, 0.1); color: #EF4444; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid rgba(239, 68, 68, 0.2); text-align: center; }
        .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #6B7280; border-top: 1px solid #282C34; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">LinkForge</div>
        <h1>Sign in to your account</h1>
        
        <?php if(isset($error) && $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Sign in</button>
        </form>
        
        <div class="footer">
            Self-hosted with LinkForge
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background-color: #16191F; border: 1px solid #282C34; border-radius: 12px; width: 100%; max-width: 440px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,0.2); }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 24px; text-align: center; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #9CA3AF; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 12px; background-color: #0F1115; border: 1px solid #282C34; border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus { border-color: #5B5CE2; }
        button { width: 100%; background-color: #5B5CE2; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; margin-top: 16px; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .error { background: rgba(239, 68, 68, 0.1); color: #EF4444; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to LinkForge</h1>
        <?php if(isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group"><label>Database Host</label><input type="text" name="db_host" value="localhost" required></div>
            <div class="form-group"><label>Database Name</label><input type="text" name="db_name" required></div>
            <div class="form-group"><label>Database User</label><input type="text" name="db_user" required></div>
            <div class="form-group"><label>Database Password</label><input type="password" name="db_pass"></div>
            <div style="height: 1px; background: #282C34; margin: 24px 0;"></div>
            <div class="form-group"><label>Admin Email</label><input type="email" name="admin_email" required></div>
            <div class="form-group"><label>Admin Password</label><input type="password" name="admin_pass" required></div>
            <button type="submit">Install LinkForge</button>
        </form>
    </div>
</body>
</html>
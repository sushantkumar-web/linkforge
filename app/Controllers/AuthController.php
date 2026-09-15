<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class AuthController {
    public function login() {
        if (isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }

        // Automatic authentication via Remember Me cookie
        if (!isset($_SESSION['user_id']) && !empty($_COOKIE['linkforge_remember'])) {
            $this->authenticateFromCookie();
        }

        $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') 
            . '://' . $_SERVER['HTTP_HOST'] 
            . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        require BASE_PATH . '/resources/views/login.php';
    }

    public function authenticate() {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember_me']);

        if (!$email || !$password) {
            die("Please enter both email and password.");
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $storedHash = $user['password_hash'] ?? $user['password'] ?? null;

        if ($user && $storedHash && password_verify($password, $storedHash)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role']; // Standardized session key

            // Update last login timestamp
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

            if ($remember) {
                $this->issueRememberCookie($user['id']);
            }

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }

        http_response_code(401);
        die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Authentication Failed</h2><p>Invalid email or password.</p><p><a href='login'>Try again</a></p></div>");
    }

    private function issueRememberCookie($userId) {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $selector, $hashedValidator, $expiresAt]);

        $cookiePayload = $selector . ':' . $validator;
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie('linkforge_remember', $cookiePayload, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    private function authenticateFromCookie() {
        $cookie = $_COOKIE['linkforge_remember'] ?? '';
        if (!str_contains($cookie, ':')) return;

        [$selector, $validator] = explode(':', $cookie, 2);

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT t.*, u.email, u.role FROM auth_tokens t JOIN users u ON t.user_id = u.id WHERE t.selector = ? AND t.expires_at > NOW() AND u.status = 'active' LIMIT 1");
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $token['user_id'];
            $_SESSION['user_email'] = $token['email'];
            $_SESSION['role'] = $token['role']; // FIXED: was 'user_role'

            // Update last login timestamp
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$token['user_id']]);

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }
    }

    public function logout() {
        // Delete the remember-me token from DB
        if (!empty($_COOKIE['linkforge_remember'])) {
            $parts = explode(':', $_COOKIE['linkforge_remember']);
            if (count($parts) === 2) {
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
                $stmt->execute([$parts[0]]);
            }

            setcookie('linkforge_remember', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        // Destroy the session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/login");
        exit;
    }
    public function showForgotForm() {
    require BASE_PATH . '/resources/views/auth/forgot.php';
}

public function forgotPassword() {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    // Always show the same response to avoid user enumeration
    $genericMessage = 'If that email is registered, a password reset link has been sent.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', $genericMessage);
        $this->redirectTo('/forgot-password');
    }

    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Rate limit: max 3 requests per email per 15 minutes
        $rate = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $rate->execute([$user['id']]);
        if ((int)$rate->fetchColumn() >= 3) {
            flash('error', $genericMessage);
            $this->redirectTo('/forgot-password');
        }

        // Generate token
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $pdo->prepare("INSERT INTO password_resets (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([$user['id'], $selector, $hashedValidator, $expiresAt]);

        // Build reset URL
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        $resetUrl = $scheme . '://' . $host . $basePath . '/reset-password?token=' . urlencode($selector . ':' . $validator);

        // Send email
        $subject = 'Reset your LinkForge password';
        $body = "
            <p>Hi,</p>
            <p>Someone requested a password reset for your LinkForge account.</p>
            <p><a href=\"{$resetUrl}\" style=\"display:inline-block;background:#5B5CE2;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;\">Reset Password</a></p>
            <p style=\"font-size:12px;color:#666;\">Or copy this link: {$resetUrl}</p>
            <p style=\"font-size:12px;color:#666;\">This link expires in 1 hour. If you didn't request this, ignore this email.</p>
        ";
        \App\Core\Mailer::send($user['email'], $subject, $body);
    }

    flash('success', $genericMessage);
    $this->redirectTo('/forgot-password');
}

public function showResetForm() {
    $token = $_GET['token'] ?? '';
    if (!str_contains($token, ':')) {
        flash('error', 'Invalid reset link.');
        $this->redirectTo('/login');
    }
    [$selector, $validator] = explode(':', $token, 2);
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE selector = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1");
    $stmt->execute([$selector]);
    $reset = $stmt->fetch();

    if (!$reset || !hash_equals($reset['hashed_validator'], hash('sha256', $validator))) {
        flash('error', 'This reset link is invalid or has expired.');
        $this->redirectTo('/forgot-password');
    }

    $token = $selector . ':' . $validator;
    require BASE_PATH . '/resources/views/auth/reset.php';
}

public function resetPassword() {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirmation'] ?? '';

    if (!str_contains($token, ':')) {
        flash('error', 'Invalid reset link.');
        $this->redirectTo('/login');
    }
    if (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters.');
        $this->redirectTo('/reset-password?token=' . urlencode($token));
    }
    if ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
        $this->redirectTo('/reset-password?token=' . urlencode($token));
    }

    [$selector, $validator] = explode(':', $token, 2);
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE selector = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1");
    $stmt->execute([$selector]);
    $reset = $stmt->fetch();

    if (!$reset || !hash_equals($reset['hashed_validator'], hash('sha256', $validator))) {
        flash('error', 'This reset link is invalid or has expired.');
        $this->redirectTo('/forgot-password');
    }

    // Update password
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);

    // Mark token used
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);

    // Invalidate all other sessions / remember-me tokens for this user
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$reset['user_id']]);

    flash('success', 'Password updated. Please sign in.');
    $this->redirectTo('/login');
}

private function redirectTo(string $path): void {
    $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
    header('Location: ' . $baseURL . $path);
    exit;
}
}
<?php
namespace App\Controllers;

use App\Core\Database;

class AuthController {
    public function login() {
        if (isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }

        $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') 
            . '://' . $_SERVER['HTTP_HOST'] 
            . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        require BASE_PATH . '/resources/views/login.php';
    }

    public function authenticate() {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            die("Please enter both email and password.");
        }

        $pdo = Database::getInstance();
        // Fixed: Querying password_hash to match the users table schema
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID on authentication to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }

        http_response_code(401);
        die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Authentication Failed</h2><p>Invalid email or password.</p><p><a href='login'>Try again</a></p></div>");
    }

    public function logout() {
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
}
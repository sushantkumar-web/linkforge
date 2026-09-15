<?php
namespace App\Controllers;

use App\Core\Database;

class UsersController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        
        // 1. Must be logged in
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . str_replace('/index.php', '', $_SERVER['PHP_SELF']) . '/login');
            exit;
        }

        // 2. RBAC Check: Only super_admin and admin can access User Management
        $role = $_SESSION['role'] ?? 'user';
        if ($role !== 'super_admin' && $role !== 'admin') {
            http_response_code(403);
            die("403 Forbidden: You do not have permission to manage users.");
        }
    }

    public function index() {
        $stmt = $this->db->query("SELECT id, email, role, status, last_login_at, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        require BASE_PATH . '/resources/views/users.php';
    }

    public function store() {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        if (!filter_var($email, FILTER_VALIDATE_URL) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Invalid email format");
        }
        if (strlen($password) < 8) {
            die("Password must be at least 8 characters");
        }

        // Only super_admin can create other admins
        if ($role === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
            die("403 Forbidden: Only Super Admins can create other Super Admins.");
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $this->db->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$email, $hashedPassword, $role]);
        } catch (\PDOException $e) {
            die("Error creating user. Email may already exist.");
        }

        header('Location: ' . str_replace('/index.php', '', $_SERVER['PHP_SELF']) . '/users');
        exit;
    }

    public function update() {
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';

        // Only super_admin can change roles to super_admin
        if ($role === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
            die("403 Forbidden: Only Super Admins can promote to Super Admin.");
        }

        // Prevent users from demoting themselves
        if ($userId === (int)$_SESSION['user_id'] && $role !== $_SESSION['role']) {
            die("You cannot change your own role.");
        }

        $stmt = $this->db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$role, $status, $userId]);

        header('Location: ' . str_replace('/index.php', '', $_SERVER['PHP_SELF']) . '/users');
        exit;
    }

    public function delete() {
        // Only Super Admins can delete users
        if ($_SESSION['role'] !== 'super_admin') {
            http_response_code(403);
            die("403 Forbidden: Only Super Admins can delete users.");
        }

        $userId = (int)($_POST['user_id'] ?? 0);

        // Prevent deleting yourself
        if ($userId === (int)$_SESSION['user_id']) {
            die("You cannot delete your own account.");
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        header('Location: ' . str_replace('/index.php', '', $_SERVER['PHP_SELF']) . '/users');
        exit;
    }
}
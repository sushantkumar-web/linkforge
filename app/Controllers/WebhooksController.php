<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\WebhookDispatcher;

class WebhooksController {

    public function __construct() {
        if (empty($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
    }

    public function index() {
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT w.*,
                   (SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = w.id) AS delivery_count,
                   (SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = w.id AND status = 'failed') AS failed_count
            FROM webhooks w
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);
        $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Recent deliveries across all hooks
        $stmt = $pdo->prepare("
            SELECT d.*, w.name AS webhook_name
            FROM webhook_deliveries d
            JOIN webhooks w ON d.webhook_id = w.id
            WHERE w.user_id = ?
            ORDER BY d.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $recentDeliveries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Show one-time secret after creation
        $newSecret = $_SESSION['new_webhook_secret'] ?? null;
        unset($_SESSION['new_webhook_secret']);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/webhooks.php';
    }

    public function store() {
        $userId = $_SESSION['user_id'];
        $name   = trim($_POST['name'] ?? 'Untitled Webhook');
        $url    = trim($_POST['url'] ?? '');
        $events = $_POST['events'] ?? ['link.created'];
        $secret = $_POST['secret'] ?? bin2hex(random_bytes(32));

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            flash('error', 'Please enter a valid webhook URL.');
            $this->back();
        }
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            flash('error', 'URL must start with http:// or https://');
            $this->back();
        }

        $eventsCsv = implode(',', array_map('trim', (array)$events));
        if ($eventsCsv === '') $eventsCsv = 'link.created';

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            INSERT INTO webhooks (user_id, name, url, secret, events)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $name, $url, $secret, $eventsCsv]);

        $_SESSION['new_webhook_secret'] = ['name' => $name, 'secret' => $secret];

        flash('success', 'Webhook created. Copy the signing secret now — it won\'t be shown again.');
        $this->back();
    }

    public function update() {
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['webhook_id'] ?? 0);

        $pdo = Database::getInstance();
        $check = $pdo->prepare("SELECT id FROM webhooks WHERE id = ? AND user_id = ?");
        $check->execute([$id, $userId]);
        if (!$check->fetch()) {
            flash('error', 'Webhook not found.');
            $this->back();
        }

        $name   = trim($_POST['name'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $events = $_POST['events'] ?? [];
        $status = $_POST['status'] ?? 'active';

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            flash('error', 'Invalid URL.');
            $this->back();
        }
        if (!in_array($status, ['active', 'disabled'], true)) $status = 'active';

        $eventsCsv = implode(',', array_map('trim', (array)$events));
        if ($eventsCsv === '') $eventsCsv = 'link.created';

        $pdo->prepare("
            UPDATE webhooks SET name = ?, url = ?, events = ?, status = ?
            WHERE id = ? AND user_id = ?
        ")->execute([$name, $url, $eventsCsv, $status, $id, $userId]);

        flash('success', 'Webhook updated.');
        $this->back();
    }

    public function delete() {
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['webhook_id'] ?? 0);

        $pdo = Database::getInstance();
        $pdo->prepare("DELETE FROM webhooks WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        flash('success', 'Webhook deleted.');
        $this->back();
    }

    public function toggle() {
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['webhook_id'] ?? 0);

        $pdo = Database::getInstance();
        $pdo->prepare("
            UPDATE webhooks SET status = IF(status = 'active', 'disabled', 'active')
            WHERE id = ? AND user_id = ?
        ")->execute([$id, $userId]);
        $this->back();
    }

    public function test() {
        header('Content-Type: application/json');
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['webhook_id'] ?? 0);

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, url, secret, events FROM webhooks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $hook = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$hook) {
            echo json_encode(['ok' => false, 'error' => 'Webhook not found.']);
            exit;
        }

        $sampleData = [
            'id'              => 0,
            'short_code'      => 'test-' . bin2hex(random_bytes(2)),
            'destination_url' => 'https://example.com/test',
            'title'           => 'Test webhook delivery',
            'clicks'          => 0,
        ];

        WebhookDispatcher::deliver($hook, 'link.test', $sampleData, 1);

        // Fetch the delivery we just created
        $last = $pdo->query("SELECT * FROM webhook_deliveries WHERE webhook_id = " . (int)$hook['id'] . " ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

        echo json_encode([
            'ok'            => ($last['status'] ?? '') === 'success',
            'response_code' => $last['response_code'] ?? null,
            'response_body' => $last['response_body'] ?? null,
            'error'         => $last['error'] ?? null,
        ]);
        exit;
    }

    public function retry() {
        $count = WebhookDispatcher::processRetries();
        flash('success', "Retried {$count} pending deliveries.");
        $this->back();
    }

    private function back() {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/webhooks');
        exit;
    }
}
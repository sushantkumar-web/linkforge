<?php
namespace App\Controllers;

use App\Core\Database;

class DomainsController {

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
            SELECT d.*,
                   (SELECT COUNT(*) FROM links WHERE domain_id = d.id) AS link_count
            FROM domains d
            WHERE d.user_id = ?
            ORDER BY d.is_primary DESC, d.created_at DESC
        ");
        $stmt->execute([$userId]);
        $domains = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Show the token after adding a new domain, or when a verification fails
        $pendingToken = $_SESSION['pending_domain_token'] ?? null;
        unset($_SESSION['pending_domain_token']);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/domains.php';
    }

    public function store() {
        $userId = $_SESSION['user_id'];
        $hostname = strtolower(trim($_POST['hostname'] ?? ''));

        // Strip protocol and trailing slash if user pasted a URL
        $hostname = preg_replace('#^https?://#i', '', $hostname);
        $hostname = rtrim($hostname, '/');

        // Basic validation
        if (empty($hostname)) {
            flash('error', 'Please enter a hostname.');
            $this->back();
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $hostname)) {
            flash('error', 'Invalid hostname. Use a domain like go.example.com (no path, no protocol).');
            $this->back();
        }

        $pdo = Database::getInstance();

        // Check globally unique
        $check = $pdo->prepare("SELECT id FROM domains WHERE hostname = ? LIMIT 1");
        $check->execute([$hostname]);
        if ($check->fetch()) {
            flash('error', 'That hostname is already registered on this instance.');
            $this->back();
        }

        // Generate verification token
        $token = 'linkforge-verify-' . bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO domains (user_id, hostname, verification_token)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $hostname, $token]);

        $_SESSION['pending_domain_token'] = [
            'hostname' => $hostname,
            'token'    => $token,
        ];

        flash('success', "Domain added. Add the DNS TXT record shown below, then click Verify.");
        $this->back();
    }

    public function verify() {
        $userId = $_SESSION['user_id'];
        $domainId = (int)($_POST['domain_id'] ?? 0);

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$domain) {
            flash('error', 'Domain not found.');
            $this->back();
        }

        if (!empty($domain['verified_at'])) {
            flash('success', 'That domain is already verified.');
            $this->back();
        }

        $recordName = '_linkforge-verify.' . $domain['hostname'];

        // Check TXT records for the verification token
        $records = @dns_get_record($recordName, DNS_TXT);
        $found = false;
        if (is_array($records)) {
            foreach ($records as $rec) {
                $txt = $rec['txt'] ?? ($rec['entries'][0] ?? '');
                if (str_contains($txt, $domain['verification_token'])) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            flash('error', "Could not find the TXT record at {$recordName}. DNS changes can take up to 30 minutes to propagate. Try again in a bit.");
            $this->back();
        }

        // Mark verified
        $pdo->prepare("UPDATE domains SET verified_at = NOW() WHERE id = ?")->execute([$domainId]);

        // If this is the user's first domain, mark it primary automatically
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM domains WHERE user_id = ? AND verified_at IS NOT NULL");
        $countStmt->execute([$userId]);
        if ((int)$countStmt->fetchColumn() === 1) {
            $pdo->prepare("UPDATE domains SET is_primary = 1 WHERE id = ?")->execute([$domainId]);
        }

        flash('success', "Domain {$domain['hostname']} verified and ready to use.");
        $this->back();
    }

    public function setPrimary() {
        $userId = $_SESSION['user_id'];
        $domainId = (int)($_POST['domain_id'] ?? 0);

        $pdo = Database::getInstance();
        $check = $pdo->prepare("SELECT id FROM domains WHERE id = ? AND user_id = ? AND verified_at IS NOT NULL LIMIT 1");
        $check->execute([$domainId, $userId]);
        if (!$check->fetch()) {
            flash('error', 'Domain not found or not yet verified.');
            $this->back();
        }

        $pdo->prepare("UPDATE domains SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE domains SET is_primary = 1 WHERE id = ?")->execute([$domainId]);

        flash('success', 'Primary domain updated.');
        $this->back();
    }

    public function delete() {
        $userId = $_SESSION['user_id'];
        $domainId = (int)($_POST['domain_id'] ?? 0);

        $pdo = Database::getInstance();

        // Check that no links use this domain
        $check = $pdo->prepare("SELECT COUNT(*) FROM links WHERE domain_id = ?");
        $check->execute([$domainId]);
        $linkCount = (int)$check->fetchColumn();
        if ($linkCount > 0) {
            flash('error', "Cannot delete: {$linkCount} link(s) still use this domain. Move or delete them first.");
            $this->back();
        }

        $pdo->prepare("DELETE FROM domains WHERE id = ? AND user_id = ?")->execute([$domainId, $userId]);

        flash('success', 'Domain removed.');
        $this->back();
    }

    private function back() {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/settings?tab=domains');
        exit;
    }
}
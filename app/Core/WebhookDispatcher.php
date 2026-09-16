<?php
namespace App\Core;

use PDO;

class WebhookDispatcher {

    /**
     * Fire an event to all matching active webhooks for a user.
     * Non-blocking: returns immediately if curl is unavailable.
     */
    public static function dispatch(int $userId, string $event, array $data): void {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                SELECT id, url, secret, events
                FROM webhooks
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            $hooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($hooks as $hook) {
                $subscribed = array_map('trim', explode(',', $hook['events']));
                if (!in_array($event, $subscribed, true)) continue;
                self::deliver($hook, $event, $data, 1);
            }
        } catch (\Throwable $e) {
            error_log('[WebhookDispatcher] ' . $e->getMessage());
        }
    }

    /**
     * Deliver a single payload to a webhook. Logs the attempt.
     */
    public static function deliver(array $hook, string $event, array $data, int $attempt): void {
        $pdo = Database::getInstance();

        $payload = [
            'event'     => $event,
            'timestamp' => time(),
            'data'      => $data,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // HMAC-SHA256 signature (Stripe/GitHub style)
        $timestamp = $payload['timestamp'];
        $signature = hash_hmac('sha256', $timestamp . '.' . $json, $hook['secret']);
        $sigHeader = 't=' . $timestamp . ',v1=' . $signature;

        // Log the attempt as pending
        $ins = $pdo->prepare("
            INSERT INTO webhook_deliveries (webhook_id, event, payload, attempt, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $ins->execute([$hook['id'], $event, $json, $attempt]);
        $deliveryId = (int)$pdo->lastInsertId();

        // Fire the request
        $ch = curl_init($hook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'LinkForge-Webhook/1.1',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-LinkForge-Event: ' . $event,
                'X-LinkForge-Delivery: ' . $deliveryId,
                'X-LinkForge-Signature: ' . $sigHeader,
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $success = ($code >= 200 && $code < 300);
        $truncatedBody = $body !== false ? mb_substr((string)$body, 0, 500) : null;

        // Determine retry
        $nextRetry = null;
        $status = $success ? 'success' : 'failed';
        if (!$success && $attempt < 3) {
            $delays = [60, 300]; // 1 min, 5 min
            $nextRetry = date('Y-m-d H:i:s', time() + $delays[$attempt - 1]);
            $status = 'pending';
        }

        // Update the delivery row
        $upd = $pdo->prepare("
            UPDATE webhook_deliveries
            SET response_code = ?, response_body = ?, status = ?, error = ?, next_retry_at = ?
            WHERE id = ?
        ");
        $upd->execute([$code ?: null, $truncatedBody, $status, $err ?: null, $nextRetry, $deliveryId]);

        // Update the webhook itself
        if ($success) {
            $pdo->prepare("UPDATE webhooks SET last_fired_at = NOW(), failure_count = 0 WHERE id = ?")
                ->execute([$hook['id']]);
        } else {
            $pdo->prepare("UPDATE webhooks SET last_fired_at = NOW(), failure_count = failure_count + 1 WHERE id = ?")
                ->execute([$hook['id']]);
        }
    }

    /**
     * Retry all pending deliveries that are due.
     * Call this from a cron job or the manual "Retry Failed" button.
     */
    public static function processRetries(): int {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("
            SELECT d.*, w.url, w.secret, w.events
            FROM webhook_deliveries d
            JOIN webhooks w ON d.webhook_id = w.id
            WHERE d.status = 'pending'
              AND d.next_retry_at IS NOT NULL
              AND d.next_retry_at <= NOW()
              AND w.status = 'active'
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $row) {
            $payload = json_decode($row['payload'], true);
            if (!$payload) continue;
            self::deliver(
                ['id' => $row['webhook_id'], 'url' => $row['url'], 'secret' => $row['secret']],
                $row['event'],
                $payload['data'] ?? [],
                (int)$row['attempt'] + 1
            );
            $count++;
        }

        return $count;
    }
}
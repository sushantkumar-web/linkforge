<?php
namespace App\Core;

class Mailer {

    /**
     * Send an email. Returns true on success, or an error string on failure.
     */
    public static function send(string $to, string $subject, string $htmlBody): bool|string {
        $settings = self::loadSettings();
        $driver = $settings['mail_driver'] ?? 'log';

        return match ($driver) {
            'smtp' => self::sendSmtp($settings, $to, $subject, $htmlBody),
            'mail' => self::sendNative($settings, $to, $subject, $htmlBody),
            default => self::sendToLog($settings, $to, $subject, $htmlBody),
        };
    }

    // ---------------------------------------------------------
    // SMTP driver (PHPMailer)
    // ---------------------------------------------------------
    private static function sendSmtp(array $s, string $to, string $subject, string $body): bool|string {
        if (empty($s['smtp_host'])) return 'SMTP host not configured.';

        $lib = BASE_PATH . '/app/Libraries/PHPMailer/';
        if (!file_exists($lib . 'PHPMailer.php')) {
            return 'PHPMailer library not installed at ' . $lib;
        }
        require_once $lib . 'Exception.php';
        require_once $lib . 'PHPMailer.php';
        require_once $lib . 'SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $s['smtp_host'];
            $mail->Port       = (int)($s['smtp_port'] ?: 587);
            $mail->SMTPAuth   = !empty($s['smtp_user']);
            $mail->Username   = $s['smtp_user'] ?? '';
            $mail->Password   = $s['smtp_pass'] ?? '';

            $secure = $s['smtp_secure'] ?? 'tls';
            if ($secure === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = '';
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($s['from_email'] ?: $s['smtp_user'], $s['from_name'] ?: 'LinkForge');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            return 'SMTP error: ' . $mail->ErrorInfo;
        }
    }

    // ---------------------------------------------------------
    // Log driver — writes .html files to storage/logs/mail/
    // ---------------------------------------------------------
    private static function sendToLog(array $s, string $to, string $subject, string $body): bool|string {
        $dir = BASE_PATH . '/storage/logs/mail';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $safeTo = preg_replace('/[^a-zA-Z0-9._-]/', '_', $to);
        $file = $dir . '/' . date('Y-m-d_His') . '_' . $safeTo . '.html';

        $wrapper = "<!DOCTYPE html><html><head><meta charset='utf-8'><title>"
            . htmlspecialchars($subject) . "</title></head><body style='font-family:sans-serif;padding:24px;background:#f5f5f5;'>"
            . "<div style='max-width:600px;margin:auto;background:#fff;padding:24px;border-radius:8px;'>"
            . "<div style='border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:16px;'>"
            . "<strong>To:</strong> " . htmlspecialchars($to) . "<br>"
            . "<strong>Subject:</strong> " . htmlspecialchars($subject) . "</div>"
            . $body
            . "</div></body></html>";

        return file_put_contents($file, $wrapper) !== false
            ? true
            : 'Could not write log file to ' . $dir;
    }

    // ---------------------------------------------------------
    // Native mail() driver
    // ---------------------------------------------------------
    private static function sendNative(array $s, string $to, string $subject, string $body): bool|string {
        $from = $s['from_email'] ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName = $s['from_name'] ?: 'LinkForge';
        $headers = "MIME-Version: 1.0\r\n"
                 . "Content-type: text/html; charset=UTF-8\r\n"
                 . "From: {$fromName} <{$from}>\r\n";

        return @mail($to, $subject, $body, $headers)
            ? true
            : 'mail() returned false (no local MTA configured).';
    }

    // ---------------------------------------------------------
    private static function loadSettings(): array {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'mail_%' OR setting_key LIKE 'smtp_%' OR setting_key LIKE 'from_%'");
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
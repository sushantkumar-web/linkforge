<?php
namespace App\Core;

class Captcha {

    /**
     * Returns the active provider name ('turnstile', 'recaptcha', 'hcaptcha')
     * or null if captcha is not configured/enabled.
     */
    public static function getActiveProvider(): ?string {
        $settings = self::loadSettings();
        if (($settings['captcha_enabled'] ?? '0') !== '1') return null;
        $provider = $settings['captcha_provider'] ?? '';
        if (!in_array($provider, ['turnstile', 'recaptcha', 'hcaptcha'], true)) return null;
        if (empty($settings['captcha_site_key']) || empty($settings['captcha_secret_key'])) return null;
        return $provider;
    }

    public static function getSiteKey(): string {
        return (string)(self::loadSettings()['captcha_site_key'] ?? '');
    }

    /**
     * Verify the captcha response from a POST request.
     * Returns true if valid or if captcha is disabled.
     * Returns a string error message if invalid.
     */
    public static function verify(): bool|string {
        $provider = self::getActiveProvider();
        if ($provider === null) return true;

        $settings = self::loadSettings();
        $secret = $settings['captcha_secret_key'] ?? '';
        if (empty($secret)) return true;

        // The response field name varies by provider
        $token = match ($provider) {
            'turnstile' => $_POST['cf-turnstile-response'] ?? '',
            'recaptcha' => $_POST['g-recaptcha-response'] ?? '',
            'hcaptcha'  => $_POST['h-captcha-response']  ?? '',
            default     => '',
        };

        if (empty($token)) {
            return 'Please complete the captcha challenge.';
        }

        $endpoint = match ($provider) {
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
            'hcaptcha'  => 'https://hcaptcha.com/siteverify',
        };

        $postData = http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'LinkForge-Captcha/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            error_log("[Captcha] Verify failed: HTTP {$httpCode} {$curlErr}");
            // Fail open — don't lock users out if the captcha service is down
            return true;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            return true;
        }

        // All three providers return { "success": bool }
        if (!empty($result['success'])) {
            // reCAPTCHA v3 returns a score — require 0.5+
            if ($provider === 'recaptcha' && isset($result['score']) && $result['score'] < 0.5) {
                return 'Captcha verification failed. Please try again.';
            }
            return true;
        }

        // Surface error codes if available
        $codes = $result['error-codes'] ?? [];
        error_log('[Captcha] Verify rejected: ' . implode(', ', (array)$codes));
        return 'Captcha verification failed. Please try again.';
    }

    private static function loadSettings(): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'captcha_%'");
            $cache = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}
<?php
namespace App\Core;

class BotDetector {

    /**
     * Substrings (lowercase) that identify a request as coming from a bot,
     * crawler, link previewer, monitoring service, or headless client.
     */
    private const PATTERNS = [
        // Search engines
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'facebookexternalhit',
        'ia_archiver', 'archive.org_bot', 'ahrefsbot', 'semrushbot',
        'mj12bot', 'dotbot', 'petalbot', 'bytespider', 'gptbot',
        'claudebot', 'anthropic-ai', 'ccbot', 'perplexitybot', 'applebot',

        // Social / preview / unfurl
        'twitterbot', 'linkedinbot', 'slackbot', 'discordbot', 'telegrambot',
        'whatsapp', 'pinterest', 'vkshare', 'redditbot', 'skypeuripreview',
        'embedly', 'quora link preview', 'outbrain', 'w3c_validator',
        'developers.google.com/speed',

        // Uptime monitors
        'pingdom', 'uptimerobot', 'statuscake', 'better uptime', 'betterstack',
        'newrelicpinger', 'site24x7', 'hetrixtools', 'freshping',

        // Headless / automation
        'headlesschrome', 'phantomjs', 'prerender', 'screaming frog',
        'lighthouse', 'pagespeed', 'chrome-lighthouse', 'google page speed',

        // Dev tools
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'okhttp', 'guzzle', 'axios/', 'node-fetch', 'libwww-perl',
        'ruby', 'php/',

        // Security scanners (worth not counting)
        'nmap', 'nikto', 'sqlmap', 'masscan', 'nuclei', 'acunetix',
        'nessus', 'netsparker',

        // Generic signals
        'bot/', '/bot', 'crawler', '-crawler', 'spider/', '/spider',
        'linkchecker', 'link-preview', 'preview-bot', 'monitoring',
        'monitis', 'sitechecker', 'wordpress/', 'bitlybot', 'urlscan',
    ];

    /**
     * Returns true if this request looks like a bot.
     * Considers the user agent and (optionally) common bot headers.
     */
    public static function isBot(): bool {
        // Respect admin setting — if disabled, treat everything as human
        if (!self::filteringEnabled()) {
            return false;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Empty user agent — overwhelmingly from bots and scripted clients
        if (trim($ua) === '') {
            return true;
        }

        $uaLower = strtolower($ua);
        foreach (self::PATTERNS as $pattern) {
            if (str_contains($uaLower, $pattern)) {
                return true;
            }
        }

        // Explicit bot headers (rare but definitive)
        if (!empty($_SERVER['HTTP_X_PURPOSE']) && $_SERVER['HTTP_X_PURPOSE'] === 'preview') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_GOOG_SOURCE'])) {
            return true;
        }

        return false;
    }

    /**
     * Which pattern triggered the detection? Useful for debugging / logging.
     */
    public static function matchedPattern(): ?string {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (trim($ua) === '') return '(empty user agent)';
        foreach (self::PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) return $pattern;
        }
        return null;
    }

    private static function filteringEnabled(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bot_filtering_enabled' LIMIT 1");
            $val = $stmt->fetchColumn();
            // Default ON if the setting is missing (fresh installs)
            $cached = ($val === false || $val === null) ? true : ($val === '1');
        } catch (\Throwable $e) {
            $cached = true;
        }

        return $cached;
    }
}
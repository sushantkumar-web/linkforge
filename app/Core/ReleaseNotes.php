<?php
namespace App\Core;

class ReleaseNotes {

    /**
     * Returns the release notes for a specific version, or null if none exist.
     */
    public static function for(string $version): ?array {
        $all = self::all();
        return $all[$version] ?? null;
    }

    /**
     * Should we show the What's New modal to this user?
     * Returns the notes array if there's something to show, or null.
     */
    public static function shouldShow(): ?array {
        $current = defined('APP_VERSION') ? APP_VERSION : null;
        if (!$current) return null;

        $seen = self::seenVersion();

        // Never show for brand-new installs (no seen version set AND no data)
        if ($seen === null) {
            // First-ever load. Mark current as seen and don't show.
            self::markSeen($current);
            return null;
        }

        if ($seen === $current) return null;

        // Version changed — show notes if we have them
        return self::for($current);
    }

    public static function seenVersion(): ?string {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'release_notes_seen_version' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val === false) return null;
            return (string)$val;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function markSeen(string $version): void {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('release_notes_seen_version', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$version]);
        } catch (\Throwable $e) {
            // Silently ignore — worst case the modal shows again
        }
    }

    /**
     * All release notes, keyed by version.
     * Add new versions here when you ship.
     */
    private static function all(): array {
        return [
            '1.2.0' => [
                'title'   => 'UTM Builder & Smarter Analytics',
                'tagline' => 'Track campaigns properly. Count clicks honestly.',
                'sections' => [
                    [
                        'icon'  => 'fa-solid fa-bullseye',
                        'title' => 'UTM Preset Builder',
                        'body'  => 'Reusable campaign tracking templates. Attach a preset when creating a link and the source, medium, and campaign parameters get appended automatically. No more hand-typing UTM strings.',
                        'link'  => '/utm-presets',
                        'cta'   => 'Open UTM Presets',
                    ],
                    [
                        'icon'  => 'fa-solid fa-shield-halved',
                        'title' => 'Bot Filtering',
                        'body'  => 'Google, Bing, Facebook previews, uptime monitors, and headless clients no longer count as clicks. Your analytics now reflect real humans only.',
                        'link'  => '/settings?tab=security',
                        'cta'   => 'Review Settings',
                    ],
                    [
                        'icon'  => 'fa-solid fa-star',
                        'title' => 'What\'s New Modal',
                        'body'  => 'That thing you\'re looking at right now. From now on, every release surfaces what changed — right here on your dashboard.',
                    ],
                ],
            ],

            // Historical notes (shown only if a user updates from an older version)
            '1.1.0' => [
                'title'   => 'Custom Domains, Targeting, Captcha & Responsive Design',
                'tagline' => 'Brand your links. Route them intelligently. Protect them from bots.',
                'sections' => [
                    [
                        'icon'  => 'fa-solid fa-globe',
                        'title' => 'Custom Domains',
                        'body'  => 'Serve short links from your own domain like go.company.com. DNS verification, primary-domain selector, and transparent routing through the redirect engine.',
                    ],
                    [
                        'icon'  => 'fa-solid fa-shuffle',
                        'title' => 'Geo & Device Targeting',
                        'body'  => 'Route visitors to different destinations based on device (mobile, desktop, tablet) or country. First matching rule wins.',
                    ],
                    [
                        'icon'  => 'fa-solid fa-mobile-screen',
                        'title' => 'Responsive Redesign',
                        'body'  => 'Full mobile drawer, adaptive tables, resizable modals. LinkForge now works well on phones and tablets.',
                    ],
                    [
                        'icon'  => 'fa-solid fa-shield-virus',
                        'title' => 'Captcha Protection',
                        'body'  => 'Opt-in support for Cloudflare Turnstile, Google reCAPTCHA v3, and hCaptcha. Fails open if the provider is unreachable.',
                    ],
                    [
                        'icon'  => 'fa-solid fa-bolt',
                        'title' => 'Webhooks',
                        'body'  => 'Real-time event delivery with HMAC-SHA256 signatures and a full delivery log.',
                    ],
                ],
            ],
        ];
    }
}
<?php
namespace App\Core;

class ReleaseNotes {

    /**
     * Returns the release notes for a specific version, or null if none exist.
     */
    public static function for(string $version): ?array {
    $all = self::all();

    // Exact match — perfect
    if (isset($all[$version])) {
        return $all[$version];
    }

    // Hotfix fallback: if this version has no notes (e.g. 1.2.1),
    // use the notes from the closest earlier version that does (e.g. 1.2.0).
    $candidates = array_filter(array_keys($all), function ($v) use ($version) {
        return version_compare($v, $version, '<=');
    });
    if (empty($candidates)) {
        return null;
    }

    usort($candidates, 'version_compare');
    $best = end($candidates);

    return $all[$best];
}

    /**
     * Should we show the What's New modal to this user?
     * Returns the notes array if there's something to show, or null.
     */
    public static function shouldShow(): ?array {
    $current = defined('APP_VERSION') ? APP_VERSION : null;
    if (!$current) return null;

    $seen = self::seenVersion();

    // Never show on first-ever load
    if ($seen === null) {
        self::markSeen($current);
        return null;
    }

    // Find which version's notes would actually be shown (may be an earlier version)
    $notes = self::for($current);
    if (!$notes) return null;

    // If we've already shown notes from that same version, don't show again
    $notesVersion = self::notesVersionFor($current);
    if ($seen === $notesVersion) return null;

    return $notes;
}

/**
 * Returns the version key whose notes would be shown for the given version.
 * E.g. '1.2.2' → '1.2.0' (fallback).
 */
private static function notesVersionFor(string $version): ?string {
    $all = self::all();
    if (isset($all[$version])) return $version;

    $candidates = array_filter(
        array_keys($all),
        fn($v) => version_compare($v, $version, '<=')
    );
    if (empty($candidates)) return null;

    usort($candidates, 'version_compare');
    return end($candidates);
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
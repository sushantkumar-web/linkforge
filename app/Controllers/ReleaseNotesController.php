<?php
namespace App\Controllers;

use App\Core\ReleaseNotes;

class ReleaseNotesController {

    public function __construct() {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            exit;
        }
    }

    /**
     * POST /release-notes/dismiss
     * Marks the current version as seen by this install.
     */
    public function dismiss() {
        $version = defined('APP_VERSION') ? APP_VERSION : null;
        if ($version) {
            ReleaseNotes::markSeen($version);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/');
        exit;
    }
}
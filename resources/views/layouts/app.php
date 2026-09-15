<?php
// resources/views/layouts/app.php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$currentURI = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.2';
$cssFile = file_exists(BASE_PATH . '/public/assets/css/app.min.css') ? 'app.min.css' : 'app.css';
$jsFile  = file_exists(BASE_PATH . '/public/assets/js/app.min.js') ? 'app.min.js' : 'app.js';
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'LinkForge') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/<?= $cssFile ?>?v=<?= $appVersion ?>">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <span style="color: var(--accent); margin-right: 8px;"></span> LINKFORGE
        </div>
        <nav class="sidebar-nav">
            <a href="<?= $baseURL ?>/" class="nav-link <?= $currentURI === $baseURL . '/' ? 'active' : '' ?>">
                <i class="fa-solid fa-border-all" style="width: 18px;"></i> <span class="nav-text">Overview</span>
            </a>
            <a href="<?= $baseURL ?>/links" class="nav-link <?= strpos($currentURI, '/links') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-link" style="width: 18px;"></i> <span class="nav-text">Links</span>
            </a>
            <a href="<?= $baseURL ?>/analytics" class="nav-link <?= strpos($currentURI, '/analytics') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line" style="width: 18px;"></i> <span class="nav-text">Analytics</span>
            </a>
            <a href="<?= $baseURL ?>/qr" class="nav-link <?= strpos($currentURI, '/qr') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-qrcode" style="width: 18px;"></i> <span class="nav-text">QR Codes</span>
            </a>

            <div class="nav-section-title">Developer</div>
            <a href="<?= $baseURL ?>/api-keys" class="nav-link <?= strpos($currentURI, '/api-keys') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-key" style="width: 18px;"></i> <span class="nav-text">API Keys</span>
            </a>

            <div class="nav-section-title">Workspace</div>
<a href="<?= $baseURL ?>/tags" class="nav-link <?= strpos($currentURI, '/tags') !== false ? 'active' : '' ?>">
    <i class="fa-solid fa-tags" style="width: 18px;"></i> <span class="nav-text">Tags</span>
</a>

            <div class="nav-section-title">System</div>
            <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                <a href="<?= $baseURL ?>/users" class="nav-link <?= strpos($currentURI, '/users') !== false ? 'active' : '' ?>">
                    <i class="fa-solid fa-users" style="width: 18px;"></i> <span class="nav-text">Users</span>
                </a>
            <?php endif; ?>
            <a href="<?= $baseURL ?>/settings" class="nav-link <?= strpos($currentURI, '/settings') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-gear" style="width: 18px;"></i> <span class="nav-text">Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <span>v<?= $appVersion ?></span>
            <span style="color: var(--status-active-text); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-circle" style="font-size: 6px;"></i> Live
            </span>
        </div>
    </aside>

    <div class="main-wrapper" style="overflow: visible;">
        <header class="topbar" style="overflow: visible;">
            <div class="search-box" style="position: relative; z-index: 1000;">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 12px; color: var(--text-muted);"></i>
                <input type="text" id="globalSearch" placeholder="Search links..." autocomplete="off">
                <span class="search-shortcut">/</span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="font-size: 13px; font-weight: 500;">
                    <i class="fa-regular fa-user" style="margin-right: 6px; color: var(--text-muted);"></i>
                    <?= htmlspecialchars($_SESSION['user_email'] ?? 'Guest') ?>
                </span>
                <a href="<?= $baseURL ?>/logout" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
                    <i class="fa-solid fa-arrow-right-from-bracket" style="font-size: 11px;"></i> Sign out
                </a>
            </div>
        </header>

        <main class="content-container">
            <?= $slot ?>
        </main>
    </div>
</div>

<div id="toast"></div>

<!-- Search dropdown — lives at body level so no parent overflow can clip it -->
<div id="searchDropdown" style="
    display: none;
    position: fixed;
    background: #16191F;
    border: 1px solid #282C34;
    border-radius: 10px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
    z-index: 99999;
    max-height: 400px;
    overflow-y: auto;
"></div>

<!-- Global config for JS -->
<script>
    window.LINKFORGE_BASE_URL = '<?= $baseURL ?>';
    window.LINKFORGE_VERSION  = '<?= $appVersion ?>';
</script>

<!-- App JS -->
<script src="<?= $baseURL ?>/assets/js/<?= $jsFile ?>?v=<?= $appVersion ?>"></script>

<!-- Search (external file so the HTML minifier can't mangle it) -->
<script src="<?= $baseURL ?>/assets/js/search.js?v=<?= $appVersion ?>"></script>
</body>
</html>
<?php
// resources/views/layouts/app.php
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$currentURI = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'LinkForge') ?></title>
    <!-- Font Awesome via Cloudflare cdnjs -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseURL ?>/assets/css/app.css">
    
</head>
<body>
<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <span style="color: var(--accent); margin-right: 8px;"></span> LINKFORGE
        </div>
        <nav class="sidebar-nav">
            <a href="<?= $baseURL ?>/" class="nav-link <?= $currentURI === $baseURL . '/' ? 'active' : '' ?>">
                <i class="fa-solid fa-border-all" style="width: 18px;"></i> <span class="nav-text">Overview</span>
            </a>
            <a href="<?= $baseURL ?>/" class="nav-link <?= strpos($currentURI, '/links') !== false ? 'active' : '' ?>">
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
            <a href="<?= $baseURL ?>/?tag=all" class="nav-link">
                <i class="fa-solid fa-tags" style="width: 18px;"></i> <span class="nav-text">Tags</span>
            </a>

            <div class="nav-section-title">System</div>
            <a href="<?= $baseURL ?>/settings" class="nav-link <?= strpos($currentURI, '/settings') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-gear" style="width: 18px;"></i> <span class="nav-text">Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <span>v1.0.2</span>
            <span style="color: var(--status-active-text); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-circle" style="font-size: 6px;"></i> Live
            </span>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
        <header class="topbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 12px; color: var(--text-muted);"></i>
                <input type="text" id="globalSearch" placeholder="Search links...">
                <span class="search-shortcut">/</span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="font-size: 13px; font-weight: 500;">
                    <i class="fa-regular fa-user" style="margin-right: 6px; color: var(--text-muted);"></i>
                    <?= htmlspecialchars($_SESSION['user_email'] ?? 'Sushant') ?>
                </span>
                <a href="<?= $baseURL ?>/login" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
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
<script src="<?= $baseURL ?>/assets/js/app.js"></script>
</body>
</html>
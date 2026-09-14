<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; display: flex; min-height: 100vh; }
        
        /* Sidebar - Locked to ~240px */
        .sidebar { width: 240px; background-color: #0F1115; border-right: 1px solid #282C34; display: flex; flex-direction: column; padding: 24px 16px; }
        .brand { font-size: 16px; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 32px; padding: 0 12px; }
        .nav-group { margin-bottom: 24px; }
        .nav-label { font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 8px; padding: 0 12px; }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; color: #9CA3AF; text-decoration: none; font-size: 14px; border-radius: 6px; margin-bottom: 2px; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background-color: rgba(255,255,255,0.05); color: #F5F5F5; }
        .nav-item.active { background-color: rgba(91, 92, 226, 0.1); color: #5B5CE2; }
        .version { margin-top: auto; padding: 0 12px; font-size: 12px; color: #6B7280; }

        /* Main Content Area */
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { height: 64px; border-bottom: 1px solid #282C34; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; }
        .header-search input { background: transparent; border: none; color: #6B7280; font-size: 14px; outline: none; width: 250px; }
        .header-search input:focus { color: #F5F5F5; }
        .user-profile { font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;}
        .user-avatar { width: 24px; height: 24px; background: #5B5CE2; border-radius: 50%; display: inline-block; }
        
        .content { padding: 32px; max-width: 1200px; margin: 0 auto; width: 100%; }
        .page-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .page-title h1 { font-size: 20px; font-weight: 600; }
        
        .btn-primary { background-color: #5B5CE2; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; transition: opacity 0.2s; }
        .btn-primary:hover { opacity: 0.9; }

        /* KPI Cards - 12px radius, subtle borders */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
        .kpi-card { background-color: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .kpi-title { font-size: 13px; color: #9CA3AF; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .kpi-trend { font-size: 12px; color: #6B7280; } 
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">LINKFORGE</div>
        <div class="nav-group">
            <a href="#" class="nav-item active">Overview</a>
            <a href="#" class="nav-item">Links</a>
            <a href="#" class="nav-item">Analytics</a>
            <a href="#" class="nav-item">QR Codes</a>
        </div>
        <div class="nav-group">
            <div class="nav-label">Developer</div>
            <a href="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/api-keys" class="nav-item">API</a>
            <a href="#" class="nav-item">Webhooks</a>
        </div>
        <div class="nav-group">
            <div class="nav-label">Workspace</div>
            <a href="#" class="nav-item">Folders</a>
            <a href="#" class="nav-item">Tags</a>
        </div>
        <a href="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/settings" class="nav-item" style="margin-top: auto; margin-bottom: 16px;">Settings</a>
        <div class="version">v1.0.0</div>
    </div>
    
    <div class="main">
        <div class="header">
            <div class="header-search">
                <input type="text" id="searchInput" placeholder="🔍 Search links (Press /)">
            </div>
            <div class="user-profile">
                <span class="user-avatar"></span> Sushant
            </div>
        </div>
        <div class="content">
            <div class="page-title">
                <h1>Overview</h1>
                <button class="btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create link</button>
            </div>
            
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Total clicks</div>
                    <div class="kpi-value"><?= number_format($total_clicks) ?></div>
                    <div class="kpi-trend">All time</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Unique visitors</div>
                    <div class="kpi-value"><?= number_format($unique_visitors) ?></div>
                    <div class="kpi-trend">Tracked securely</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Active links</div>
                    <div class="kpi-value"><?= number_format($active_links) ?></div>
                    <div class="kpi-trend">Currently routing</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Total links</div>
                    <div class="kpi-value"><?= number_format($total_links) ?></div>
                    <div class="kpi-trend">Created</div>
                </div>
            </div>
            
            <!-- The Links Table -->
            <div style="background: #16191F; border: 1px solid #282C34; border-radius: 12px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #282C34; font-size: 11px; text-transform: uppercase; color: #6B7280; letter-spacing: 0.5px;">
                            <th style="padding: 16px 24px; font-weight: 600;">Link</th>
                            <th style="padding: 16px 24px; font-weight: 600;">Destination</th>
                            <th style="padding: 16px 24px; font-weight: 600;">Clicks</th>
                            <th style="padding: 16px 24px; font-weight: 600;">Status</th>
                            <th style="padding: 16px 24px; font-weight: 600;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recent_links)): ?>
                        <tr>
                            <td colspan="5" style="padding: 32px; text-align: center; color: #6B7280; font-size: 14px;">No links yet. Create your first short link above.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($recent_links as $link): ?>
                            <tr style="border-bottom: 1px solid #282C34; transition: background 0.2s;">
                                <td style="padding: 16px 24px; font-size: 14px; font-weight: 500; color: #F5F5F5;">
                                    <a href="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/analytics?id=<?= $link['id'] ?>" style="color: #5B5CE2; text-decoration: none;">
    /<?= htmlspecialchars($link['short_code']) ?>
</a>
                                </td>
                                <td style="padding: 16px 24px; font-size: 13px; color: #9CA3AF; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($link['destination_url']) ?>
                                </td>
                                <td style="padding: 16px 24px; font-size: 14px; color: #F5F5F5;">
                                    <?= number_format($link['clicks']) ?>
                                </td>
                                <td style="padding: 16px 24px;">
                                    <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; 
                                        <?= $link['status'] === 'active' ? 'background: rgba(16, 185, 129, 0.1); color: #10B981;' : 'background: rgba(239, 68, 68, 0.1); color: #EF4444;' ?>">
                                        <?= htmlspecialchars($link['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 16px 24px; text-align: right; display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                    <button onclick="copyLink('<?= $baseURL . '/' . $link['short_code'] ?>')" style="background: transparent; border: 1px solid #282C34; color: #9CA3AF; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">Copy</button>
                                    <button onclick="showQR('<?= $baseURL . '/' . $link['short_code'] ?>', '/<?= htmlspecialchars($link['short_code']) ?>')" style="background: transparent; border: 1px solid #282C34; color: #9CA3AF; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">QR</button>
                                    
                                    <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/links/toggle" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <button type="submit" style="background: transparent; border: 1px solid #282C34; color: <?= $link['status'] === 'active' ? '#EAB308' : '#10B981' ?>; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                                            <?= $link['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/links/delete" style="margin: 0;" onsubmit="return confirm('Delete this link? This will also delete all analytics for it.');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <button type="submit" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Create Link Modal -->
<div id="createModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 50; align-items: center; justify-content: center;">
    <div style="background: #16191F; padding: 32px; border-radius: 12px; width: 100%; max-width: 400px; border: 1px solid #282C34;">
        <h2 style="font-size: 18px; margin-bottom: 24px; font-weight: 600;">Create a new link</h2>
        <!-- Note the dynamic form action so it works on localhost -->
        <form method="POST" action="<?= str_replace('/index.php', '', $_SERVER['PHP_SELF']) ?>/links/create">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #9CA3AF; margin-bottom: 6px;">Destination URL</label>
                <input type="url" name="url" placeholder="https://example.com/very/long/url" required style="width: 100%; padding: 10px; background: #0F1115; border: 1px solid #282C34; border-radius: 8px; color: #fff;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; color: #9CA3AF; margin-bottom: 6px;">Short code (optional)</label>
                <input type="text" name="slug" placeholder="e.g. summer-sale" style="width: 100%; padding: 10px; background: #0F1115; border: 1px solid #282C34; border-radius: 8px; color: #fff;">
            </div>

            <!-- Advanced Options Toggle -->
            <div style="margin-bottom: 24px; font-size: 13px;">
                <a href="#" onclick="document.getElementById('advancedOptions').style.display='block'; this.style.display='none'; return false;" style="color: #5B5CE2; text-decoration: none;">+ Advanced options (UTM Builder)</a>
            </div>

            <!-- Advanced Options Container -->
            <div id="advancedOptions" style="display: none; background: rgba(255,255,255,0.02); border: 1px solid #282C34; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <label style="display: block; font-size: 13px; color: #9CA3AF; margin-bottom: 12px; font-weight: 500;">UTM Parameters</label>
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <div style="flex: 1;">
                        <input type="text" name="utm_source" placeholder="Source (e.g. instagram)" style="width: 100%; padding: 8px; background: #0F1115; border: 1px solid #282C34; border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <div style="flex: 1;">
                        <input type="text" name="utm_medium" placeholder="Medium (e.g. social)" style="width: 100%; padding: 8px; background: #0F1115; border: 1px solid #282C34; border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                </div>
                <div>
                    <input type="text" name="utm_campaign" placeholder="Campaign (e.g. summer_2026)" style="width: 100%; padding: 8px; background: #0F1115; border: 1px solid #282C34; border-radius: 6px; color: #fff; font-size: 13px;">
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="flex: 1; padding: 10px; background: transparent; border: 1px solid #282C34; color: #fff; border-radius: 8px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn-primary" style="flex: 1;">Create link</button>
            </div>
        </form>
    </div>
</div>
<!-- QR Code Modal -->
<div id="qrModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 50; align-items: center; justify-content: center;">
    <div style="background: #16191F; padding: 32px; border-radius: 12px; width: 100%; max-width: 320px; border: 1px solid #282C34; text-align: center;">
        <h2 id="qrTitle" style="font-size: 16px; margin-bottom: 24px; font-weight: 600; font-family: 'JetBrains Mono', monospace;">/slug</h2>
        
        <div style="background: white; padding: 16px; border-radius: 8px; display: inline-block; margin-bottom: 24px;">
            <img id="qrImage" src="" alt="QR Code" style="width: 200px; height: 200px; display: block;">
        </div>
        
        <div style="display: flex; gap: 12px; flex-direction: column;">
            <a id="qrDownloadPNG" href="#" download="linkforge-qr.png" class="btn-primary" style="text-decoration: none; display: block;">Download PNG</a>
            <button type="button" onclick="document.getElementById('qrModal').style.display='none'" style="padding: 10px; background: transparent; border: 1px solid #282C34; color: #fff; border-radius: 8px; cursor: pointer;">Close</button>
        </div>
    </div>
</div>
<script>
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard: ' + url); // We'll upgrade this to a sleek toast notification later
        });
    }
</script>
<script>
    // 1. Instant Search
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#linksTable tbody tr');
        
        rows.forEach(row => {
            if(row.cells.length > 1) { // Skip the 'No links yet' row
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            }
        });
    });

    // 2. Keyboard Shortcut (Press '/' to search)
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
            e.preventDefault();
            searchInput.focus();
        }
    });

    // 3. QR Code Generator
    function showQR(fullUrl, slug) {
        document.getElementById('qrTitle').innerText = slug;
        
        // Using a fast, public QR API to keep LinkForge zero-dependency
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=${encodeURIComponent(fullUrl)}&margin=10`;
        
        document.getElementById('qrImage').src = qrUrl;
        
        // Fetch the image as a blob so the download attribute works cross-origin
        fetch(qrUrl)
            .then(response => response.blob())
            .then(blob => {
                const blobUrl = window.URL.createObjectURL(blob);
                document.getElementById('qrDownloadPNG').href = blobUrl;
            });
            
        document.getElementById('qrModal').style.display = 'flex';
    }
</script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LinkForge</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #0F1115; color: #F5F5F5; display: flex; min-height: 100vh; }
        .sidebar { width: 240px; background-color: #0F1115; border-right: 1px solid #282C34; display: flex; flex-direction: column; padding: 24px 16px; }
        .brand { font-size: 16px; font-weight: 600; margin-bottom: 32px; padding: 0 12px; }
        .nav-group { margin-bottom: 24px; }
        .nav-label { font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 600; margin-bottom: 8px; padding: 0 12px; }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; color: #9CA3AF; text-decoration: none; font-size: 14px; border-radius: 6px; margin-bottom: 2px; }
        .nav-item:hover, .nav-item.active { background-color: rgba(91, 92, 226, 0.1); color: #5B5CE2; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { height: 64px; border-bottom: 1px solid #282C34; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; }
        .content { padding: 32px; max-width: 1200px; margin: 0 auto; width: 100%; }
        .page-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-primary { background-color: #5B5CE2; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
        .kpi-card { background-color: #16191F; border: 1px solid #282C34; border-radius: 12px; padding: 20px; }
        .kpi-title { font-size: 13px; color: #9CA3AF; margin-bottom: 8px; }
        .kpi-value { font-size: 24px; font-weight: 600; }
        
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
        .filter-tab { color: #9CA3AF; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; }
        .filter-tab.active { background: #16191F; color: #F5F5F5; border: 1px solid #282C34; }
        
        table { width: 100%; border-collapse: collapse; background: #16191F; border: 1px solid #282C34; border-radius: 12px; overflow: hidden; }
        th { font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 14px 20px; border-bottom: 1px solid #282C34; text-align: left; }
        td { padding: 14px 20px; border-bottom: 1px solid #282C34; font-size: 13px; }
        .btn-action { background: transparent; border: 1px solid #282C34; color: #9CA3AF; padding: 5px 10px; border-radius: 6px; font-size: 12px; cursor: pointer; }
        .btn-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">LINKFORGE</div>
        <div class="nav-group">
            <a href="<?= $baseURL ?>/" class="nav-item active">Overview</a>
            <a href="<?= $baseURL ?>/api-keys" class="nav-item">API Keys</a>
            <a href="<?= $baseURL ?>/settings" class="nav-item">Settings</a>
        </div>
    </div>
    
    <div class="main">
        <div class="header">
            <input type="text" id="searchInput" placeholder="🔍 Search links (Press /)" style="background:transparent; border:none; color:#fff; outline:none; width:300px;">
            <div>Sushant</div>
        </div>

        <div class="content">
            <div class="page-title">
                <h1>Links</h1>
                <button class="btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create link</button>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-title">Total clicks</div><div class="kpi-value"><?= number_format($total_clicks) ?></div></div>
                <div class="kpi-card"><div class="kpi-title">Unique visitors</div><div class="kpi-value"><?= number_format($unique_visitors) ?></div></div>
                <div class="kpi-card"><div class="kpi-title">Active links</div><div class="kpi-value"><?= number_format($active_links) ?></div></div>
                <div class="kpi-card"><div class="kpi-title">Total links</div><div class="kpi-value"><?= number_format($total_links) ?></div></div>
            </div>

            <div class="filter-tabs">
                <a href="<?= $baseURL ?>/?filter=all" class="filter-tab <?= $filter==='all'?'active':'' ?>">All</a>
                <a href="<?= $baseURL ?>/?filter=active" class="filter-tab <?= $filter==='active'?'active':'' ?>">Active</a>
                <a href="<?= $baseURL ?>/?filter=disabled" class="filter-tab <?= $filter==='disabled'?'active':'' ?>">Disabled</a>
                <a href="<?= $baseURL ?>/?filter=expired" class="filter-tab <?= $filter==='expired'?'active':'' ?>">Expired</a>
            </div>

            <table id="linksTable">
                <thead>
                    <tr>
                        <th>Link / Title</th>
                        <th>Destination</th>
                        <th>Clicks</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recent_links)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:32px; color:#6B7280;">No links match this filter.</td></tr>
                    <?php else: foreach($recent_links as $l): 
                        $isExpired = $l['expires_at'] && strtotime($l['expires_at']) <= time();
                    ?>
                        <tr>
                            <td>
                                <div><a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" style="color:#5B5CE2; font-family:'JetBrains Mono',monospace; font-weight:600; text-decoration:none;">/<?= htmlspecialchars($l['short_code']) ?></a></div>
                                <div style="font-size:12px; color:#9CA3AF;"><?= htmlspecialchars($l['title'] ?: 'Untitled') ?></div>
                                <?php if(!empty($l['tags'])): ?>
    <div style="margin-top: 4px; display: flex; gap: 4px; flex-wrap: wrap;">
        <?php foreach(explode(',', $l['tags']) as $tag): ?>
            <span style="font-size: 10px; background: #282C34; color: #9CA3AF; padding: 2px 6px; border-radius: 4px;">#<?= htmlspecialchars($tag) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
                            </td>
                            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#9CA3AF;">
                                <?= htmlspecialchars($l['destination_url']) ?>
                            </td>
                            <td><?= number_format($l['clicks']) ?></td>
                            <td>
                                <span style="font-size:11px; font-weight:600; text-transform:uppercase; padding:3px 8px; border-radius:4px;
                                    <?= $isExpired ? 'background:rgba(239,68,68,0.1);color:#EF4444;' : ($l['status']==='active'?'background:rgba(16,185,129,0.1);color:#10B981;':'background:rgba(234,179,8,0.1);color:#EAB308;') ?>">
                                    <?= $isExpired ? 'EXPIRED' : htmlspecialchars($l['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:12px; color:#6B7280;">
                                <?= $l['expires_at'] ? date('M j, Y H:i', strtotime($l['expires_at'])) : 'Never' ?>
                            </td>
                            <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px; padding: 14px 20px;">
                                <button class="btn-action" onclick="showNativeQR('<?= $baseURL . '/' . $l['short_code'] ?>', '/<?= $l['short_code'] ?>')">QR</button>
                                <button class="btn-action" onclick="copyLink('<?= $baseURL . '/' . $l['short_code'] ?>')">Copy</button>
                                <button class="btn-action" onclick='openEditModal(<?= json_encode($l) ?>)'>Edit</button>
                                <form method="POST" action="<?= $baseURL ?>/links/toggle" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                                    <button class="btn-action" type="submit"><?= $l['status']==='active'?'Disable':'Enable' ?></button>
                                </form>
                                <form method="POST" action="<?= $baseURL ?>/links/delete" style="margin:0;" onsubmit="return confirm('Delete this link and its analytics?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                                    <button class="btn-action btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="createModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:50; align-items:center; justify-content:center;">
        <div style="background:#16191F; padding:32px; border-radius:12px; width:100%; max-width:440px; border:1px solid #282C34;">
            <h2 style="font-size:18px; margin-bottom:20px;">Create a new link</h2>
            <form method="POST" action="<?= $baseURL ?>/links/create">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Title (optional)</label>
                    <input type="text" name="title" placeholder="e.g. Summer Campaign" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Destination URL</label>
                    <input type="url" name="url" placeholder="https://example.com/very/long/url" required style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Short code (optional)</label>
                    <input type="text" name="slug" placeholder="e.g. sale2026" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Expiration Date/Time (optional)</label>
                    <input type="datetime-local" name="expires_at" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:12px;">
    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Tags (comma-separated)</label>
    <input type="text" name="tags" placeholder="marketing, launch, blog" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
</div>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="flex:1; padding:10px; background:transparent; border:1px solid #282C34; color:#fff; border-radius:8px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="flex:1;">Create link</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:50; align-items:center; justify-content:center;">
        <div style="background:#16191F; padding:32px; border-radius:12px; width:100%; max-width:440px; border:1px solid #282C34;">
            <h2 style="font-size:18px; margin-bottom:20px;">Edit link</h2>
            <form method="POST" action="<?= $baseURL ?>/links/update">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="link_id" id="editLinkId">
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Title</label>
                    <input type="text" name="title" id="editTitle" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Destination URL</label>
                    <input type="url" name="url" id="editUrl" required style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Expiration Date/Time</label>
                    <input type="datetime-local" name="expires_at" id="editExpiresAt" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
                </div>
                <div style="margin-bottom:12px;">
    <label style="font-size:12px; color:#9CA3AF; display:block; margin-bottom:4px;">Tags (comma-separated)</label>
    <input type="text" name="tags" id="editTags" style="width:100%; padding:10px; background:#0F1115; border:1px solid #282C34; border-radius:8px; color:#fff;">
</div>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="flex:1; padding:10px; background:transparent; border:1px solid #282C34; color:#fff; border-radius:8px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="flex:1;">Save changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Native SVG QR Modal -->
    <div id="qrModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:50; align-items:center; justify-content:center;">
        <div style="background:#16191F; padding:32px; border-radius:12px; width:100%; max-width:320px; border:1px solid #282C34; text-align:center;">
            <h2 id="qrTitle" style="font-size:16px; margin-bottom:20px; font-family:'JetBrains Mono',monospace;">/slug</h2>
            <div id="qrContainer" style="background:#fff; padding:12px; border-radius:8px; display:inline-block; margin-bottom:20px;">
                <img id="qrImage" src="" alt="Native QR Code" style="width:200px; height:200px; display:block;">
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <a id="qrDownload" href="#" download="linkforge-qr.svg" class="btn-primary" style="text-decoration:none;">Download SVG</a>
                <button type="button" onclick="document.getElementById('qrModal').style.display='none'" style="padding:10px; background:transparent; border:1px solid #282C34; color:#fff; border-radius:8px; cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    <script>
        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => alert('Copied: ' + url));
        }

        function showNativeQR(url, slug) {
            document.getElementById('qrTitle').innerText = slug;
            const qrSrc = '<?= $baseURL ?>/qr?data=' + encodeURIComponent(url);
            document.getElementById('qrImage').src = qrSrc;
            document.getElementById('qrDownload').href = qrSrc;
            document.getElementById('qrModal').style.display = 'flex';
        }

        function openEditModal(link) {
            document.getElementById('editLinkId').value = link.id;
            document.getElementById('editTitle').value = link.title || '';
            document.getElementById('editTags').value = link.tags || '';
            document.getElementById('editUrl').value = link.destination_url;
            if (link.expires_at) {
                document.getElementById('editExpiresAt').value = link.expires_at.replace(' ', 'T').substring(0, 16);
            } else {
                document.getElementById('editExpiresAt').value = '';
            }
            document.getElementById('editModal').style.display = 'flex';
        }

        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('#linksTable tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
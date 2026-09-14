<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Links</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Manage, track, and secure your short URLs.</p>
    </div>
    <button onclick="openModal('createModal')" class="btn btn-primary">+ Create link</button>
</div>

<!-- KPI Summary Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total clicks</div>
        <div class="value"><?= number_format($total_clicks ?? 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Unique visitors</div>
        <div class="value"><?= number_format($unique_visitors ?? 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Active links</div>
        <div class="value"><?= number_format($active_links ?? 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Total links</div>
        <div class="value"><?= number_format(count($recent_links)) ?></div>
    </div>
</div>

<!-- Links Table Card -->
<div class="table-card">
    <div class="table-tabs">
        <a href="<?= $baseURL ?>/?filter=all" class="tab-item <?= ($filter === 'all') ? 'active' : '' ?>">All</a>
        <a href="<?= $baseURL ?>/?filter=active" class="tab-item <?= ($filter === 'active') ? 'active' : '' ?>">Active</a>
        <a href="<?= $baseURL ?>/?filter=disabled" class="tab-item <?= ($filter === 'disabled') ? 'active' : '' ?>">Disabled</a>
        <a href="<?= $baseURL ?>/?filter=expired" class="tab-item <?= ($filter === 'expired') ? 'active' : '' ?>">Expired</a>
    </div>

    <table class="data-table">
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
                <tr>
                    <td colspan="6" style="text-align:center; padding: 48px; color: var(--text-muted);">
                        No links found. Click <strong>+ Create link</strong> to generate your first short URL.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach($recent_links as $l): 
                if (empty($l['id'])) continue;
                $isExpired = !empty($l['expires_at']) && strtotime($l['expires_at']) <= time();
            ?>
            <tr>
                <td>
                    <div>
                        <a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" class="font-mono" style="color: var(--accent); font-weight: 600;">/<?= htmlspecialchars($l['short_code']) ?></a>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($l['title'] ?? 'Untitled') ?></div>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px;">
                        <?php if (!empty($l['pass_hash'])): ?>
                            <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; font-size: 10px;">🔒 Protected</span>
                        <?php endif; ?>
                        <?php if(!empty($l['tags'])): ?>
                            <?php foreach(explode(',', $l['tags']) as $tag): ?>
                                <span class="badge badge-tag">#<?= htmlspecialchars(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary);">
                    <?= htmlspecialchars($l['destination_url']) ?>
                </td>
                <td class="font-mono"><?= number_format((int)$l['clicks']) ?></td>
                <td>
                    <span class="badge <?= $isExpired ? 'badge-expired' : ($l['status'] === 'active' ? 'badge-active' : 'badge-disabled') ?>">
                        <?= $isExpired ? 'EXPIRED' : htmlspecialchars($l['status']) ?>
                    </span>
                </td>
                <td style="font-size: 12px; color: var(--text-muted);">
                    <?= !empty($l['expires_at']) ? date('M j, Y H:i', strtotime($l['expires_at'])) : 'Never' ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <a href="<?= $baseURL ?>/qr?id=<?= $l['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">QR</a>
                        <button onclick="copyToClipboard('<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $baseURL . '/' . $l['short_code'] ?>')" class="btn btn-secondary btn-sm">Copy</button>
                        <button onclick='openEditModal(<?= json_encode($l) ?>)' class="btn btn-secondary btn-sm">Edit</button>
                        <form method="POST" action="<?= $baseURL ?>/links/toggle" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $l['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="POST" action="<?= $baseURL ?>/links/delete" style="display:inline;" onsubmit="return confirm('Delete this link permanently?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Create Link -->
<div id="createModal" class="modal-backdrop">
    <div class="modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Create a new link</h3>
        <form method="POST" action="<?= $baseURL ?>/links/create">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label class="form-label">Destination URL</label>
                <input type="url" name="url" placeholder="https://example.com/long-page" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Title (optional)</label>
                <input type="text" name="title" placeholder="Campaign or Resource title" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Custom Slug (optional)</label>
                <input type="text" name="slug" placeholder="e.g. launch" class="form-input font-mono">
            </div>
            <div class="form-group">
                <label class="form-label">Password Protection (optional)</label>
                <input type="password" name="password" placeholder="Leave blank for public access" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Tags (comma-separated)</label>
                <input type="text" name="tags" placeholder="marketing, announcement" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration (optional)</label>
                <input type="datetime-local" name="expires_at" class="form-input">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('createModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create link</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Link -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Edit Link</h3>
        <form method="POST" action="<?= $baseURL ?>/links/update">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="link_id" id="editLinkId">
            
            <div class="form-group">
                <label class="form-label">Destination URL</label>
                <input type="url" name="url" id="editUrl" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" id="editTitle" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">New Password (leave empty to keep current)</label>
                <input type="password" name="password" placeholder="New password" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Tags (comma-separated)</label>
                <input type="text" name="tags" id="editTags" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration</label>
                <input type="datetime-local" name="expires_at" id="editExpiresAt" class="form-input">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(link) {
    document.getElementById('editLinkId').value = link.id;
    document.getElementById('editUrl').value = link.destination_url;
    document.getElementById('editTitle').value = link.title || '';
    document.getElementById('editTags').value = link.tags || '';
    document.getElementById('editExpiresAt').value = link.expires_at ? link.expires_at.replace(' ', 'T').substring(0, 16) : '';
    openModal('editModal');
}
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "Links - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
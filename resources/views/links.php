<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<!-- Flatpickr Assets -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/themes/dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

<style>
.lf-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.lf-modal-backdrop.active { display: flex !important; }
.lf-modal-card {
    background: #16191F;
    border: 1px solid #282C34;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
}
.preset-pills { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
.preset-btn {
    background: var(--bg-app, #0F1115);
    border: 1px solid var(--border-color, #282C34);
    color: var(--text-secondary, #9CA3AF);
    padding: 4px 8px;
    border-radius: var(--radius-sm, 6px);
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-btn:hover {
    color: var(--text-primary, #F5F5F5);
    border-color: var(--accent, #5B5CE2);
}

/* Bulk selection UI */
.link-checkbox {
    width: 16px;
    height: 16px;
    accent-color: var(--accent);
    cursor: pointer;
    vertical-align: middle;
}
.row-selected {
    background: rgba(91, 92, 226, 0.08) !important;
}
#bulkBar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(120%);
    background: #16191F;
    border: 1px solid #282C34;
    border-radius: 12px;
    padding: 12px 20px;
    box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.7);
    z-index: 9998;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
}
#bulkBar.active {
    transform: translateX(-50%) translateY(0);
    pointer-events: auto;
}
</style>

<!-- Flash: Success -->
<?php if ($msg = flash('success')): ?>
<div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; color: #10B981; font-size: 13px; font-weight: 500;">
    <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Flash: Error -->
<?php if ($err = flash('error')): ?>
<div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; color: #EF4444; font-size: 13px; font-weight: 500;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i><?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<!-- Flash: Slug Taken -->
<?php if ($taken = flash('slug_taken')): ?>
<div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 16px 18px; margin-bottom: 20px;">
    <div style="color: #EF4444; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
        <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
        The slug <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($taken['attempted']) ?></code> is already taken globally.
    </div>
    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px;">
        Short codes are the public URL — they must be globally unique across all users. Try one of these instead:
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php foreach ($taken['suggestions'] as $s): ?>
            <button type="button" onclick="useSlug('<?= htmlspecialchars($s) ?>')" class="preset-btn" style="font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($s) ?></button>
        <?php endforeach; ?>
    </div>
</div>
<script>
function useSlug(slug) {
    document.querySelector('input[name="slug"]').value = slug;
    openModal('createModal');
    const urlInput = document.querySelector('input[name="url"]');
    if (urlInput && !urlInput.value) urlInput.focus();
}
</script>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Links</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Manage, track, and secure your short URLs.</p>
    </div>
    <button type="button" onclick="openModal('createModal')" class="btn btn-primary">+ Create link</button>
</div>

<!-- Active tag filter banner -->
<?php if (!empty($activeTag)): ?>
<div style="display: flex; align-items: center; justify-content: space-between; background: rgba(91,92,226,0.1); border: 1px solid rgba(91,92,226,0.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
    <div style="font-size: 13px; color: var(--text-primary);">
        <i class="fa-solid fa-filter" style="margin-right: 8px; color: var(--accent);"></i>
        Filtering by tag: <strong style="color: var(--accent);">#<?= htmlspecialchars($activeTag) ?></strong>
    </div>
    <a href="<?= $baseURL ?>/?filter=<?= htmlspecialchars($filter ?? 'all') ?>" class="btn btn-secondary btn-sm">Clear filter</a>
</div>
<?php endif; ?>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi-card"><div class="label">Total clicks</div><div class="value"><?= number_format($total_clicks ?? 0) ?></div></div>
    <div class="kpi-card"><div class="label">Unique visitors</div><div class="value"><?= number_format($unique_visitors ?? 0) ?></div></div>
    <div class="kpi-card"><div class="label">Active links</div><div class="value"><?= number_format($active_links ?? 0) ?></div></div>
    <div class="kpi-card"><div class="label">Total links</div><div class="value"><?= number_format(count($recent_links)) ?></div></div>
</div>

<?php
// Keep the tag filter when switching tabs
$tagQS = !empty($activeTag) ? '&tag=' . urlencode($activeTag) : '';
?>

<div class="table-card">
    <div class="table-tabs">
        <a href="<?= $baseURL ?>/?filter=all<?= $tagQS ?>" class="tab-item <?= ($filter === 'all') ? 'active' : '' ?>">All</a>
        <a href="<?= $baseURL ?>/?filter=active<?= $tagQS ?>" class="tab-item <?= ($filter === 'active') ? 'active' : '' ?>">Active</a>
        <a href="<?= $baseURL ?>/?filter=disabled<?= $tagQS ?>" class="tab-item <?= ($filter === 'disabled') ? 'active' : '' ?>">Disabled</a>
        <a href="<?= $baseURL ?>/?filter=expired<?= $tagQS ?>" class="tab-item <?= ($filter === 'expired') ? 'active' : '' ?>">Expired</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 36px;">
                    <input type="checkbox" id="selectAllLinks" class="link-checkbox" title="Select all">
                </th>
                <th>Link / Title</th>
                <th>Destination</th>
                <th>Clicks</th>
                <th>Status</th>
                <th>Expires</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_links)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 48px; color: var(--text-muted);">
                        No links found. Click <strong>+ Create link</strong> to generate your first short URL.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($recent_links as $l):
                if (empty($l['id'])) continue;
                $isExpired = !empty($l['expires_at']) && strtotime($l['expires_at']) <= time();
            ?>
            <tr data-link-row="<?= (int)$l['id'] ?>">
                <td>
                    <input type="checkbox" class="link-checkbox" name="link_ids[]" value="<?= (int)$l['id'] ?>">
                </td>
                <td>
                    <div>
                        <a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" class="font-mono" style="color: var(--accent); font-weight: 600;">/<?= htmlspecialchars($l['short_code']) ?></a>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($l['title'] ?? 'Untitled') ?></div>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px;">
                        <?php if (!empty($l['pass_hash'])): ?>
                            <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; font-size: 10px;">
                                <i class="fa-solid fa-lock" style="margin-right: 3px;"></i> Protected
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($l['tags'])): ?>
                            <?php foreach (explode(',', $l['tags']) as $tag): ?>
                                <a href="<?= $baseURL ?>/?tag=<?= urlencode(trim($tag)) ?>" style="text-decoration: none;">
                                    <span class="badge badge-tag" style="cursor: pointer;">#<?= htmlspecialchars(trim($tag)) ?></span>
                                </a>
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
                        <button type="button" onclick="copyToClipboard('<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $baseURL . '/' . $l['short_code'] ?>')" class="btn btn-secondary btn-sm">Copy</button>
                        <button type="button"
                            class="btn btn-secondary btn-sm"
                            data-id="<?= $l['id'] ?>"
                            data-url="<?= htmlspecialchars($l['destination_url'], ENT_QUOTES, 'UTF-8') ?>"
                            data-fallback="<?= htmlspecialchars($l['fallback_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-title="<?= htmlspecialchars($l['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-tags="<?= htmlspecialchars($l['tags'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-expires="<?= htmlspecialchars($l['expires_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-protected="<?= !empty($l['pass_hash']) ? '1' : '0' ?>"
                            onclick="openEditModal(this)">Edit</button>

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

<!-- Hidden values for the bulk JS -->
<input type="hidden" id="bulkFormAction" value="<?= $baseURL ?>/links/bulk">
<input type="hidden" id="bulkCsrf" value="<?= $_SESSION['csrf_token'] ?>">

<!-- Floating bulk action bar -->
<div id="bulkBar">
    <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">
        <span id="bulkCount">0</span> selected
    </span>
    <div style="width: 1px; height: 24px; background: var(--border-color);"></div>
    <div style="display: flex; gap: 8px;">
        <button type="button" onclick="bulkSubmit('enable')" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-circle-check" style="margin-right: 4px; font-size: 11px;"></i> Enable
        </button>
        <button type="button" onclick="bulkSubmit('disable')" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-circle-pause" style="margin-right: 4px; font-size: 11px;"></i> Disable
        </button>
        <button type="button" onclick="bulkSubmit('delete')" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-trash" style="margin-right: 4px; font-size: 11px;"></i> Delete
        </button>
    </div>
    <div style="width: 1px; height: 24px; background: var(--border-color);"></div>
    <button type="button" onclick="bulkClear()" class="btn btn-secondary btn-sm" title="Clear selection">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>

<!-- Modal: Create Link -->
<div id="createModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
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
                <input type="password" name="password" placeholder="Leave blank for public access" autocomplete="new-password" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Tags (comma-separated)</label>
                <input type="text" name="tags" placeholder="marketing, announcement" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration (optional)</label>
                <div style="position: relative;">
                    <input type="text" name="expires_at" id="createExpiresAt" placeholder="Pick date & time" class="form-input" style="cursor: pointer;">
                    <i class="fa-regular fa-calendar" style="position: absolute; right: 12px; top: 12px; color: var(--text-muted); pointer-events: none;"></i>
                </div>
                <div class="preset-pills">
                    <button type="button" class="preset-btn" onclick="setPreset('create', 24)">+24 Hours</button>
                    <button type="button" class="preset-btn" onclick="setPreset('create', 168)">+7 Days</button>
                    <button type="button" class="preset-btn" onclick="setPreset('create', 720)">+30 Days</button>
                    <button type="button" class="preset-btn" onclick="clearPreset('create')" style="color: var(--status-expired-text);">Clear</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Expiration Fallback URL (optional)</label>
                <input type="url" name="fallback_url" placeholder="https://example.com/campaign-ended" class="form-input">
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                    Where visitors will be sent if the link expires. If left empty, an HTTP 410 page is shown.
                </span>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('createModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create link</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Link -->
<div id="editModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
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
                <label class="form-label">Password Protection</label>
                <input type="password" name="password" id="editPassword" placeholder="Enter new password" autocomplete="new-password" class="form-input">
                <div id="removePasswordContainer" style="margin-top: 8px; display: none;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--status-expired-text); cursor: pointer;">
                        <input type="checkbox" name="remove_password" id="removePasswordCheckbox" value="1">
                        Remove password protection from this link
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Tags (comma-separated)</label>
                <input type="text" name="tags" id="editTags" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Expiration</label>
                <div style="position: relative;">
                    <input type="text" name="expires_at" id="editExpiresAt" placeholder="Pick date & time (or leave blank for permanent)" class="form-input" style="cursor: pointer;">
                    <i class="fa-regular fa-calendar" style="position: absolute; right: 12px; top: 12px; color: var(--text-muted); pointer-events: none;"></i>
                </div>
                <div class="preset-pills">
                    <button type="button" class="preset-btn" onclick="setPreset('edit', 24)">+24 Hours</button>
                    <button type="button" class="preset-btn" onclick="setPreset('edit', 168)">+7 Days</button>
                    <button type="button" class="preset-btn" onclick="setPreset('edit', 720)">+30 Days</button>
                    <button type="button" class="preset-btn" onclick="clearPreset('edit')" style="color: var(--status-expired-text);">Clear / Never</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Expiration Fallback URL</label>
                <input type="url" name="fallback_url" id="editFallbackUrl" placeholder="https://example.com/campaign-ended" class="form-input">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// --- Flatpickr setup ---
window.createPicker = null;
window.editPicker = null;
window.initPickers = function() {
    if (typeof flatpickr !== 'undefined') {
        window.createPicker = flatpickr("#createExpiresAt", { enableTime: true, dateFormat: "Y-m-d H:i:00", altInput: true, altFormat: "M j, Y h:i K", minDate: "today", time_24hr: false });
        window.editPicker = flatpickr("#editExpiresAt", { enableTime: true, dateFormat: "Y-m-d H:i:00", altInput: true, altFormat: "M j, Y h:i K", time_24hr: false });
    }
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initPickers);
} else {
    window.initPickers();
}

// --- Modal helpers ---
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) {
        window.closeModal(e.target.id);
    }
});
window.openModal = function(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); };
window.closeModal = function(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); };

// --- Edit modal populator ---
window.openEditModal = function(button) {
    try {
        var id = button.getAttribute('data-id') || '';
        var url = button.getAttribute('data-url') || '';
        var title = button.getAttribute('data-title') || '';
        var tags = button.getAttribute('data-tags') || '';
        var expires = button.getAttribute('data-expires') || '';
        var isProtected = button.getAttribute('data-protected') === '1';
        var fallback = button.getAttribute('data-fallback') || '';
        var fallbackInput = document.getElementById('editFallbackUrl');
        if (fallbackInput) fallbackInput.value = fallback;
        document.getElementById('editLinkId').value = id;
        document.getElementById('editUrl').value = url;
        document.getElementById('editTitle').value = title;
        document.getElementById('editTags').value = tags;
        var passInput = document.getElementById('editPassword');
        if (passInput) passInput.value = '';
        var removeContainer = document.getElementById('removePasswordContainer');
        var removeCheckbox = document.getElementById('removePasswordCheckbox');
        if (removeCheckbox) removeCheckbox.checked = false;
        if (removeContainer) removeContainer.style.display = isProtected ? 'block' : 'none';
        if (window.editPicker && typeof window.editPicker.setDate === 'function') {
            if (expires && expires !== 'Never') window.editPicker.setDate(expires, true);
            else window.editPicker.clear();
        }
        window.openModal('editModal');
    } catch (err) {
        console.error("Edit modal error:", err);
    }
};

window.setPreset = function(modalType, hoursToAdd) {
    var targetDate = new Date(Date.now() + hoursToAdd * 3600 * 1000);
    var picker = (modalType === 'create') ? window.createPicker : window.editPicker;
    if (picker && typeof picker.setDate === 'function') picker.setDate(targetDate, true);
};
window.clearPreset = function(modalType) {
    var picker = (modalType === 'create') ? window.createPicker : window.editPicker;
    if (picker && typeof picker.clear === 'function') picker.clear();
};

// ============================================================
// BULK SELECTION ENGINE
// ============================================================
(function() {
    var selectAll     = document.getElementById('selectAllLinks');
    var bulkBar       = document.getElementById('bulkBar');
    var bulkCountEl   = document.getElementById('bulkCount');
    var checkboxes    = document.querySelectorAll('.link-checkbox[name="link_ids[]"]');

    function updateBar() {
        var checked = document.querySelectorAll('.link-checkbox[name="link_ids[]"]:checked');
        var n = checked.length;

        if (n > 0) {
            bulkCountEl.textContent = n;
            bulkBar.classList.add('active');
        } else {
            bulkBar.classList.remove('active');
        }

        // Highlight selected rows
        document.querySelectorAll('tr[data-link-row]').forEach(function(row) {
            var cb = row.querySelector('.link-checkbox');
            if (cb && cb.checked) row.classList.add('row-selected');
            else row.classList.remove('row-selected');
        });

        // Sync select-all state
        if (selectAll) {
            selectAll.checked = (n === checkboxes.length && checkboxes.length > 0);
            selectAll.indeterminate = (n > 0 && n < checkboxes.length);
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBar();
        });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBar);
    });

    // Clear selection
    window.bulkClear = function() {
        checkboxes.forEach(function(cb) { cb.checked = false; });
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateBar();
    };

    // Submit bulk action
    window.bulkSubmit = function(action) {
        var checked = document.querySelectorAll('.link-checkbox[name="link_ids[]"]:checked');
        if (checked.length === 0) {
            alert('No links selected.');
            return;
        }

        // Confirm destructive actions
        if (action === 'delete') {
            var plural = checked.length === 1 ? 'link' : 'links';
            if (!confirm('Delete ' + checked.length + ' ' + plural + '? This cannot be undone.')) return;
        }
        if (action === 'disable') {
            if (!confirm('Disable ' + checked.length + ' selected link(s)?')) return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = document.getElementById('bulkFormAction').value;
        form.style.display = 'none';

        function addField(name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        addField('csrf_token', document.getElementById('bulkCsrf').value);
        addField('bulk_action', action);

        checked.forEach(function(cb) {
            addField('link_ids[]', cb.value);
        });

        document.body.appendChild(form);
        form.submit();
    };
})();
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "Links - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
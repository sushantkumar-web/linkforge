<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<style>
.lf-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px; }
.lf-modal-backdrop.active { display: flex !important; }
.lf-modal-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: var(--shadow-lg); }

.utm-preview {
    background: var(--bg-app);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 8px;
    word-break: break-all;
    line-height: 1.6;
}
.utm-preview .param { color: var(--accent); }
.utm-preview .val    { color: var(--text-primary); }

.preset-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    background: var(--bg-app);
    border: 1px solid var(--border-color);
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text-secondary);
    margin-right: 4px;
    margin-bottom: 4px;
}
.preset-tag .key { color: var(--accent-text); }

.default-star {
    color: #FBBF24;
    font-size: 11px;
    margin-left: 4px;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">UTM Presets</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">
            Reusable campaign tracking templates. Attach one to any link when creating it.
        </p>
    </div>
    <button type="button" onclick="openUtmModal('create')" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> New Preset
    </button>
</div>

<?php if ($msg = flash('success')): ?>
<div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; color: #10B981; font-size: 13px; font-weight: 500;">
    <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($err = flash('error')): ?>
<div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; color: #EF4444; font-size: 13px; font-weight: 500;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i><?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<?php if (empty($presets)): ?>
    <div class="table-card" style="padding: 60px 20px; text-align: center;">
        <i class="fa-solid fa-bullseye" style="font-size: 40px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <div style="font-size: 16px; font-weight: 600; margin-bottom: 6px;">No UTM presets yet</div>
        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            Create a preset to track campaigns consistently across all your links.
        </div>
        <button type="button" onclick="openUtmModal('create')" class="btn btn-primary">Create your first preset</button>
    </div>
<?php else: ?>
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Parameters</th>
                    <th>Usage</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($presets as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;">
                            <?= htmlspecialchars($p['name']) ?>
                            <?php if ((int)$p['is_default'] === 1): ?>
                                <i class="fa-solid fa-star default-star" title="Default preset"></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="preset-tag"><span class="key">source</span>=<?= htmlspecialchars($p['utm_source']) ?></span>
                        <span class="preset-tag"><span class="key">medium</span>=<?= htmlspecialchars($p['utm_medium']) ?></span>
                        <?php if (!empty($p['utm_campaign'])): ?>
                            <span class="preset-tag"><span class="key">campaign</span>=<?= htmlspecialchars($p['utm_campaign']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($p['utm_term'])): ?>
                            <span class="preset-tag"><span class="key">term</span>=<?= htmlspecialchars($p['utm_term']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($p['utm_content'])): ?>
                            <span class="preset-tag"><span class="key">content</span>=<?= htmlspecialchars($p['utm_content']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="font-mono" style="font-size: 13px;"><?= number_format((int)$p['usage_count']) ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px;">
                            <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?= (int)$p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                data-source="<?= htmlspecialchars($p['utm_source'], ENT_QUOTES) ?>"
                                data-medium="<?= htmlspecialchars($p['utm_medium'], ENT_QUOTES) ?>"
                                data-campaign="<?= htmlspecialchars($p['utm_campaign'] ?? '', ENT_QUOTES) ?>"
                                data-term="<?= htmlspecialchars($p['utm_term'] ?? '', ENT_QUOTES) ?>"
                                data-content="<?= htmlspecialchars($p['utm_content'] ?? '', ENT_QUOTES) ?>"
                                data-default="<?= (int)$p['is_default'] ?>"
                                onclick="openUtmEditModal(this)">Edit</button>

                            <form method="POST" action="<?= $baseURL ?>/utm-presets/delete" style="display:inline;"
                                  onsubmit="return confirm('Delete this preset? Existing links keep their URLs.')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="preset_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Create modal -->
<div id="utmCreateModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 4px;">New UTM Preset</h3>
        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 20px;">
            Values are lowercase. Spaces become underscores automatically.
        </p>
        <form method="POST" action="<?= $baseURL ?>/utm-presets/store" onsubmit="syncUtmPreview('create')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label class="form-label">Preset Name <span style="color: #EF4444;">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Instagram Stories Q3" class="form-input">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Source <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="utm_source" id="create-source" required
                           placeholder="instagram" class="form-input font-mono"
                           oninput="syncUtmPreview('create')">
                </div>
                <div class="form-group">
                    <label class="form-label">Medium <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="utm_medium" id="create-medium" required
                           placeholder="social" class="form-input font-mono"
                           oninput="syncUtmPreview('create')">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Campaign</label>
                <input type="text" name="utm_campaign" id="create-campaign"
                       placeholder="summer_sale" class="form-input font-mono"
                       oninput="syncUtmPreview('create')">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Term <span style="font-size: 11px; color: var(--text-muted);">(optional)</span></label>
                    <input type="text" name="utm_term" id="create-term"
                           placeholder="running_shoes" class="form-input font-mono"
                           oninput="syncUtmPreview('create')">
                </div>
                <div class="form-group">
                    <label class="form-label">Content <span style="font-size: 11px; color: var(--text-muted);">(optional)</span></label>
                    <input type="text" name="utm_content" id="create-content"
                           placeholder="cta_button" class="form-input font-mono"
                           oninput="syncUtmPreview('create')">
                </div>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="is_default" value="1">
                    Set as default preset
                </label>
            </div>

            <label class="form-label" style="margin-top: 8px;">Preview</label>
            <div class="utm-preview" id="create-preview">
                Fill in Source and Medium to see the parameters.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('utmCreateModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Preset</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div id="utmEditModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 20px;">Edit UTM Preset</h3>
        <form method="POST" action="<?= $baseURL ?>/utm-presets/update" onsubmit="syncUtmPreview('edit')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="preset_id" id="edit-id">

            <div class="form-group">
                <label class="form-label">Preset Name <span style="color: #EF4444;">*</span></label>
                <input type="text" name="name" id="edit-name" required class="form-input">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Source <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="utm_source" id="edit-source" required class="form-input font-mono" oninput="syncUtmPreview('edit')">
                </div>
                <div class="form-group">
                    <label class="form-label">Medium <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="utm_medium" id="edit-medium" required class="form-input font-mono" oninput="syncUtmPreview('edit')">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Campaign</label>
                <input type="text" name="utm_campaign" id="edit-campaign" class="form-input font-mono" oninput="syncUtmPreview('edit')">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Term</label>
                    <input type="text" name="utm_term" id="edit-term" class="form-input font-mono" oninput="syncUtmPreview('edit')">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <input type="text" name="utm_content" id="edit-content" class="form-input font-mono" oninput="syncUtmPreview('edit')">
                </div>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="is_default" id="edit-default" value="1">
                    Default preset
                </label>
            </div>

            <label class="form-label" style="margin-top: 8px;">Preview</label>
            <div class="utm-preview" id="edit-preview"></div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('utmEditModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openModal  = function(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); };
window.closeModal = function(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); };

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) {
        window.closeModal(e.target.id);
    }
});

window.openUtmModal = function(type) {
    // Reset create form
    if (type === 'create') {
        document.querySelector('#utmCreateModal form').reset();
        syncUtmPreview('create');
    }
    window.openModal('utmCreateModal');
};

window.openUtmEditModal = function(btn) {
    document.getElementById('edit-id').value       = btn.dataset.id;
    document.getElementById('edit-name').value     = btn.dataset.name;
    document.getElementById('edit-source').value   = btn.dataset.source;
    document.getElementById('edit-medium').value   = btn.dataset.medium;
    document.getElementById('edit-campaign').value = btn.dataset.campaign;
    document.getElementById('edit-term').value     = btn.dataset.term;
    document.getElementById('edit-content').value  = btn.dataset.content;
    document.getElementById('edit-default').checked = btn.dataset.default === '1';

    syncUtmPreview('edit');
    window.openModal('utmEditModal');
};

window.syncUtmPreview = function(prefix) {
    var src = (document.getElementById(prefix + '-source')?.value || '').trim();
    var med = (document.getElementById(prefix + '-medium')?.value || '').trim();
    var cmp = (document.getElementById(prefix + '-campaign')?.value || '').trim();
    var trm = (document.getElementById(prefix + '-term')?.value || '').trim();
    var cnt = (document.getElementById(prefix + '-content')?.value || '').trim();
    var box = document.getElementById(prefix + '-preview');
    if (!box) return;

    var parts = [];
    if (src) parts.push('utm_source=' + encodeURIComponent(src.toLowerCase().replace(/\s+/g, '_')));
    if (med) parts.push('utm_medium=' + encodeURIComponent(med.toLowerCase().replace(/\s+/g, '_')));
    if (cmp) parts.push('utm_campaign=' + encodeURIComponent(cmp.toLowerCase().replace(/\s+/g, '_')));
    if (trm) parts.push('utm_term=' + encodeURIComponent(trm.toLowerCase().replace(/\s+/g, '_')));
    if (cnt) parts.push('utm_content=' + encodeURIComponent(cnt.toLowerCase().replace(/\s+/g, '_')));

    if (!parts.length) {
        box.textContent = 'Fill in Source and Medium to see the parameters.';
        return;
    }

    var html = '<span style="color: var(--text-muted);">https://example.com/page</span><br>?';
    html += parts.map(function(p) {
        var kv = p.split('=');
        return '<span class="param">' + kv[0] + '</span>=<span class="val">' + kv[1] + '</span>';
    }).join('<br>&amp;');

    box.innerHTML = html;
};
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "UTM Presets - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
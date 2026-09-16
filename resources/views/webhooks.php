<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<style>
.lf-modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; }
.lf-modal-backdrop.active { display: flex !important; }
.lf-modal-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
.event-checkbox { display: flex; align-items: center; gap: 8px; padding: 8px 0; font-size: 13px; cursor: pointer; }
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Webhooks</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Receive real-time POST requests when links are created, updated, or clicked.</p>
    </div>
    <button type="button" onclick="openModal('createWebhookModal')" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Add Webhook
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

<?php if ($newSecret): ?>
<div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
        <span style="font-size: 14px; font-weight: 600; color: #10B981;">
            <i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i> Webhook created: <?= htmlspecialchars($newSecret['name']) ?>
        </span>
        <span style="font-size: 12px; color: #EF4444; font-weight: 500;">Copy the signing secret now — it won't be shown again.</span>
    </div>
    <div style="display: flex; gap: 8px;">
        <input type="text" readonly value="<?= htmlspecialchars($newSecret['secret']) ?>" class="form-input font-mono" style="background: #0B0D11; border-color: #10B981; color: #F3F4F6;">
        <button type="button" class="btn btn-primary" onclick="copyToClipboard('<?= htmlspecialchars($newSecret['secret']) ?>')">Copy</button>
    </div>
    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 10px;">
        Verify incoming requests by computing <code>HMAC-SHA256(secret, "{timestamp}.{raw_body}")</code> and comparing with the <code>X-LinkForge-Signature</code> header.
    </p>
</div>
<?php endif; ?>

<div class="table-card" style="margin-bottom: 32px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 14px;">Active Endpoints</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>URL</th>
                <th>Events</th>
                <th>Status</th>
                <th>Last Fired</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($webhooks)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 48px; color: var(--text-muted);">
                    No webhooks yet. Add one to receive real-time POST notifications.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($webhooks as $w): ?>
            <tr style="<?= $w['status'] !== 'active' ? 'opacity: 0.55;' : '' ?>">
                <td>
                    <div style="font-weight: 600;"><?= htmlspecialchars($w['name']) ?></div>
                    <?php if ((int)$w['failure_count'] > 0): ?>
                        <div style="font-size: 11px; color: #EF4444;">
                            <i class="fa-solid fa-triangle-exclamation" style="margin-right: 3px;"></i><?= (int)$w['failure_count'] ?> consecutive failures
                        </div>
                    <?php endif; ?>
                </td>
                <td class="font-mono" style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: var(--text-secondary);">
                    <?= htmlspecialchars($w['url']) ?>
                </td>
                <td>
                    <?php foreach (explode(',', $w['events']) as $e): ?>
                        <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; margin-right: 4px; font-size: 10px;">
                            <?= htmlspecialchars(trim($e)) ?>
                        </span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <span class="badge <?= $w['status'] === 'active' ? 'badge-active' : 'badge-disabled' ?>">
                        <?= htmlspecialchars($w['status']) ?>
                    </span>
                </td>
                <td style="font-size: 12px; color: var(--text-muted);">
                    <?= !empty($w['last_fired_at']) ? date('M j, H:i', strtotime($w['last_fired_at'])) : 'Never' ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="testWebhook(<?= (int)$w['id'] ?>)">Test</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                            data-id="<?= (int)$w['id'] ?>"
                            data-name="<?= htmlspecialchars($w['name'], ENT_QUOTES) ?>"
                            data-url="<?= htmlspecialchars($w['url'], ENT_QUOTES) ?>"
                            data-events="<?= htmlspecialchars($w['events'], ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($w['status'], ENT_QUOTES) ?>"
                            onclick="openEditWebhook(this)">Edit</button>
                        <form method="POST" action="<?= $baseURL ?>/webhooks/toggle" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="webhook_id" value="<?= (int)$w['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $w['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="POST" action="<?= $baseURL ?>/webhooks/delete" style="display:inline;" onsubmit="return confirm('Delete this webhook permanently?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="webhook_id" value="<?= (int)$w['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="table-card">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color);">
        <div style="font-weight: 600; font-size: 14px;">Recent Deliveries</div>
        <form method="POST" action="<?= $baseURL ?>/webhooks/retry" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Retry Failed</button>
        </form>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th>Event</th>
                <th>Webhook</th>
                <th>Code</th>
                <th>Attempt</th>
                <th style="text-align: right;">Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentDeliveries)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 32px; color: var(--text-muted);">
                    No deliveries yet. Webhooks fire when matching events occur.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($recentDeliveries as $d): ?>
            <tr>
                <td>
                    <span class="badge" style="background: <?= $d['status'] === 'success' ? 'rgba(16,185,129,0.15)' : ($d['status'] === 'pending' ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)') ?>; color: <?= $d['status'] === 'success' ? '#10B981' : ($d['status'] === 'pending' ? '#F59E0B' : '#EF4444') ?>; font-size: 11px;">
                        <?= htmlspecialchars($d['status']) ?>
                    </span>
                </td>
                <td class="font-mono" style="font-size: 12px;"><?= htmlspecialchars($d['event']) ?></td>
                <td style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($d['webhook_name']) ?></td>
                <td class="font-mono" style="font-size: 12px;">
                    <?= $d['response_code'] ? (int)$d['response_code'] : '—' ?>
                </td>
                <td class="font-mono" style="font-size: 12px; color: var(--text-muted);">#<?= (int)$d['attempt'] ?></td>
                <td style="text-align: right; font-size: 12px; color: var(--text-muted);">
                    <?= date('M j, H:i:s', strtotime($d['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create modal -->
<div id="createWebhookModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Add Webhook</h3>
        <form method="POST" action="<?= $baseURL ?>/webhooks/store">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" required placeholder="e.g. Slack notifications" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Endpoint URL</label>
                <input type="url" name="url" required placeholder="https://hooks.slack.com/services/..." class="form-input">
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">Must accept POST with JSON body.</span>
            </div>
            <div class="form-group">
                <label class="form-label">Subscribe to Events</label>
                <div style="margin-top: 4px;">
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.created" checked> Link created</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.updated" checked> Link updated</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.deleted"> Link deleted</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.clicked"> Link clicked (throttled to 1 per minute per link)</label>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('createWebhookModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Webhook</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div id="editWebhookModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Edit Webhook</h3>
        <form method="POST" action="<?= $baseURL ?>/webhooks/update">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="webhook_id" id="editWebhookId">
            <input type="hidden" name="status" id="editWebhookStatus" value="active">
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="editWebhookName" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Endpoint URL</label>
                <input type="url" name="url" id="editWebhookUrl" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Events</label>
                <div style="margin-top: 4px;">
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.created" id="ev-created"> Link created</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.updated" id="ev-updated"> Link updated</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.deleted" id="ev-deleted"> Link deleted</label>
                    <label class="event-checkbox"><input type="checkbox" name="events[]" value="link.clicked" id="ev-clicked"> Link clicked</label>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('editWebhookModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openModal = function(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); };
window.closeModal = function(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); };
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) window.closeModal(e.target.id);
});

window.openEditWebhook = function(btn) {
    document.getElementById('editWebhookId').value = btn.dataset.id;
    document.getElementById('editWebhookName').value = btn.dataset.name;
    document.getElementById('editWebhookUrl').value = btn.dataset.url;
    document.getElementById('editWebhookStatus').value = btn.dataset.status;

    var evs = (btn.dataset.events || '').split(',').map(function(s){ return s.trim(); });
    document.getElementById('ev-created').checked = evs.indexOf('link.created') !== -1;
    document.getElementById('ev-updated').checked = evs.indexOf('link.updated') !== -1;
    document.getElementById('ev-deleted').checked = evs.indexOf('link.deleted') !== -1;
    document.getElementById('ev-clicked').checked = evs.indexOf('link.clicked') !== -1;

    window.openModal('editWebhookModal');
};

window.testWebhook = function(id) {
    var btn = event.target;
    var originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Sending…';

    var fd = new FormData();
    fd.append('webhook_id', id);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    fetch('<?= $baseURL ?>/webhooks/test', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                alert('✓ Test delivered. Response: HTTP ' + (data.response_code || '?'));
            } else {
                alert('✕ Test failed.\nHTTP: ' + (data.response_code || 'n/a') + '\nError: ' + (data.error || data.response_body || 'unknown'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); })
        .finally(function() { btn.disabled = false; btn.innerText = originalText; });
};
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "Webhooks - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
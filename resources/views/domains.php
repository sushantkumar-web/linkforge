<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<style>
.lf-modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; }
.lf-modal-backdrop.active { display: flex !important; }
.lf-modal-card { background: #16191F; border: 1px solid #282C34; border-radius: 12px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Custom Domains</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Serve short links from your own domain. Improves trust and click-through rate.</p>
    </div>
    <button type="button" onclick="openModal('addDomainModal')" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Add Domain
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

<?php if ($pendingToken): ?>
<div style="background: rgba(91,92,226,0.08); border: 1px solid rgba(91,92,226,0.3); border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
    <div style="font-size: 14px; font-weight: 600; color: var(--accent); margin-bottom: 12px;">
        <i class="fa-solid fa-dns" style="margin-right: 6px;"></i> DNS Setup Required for <?= htmlspecialchars($pendingToken['hostname']) ?>
    </div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
        Add the following DNS record at your domain registrar or DNS provider:
    </p>
    <table style="width: 100%; font-size: 13px; background: var(--bg-app); border-radius: 6px; padding: 12px;">
        <tr>
            <td style="padding: 6px 12px; color: var(--text-muted); width: 100px;">Type</td>
            <td style="padding: 6px 12px;" class="font-mono">TXT</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; color: var(--text-muted);">Name / Host</td>
            <td style="padding: 6px 12px;" class="font-mono">_linkforge-verify.<?= htmlspecialchars($pendingToken['hostname']) ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; color: var(--text-muted);">Value</td>
            <td style="padding: 6px 12px;" class="font-mono" style="word-break: break-all;"><?= htmlspecialchars($pendingToken['token']) ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; color: var(--text-muted);">TTL</td>
            <td style="padding: 6px 12px;" class="font-mono">3600 (or Auto)</td>
        </tr>
    </table>
    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 12px;">
        Then point a CNAME or A record for <strong><?= htmlspecialchars($pendingToken['hostname']) ?></strong> at this server so traffic reaches LinkForge.
    </p>
</div>
<?php endif; ?>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Hostname</th>
                <th>Status</th>
                <th>Links</th>
                <th>Added</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($domains)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 48px; color: var(--text-muted);">
                        No custom domains yet. Add one to serve links from your own domain.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($domains as $d):
                $isVerified = !empty($d['verified_at']);
                $isPrimary = (int)$d['is_primary'] === 1;
            ?>
            <tr>
                <td>
                    <div class="font-mono" style="font-weight: 600;"><?= htmlspecialchars($d['hostname']) ?></div>
                    <?php if ($isPrimary): ?>
                        <span class="badge" style="background: rgba(91,92,226,0.15); color: #8B8DF8; font-size: 10px; margin-top: 4px;">
                            <i class="fa-solid fa-star" style="margin-right: 3px;"></i> Primary
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isVerified): ?>
                        <span class="badge badge-active">Verified</span>
                    <?php else: ?>
                        <span class="badge" style="background: rgba(245,158,11,0.15); color: #F59E0B;">Pending DNS</span>
                    <?php endif; ?>
                </td>
                <td class="font-mono"><?= number_format((int)$d['link_count']) ?></td>
                <td style="font-size: 12px; color: var(--text-muted);"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <?php if (!$isVerified): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="showDns(<?= (int)$d['id'] ?>)">Show DNS</button>
                            <form method="POST" action="<?= $baseURL ?>/domains/verify" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="domain_id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Verify now</button>
                            </form>
                        <?php else: ?>
                            <?php if (!$isPrimary): ?>
                                <form method="POST" action="<?= $baseURL ?>/domains/set-primary" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="domain_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Make primary</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="POST" action="<?= $baseURL ?>/domains/delete" style="display:inline;" onsubmit="return confirm('Delete this domain? Links attached to it must be moved first.');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="domain_id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add domain modal -->
<div id="addDomainModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Add Custom Domain</h3>
        <form method="POST" action="<?= $baseURL ?>/domains/store">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label class="form-label">Hostname</label>
                <input type="text" name="hostname" required placeholder="go.example.com" class="form-input font-mono">
                <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 6px;">
                    No protocol, no path. Just the hostname. Examples: <code>go.company.com</code>, <code>link.co</code>.
                </span>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('addDomainModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Domain</button>
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

window.showDns = function(id) {
    // For pending domains, just scroll to the DNS banner if it exists
    var banner = document.querySelector('div[style*="DNS Setup Required"]');
    if (banner) banner.scrollIntoView({ behavior: 'smooth' });
};
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "Custom Domains - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
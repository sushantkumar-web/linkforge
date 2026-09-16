<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">System Health</h1>
    <p style="font-size: 13px; color: var(--text-secondary);">Environment, migrations, and filesystem status.</p>
</div>

<?php if (!empty($pending)): ?>
<div style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
    <div style="font-size: 14px; font-weight: 600; color: #F59E0B; margin-bottom: 8px;">
        <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i> <?= count($pending) ?> pending migration<?= count($pending) === 1 ? '' : 's' ?>
    </div>
    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
        These migration files exist on disk but haven't been applied to the database. Run them by clicking below.
    </div>
    <ul style="font-family: monospace; font-size: 12px; color: var(--text-primary); margin: 0 0 12px 20px;">
        <?php foreach ($pending as $p): ?>
            <li><?= htmlspecialchars($p) ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="POST" action="<?= $baseURL ?>/settings/update" style="margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">Run Pending Migrations</button>
    </form>
</div>
<?php endif; ?>

<!-- Environment card -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Environment</div>
        <table style="width: 100%; font-size: 13px;">
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">LinkForge version</td>
                <td style="text-align: right;"><span class="font-mono" style="color: var(--accent);">v<?= htmlspecialchars($currentVersion) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">PHP version</td>
                <td style="text-align: right;"><span class="font-mono"><?= htmlspecialchars($phpVersion) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">MySQL / MariaDB</td>
                <td style="text-align: right;"><span class="font-mono"><?= htmlspecialchars($mysqlVersion) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Database</td>
                <td style="text-align: right;"><span class="font-mono"><?= htmlspecialchars($dbName) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Database size</td>
                <td style="text-align: right;"><span class="font-mono"><?= htmlspecialchars($dbSize) ?> MB</span></td>
            </tr>
            <tr>
    <td style="padding: 6px 0; color: var(--text-secondary);">Cache driver</td>
    <td style="text-align: right;">
        <span class="font-mono" style="color: <?= \App\Core\Cache::getDriverName() === 'apcu' ? '#10B981' : (\App\Core\Cache::getDriverName() === 'file' ? '#F59E0B' : '#EF4444') ?>;">
            <?= htmlspecialchars(\App\Core\Cache::getDriverName()) ?>
        </span>
    </td>
</tr>
        </table>
    </div>

    <div class="table-card" style="padding: 20px;">
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Migrations</div>
        <table style="width: 100%; font-size: 13px;">
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Files on disk</td>
                <td style="text-align: right;"><span class="font-mono"><?= count($onDisk) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Applied</td>
                <td style="text-align: right;"><span class="font-mono" style="color: #10B981;"><?= count($applied) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Pending</td>
                <td style="text-align: right;"><span class="font-mono" style="color: <?= count($pending) === 0 ? '#10B981' : '#F59E0B' ?>;"><?= count($pending) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Last applied</td>
                <td style="text-align: right;"><span class="font-mono" style="font-size: 11px;"><?= htmlspecialchars($lastApplied) ?></span></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: var(--text-secondary);">Last DB backup</td>
                <td style="text-align: right;"><span class="font-mono" style="font-size: 11px;"><?= htmlspecialchars($lastBackup) ?></span></td>
            </tr>
        </table>
    </div>
</div>

<!-- Filesystem card -->
<div class="table-card" style="padding: 20px; margin-bottom: 24px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Filesystem Permissions</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Path</th>
                <th>Exists</th>
                <th>Writable</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fsStatus as $label => $status): ?>
            <tr>
                <td class="font-mono" style="font-size: 12px;" title="<?= htmlspecialchars($checks[$label]) ?>"><?= htmlspecialchars($label) ?></td>
                <td>
                    <?php if ($status['exists']): ?>
                        <span class="badge" style="background: rgba(16,185,129,0.15); color: #10B981; font-size: 11px;">Yes</span>
                    <?php else: ?>
                        <span class="badge" style="background: rgba(239,68,68,0.15); color: #EF4444; font-size: 11px;">Missing</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($status['writable']): ?>
                        <span class="badge" style="background: rgba(16,185,129,0.15); color: #10B981; font-size: 11px;">Writable</span>
                    <?php else: ?>
                        <span class="badge" style="background: rgba(239,68,68,0.15); color: #EF4444; font-size: 11px;">Read-only</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Feature availability -->
<div class="table-card" style="padding: 20px;">
    <div style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Available Features</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
        <?php foreach ($features as $name => $ok): ?>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <?php if ($ok): ?>
                    <i class="fa-solid fa-circle-check" style="color: #10B981;"></i>
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark" style="color: #EF4444;"></i>
                <?php endif; ?>
                <span style="color: var(--text-primary); font-family: monospace; font-size: 12px;"><?= htmlspecialchars($name) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>

<?php
$slot = ob_get_clean();
$pageTitle = "System Health - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
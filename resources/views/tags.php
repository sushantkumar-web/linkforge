<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$isSuperAdmin = ($_SESSION['role'] ?? 'user') === 'super_admin';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Tags</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">
            Organize and filter your links. Tags are created automatically when you add them to a link.
        </p>
    </div>
    <a href="<?= $baseURL ?>/" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Create a link
    </a>
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

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Total tags</div>
        <div class="value font-mono"><?= number_format($totalTags) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">In use</div>
        <div class="value font-mono"><?= number_format($usedTags) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label">Unused</div>
        <div class="value font-mono"><?= number_format($unusedTags) ?></div>
    </div>
    <div class="kpi-card">
        <div class="label"><?= $isSuperAdmin ? 'Scope' : 'Your tags' ?></div>
        <div class="value" style="font-size: 16px;">
            <?= $isSuperAdmin ? 'All users' : 'Personal' ?>
        </div>
    </div>
</div>

<?php if (empty($tags)): ?>
    <div class="table-card" style="padding: 60px 20px; text-align: center;">
        <i class="fa-solid fa-tags" style="font-size: 40px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <div style="font-size: 16px; font-weight: 600; margin-bottom: 6px;">No tags yet</div>
        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            Add tags to your links when creating or editing them. They'll show up here automatically.
        </div>
        <a href="<?= $baseURL ?>/" class="btn btn-primary">Create your first link</a>
    </div>
<?php else: ?>
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tag</th>
                    <?php if ($isSuperAdmin): ?>
                        <th>Owner</th>
                    <?php endif; ?>
                    <th style="text-align: right;">Links</th>
                    <th style="text-align: right;">Total clicks</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $t): ?>
                <tr>
                    <td>
                        <a href="<?= $baseURL ?>/?tag=<?= urlencode($t['name']) ?>" style="text-decoration: none;">
                            <span class="badge badge-tag" style="font-size: 12px; padding: 4px 10px;">
                                #<?= htmlspecialchars($t['name']) ?>
                            </span>
                        </a>
                    </td>
                    <?php if ($isSuperAdmin): ?>
                        <td style="font-size: 12px; color: var(--text-muted);">
                            <?= htmlspecialchars($t['owner_email'] ?? 'unknown') ?>
                        </td>
                    <?php endif; ?>
                    <td class="font-mono" style="text-align: right;">
                        <?php if ((int)$t['link_count'] > 0): ?>
                            <a href="<?= $baseURL ?>/?tag=<?= urlencode($t['name']) ?>" style="color: var(--accent); text-decoration: none;">
                                <?= number_format((int)$t['link_count']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="font-mono" style="text-align: right;">
                        <?= number_format((int)$t['total_clicks']) ?>
                    </td>
                    <td style="font-size: 12px; color: var(--text-muted);">
                        <?= date('M j, Y', strtotime($t['created_at'])) ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px;">
                            <a href="<?= $baseURL ?>/?tag=<?= urlencode($t['name']) ?>" class="btn btn-secondary btn-sm">
                                View links
                            </a>
                            <form method="POST" action="<?= $baseURL ?>/tags/delete" style="display: inline;" onsubmit="return confirm('Delete this tag? Links will stay, but the tag association is removed.');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="tag_id" value="<?= (int)$t['id'] ?>">
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

<?php
$slot = ob_get_clean();
$pageTitle = "Tags - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
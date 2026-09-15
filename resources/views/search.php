<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
$queryDisplay = htmlspecialchars($q ?? '');
?>

<div style="margin-bottom: 24px;">
    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
        <a href="<?= $baseURL ?>/" style="color: var(--text-muted); text-decoration: none;">← Back to links</a>
    </div>
    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">
        <?php if (!empty($q)): ?>
            Search results for <span style="color: var(--accent);">"<?= $queryDisplay ?>"</span>
        <?php else: ?>
            Search
        <?php endif; ?>
    </h1>
    <p style="font-size: 13px; color: var(--text-secondary);">
        <?php if (!empty($q)): ?>
            <?= number_format(count($results)) ?> link<?= count($results) === 1 ? '' : 's' ?> found
            <?php if (($_SESSION['role'] ?? 'user') === 'super_admin'): ?>
                <span style="color: var(--text-muted);">· searching across all users (Super Admin)</span>
            <?php endif; ?>
        <?php else: ?>
            Type a query in the topbar to search links, titles, destinations, and tags.
        <?php endif; ?>
    </p>
</div>

<?php if (!empty($q) && empty($results)): ?>
    <div class="table-card" style="padding: 60px 20px; text-align: center;">
        <i class="fa-solid fa-magnifying-glass" style="font-size: 32px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <div style="font-size: 15px; font-weight: 600; margin-bottom: 6px;">No matches for "<?= $queryDisplay ?>"</div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Try searching by short code, title, destination URL, or tag name.
        </div>
    </div>
<?php elseif (!empty($results)): ?>
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Link / Title</th>
                    <th>Destination</th>
                    <th>Clicks</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $l):
                    $isExpired = !empty($l['expires_at']) && strtotime($l['expires_at']) <= time();
                ?>
                <tr>
                    <td>
                        <div>
                            <a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" class="font-mono" style="color: var(--accent); font-weight: 600;">/<?= htmlspecialchars($l['short_code']) ?></a>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($l['title'] ?? 'Untitled') ?></div>
                        <?php if (($_SESSION['role'] ?? 'user') === 'super_admin' && !empty($l['owner_email'])): ?>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                <i class="fa-regular fa-user" style="margin-right: 4px;"></i><?= htmlspecialchars($l['owner_email']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary);">
                        <?= htmlspecialchars($l['destination_url']) ?>
                    </td>
                    <td class="font-mono"><?= number_format((int)$l['clicks']) ?></td>
                    <td>
                        <span class="badge <?= $isExpired ? 'badge-expired' : ($l['status'] === 'active' ? 'badge-active' : 'badge-disabled') ?>">
                            <?= $isExpired ? 'EXPIRED' : htmlspecialchars($l['status']) ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a href="<?= $baseURL ?>/analytics?id=<?= $l['id'] ?>" class="btn btn-secondary btn-sm">Analytics</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
$pageTitle = "Search - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
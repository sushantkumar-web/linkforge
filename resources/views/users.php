<?php
ob_start();
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<!-- LinkForge Modal System CSS -->
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
.lf-modal-backdrop.active {
    display: flex !important;
}
.lf-modal-card {
    background: #16191F;
    border: 1px solid #282C34;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
}
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">Users</h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Manage team members, roles, and account access.</p>
    </div>
    <button type="button" onclick="openModal('createUserModal')" class="btn btn-primary">
        <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Add User
    </button>
</div>

<!-- Users Table Card -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr style="<?= $u['status'] !== 'active' ? 'opacity: 0.6;' : '' ?>">
                <td>
                    <div style="font-weight: 600;"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td>
                    <span class="badge" style="background: <?= $u['role'] === 'super_admin' ? 'rgba(239,68,68,0.15)' : ($u['role'] === 'admin' ? 'rgba(91,92,226,0.15)' : 'rgba(16,185,129,0.15)') ?>; color: <?= $u['role'] === 'super_admin' ? '#EF4444' : ($u['role'] === 'admin' ? '#8B8DF8' : '#10B981') ?>;">
                        <?= strtoupper($u['role']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['status'] === 'active' ? 'badge-active' : 'badge-disabled' ?>">
                        <?= htmlspecialchars($u['status']) ?>
                    </span>
                </td>
                <td style="font-size: 12px; color: var(--text-muted);">
                    <?= !empty($u['last_login_at']) ? date('M j, Y H:i', strtotime($u['last_login_at'])) : 'Never' ?>
                </td>
                <td style="font-size: 12px; color: var(--text-muted);">
                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <!-- Edit Button triggers Modal -->
                        <button type="button" class="btn btn-secondary btn-sm" 
                            onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['role']) ?>', '<?= htmlspecialchars($u['status']) ?>')">
                            Edit
                        </button>
                        
                        <!-- Delete Form -->
                        <?php if ($u['id'] !== $_SESSION['user_id'] && $_SESSION['role'] === 'super_admin'): ?>
                        <form method="POST" action="<?= $baseURL ?>/users/delete" style="display:inline;" onsubmit="return confirm('Delete this user permanently?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Create User -->
<div id="createUserModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Add New User</h3>
        <form method="POST" action="<?= $baseURL ?>/users/store">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" required class="form-input" placeholder="user@example.com">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" required class="form-input" placeholder="Minimum 8 characters">
            </div>

            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-input">
                    <option value="user">User (Own links only)</option>
                    <option value="admin">Admin (Manage links & users)</option>
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <option value="super_admin">Super Admin (Full access)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('createUserModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div id="editUserModal" class="lf-modal-backdrop">
    <div class="lf-modal-card">
        <h3 style="font-size: 16px; margin-bottom: 16px;">Edit User</h3>
        <form method="POST" action="<?= $baseURL ?>/users/update">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="editUserRole" class="form-input">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <option value="super_admin">Super Admin</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="editUserStatus" class="form-input">
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px;">
                <button type="button" onclick="closeModal('editUserModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Backdrop click to close
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) {
        window.closeModal(e.target.id);
    }
});

window.openModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
};

window.closeModal = function(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
};

window.openEditUserModal = function(id, role, status) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserRole').value = role;
    document.getElementById('editUserStatus').value = status;
    window.openModal('editUserModal');
};
</script>

<?php
$slot = ob_get_clean();
$pageTitle = "Users - LinkForge";
require BASE_PATH . '/resources/views/layouts/app.php';
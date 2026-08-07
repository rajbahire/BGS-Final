<?php
// ============================================================
//  admin/manage-hods.php — Manage HODs
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dept  = (int)$_POST['department_id'];
        $pass  = $_POST['password'] ?? 'hod@1234';

        if ($name && $email && $dept) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                setFlash('error', 'Email already exists.');
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,phone) VALUES (?,?,?,'hod',?,?)")
                    ->execute([$name,$email,$hash,$dept,$phone]);
                logActivity($pdo,$user['id'],'add_hod',"Added HOD: $name");
                setFlash('success', "HOD \"$name\" added. Password: $pass");
            }
        } else {
            setFlash('error', 'Name, email and department are required.');
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['name']  ?? '');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $dept   = (int)$_POST['department_id'];
        $active = (int)$_POST['is_active'];

        if (!$email) {
            setFlash('error', 'Email is required.');
        } else {
            // Check duplicate email — exclude current HOD's own record
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                setFlash('error', 'That email is already used by another account.');
            } else {
                $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,department_id=?,is_active=? WHERE id=? AND role='hod'")
                    ->execute([$name,$email,$phone,$dept,$active,$id]);
                logActivity($pdo,$user['id'],'edit_hod',"Updated HOD #$id");
                setFlash('success', 'HOD updated successfully.');
            }
        }
    }

    if ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $pass = 'hod@1234';
        $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='hod'")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        setFlash('success', "Password reset to: $pass");
    }

    if ($action === 'deactivate') {
        $id = (int)$_POST['id'];
        $s  = $pdo->prepare("SELECT name FROM users WHERE id=? AND role='hod'");
        $s->execute([$id]); $row = $s->fetch();
        if ($row) {
            $pdo->prepare("UPDATE users SET is_active=0 WHERE id=? AND role='hod'")->execute([$id]);
            logActivity($pdo,$user['id'],'deactivate_hod',"Admin deactivated HOD: {$row['name']}");
            setFlash('warning', "HOD \"{$row['name']}\" deactivated.");
        }
    }

    if ($action === 'activate') {
        $id = (int)$_POST['id'];
        $s  = $pdo->prepare("SELECT name FROM users WHERE id=? AND role='hod'");
        $s->execute([$id]); $row = $s->fetch();
        if ($row) {
            $pdo->prepare("UPDATE users SET is_active=1 WHERE id=? AND role='hod'")->execute([$id]);
            logActivity($pdo,$user['id'],'activate_hod',"Admin reactivated HOD: {$row['name']}");
            setFlash('success', "HOD \"{$row['name']}\" reactivated.");
        }
    }

    header('Location: manage-hods.php'); exit;
}

$depts = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$hods  = $pdo->query(
    "SELECT u.*, d.name AS dept_name FROM users u
     LEFT JOIN departments d ON d.id=u.department_id
     WHERE u.role='hod' ORDER BY u.name"
)->fetchAll();

// Departments that already have an active HOD (excluded from Add form)
$assignedDeptIds = array_column(
    $pdo->query("SELECT DISTINCT department_id FROM users WHERE role='hod' AND is_active=1 AND department_id IS NOT NULL")->fetchAll(),
    'department_id'
);
$assignedDeptIds = array_map('intval', $assignedDeptIds);

renderHead('Manage HODs');
?>
<div class="app-layout">
<?php renderSidebar('manage-hods','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage HODs', [
    ['label' => 'Home',       'href' => 'dashboard.php'],
    ['label' => 'Manage HODs'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Manage HODs</h1>
            <p>Add and manage Head of Department accounts</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-hod-add')"><?= svgIcon('add') ?> Add HOD</button>
    </div>

        <!-- List -->
        <div class="card">
            <div class="card-header">
                <h3>HODs (<?= count($hods) ?>)</h3>
            </div>
            <?php if ($hods): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($hods as $i => $h): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td>
                            <div class="fw-500"><?= e($h['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($h['phone'] ?? '') ?></div>
                        </td>
                        <td class="text-sm"><?= e($h['email']) ?></td>
                        <td><?= e($h['dept_name'] ?? '—') ?></td>
                        <td><?= $h['is_active'] ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-hod-<?= $h['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button class="btn btn-outline btn-sm"
                                            onclick="return confirmAction('Reset HOD password to hod@1234?')"><?= svgIcon('reset') ?></button>
                                </form>
                                <?php if ($h['is_active']): ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Deactivate this HOD?')">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Activate this HOD?')">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn btn-activate btn-sm"><?= svgIcon('check') ?> Activate</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('manage-hods') ?></div><h3>No HODs added yet</h3></div>
            <?php endif; ?>
        </div>
</div>
</div>
</div>

<?php
// ── Modals (rendered outside the layout so display:none ancestors don't trap them) ──
?>
<!-- Add HOD Modal -->
<div class="modal-backdrop" id="modal-hod-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add HOD</h3></span>
            <button class="modal-close" onclick="closeModal('modal-hod-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="Dr. Full Name">
                </div>
                <div class="form-group">
                    <label>Email <span style="color:red">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="hod@gcea.edu">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" class="form-control" value="hod@1234">
                </div>
                <div class="form-group">
                    <label>Department <span style="color:red">*</span></label>
                    <select name="department_id" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($depts as $d):
                            if (in_array((int)$d['id'], $assignedDeptIds, true)) continue;
                        ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="10-digit number">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-hod-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add HOD</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($hods as $h): ?>
<!-- Edit HOD Modal: hod-<?= $h['id'] ?> -->
<div class="modal-backdrop" id="modal-hod-<?= $h['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit HOD</h3></span>
            <button class="modal-close" onclick="closeModal('modal-hod-<?= $h['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $h['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($h['name']) ?>" placeholder="Dr. Full Name">
                </div>
                <div class="form-group">
                    <label>Email <span style="color:red">*</span></label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= e($h['email']) ?>">
                </div>
                <div class="form-group">
                    <label>Department <span style="color:red">*</span></label>
                    <select name="department_id" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($depts as $d):
                            // Skip departments already assigned to another active HOD
                            // Always show the current HOD's own dept (so it stays selectable)
                            $isOwnDept    = (int)($h['department_id'] ?? 0) === (int)$d['id'];
                            $alreadyTaken = !$isOwnDept && in_array((int)$d['id'], $assignedDeptIds, true);
                            if ($alreadyTaken) continue;
                        ?>
                        <option value="<?= $d['id'] ?>"
                            <?= $isOwnDept ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= e($h['phone'] ?? '') ?>" placeholder="10-digit number">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $h['is_active']?'selected':'' ?>>Active</option>
                        <option value="0" <?= !$h['is_active']?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-hod-<?= $h['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update HOD</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
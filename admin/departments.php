<?php
// ============================================================
//  admin/departments.php — Manage Departments
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']       ?? '');
        $short = strtoupper(trim($_POST['short_name'] ?? ''));
        if ($name && $short) {
            $pdo->prepare("INSERT INTO departments (name, short_name) VALUES (?,?)")
                ->execute([$name, $short]);
            logActivity($pdo, $user['id'], 'add_department', "Added department: $name");
            setFlash('success', "Department \"$name\" added successfully.");
        } else {
            setFlash('error', 'Name and short name are required.');
        }
    }

    if ($action === 'edit') {
        $id    = (int)$_POST['id'];
        $name  = trim($_POST['name']       ?? '');
        $short = strtoupper(trim($_POST['short_name'] ?? ''));
        $active= (int)($_POST['is_active'] ?? 1);
        if ($id && $name && $short) {
            $pdo->prepare("UPDATE departments SET name=?, short_name=?, is_active=? WHERE id=?")
                ->execute([$name, $short, $active, $id]);
            logActivity($pdo, $user['id'], 'edit_department', "Updated department #$id");
            setFlash('success', 'Department updated.');
        } else {
            setFlash('error', 'All fields required.');
        }
    }

    if ($action === 'toggle_active') {
        $id        = (int)$_POST['id'];
        $newStatus = (int)$_POST['new_status'];   // 0 = deactivate, 1 = activate
        $label     = $newStatus ? 'activated' : 'deactivated';
        $pdo->prepare("UPDATE departments SET is_active=? WHERE id=?")->execute([$newStatus, $id]);
        logActivity($pdo, $user['id'], $label.'_department', ucfirst($label)." department #$id");
        setFlash('success', "Department $label successfully.");
    }

    header('Location: departments.php'); exit;
}

// All departments with counts
$depts = $pdo->query(
    "SELECT d.*,
        (SELECT COUNT(*) FROM classes c WHERE c.department_id=d.id) AS class_count,
        (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id AND u.role='teacher') AS teacher_count
     FROM departments d ORDER BY d.name"
)->fetchAll();

renderHead('Departments');
?>
<div class="app-layout">
<?php renderSidebar('departments', 'admin', $user); ?>
<div class="main-content">
<?php renderTopbar('Departments', [
    ['label' => 'Home',       'href' => 'dashboard.php'],
    ['label' => 'Departments'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="page-header page-header-btn">
        <div>
            <h1>Departments</h1>
            <p>Manage all departments of the college</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-dept-add')"><?= svgIcon('add') ?> Add Department</button>
    </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <h3>All Departments (<?= count($depts) ?>)</h3>
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                    <input type="text" class="form-control" style="width:200px"
                           placeholder="Search…" data-search-table="dept-table">
                </div>
            </div>
            <?php if ($depts): ?>
            <div class="table-wrap">
                <table id="dept-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department Name</th>
                            <th>Short</th>
                            <th>Classes</th>
                            <th>Teachers</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($depts as $i => $d): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td class="fw-500"><?= e($d['name']) ?></td>
                        <td><span class="badge badge-expert"><?= e($d['short_name']) ?></span></td>
                        <td><?= (int)$d['class_count'] ?></td>
                        <td><?= (int)$d['teacher_count'] ?></td>
                        <td>
                            <?php if ($d['is_active']): ?>
                            <span class="badge badge-approved">Active</span>
                            <?php else: ?>
                            <span class="badge badge-rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-dept-<?= $d['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <?php if ($d['is_active']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirmAction('Deactivate this department?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="new_status" value="0">
                                    <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline" onsubmit="return confirmAction('Activate this department?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="new_status" value="1">
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
            <div class="empty-state">
                <div class="icon"><?= svgIcon('department') ?></div>
                <h3>No departments yet</h3>
                <p>Add your first department using the button above.</p>
            </div>
            <?php endif; ?>
        </div>
</div>
</div>
</div>

<?php
// ── Modals (rendered outside the layout so display:none ancestors don't trap them) ──
?>
<!-- Add Department Modal -->
<div class="modal-backdrop" id="modal-dept-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Department</h3></span>
            <button class="modal-close" onclick="closeModal('modal-dept-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Department Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           placeholder="e.g. Computer Science & Engineering">
                </div>
                <div class="form-group">
                    <label>Short Name <span style="color:red">*</span></label>
                    <input type="text" name="short_name" class="form-control" required
                           maxlength="20" placeholder="e.g. CSE">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-dept-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Department</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($depts as $d): ?>
<!-- Edit Department Modal: dept-<?= $d['id'] ?> -->
<div class="modal-backdrop" id="modal-dept-<?= $d['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Department</h3></span>
            <button class="modal-close" onclick="closeModal('modal-dept-<?= $d['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Department Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($d['name']) ?>"
                           placeholder="e.g. Computer Science & Engineering">
                </div>
                <div class="form-group">
                    <label>Short Name <span style="color:red">*</span></label>
                    <input type="text" name="short_name" class="form-control" required
                           maxlength="20" value="<?= e($d['short_name']) ?>"
                           placeholder="e.g. CSE">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $d['is_active']?'selected':'' ?>>Active</option>
                        <option value="0" <?= !$d['is_active']?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-dept-<?= $d['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update Department</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
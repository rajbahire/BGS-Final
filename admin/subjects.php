<?php
// ============================================================
//  admin/subjects.php — Manage Subjects
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $classId = (int)$_POST['class_id'];
        $name    = trim($_POST['subject_name'] ?? '');
        $code    = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode    = $_POST['mode'] ?? 'theory';
        if ($classId && $name && $code) {
            $pdo->prepare("INSERT INTO subjects (class_id,subject_name,subject_code,mode) VALUES (?,?,?,?)")
                ->execute([$classId,$name,$code,$mode]);
            logActivity($pdo,$user['id'],'add_subject',"Added subject: $name ($code)");
            setFlash('success',"Subject \"$name\" added.");
        } else {
            setFlash('error','All required fields must be filled.');
        }
    }

    if ($action === 'edit') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['subject_name'] ?? '');
        $code = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode = $_POST['mode'] ?? 'theory';
        $act  = (int)$_POST['is_active'];
        if ($id && $name && $code) {
            $pdo->prepare("UPDATE subjects SET subject_name=?,subject_code=?,mode=?,is_active=? WHERE id=?")
                ->execute([$name,$code,$mode,$act,$id]);
            setFlash('success','Subject updated.');
        }
    }

    if ($action === 'toggle_active') {
        $id        = (int)$_POST['id'];
        $newStatus = (int)$_POST['new_status'];   // 0 = deactivate, 1 = activate
        if ($id) {
            $label = $newStatus ? 'activated' : 'deactivated';
            $pdo->prepare("UPDATE subjects SET is_active=? WHERE id=?")->execute([$newStatus, $id]);
            logActivity($pdo, $user['id'], $label.'_subject', "Admin $label subject #$id");
            setFlash('success', "Subject $label successfully.");
        }
    }

    header('Location: subjects.php' . ($_POST['filter_dept'] ? '?dept='.(int)$_POST['filter_dept'] : ''));
    exit;
}

$filterDept  = (int)($_GET['dept']  ?? 0);
$filterClass = (int)($_GET['class'] ?? 0);

$depts   = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

// Load classes for selected dept (for filter)
$classes = [];
if ($filterDept) {
    $sc = $pdo->prepare("SELECT * FROM classes WHERE department_id=? AND is_active=1 ORDER BY year,semester");
    $sc->execute([$filterDept]); $classes = $sc->fetchAll();
}

// Load subjects
$sql = "SELECT s.*, c.label AS class_label, d.name AS dept_name, d.id AS dept_id
        FROM subjects s
        JOIN classes c ON c.id=s.class_id
        JOIN departments d ON d.id=c.department_id
        WHERE 1=1";
$params = [];
if ($filterDept)  { $sql .= " AND d.id=?";   $params[] = $filterDept; }
if ($filterClass) { $sql .= " AND c.id=?";   $params[] = $filterClass; }
$sql .= " ORDER BY d.name, c.year, c.semester, s.subject_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$subjects = $stmt->fetchAll();

// All classes for the add form
$allClasses = $pdo->query(
    "SELECT c.*, d.name AS dept_name FROM classes c
     JOIN departments d ON d.id=c.department_id
     WHERE c.is_active=1 ORDER BY d.name,c.year,c.semester"
)->fetchAll();

renderHead('Subjects');
?>
<div class="app-layout">
<?php renderSidebar('subjects','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('Subjects', [
    ['label' => 'Home',    'href' => 'dashboard.php'],
    ['label' => 'Subjects'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Subjects</h1>
            <p>Manage subjects and subject codes per class</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-subject-add')"><?= svgIcon('add') ?> Add Subject</button>
    </div>

        <div>
            <!-- Filter bar -->
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body" style="padding:.9rem">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                        <div class="form-group" style="margin:0">
                            <label>Department</label>
                            <select name="dept" class="form-control" style="width:200px" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $filterDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($classes): ?>
                        <div class="form-group" style="margin:0">
                            <label>Class</label>
                            <select name="class" class="form-control" style="width:180px" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <a href="subjects.php" class="btn btn-outline btn-sm" style="padding: 7px 15px;">Clear</a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Subjects (<?= count($subjects) ?>)</h3>
                </div>
                <?php if ($subjects): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>#</th><th>Subject</th><th>Code</th><th>Class</th><th>Mode</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $i => $s): ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td class="fw-500"><?= e($s['subject_name']) ?></td>
                            <td><span class="badge badge-expert"><?= e($s['subject_code']) ?></span></td>
                            <td class="text-sm"><?= e($s['class_label']) ?><br><span class="text-muted text-xs"><?= e($s['dept_name']) ?></span></td>
                            <td><?= modeBadge($s['mode']) ?></td>
                            <td><?= $s['is_active'] ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
                             <td>
                                <div class="d-flex gap-8" style="flex-wrap:wrap">
                                    <button type="button" class="btn btn-outline btn-sm"
                                            onclick="openModal('modal-subject-<?= $s['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                    <?php if ($s['is_active']): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirmAction('Deactivate this subject?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="new_status" value="0">
                                        <input type="hidden" name="filter_dept" value="<?= $filterDept ?>">
                                        <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirmAction('Activate this subject?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="new_status" value="1">
                                        <input type="hidden" name="filter_dept" value="<?= $filterDept ?>">
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
                <div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No subjects found</h3><p>Add subjects using the button above.</p></div>
                <?php endif; ?>
            </div>
        </div>
</div>
</div>
</div>

<?php
// ── Modals (rendered outside the layout so display:none ancestors don't trap them) ──
?>
<!-- Add Subject Modal -->
<div class="modal-backdrop" id="modal-subject-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Subject</h3></span>
            <button class="modal-close" onclick="closeModal('modal-subject-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="filter_dept" value="<?= $filterDept ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Class <span style="color:red">*</span></label>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Select Class —</option>
                        <?php
                        $currentDept = '';
                        foreach ($allClasses as $c):
                            if ($c['dept_name'] !== $currentDept):
                                if ($currentDept) echo '</optgroup>';
                                $currentDept = $c['dept_name'];
                                echo '<optgroup label="' . e($c['dept_name']) . '">';
                            endif;
                        ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['label']) ?></option>
                        <?php endforeach; if ($currentDept) echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subject Name <span style="color:red">*</span></label>
                    <input type="text" name="subject_name" class="form-control" required
                           placeholder="e.g. Data Structures">
                </div>

                <div class="form-group">
                    <label>Subject Code <span style="color:red">*</span></label>
                    <input type="text" name="subject_code" class="form-control" required
                           style="text-transform:uppercase" placeholder="e.g. CS301">
                </div>

                <div class="form-group">
                    <label>Mode <span style="color:red">*</span></label>
                    <select name="mode" class="form-control" required>
                        <option value="">— Select Mode —</option>
                        <option value="theory">Theory</option>
                        <option value="practical">Practical</option>
                        <option value="theory & practical">Theory & Practical</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-subject-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($subjects as $s): ?>
<!-- Edit Subject Modal: subject-<?= $s['id'] ?> -->
<div class="modal-backdrop" id="modal-subject-<?= $s['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Subject</h3></span>
            <button class="modal-close" onclick="closeModal('modal-subject-<?= $s['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="filter_dept" value="<?= $filterDept ?>">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="text-muted">Class</label>
                    <input type="text" class="form-control" disabled
                           value="<?= e($s['class_label'] . ' — ' . $s['dept_name']) ?>">
                </div>

                <div class="form-group">
                    <label>Subject Name <span style="color:red">*</span></label>
                    <input type="text" name="subject_name" class="form-control" required
                           value="<?= e($s['subject_name']) ?>" placeholder="e.g. Data Structures">
                </div>

                <div class="form-group">
                    <label>Subject Code <span style="color:red">*</span></label>
                    <input type="text" name="subject_code" class="form-control" required
                           style="text-transform:uppercase"
                           value="<?= e($s['subject_code']) ?>" placeholder="e.g. CS301">
                </div>

                <div class="form-group">
                    <label>Mode <span style="color:red">*</span></label>
                    <select name="mode" class="form-control" required>
                        <option value="theory"    <?= $s['mode']==='theory'   ?'selected':'' ?>>Theory</option>
                        <option value="practical" <?= $s['mode']==='practical'      ?'selected':'' ?>>Practical</option>
                        <option value="theory & practical" <?= $s['mode']==='theory & practical' ?'selected':'' ?>>Theory & Practical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $s['is_active']?'selected':'' ?>>Active</option>
                        <option value="0" <?= !$s['is_active']?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-subject-<?= $s['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update Subject</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
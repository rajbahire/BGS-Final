<?php
// ============================================================
//  hod/subjects.php — HOD: Manage Subjects
//  Scoped to HOD's own department classes only
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// ── Helper: verify a class belongs to this HOD's dept ────────
function classOwnedByHod(PDO $pdo, int $classId, int $deptId): bool {
    $s = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
    $s->execute([$classId, $deptId]);
    return (bool)$s->fetch();
}

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add subject ──────────────────────────────────────────
    if ($action === 'add') {
        $classId = (int)$_POST['class_id'];
        $name    = trim($_POST['subject_name'] ?? '');
        $code    = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode    = $_POST['mode'] ?? 'theory';

        if ($classId && $name && $code) {
            if (!classOwnedByHod($pdo, $classId, $deptId)) {
                setFlash('error', 'Access denied — class does not belong to your department.');
            } else {
                try {
                    $pdo->prepare("INSERT INTO subjects (class_id,subject_name,subject_code,mode) VALUES (?,?,?,?)")
                        ->execute([$classId, $name, $code, $mode]);
                    logActivity($pdo, $user['id'], 'add_subject', "HOD added subject: $name ($code)");
                    setFlash('success', "Subject \"$name\" added successfully.");
                } catch (PDOException $e) {
                    setFlash('error', 'Failed to add subject. Please try again.');
                }
            }
        } else {
            setFlash('error', 'All required fields must be filled.');
        }
    }

    // ── Edit subject ─────────────────────────────────────────
    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['subject_name'] ?? '');
        $code   = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode   = $_POST['mode'] ?? 'theory';
        $active = (int)$_POST['is_active'];

        if ($id && $name && $code) {
            // Verify subject belongs to a class in this HOD's dept
            $verify = $pdo->prepare(
                "SELECT s.id FROM subjects s
                 JOIN classes c ON c.id=s.class_id
                 WHERE s.id=? AND c.department_id=?"
            );
            $verify->execute([$id, $deptId]);
            if ($verify->fetch()) {
                $pdo->prepare("UPDATE subjects SET subject_name=?,subject_code=?,mode=?,is_active=? WHERE id=?")
                    ->execute([$name, $code, $mode, $active, $id]);
                logActivity($pdo, $user['id'], 'edit_subject', "HOD updated subject ID $id: $name ($code)");
                setFlash('success', 'Subject updated successfully.');
            } else {
                setFlash('error', 'Access denied.');
            }
        } else {
            setFlash('error', 'All required fields must be filled.');
        }
    }

    // ── Toggle Active (deactivate / activate) ──────────────────
    if ($action === 'toggle_active') {
        $id        = (int)$_POST['id'];
        $newStatus = (int)$_POST['new_status'];   // 0 = deactivate, 1 = activate
        if ($id) {
            $verify = $pdo->prepare(
                "SELECT s.id FROM subjects s
                 JOIN classes c ON c.id=s.class_id
                 WHERE s.id=? AND c.department_id=?"
            );
            $verify->execute([$id, $deptId]);
            if ($verify->fetch()) {
                $label = $newStatus ? 'activated' : 'deactivated';
                $pdo->prepare("UPDATE subjects SET is_active=? WHERE id=?")->execute([$newStatus, $id]);
                logActivity($pdo, $user['id'], $label.'_subject', "HOD $label subject ID $id");
                setFlash('success', "Subject $label successfully.");
            } else {
                setFlash('error', 'Access denied.');
            }
        }
    }

    $filterClass = (int)($_POST['filter_class'] ?? 0);
    header('Location: subjects.php' . ($filterClass ? "?class=$filterClass" : '')); exit;
}

$filterClass = (int)($_GET['class'] ?? 0);

// ── All classes for this dept (for filter + add form) ─────────
$deptClasses = $pdo->prepare(
    "SELECT * FROM classes WHERE department_id=? AND is_active=1 ORDER BY year, semester"
);
$deptClasses->execute([$deptId]);
$deptClasses = $deptClasses->fetchAll();

// ── Load subjects ─────────────────────────────────────────────
$sql    = "SELECT s.*, c.label AS class_label, c.year, c.semester
           FROM subjects s
           JOIN classes c ON c.id=s.class_id
           WHERE c.department_id=?";
$params = [$deptId];
if ($filterClass) { $sql .= " AND s.class_id=?"; $params[] = $filterClass; }
$sql .= " ORDER BY c.year, c.semester, s.subject_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$subjects = $stmt->fetchAll();

$deptName = $user['dept_name'] ?: 'Your Department';

renderHead('HOD — Subjects');
?>
<div class="app-layout">
<?php renderSidebar('subjects','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Subjects', [
    ['label' => 'Home',             'href' => 'dashboard.php'],
    ['label' => 'Manage Subjects'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Subjects</h1>
            <p>Manage subjects for <strong><?= e($deptName) ?></strong> classes</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-subject-add')"><?= svgIcon('add') ?> Add Subject</button>
    </div>

        <!-- Filter + Table -->
        <div>
            <!-- Filter bar -->
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body" style="padding:.9rem">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                        <div class="form-group" style="margin:0;flex:1;min-width:200px">
                            <label>Filter by Class</label>
                            <select name="class" class="form-control" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($deptClasses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($filterClass): ?>
                        <a href="subjects.php" class="btn btn-outline">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="card">
                <div class="card-header">
                        <h3>Subjects (<?= count($subjects) ?>)</h3>
                        <?php if ($filterClass): ?>
                        <span class="badge badge-expert">Filtered</span>
                        <?php endif; ?>
                </div>
                <?php if ($subjects): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Class</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $i => $s): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-500"><?= e($s['subject_name']) ?></td>
                            <td><span class="badge badge-expert"><?= e($s['subject_code']) ?></span></td>
                            <td class="text-sm"><?= e($s['class_label']) ?></td>
                            <td><?= modeBadge($s['mode']) ?></td>
                            <td>
                                <?= $s['is_active']
                                    ? '<span class="badge badge-approved">Active</span>'
                                    : '<span class="badge badge-rejected">Inactive</span>' ?>
                            </td>
                            <td>
                                <div class="d-flex gap-8">
                                    <button type="button" class="btn btn-outline btn-sm"
                                            onclick="openModal('modal-subject-<?= $s['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                    <?php if ($s['is_active']): ?>
                                    <form method="POST" style="margin:0"
                                          onsubmit="return confirmAction('Deactivate this subject?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="new_status" value="0">
                                        <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                                        <button type="submit" class="btn btn-sm btn-delete"><?= svgIcon('delete') ?> Deactivate</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="margin:0"
                                          onsubmit="return confirmAction('Activate this subject?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="new_status" value="1">
                                        <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                                        <button type="submit" class="btn btn-sm btn-activate"><?= svgIcon('check') ?> Activate</button>
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
                    <div class="icon"><?= svgIcon('subjects') ?></div>
                    <h3>No subjects found</h3>
                    <p><?= $filterClass ? 'No subjects in this class.' : 'Add a subject using the button above.' ?></p>
                </div>
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
            <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Class <span style="color:red">*</span></label>
                    <?php if (empty($deptClasses)): ?>
                    <div class="alert alert-warning" style="font-size:.85rem;margin:0">
                        <?= svgIcon('warning') ?> No active classes found. <a href="classes.php">Add a class first.</a>
                    </div>
                    <?php else: ?>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Select Class —</option>
                        <?php foreach ($deptClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
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
                <button type="submit" class="btn btn-primary"
                        <?= empty($deptClasses) ? 'disabled' : '' ?>>Add Subject</button>
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
            <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="text-muted">Class</label>
                    <input type="text" class="form-control" disabled
                           value="<?= e($s['class_label'] ?? '—') ?>"
                           style="background:var(--bg);color:var(--light)">
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
                        <option value="practical" <?= $s['mode']==='practical'?'selected':'' ?>>Practical</option>
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
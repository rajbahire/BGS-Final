<?php
// ============================================================
//  hod/classes.php — HOD: Manage Classes (Year + Semester)
//  Scoped to HOD's own department only
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add ──────────────────────────────────────────────────
    if ($action === 'add') {
        $year  = (int)$_POST['year'];
        $sem   = (int)$_POST['semester'];
        $label = trim($_POST['label'] ?? '');
        if ($year && $sem && $label) {
            try {
                $pdo->prepare("INSERT INTO classes (department_id,year,semester,label) VALUES (?,?,?,?)")
                    ->execute([$deptId, $year, $sem, $label]);
                logActivity($pdo, $user['id'], 'add_class', "HOD added class: $label");
                setFlash('success', "Class \"$label\" added successfully.");
            } catch (PDOException $e) {
                setFlash('error', 'A class for this year and semester already exists in your department.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
    }

    // ── Edit ─────────────────────────────────────────────────
    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $label  = trim($_POST['label'] ?? '');
        $year   = (int)$_POST['year'];
        $sem    = (int)$_POST['semester'];
        $active = (int)$_POST['is_active'];
        if ($id && $label && $year && $sem) {
            // Verify class belongs to this HOD's department
            $check = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            if ($check->fetch()) {
                try {
                    $pdo->prepare("UPDATE classes SET label=?, year=?, semester=?, is_active=? WHERE id=? AND department_id=?")
                        ->execute([$label, $year, $sem, $active, $id, $deptId]);
                    logActivity($pdo, $user['id'], 'edit_class', "HOD updated class ID $id: $label");
                    setFlash('success', 'Class updated successfully.');
                } catch (PDOException $e) {
                    setFlash('error', 'A class with that year/semester already exists in your department.');
                }
            } else {
                setFlash('error', 'Access denied.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
    }

    // ── Toggle Active (deactivate / activate) ──────────────────
    if ($action === 'toggle_active') {
        $id        = (int)$_POST['id'];
        $newStatus = (int)$_POST['new_status'];   // 0 = deactivate, 1 = activate
        if ($id) {
            $check = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            if ($check->fetch()) {
                $label = $newStatus ? 'activated' : 'deactivated';
                $pdo->prepare("UPDATE classes SET is_active=? WHERE id=? AND department_id=?")
                    ->execute([$newStatus, $id, $deptId]);
                logActivity($pdo, $user['id'], $label.'_class', "HOD $label class ID $id");
                setFlash('success', "Class $label successfully.");
            } else {
                setFlash('error', 'Access denied.');
            }
        }
    }

    header('Location: classes.php'); exit;
}

// ── Load classes for this dept ────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT c.*,
        (SELECT COUNT(*) FROM subjects s WHERE s.class_id=c.id) AS subject_count
     FROM classes c
     WHERE c.department_id=?
     ORDER BY c.year, c.semester"
);
$stmt->execute([$deptId]);
$classes = $stmt->fetchAll();

// ── Fetch dept short_name for label generation ───────────────
$deptRow  = $pdo->prepare("SELECT name, short_name FROM departments WHERE id=? LIMIT 1");
$deptRow->execute([$deptId]);
$deptRow  = $deptRow->fetch();
$deptName  = $deptRow['name']      ?? ($user['dept_name'] ?: 'Your Department');
$deptShort = $deptRow['short_name'] ?? '';

// ── Year/Semester limits based on department ─────────────────
$maxYear = ($deptShort === 'MCA') ? 2 : 4;
$maxSem  = ($deptShort === 'MCA') ? 4 : 8;

// ── Build taken year+semester pairs for this dept (for JS filter) ─
$takenPairs = array_map(
    fn($c) => [(int)$c['year'], (int)$c['semester']],
    $classes
);

renderHead('HOD — Classes');
?>
<div class="app-layout">
<?php renderSidebar('classes','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Classes', [
    ['label' => 'Home',           'href' => 'dashboard.php'],
    ['label' => 'Manage Classes'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
        <h1>Classes</h1>
        <p>Manage year & semester classes for <strong><?= e($deptName) ?></strong></p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-class-add')"><?= svgIcon('add') ?> Add Class</button>
    </div>

        <!-- Class List Table -->
        <div class="card">
            <div class="card-header">
                <h3>Classes (<?= count($classes) ?>)</h3>
            </div>
            <?php if ($classes): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Classes</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($classes as $i => $c): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-500"><?= e($c['label']) ?></td>
                        <td><?= (int)$c['year'] ?></td>
                        <td>Sem <?= (int)$c['semester'] ?></td>
                        <td><?= (int)$c['subject_count'] ?></td>
                        <td>
                            <?= $c['is_active']
                                ? '<span class="badge badge-approved">Active</span>'
                                : '<span class="badge badge-rejected">Inactive</span>' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-8">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-class-<?= $c['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <?php if ($c['is_active']): ?>
                                <form method="POST" style="margin:0"
                                      onsubmit="return confirmAction('Deactivate this class?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="new_status" value="0">
                                    <button type="submit" class="btn btn-sm btn-delete"><?= svgIcon('delete') ?> Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="margin:0"
                                      onsubmit="return confirmAction('Activate this class?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="new_status" value="1">
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
                <div class="icon"><?= svgIcon('classes') ?></div>
                <h3>No classes yet</h3>
                <p>Add a class using the button above.</p>
            </div>
            <?php endif; ?>
        </div>
</div>
</div>
</div>

<?php
// ── Modals (rendered outside the layout so display:none ancestors don't trap them) ──
?>
<!-- Add Class Modal -->
<div class="modal-backdrop" id="modal-class-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Class</h3></span>
            <button class="modal-close" onclick="closeModal('modal-class-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <!-- Department (read-only display) -->
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" value="<?= e($deptName) ?>" disabled
                           style="background:var(--bg);color:var(--light)">
                </div>

                <div class="form-group">
                    <label>Year <span style="color:red">*</span></label>
                    <select name="year" id="sel-year" class="form-control" required
                            onchange="filterSemesters(); autoLabel();">
                        <option value="">— Select Year —</option>
                        <option value="1">1st Year (FY)</option>
                        <option value="2">2nd Year (SY)</option>
                        <?php if ($maxYear >= 3): ?>
                        <option value="3">3rd Year (TY)</option>
                        <?php endif; ?>
                        <?php if ($maxYear >= 4): ?>
                        <option value="4">4th Year (LY)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester <span style="color:red">*</span></label>
                    <select name="semester" id="sel-sem" class="form-control" required
                            onchange="autoLabel()">
                        <option value="">— Select Year first —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Class Label <span style="color:red">*</span></label>
                    <input type="text" name="label" id="lbl-input" class="form-control" required
                           placeholder="e.g. FY <?= e($deptShort ?: 'DEPT') ?> Sem 1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-class-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Class</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($classes as $c): ?>
<!-- Edit Class Modal: class-<?= $c['id'] ?> -->
<div class="modal-backdrop" id="modal-class-<?= $c['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Class</h3></span>
            <button class="modal-close" onclick="closeModal('modal-class-<?= $c['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-body">
                <!-- Department (read-only display) -->
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" value="<?= e($deptName) ?>" disabled
                           style="background:var(--bg);color:var(--light)">
                </div>

                <div class="form-group">
                    <label>Year <span style="color:red">*</span></label>
                    <select name="year" class="form-control" required
                            onchange="autoLabel()">
                        <option value="1" <?= $c['year']==1?'selected':'' ?>>1st Year (FY)</option>
                        <option value="2" <?= $c['year']==2?'selected':'' ?>>2nd Year (SY)</option>
                        <?php if ($maxYear >= 3): ?>
                        <option value="3" <?= $c['year']==3?'selected':'' ?>>3rd Year (TY)</option>
                        <?php endif; ?>
                        <?php if ($maxYear >= 4): ?>
                        <option value="4" <?= $c['year']==4?'selected':'' ?>>4th Year (LY)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester <span style="color:red">*</span></label>
                    <select name="semester" class="form-control" required
                            onchange="autoLabel()">
                        <?php for ($s = 1; $s <= $maxSem; $s++): ?>
                        <option value="<?= $s ?>" <?= $c['semester']==$s?'selected':'' ?>>Semester <?= $s ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Class Label <span style="color:red">*</span></label>
                    <input type="text" name="label" class="form-control" required
                           value="<?= e($c['label']) ?>"
                           placeholder="e.g. FY <?= e($deptShort ?: 'DEPT') ?> Sem 1">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $c['is_active']?'selected':'' ?>>Active</option>
                        <option value="0" <?= !$c['is_active']?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-class-<?= $c['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update Class</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
<script>
const deptShort  = '<?= e($deptShort) ?>';
const yLabels    = {1:'FY',2:'SY',3:'TY',4:'LY'};
const takenPairs = <?= json_encode($takenPairs) ?>; // [[year,sem], ...] already taken
const maxSem     = <?= (int)$maxSem ?>;

function filterSemesters() {
    const year = document.getElementById('sel-year');
    const sem  = document.getElementById('sel-sem');
    if (!year || !sem) return;

    const yearV = parseInt(year.value) || 0;

    if (!yearV) {
        sem.innerHTML = '<option value="">— Select Year first —</option>';
        return;
    }

    // Each year maps to exactly 2 semesters: Year Y → Sem (2Y-1) and (2Y)
    const yearSems = [yearV * 2 - 1, yearV * 2].filter(s => s <= maxSem);

    // Collect taken semesters for this year
    const taken = new Set();
    takenPairs.forEach(([y, s]) => { if (y === yearV) taken.add(s); });

    const prevVal = sem.value;
    sem.innerHTML = '<option value="">— Select Semester —</option>';
    let added = 0;
    yearSems.forEach(s => {
        if (!taken.has(s)) {
            const o = document.createElement('option');
            o.value = s;
            o.textContent = 'Semester ' + s;
            if (parseInt(prevVal) === s) o.selected = true;
            sem.appendChild(o);
            added++;
        }
    });

    if (added === 0) {
        sem.innerHTML = '<option value="">All semesters taken for this year</option>';
    }
}

// Auto-fill the class label for whichever modal is currently open.
// Works for both the Add modal (#sel-year / #sel-sem) and any per-row
// Edit modal (year/semester selects inside the same open form).
function autoLabel() {
    const form = document.querySelector('.modal-backdrop.open form');
    if (!form) return;
    const year = form.querySelector('[name="year"]');
    const sem  = form.querySelector('[name="semester"]');
    const lbl  = form.querySelector('[name="label"]');
    if (!year || !sem || !lbl) return;
    const yL = yLabels[year.value] || '';
    if (yL && sem.value) {
        lbl.value = yL + ' ' + deptShort + ' Sem ' + sem.value;
    }
}
</script>
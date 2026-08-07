<?php
// ============================================================
//  admin/classes.php — Manage Classes (Year + Semester)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $dept   = (int)$_POST['department_id'];
        $year   = (int)$_POST['year'];
        $sem    = (int)$_POST['semester'];
        $label  = trim($_POST['label'] ?? '');
        if ($dept && $year && $sem && $label) {
            try {
                $pdo->prepare("INSERT INTO classes (department_id,year,semester,label) VALUES (?,?,?,?)")
                    ->execute([$dept, $year, $sem, $label]);
                logActivity($pdo, $user['id'], 'add_class', "Added class: $label");
                setFlash('success', "Class \"$label\" added.");
            } catch (PDOException $e) {
                setFlash('error', 'Class already exists for this department/year/semester.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $label  = trim($_POST['label'] ?? '');
        $year   = (int)$_POST['year'];
        $sem    = (int)$_POST['semester'];
        $active = (int)$_POST['is_active'];
        if ($id && $label && $year && $sem) {
            try {
                $pdo->prepare("UPDATE classes SET label=?, year=?, semester=?, is_active=? WHERE id=?")
                    ->execute([$label, $year, $sem, $active, $id]);
                logActivity($pdo, $user['id'], 'edit_class', "Updated class: $label");
                setFlash('success', 'Class updated.');
            } catch (PDOException $e) {
                setFlash('error', 'A class with that year/semester already exists for this department.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
    }

    if ($action === 'toggle_active') {
        $id        = (int)$_POST['id'];
        $newStatus = (int)$_POST['new_status'];   // 0 = deactivate, 1 = activate
        if ($id) {
            $label = $newStatus ? 'activated' : 'deactivated';
            $pdo->prepare("UPDATE classes SET is_active=? WHERE id=?")->execute([$newStatus, $id]);
            logActivity($pdo, $user['id'], $label.'_class', "Admin $label class #$id");
            setFlash('success', "Class $label successfully.");
        }
    }

    header('Location: classes.php' . ($_POST['dept_filter'] ? '?dept='.(int)$_POST['dept_filter'] : ''));
    exit;
}

$filterDept = (int)($_GET['dept'] ?? 0);
$depts = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$sql    = "SELECT c.*, d.name AS dept_name, d.short_name AS dept_short,
            (SELECT COUNT(*) FROM subjects s WHERE s.class_id=c.id) AS subject_count
           FROM classes c JOIN departments d ON d.id=c.department_id WHERE 1=1";
$params = [];
if ($filterDept) { $sql .= " AND c.department_id=?"; $params[] = $filterDept; }

// Build a map of taken year+semester combinations per department for the JS filter
$takenRaw = $pdo->query("SELECT department_id, year, semester FROM classes")->fetchAll();
$takenMap = []; // [dept_id => [[year,sem], ...]]
foreach ($takenRaw as $t) {
    $takenMap[(int)$t['department_id']][] = [(int)$t['year'], (int)$t['semester']];
}

$sql .= " ORDER BY d.name, c.year, c.semester";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$classes = $stmt->fetchAll();

renderHead('Classes');
?>
<div class="app-layout">
<?php renderSidebar('classes','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('Classes', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => 'Classes'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Classes</h1>
            <p>Manage year and semester combinations per department</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-class-add')"><?= svgIcon('add') ?> Add Class</button>
    </div>

        <div>
            <!-- Dept filter -->
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body" style="padding:.9rem">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end">
                        <div class="form-group" style="margin:0;flex:1">
                            <label>Filter by Department</label>
                            <select name="dept" class="form-control" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $filterDept==$d['id']?'selected':'' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a href="classes.php" class="btn btn-outline btn-sm" style="padding: 7px 15px;">Clear</a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Classes (<?= count($classes) ?>)</h3>
                </div>
                <?php if ($classes): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Classes</th><th>Department</th><th>Year</th><th>Sem</th><th>Subjects</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($classes as $i => $c): ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td class="fw-500"><?= e($c['label']) ?></td>
                            <td class="text-sm"><?= e($c['dept_name']) ?></td>
                            <td><?= (int)$c['year'] ?></td>
                            <td><?= (int)$c['semester'] ?></td>
                            <td><?= (int)$c['subject_count'] ?></td>
                            <td><?= $c['is_active'] ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-class-<?= $c['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <?php if ($c['is_active']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirmAction('Deactivate this class?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="new_status" value="0">
                                    <input type="hidden" name="dept_filter" value="<?= $filterDept ?>">
                                    <button type="submit" class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline" onsubmit="return confirmAction('Activate this class?')">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="new_status" value="1">
                                    <input type="hidden" name="dept_filter" value="<?= $filterDept ?>">
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
                <div class="empty-state"><div class="icon"><?= svgIcon('classes') ?></div><h3>No classes found</h3><p>Add a class using the button above.</p></div>
                <?php endif; ?>
            </div>
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
            <input type="hidden" name="dept_filter" value="<?= $filterDept ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Department <span style="color:red">*</span></label>
                    <select name="department_id" id="sel-dept" class="form-control" required
                            onchange="filterYears(); filterSemesters(); autoLabel();">
                        <option value="">— Select —</option>
                        <?php foreach ($depts as $d):
                            $maxYear = ($d['short_name'] === 'MCA') ? 2 : 4;
                            $maxSem  = ($d['short_name'] === 'MCA') ? 4 : 8;
                        ?>
                        <option value="<?= $d['id'] ?>" data-short="<?= e($d['short_name']) ?>"
                                data-max-year="<?= $maxYear ?>" data-max-sem="<?= $maxSem ?>"
                                <?= $filterDept==$d['id']?'selected':'' ?>>
                            <?= e($d['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year <span style="color:red">*</span></label>
                    <select name="year" id="sel-year" class="form-control" required
                            onchange="filterSemesters(); autoLabel();">
                        <option value="">— Select —</option>
                        <option value="1">1st Year (FY)</option>
                        <option value="2">2nd Year (SY)</option>
                        <option value="3">3rd Year (TY)</option>
                        <option value="4">4th Year (LY)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester <span style="color:red">*</span></label>
                    <select name="semester" id="sel-sem" class="form-control" required
                            onchange="autoLabel()">
                        <option value="">— Select Dept & Year first —</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Label <span style="color:red">*</span></label>
                    <input type="text" name="label" id="label-input" class="form-control" required
                           placeholder="e.g. Year-Department-Semester">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-class-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Class</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($classes as $c):
    $rowMaxYear = ($c['dept_short'] === 'MCA') ? 2 : 4;
    $rowMaxSem  = ($c['dept_short'] === 'MCA') ? 4 : 8;
?>
<!-- Edit Class Modal: class-<?= $c['id'] ?> -->
<div class="modal-backdrop" id="modal-class-<?= $c['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Class</h3></span>
            <button class="modal-close" onclick="closeModal('modal-class-<?= $c['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST" data-dept-short="<?= e($c['dept_short']) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="dept_filter" value="<?= $filterDept ?>">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="text-muted">Department</label>
                    <input type="text" class="form-control" disabled
                           value="<?= e($c['dept_name'] . ' (' . $c['dept_short'] . ')') ?>">
                </div>
                <div class="form-group">
                    <label>Year <span style="color:red">*</span></label>
                    <select name="year" id="sel-year-edit-<?= $c['id'] ?>" class="form-control" required
                            onchange="autoLabel()">
                        <option value="1" <?= $c['year']==1?'selected':'' ?>>1st Year (FY)</option>
                        <option value="2" <?= $c['year']==2?'selected':'' ?>>2nd Year (SY)</option>
                        <?php if ($rowMaxYear >= 3): ?>
                        <option value="3" <?= $c['year']==3?'selected':'' ?>>3rd Year (TY)</option>
                        <?php endif; ?>
                        <?php if ($rowMaxYear >= 4): ?>
                        <option value="4" <?= $c['year']==4?'selected':'' ?>>4th Year (LY)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester <span style="color:red">*</span></label>
                    <select name="semester" id="sel-sem-edit-<?= $c['id'] ?>" class="form-control" required
                            onchange="autoLabel()">
                        <?php for ($s = 1; $s <= $rowMaxSem; $s++): ?>
                        <option value="<?= $s ?>" <?= $c['semester']==$s?'selected':'' ?>>Semester <?= $s ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Label <span style="color:red">*</span></label>
                    <input type="text" name="label" id="label-input-<?= $c['id'] ?>" class="form-control" required
                           value="<?= e($c['label']) ?>" placeholder="e.g. Year-Department-Semester">
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
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
<script>
const yearLabels = {1:'FY',2:'SY',3:'TY',4:'LY'};
const takenMap   = <?= json_encode($takenMap) ?>; // {deptId: [[year,sem],...], ...}

function filterYears() {
    const dept  = document.getElementById('sel-dept');
    const year  = document.getElementById('sel-year');
    if (!dept || !year) return;
    const opt   = dept.selectedOptions[0];
    const maxY  = parseInt(opt?.dataset.maxYear || '4');
    const allYearOpts = year.querySelectorAll('option[value]');
    allYearOpts.forEach(o => {
        const v = parseInt(o.value);
        if (!v) return; // skip placeholder
        o.hidden = (v > maxY);
        if (v > maxY && year.value == v) year.value = '';
    });
}

function filterSemesters() {
    const dept  = document.getElementById('sel-dept');
    const year  = document.getElementById('sel-year');
    const sem   = document.getElementById('sel-sem');
    if (!dept || !year || !sem) return;

    const deptId = parseInt(dept.value) || 0;
    const yearV  = parseInt(year.value) || 0;
    const opt    = dept.selectedOptions[0];
    const maxSem = parseInt(opt?.dataset.maxSem || '8');

    if (!deptId || !yearV) {
        sem.innerHTML = '<option value="">— Select Dept & Year first —</option>';
        return;
    }

    // Each year maps to exactly 2 semesters: Year Y → Sem (2Y-1) and (2Y)
    const yearSems = [yearV * 2 - 1, yearV * 2].filter(s => s <= maxSem);

    // Semesters already taken for this dept+year
    const taken = new Set();
    if (deptId && takenMap[deptId]) {
        takenMap[deptId].forEach(([y, s]) => { if (y === yearV) taken.add(s); });
    }

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
// Works for both the Add modal (dept select present → use its data-short)
// and any Edit modal (data-dept-short on the form).
function autoLabel(){
    const form = document.querySelector('.modal-backdrop.open form');
    if (!form) return;
    const year = form.querySelector('[name="year"]');
    const sem  = form.querySelector('[name="semester"]');
    const lbl  = form.querySelector('[name="label"]');
    if (!year || !sem || !lbl) return;

    let short = '';
    const deptSel = form.querySelector('[name="department_id"]');
    if (deptSel && deptSel.selectedOptions && deptSel.selectedOptions[0]) {
        short = deptSel.selectedOptions[0].getAttribute('data-short') || '';
    } else {
        short = form.getAttribute('data-dept-short') || '';
    }

    const yLbl = yearLabels[year.value] || '';
    if (short && yLbl && sem.value) {
        lbl.value = yLbl + ' ' + short + ' Sem ' + sem.value;
    }
}
</script>

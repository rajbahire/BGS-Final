<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)$_POST['teacher_id'];
        $day       = (int)$_POST['day_of_week'];
        $slot      = trim($_POST['time_slot'] ?? '');
        $mode      = $_POST['mode'] ?? 'theory';
        $acadYear  = trim($_POST['academic_year'] ?? date('Y').'-'.(date('y')+1));
        if ($classId && $subjectId && $teacherId && $day && $slot) {
            $pdo->prepare("INSERT INTO timetable (department_id,class_id,subject_id,teacher_id,day_of_week,time_slot,mode,academic_year) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$deptId,$classId,$subjectId,$teacherId,$day,$slot,$mode,$acadYear]);
            logActivity($pdo,$user['id'],'add_timetable',"Added timetable entry");
            setFlash('success','Timetable entry added.');
        } else { setFlash('error','All fields are required.'); }
    }
    if ($action === 'delete') {
        $id=(int)$_POST['entry_id'];
        $pdo->prepare("DELETE FROM timetable WHERE id=? AND department_id=?")->execute([$id,$deptId]);
        setFlash('success','Entry removed.');
    }
    header('Location: timetable.php?class='.($_POST['filter_class']??'')); exit;
}

$filterClass = (int)($_GET['class'] ?? 0);
$classes     = $pdo->prepare("SELECT * FROM classes WHERE department_id=? AND is_active=1 ORDER BY year,semester"); $classes->execute([$deptId]); $classes=$classes->fetchAll();
$subjects    = [];
$teachers    = $pdo->prepare("SELECT * FROM users WHERE role='teacher' AND department_id=? AND is_active=1 ORDER BY name"); $teachers->execute([$deptId]); $teachers=$teachers->fetchAll();

$entries = [];
if ($filterClass) {
    $subjects = $pdo->prepare("SELECT * FROM subjects WHERE class_id=? AND is_active=1 ORDER BY subject_name"); $subjects->execute([$filterClass]); $subjects=$subjects->fetchAll();
    $entries  = $pdo->prepare("SELECT t.*,s.subject_name,s.subject_code,u.name AS teacher_name,c.label AS class_label FROM timetable t JOIN subjects s ON s.id=t.subject_id JOIN users u ON u.id=t.teacher_id JOIN classes c ON c.id=t.class_id WHERE t.department_id=? AND t.class_id=? ORDER BY t.day_of_week,t.time_slot"); $entries->execute([$deptId,$filterClass]); $entries=$entries->fetchAll();
}

$days=['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$slots=['08:00-09:00','09:00-10:00','10:00-11:00','11:00-12:00','12:00-13:00','13:00-14:00','14:00-15:00','15:00-16:00','16:00-17:00'];

renderHead('Timetable');
?>
<div class="app-layout">
<?php renderSidebar('timetable','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Timetable', [
    ['label' => 'Home',       'href' => 'dashboard.php'],
    ['label' => 'Timetable'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Timetable</h1><p>Assign teachers to subject slots per class</p></div>

    <!-- Class Filter -->
    <div class="card" style="margin-bottom:1.2rem">
        <div class="card-body" style="padding:.9rem">
            <form method="GET" style="display:flex;gap:10px;align-items:flex-end">
                <div class="form-group" style="margin:0;flex:1">
                    <label>Select Class to View / Edit Timetable</label>
                    <select name="class" class="form-control" onchange="this.form.submit()">
                        <option value="">— Select Class —</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if($filterClass): ?>
    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

        <!-- Timetable entries -->
        <div class="card">
            <div class="card-header"><h3>Timetable Entries (<?= count($entries) ?>)</h3></div>
            <?php if($entries): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Mode</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($entries as $e): ?>
                    <tr>
                        <td class="fw-500"><?= $days[$e['day_of_week']]??$e['day_of_week'] ?></td>
                        <td><?= e($e['time_slot']) ?></td>
                        <td><?= e($e['subject_name']) ?> <span class="badge badge-expert" style="font-size:.66rem"><?= e($e['subject_code']) ?></span></td>
                        <td><?= e($e['teacher_name']) ?></td>
                        <td><?= modeBadge($e['mode']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action"       value="delete">
                                <input type="hidden" name="entry_id"     value="<?= $e['id'] ?>">
                                <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                                <button class="btn btn-outline btn-sm" style="color:var(--rejected);border-color:#FECACA"
                                        onclick="return confirmAction('Remove this entry?')"><?= svgIcon('delete') ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>No timetable entries</h3><p>Add entries using the form.</p></div>
            <?php endif; ?>
        </div>

        <!-- Add Entry Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= svgIcon('add') ?> Add Entry</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action"       value="add">
                    <input type="hidden" name="class_id"     value="<?= $filterClass ?>">
                    <input type="hidden" name="filter_class" value="<?= $filterClass ?>">

                    <div class="form-group">
                        <label>Subject <span style="color:red">*</span></label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['subject_name'].' ('.$s['subject_code'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Teacher <span style="color:red">*</span></label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Day <span style="color:red">*</span></label>
                        <select name="day_of_week" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php for($d=1;$d<=6;$d++): ?>
                            <option value="<?= $d ?>"><?= $days[$d] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time Slot <span style="color:red">*</span></label>
                        <select name="time_slot" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach($slots as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode</label>
                        <select name="mode" class="form-control">
                            <option value="theory">Theory</option>
                            <option value="practical">Practical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= date('Y').'-'.(date('y')+1) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%"><?= svgIcon('add') ?> Add Entry</button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>Select a class above</h3><p>Choose a class to view and manage its timetable.</p></div></div>
    <?php endif; ?>
</div>
</div>
</div>
<?php renderFooter(); ?>

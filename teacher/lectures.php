<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

// Teacher's assigned subjects & class
$teacher = $pdo->prepare(
    "SELECT u.*,
            s1.subject_name, s1.subject_code, s1.mode AS subject_mode,
            s2.subject_name AS subject_name_2, s2.subject_code AS subject_code_2
     FROM users u
     LEFT JOIN subjects s1 ON s1.id=u.subject_id
     LEFT JOIN subjects s2 ON s2.id=u.subject_id_2
     WHERE u.id=?"
);
$teacher->execute([$uid]); $teacher=$teacher->fetch();

// Build the list of subjects this teacher is allowed to log lectures for
$assignedSubjects = [];
if ($teacher['subject_id']) {
    $assignedSubjects[] = [
        'id'    => $teacher['subject_id'],
        'label' => $teacher['subject_name'].' ('.$teacher['subject_code'].')',
    ];
}
if ($teacher['subject_id_2']) {
    $assignedSubjects[] = [
        'id'    => $teacher['subject_id_2'],
        'label' => $teacher['subject_name_2'].' ('.$teacher['subject_code_2'].')',
    ];
}
$assignedIds = array_column($assignedSubjects, 'id');
$hasSubject  = count($assignedSubjects) > 0;

// Attach mode to each assigned subject for JS
foreach ($assignedSubjects as &$as) {
    $mRow = $pdo->prepare("SELECT mode FROM subjects WHERE id=?");
    $mRow->execute([$as['id']]); $mRow = $mRow->fetch();
    $as['mode'] = $mRow['mode'] ?? 'theory';
}
unset($as);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date    = $_POST['lecture_date'] ?? '';
        $subjId  = (int)($_POST['subject_id'] ?? 0);
        $tHrs    = (float)$_POST['theory_hours'];
        $pHrs    = (float)$_POST['practical_hours'];
        $oHrs    = (float)$_POST['other_hours'];
        $notes   = trim($_POST['notes'] ?? '');

        // Derive class_id from the chosen subject
        $classId = 0;
        if ($subjId) {
            $cRow = $pdo->prepare("SELECT class_id FROM subjects WHERE id=?");
            $cRow->execute([$subjId]); $cRow = $cRow->fetch();
            $classId = (int)($cRow['class_id'] ?? 0);
        }

        if (!$date) { setFlash('error','Date is required.'); }
        elseif ($tHrs + $pHrs + $oHrs <= 0) { setFlash('error','Enter at least some hours.'); }
        elseif (!$subjId || !in_array($subjId, $assignedIds)) { setFlash('error','Invalid subject. You can only log lectures for your assigned subjects.'); }
        else {
            $pdo->prepare("INSERT INTO lectures (teacher_id,subject_id,class_id,lecture_date,theory_hours,practical_hours,other_hours,notes) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$uid,$subjId,$classId?:null,$date,$tHrs,$pHrs,$oHrs,$notes]);
            logActivity($pdo,$uid,'add_lecture',"Added lecture on $date");
            setFlash('success','Lecture entry added.');
        }
    }

    if ($action === 'delete') {
        $lid = (int)$_POST['lecture_id'];
        // Only delete if not linked to a submitted/approved bill
        $check = $pdo->prepare("SELECT l.id FROM lectures l LEFT JOIN bill_lectures bl ON bl.lecture_id=l.id LEFT JOIN bills b ON b.id=bl.bill_id AND b.status IN ('pending','approved') WHERE l.id=? AND l.teacher_id=? AND b.id IS NULL");
        $check->execute([$lid,$uid]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM lectures WHERE id=? AND teacher_id=?")->execute([$lid,$uid]);
            setFlash('success','Entry deleted.');
        } else { setFlash('error','Cannot delete: entry is linked to a submitted or approved bill.'); }
    }

    header('Location: lectures.php?month='.($_POST['fm']??'').'&year='.($_POST['fy']??'')); exit;
}

$fm = (int)($_GET['month'] ?? 0);
$fy = (int)($_GET['year']  ?? date('Y'));

// Pagination settings
$perPage = 10;
$page    = currentPage();
$offset  = paginationOffset($page, $perPage);

// Base count query
$countSql = "SELECT COUNT(*) FROM lectures l WHERE l.teacher_id=?";
$countParams = [$uid];
if ($fm) { $countSql.=" AND MONTH(l.lecture_date)=?"; $countParams[]=$fm; }
if ($fy) { $countSql.=" AND YEAR(l.lecture_date)=?";  $countParams[]=$fy; }
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = totalPages($totalRecords, $perPage);

// Main query with pagination
$sql    = "SELECT l.*,s.subject_name,s.subject_code,c.label AS class_label FROM lectures l LEFT JOIN subjects s ON s.id=l.subject_id LEFT JOIN classes c ON c.id=l.class_id WHERE l.teacher_id=?";
$params = [$uid];
if ($fm) { $sql.=" AND MONTH(l.lecture_date)=?"; $params[]=$fm; }
if ($fy) { $sql.=" AND YEAR(l.lecture_date)=?";  $params[]=$fy; }
$sql.=" ORDER BY l.lecture_date DESC LIMIT $perPage OFFSET $offset";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $lectures=$stmt->fetchAll();

// Total hours for filtered records (all pages, for summary)
$totalHrsSql = "SELECT SUM(theory_hours) AS theory, SUM(practical_hours) AS practical, SUM(other_hours) AS other FROM lectures WHERE teacher_id=?";
$totalHrsParams = [$uid];
if ($fm) { $totalHrsSql.=" AND MONTH(lecture_date)=?"; $totalHrsParams[]=$fm; }
if ($fy) { $totalHrsSql.=" AND YEAR(lecture_date)=?";  $totalHrsParams[]=$fy; }
$stmtTotal = $pdo->prepare($totalHrsSql);
$stmtTotal->execute($totalHrsParams);
$tTotals = $stmtTotal->fetch(PDO::FETCH_ASSOC) ?: ['theory'=>0,'practical'=>0,'other'=>0];
$tTotals = ['theory'=>(float)$tTotals['theory'], 'practical'=>(float)$tTotals['practical'], 'other'=>(float)$tTotals['other']];

// (assignedSubjects already built above — no extra query needed)

// Determine which hour types this teacher uses
$showTheory    = in_array($teacher['teacher_mode'], ['theory',    'theory & practical']);
$showPractical = in_array($teacher['teacher_mode'], ['practical', 'theory & practical']);

renderHead('My Lectures');
?>
<div class="app-layout">
<?php renderSidebar('lectures','teacher',$user); ?>
<div class="main-content">
<?php renderTopbar('My Lectures', [
    ['label' => 'Home',    'href' => 'dashboard.php'],
    ['label' => 'My Lectures'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
        <h1>My Lectures</h1>
        <p>Record and manage your lecture sessions — Theory, Practical, Other</p>
        </div>
        <button type="button" class="btn btn-primary"
                            onclick="openModal('modal-lecture-add')"><?= svgIcon('add') ?> Add Lecture Entry</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;align-items:start">

        <div>
            <!-- Filter -->
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body" style="padding:.8rem">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                        <div class="form-group" style="margin:0">
                            <label>Month</label>
                            <select name="month" class="form-control" style="width:130px">
                                <option value="">All Months</option>
                                <?php for($m=1;$m<=12;$m++): ?>
                                <option value="<?= $m ?>" <?= $fm==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Year</label>
                            <select name="year" class="form-control" style="width:100px">
                                <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                                <option value="<?= $y ?>" <?= $fy==$y?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="padding: 10px 20px;">Filter</button>
                        <a href="lectures.php" class="btn btn-outline btn-sm" style="padding: 7px 15px;">Clear</a>
                    </form>
                </div>
            </div>

            <?php if($fm && ($tTotals['theory']+$tTotals['practical']+$tTotals['other']) > 0): ?>
            <div class="alert alert-info" style="margin-bottom:1rem">
                <?= svgIcon('chart') ?> <strong><?= date('F',mktime(0,0,0,$fm,1)) ?> <?= $fy ?>:</strong>
                <?php if($showTheory): ?>Theory <?= number_format($tTotals['theory'],1) ?> hrs &nbsp;|&nbsp;<?php endif; ?>
                <?php if($showPractical): ?>Practical <?= number_format($tTotals['practical'],1) ?> hrs &nbsp;|&nbsp;<?php endif; ?>
                Other <?= number_format($tTotals['other'],1) ?> hrs
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Lecture Entries (<?= count($lectures) ?>)</h3>
                    
                </div>
                <?php if($lectures): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Date</th><th>Subject</th><th>Class</th><?php if($showTheory): ?><th>Theory Hrs</th><?php endif; ?><?php if($showPractical): ?><th>Practical Hrs</th><?php endif; ?><th>Other Hrs</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach($lectures as $i=>$l):
                            $recordNum = $offset + $i + 1;
                        ?>
                        <tr>
                            <td class="text-muted"><?= $recordNum ?></td>
                            <td><?= fmtDate($l['lecture_date']) ?></td>
                            <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code']?'<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>':'' ?></td>
                            <td class="text-sm text-muted"><?= e($l['class_label']??'—') ?></td>
                            <?php if($showTheory): ?><td><?= number_format($l['theory_hours'],1) ?></td><?php endif; ?>
                            <?php if($showPractical): ?><td><?= number_format($l['practical_hours'],1) ?></td><?php endif; ?>
                            <td><?= number_format($l['other_hours'],1) ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="action"     value="delete">
                                    <input type="hidden" name="lecture_id" value="<?= $l['id'] ?>">
                                    <input type="hidden" name="fm" value="<?= $fm ?>">
                                    <input type="hidden" name="fy" value="<?= $fy ?>">
                                    <button class="btn btn-outline btn-sm" style="color:var(--rejected);border-color:#FECACA"
                                            onclick="return confirmAction('Delete this entry?')"><?= svgIcon('delete') ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>No lectures found</h3><p>Add entries using the form.</p></div>
                <?php endif; ?>
                <?php if ($totalPages > 1): ?>
                    <?= renderPagination($page, $totalPages, $totalRecords, $perPage, $offset, 'lectures', 'Lecture entries pagination') ?>
                <?php endif; ?>
            </div>
        </div>


    </div>
</div>
</div>
</div>
<?php
// ── Modal (rendered outside the layout so display:none ancestors don't trap it) ──
?>
<!-- Add Lecture Entry Modal -->
<div class="modal-backdrop" id="modal-lecture-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Lecture Entry</h3></span>
            <button class="modal-close" onclick="closeModal('modal-lecture-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="fm" value="<?= $fm ?>">
            <input type="hidden" name="fy" value="<?= $fy ?>">
            <div class="modal-body">
                <?php if(!$hasSubject): ?>
                <div class="alert alert-warning"><?= svgIcon('warning') ?> No subject assigned. Please ask your HOD to assign a subject.</div>
                <?php else: ?>
                <div class="form-group">
                    <label>Date <span style="color:red">*</span></label>
                    <input type="date" name="lecture_date" class="form-control" data-today required max="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Subject <span style="color:red">*</span></label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">— Select Subject —</option>
                        <?php foreach($assignedSubjects as $as): ?>
                        <option value="<?= $as['id'] ?>"><?= e($as['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if($showTheory): ?>
                <div class="form-group">
                    <label>Theory Hours</label>
                    <input type="number" name="theory_hours" class="form-control" step="0.5" min="0" value="1">
                </div>
                <?php else: ?>
                <input type="hidden" name="theory_hours" value="0">
                <?php endif; ?>

                <?php if($showPractical): ?>
                <div class="form-group">
                    <label>Practical Hours</label>
                    <input type="number" name="practical_hours" class="form-control" step="0.5" min="0" value="1">
                </div>
                <?php else: ?>
                <input type="hidden" name="practical_hours" value="0">
                <?php endif; ?>

                <div class="form-group">
                    <label>Other Hours</label>
                    <input type="number" name="other_hours" class="form-control" step="0.5" min="0" value="0">
                </div>

                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any remarks…"></textarea>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-lecture-add')">Cancel</button>
                <button type="submit" class="btn btn-primary" <?= !$hasSubject ? 'disabled' : '' ?>><?= svgIcon('add') ?> Add Entry</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>

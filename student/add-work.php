<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();
$user = currentUser();
$uid  = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date  = $_POST['work_date'] ?? '';
        $hours = (float)($_POST['hours'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');

        if (!$date) { setFlash('error','Date is required.'); }
        elseif ($hours <= 0 || $hours > 12) { setFlash('error','Hours must be between 0 and 12.'); }
        else {
            $pdo->prepare("INSERT INTO student_work (student_id,work_date,hours,description) VALUES (?,?,?,?)")
                ->execute([$uid,$date,$hours,$desc]);
            logActivity($pdo,$uid,'add_work',"Added $hours hrs on $date");
            setFlash('success','Work entry added.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['work_id'];
        $check = $pdo->prepare("SELECT sw.id FROM student_work sw WHERE sw.id=? AND sw.student_id=? AND sw.id NOT IN (SELECT 0)");
        // ensure not linked to approved/pending bill via period overlap — simple guard:
        $linked = $pdo->prepare("SELECT id FROM student_bills WHERE student_id=? AND status IN ('pending','approved') AND ? BETWEEN period_from AND period_to");
        $w = $pdo->prepare("SELECT work_date FROM student_work WHERE id=? AND student_id=?"); $w->execute([$id,$uid]); $wd=$w->fetchColumn();
        if ($wd) {
            $linked->execute([$uid,$wd]);
            if ($linked->fetch()) {
                setFlash('error','Cannot delete: this date is part of a submitted bill.');
            } else {
                $pdo->prepare("DELETE FROM student_work WHERE id=? AND student_id=?")->execute([$id,$uid]);
                setFlash('success','Entry deleted.');
            }
        }
    }

    header('Location: add-work.php?month='.($_POST['fm']??'').'&year='.($_POST['fy']??'')); exit;
}

$fm = (int)($_GET['month'] ?? 0);
$fy = (int)($_GET['year']  ?? date('Y'));

$sql = "SELECT * FROM student_work WHERE student_id=?";
$params = [$uid];
if ($fm) { $sql.=" AND MONTH(work_date)=?"; $params[]=$fm; }
if ($fy) { $sql.=" AND YEAR(work_date)=?";  $params[]=$fy; }
$sql.=" ORDER BY work_date DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $work=$stmt->fetchAll();

$totalHrs = array_sum(array_column($work,'hours'));

renderHead('Add Work');
?>
<div class="app-layout">
<?php renderSidebar('add-work','student',$user); ?>
<div class="main-content">
<?php renderTopbar('Add Work Hours', [
    ['label' => 'Home',        'href' => 'dashboard.php'],
    ['label' => 'Add Work Hours'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Work Log</h1>
            <p>Record your daily Earn &amp; Learn work hours</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModal('modal-work-add')"><?= svgIcon('add') ?> Add Work Entry</button>
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
                                <option value="">All</option>
                                <?php for($m=1;$m<=12;$m++): ?>
                                <option value="<?= $m ?>" <?= $fm==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Year</label>
                            <select name="year" class="form-control" style="width:100px">
                                <?php for($y=date('Y');$y>=date('Y')-2;$y--): ?>
                                <option value="<?= $y ?>" <?= $fy==$y?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary  btn-filter btn-sm" style="padding: 10px 20px;">Filter</button>
                        <a href="add-work.php" class="btn btn-outline btn-sm btn-clear" style="padding: 7px 15px;">Clear</a>
                    </form>
                </div>
            </div>

            <?php if($fm): ?>
            <div class="alert alert-info mb-2"><?= svgIcon('chart') ?> Total for <?= date('F',mktime(0,0,0,$fm,1)) ?> <?= $fy ?>: <strong><?= number_format($totalHrs,1) ?> hrs</strong></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Work Entries (<?= count($work) ?>)</h3>
                </div>
                <?php if($work): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Date</th><th>Hours</th><th>Description</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach($work as $i=>$w): ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td><?= fmtDate($w['work_date']) ?></td>
                            <td class="fw-600"><?= number_format($w['hours'],1) ?></td>
                            <td class="text-sm text-muted"><?= e($w['description']?:'—') ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="action"  value="delete">
                                    <input type="hidden" name="work_id" value="<?= $w['id'] ?>">
                                    <input type="hidden" name="fm" value="<?= $fm ?>">
                                    <input type="hidden" name="fy" value="<?= $fy ?>">
                                    <button class="btn btn-outline btn-sm" style="color:var(--rejected);border-color:#FECACA" onclick="return confirmAction('Delete this entry?')"><?= svgIcon('delete') ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('pending') ?></div><h3>No work entries found</h3></div>
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
<!-- Add Work Entry Modal -->
<div class="modal-backdrop" id="modal-work-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Work Entry</h3></span>
            <button class="modal-close" onclick="closeModal('modal-work-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="fm" value="<?= $fm ?>">
            <input type="hidden" name="fy" value="<?= $fy ?>">
            <div class="modal-body">
                <div class="form-group"><label>Date <span style="color:red">*</span></label><input type="date" name="work_date" class="form-control" data-today required max="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label>Hours <span style="color:red">*</span></label><input type="number" name="hours" class="form-control" step="0.5" min="0.5" max="12" value="2" required></div>
                <div class="form-group"><label>Description (optional)</label><textarea name="description" class="form-control" rows="2" placeholder="What work did you do?"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-work-add')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('add') ?> Add Entry</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>

<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherId = (int)$_POST['teacher_id'];
    $month     = (int)$_POST['bill_month'];
    $year      = (int)$_POST['bill_year'];

    $t = $pdo->prepare("SELECT * FROM users WHERE id=? AND department_id=? AND role='teacher'");
    $t->execute([$teacherId,$deptId]); $t=$t->fetch();
    if (!$t) { setFlash('error','Teacher not found.'); header('Location: manual-bill.php'); exit; }

    $dup = $pdo->prepare("SELECT id FROM bills WHERE teacher_id=? AND MONTH(period_from)=? AND YEAR(period_from)=? AND status IN ('pending','approved')");
    $dup->execute([$teacherId,$month,$year]);
    if ($dup->fetch()) { setFlash('error','A bill already exists for this teacher and month.'); header('Location: manual-bill.php'); exit; }

    $lq = $pdo->prepare("SELECT * FROM lectures WHERE teacher_id=? AND MONTH(lecture_date)=? AND YEAR(lecture_date)=? AND id NOT IN (SELECT lecture_id FROM bill_lectures)");
    $lq->execute([$teacherId,$month,$year]); $lectures=$lq->fetchAll();

    if (!$lectures) { setFlash('error','No unbilled lecture entries found.'); header('Location: manual-bill.php'); exit; }

    $tHrs=array_sum(array_column($lectures,'theory_hours'));
    $pHrs=array_sum(array_column($lectures,'practical_hours'));
    $oHrs=array_sum(array_column($lectures,'other_hours'));
    $tAmt=$tHrs*(float)$t['rate_theory'];
    $pAmt=$pHrs*(float)$t['rate_practical'];
    $oAmt=$oHrs*(float)$t['rate_other'];
    $total=$tAmt+$pAmt+$oAmt;
    $from=date("$year-$month-01"); $to=date("$year-$month-t");
    $my=date('F Y',mktime(0,0,0,$month,1,$year));

    $ins=$pdo->prepare("INSERT INTO bills (teacher_id,generated_by,month_year,period_from,period_to,total_theory_hrs,total_practical_hrs,total_other_hrs,rate_theory,rate_practical,rate_other,theory_amount,practical_amount,other_amount,total_amount,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
    $ins->execute([$teacherId,$user['id'],$my,$from,$to,$tHrs,$pHrs,$oHrs,$t['rate_theory'],$t['rate_practical'],$t['rate_other'],$tAmt,$pAmt,$oAmt,$total]);
    $billId=$pdo->lastInsertId();

    $link=$pdo->prepare("INSERT INTO bill_lectures (bill_id,lecture_id) VALUES (?,?)");
    foreach($lectures as $l) $link->execute([$billId,$l['id']]);

    logActivity($pdo,$user['id'],'manual_bill',"Created bill #$billId for {$t['name']} — $my");
    setFlash('success',"Bill #$billId created for {$t['name']} ($my). Total: ".formatINR($total));
    header("Location: request-detail.php?id=$billId"); exit;
}

$selTeacher=(int)($_GET['teacher']??0);
$selMonth=(int)($_GET['month']??0);
$selYear=(int)($_GET['year']??date('Y'));
$preview=[]; $previewTeacher=null;

if ($selTeacher && $selMonth) {
    $pq=$pdo->prepare("SELECT * FROM users WHERE id=? AND department_id=? AND role='teacher'");
    $pq->execute([$selTeacher,$deptId]); $previewTeacher=$pq->fetch();
    if ($previewTeacher) {
        $lq=$pdo->prepare("SELECT l.*,s.subject_name,s.subject_code FROM lectures l LEFT JOIN subjects s ON s.id=l.subject_id WHERE l.teacher_id=? AND MONTH(l.lecture_date)=? AND YEAR(l.lecture_date)=? AND l.id NOT IN (SELECT lecture_id FROM bill_lectures) ORDER BY l.lecture_date");
        $lq->execute([$selTeacher,$selMonth,$selYear]); $preview=$lq->fetchAll();
    }
}

$teachers=$pdo->prepare("SELECT u.*,s.subject_code FROM users u LEFT JOIN subjects s ON s.id=u.subject_id WHERE u.role='teacher' AND u.department_id=? AND u.is_active=1 ORDER BY u.name");
$teachers->execute([$deptId]); $teachers=$teachers->fetchAll();

renderHead('Manual Bill');
?>
<div class="app-layout">
<?php renderSidebar('manual-bill','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manual Bill Generation', [
    ['label' => 'Home',                   'href' => 'dashboard.php'],
    ['label' => 'Manual Bill Generation'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Manual Bill</h1><p>Generate a bill on behalf of a teacher</p></div>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:1.5rem;align-items:start">
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= svgIcon('calendar') ?> Select</h3></div>
            <div class="card-body">
                <form method="GET">
                    <div class="form-group">
                        <label>Teacher</label>
                        <select name="teacher" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $selTeacher==$t['id']?'selected':'' ?>><?= e($t['name']) ?> (<?= e($t['subject_code']??'—') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>" <?= $selMonth==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" class="form-control">
                            <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                            <option value="<?= $y ?>" <?= $selYear==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Preview →</button>
                </form>
            </div>
        </div>

        <div>
            <?php if($selTeacher && $selMonth && $previewTeacher && $preview):
                $tHrs=array_sum(array_column($preview,'theory_hours'));
                $pHrs=array_sum(array_column($preview,'practical_hours'));
                $oHrs=array_sum(array_column($preview,'other_hours'));
                $tAmt=$tHrs*(float)$previewTeacher['rate_theory'];
                $pAmt=$pHrs*(float)$previewTeacher['rate_practical'];
                $oAmt=$oHrs*(float)$previewTeacher['rate_other'];
                $total=$tAmt+$pAmt+$oAmt;
                $my=date('F Y',mktime(0,0,0,$selMonth,1,$selYear));
            ?>
            <div class="card">
                <div class="card-header"><h3><?= svgIcon('receipt') ?> Preview — <?= e($my) ?> — <?= e($previewTeacher['name']) ?></h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
                        <div style="text-align:center;padding:.9rem;background:var(--primary-lt);border-radius:var(--radius)">
                            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Theory</div>
                            <div style="font-size:1.4rem;font-weight:600;color:var(--primary)"><?= number_format($tHrs,1) ?> hrs</div>
                            <div class="text-xs" style="color:var(--primary)"><?= formatINR($tAmt) ?></div>
                        </div>
                        <div style="text-align:center;padding:.9rem;background:#F0FDFA;border-radius:var(--radius)">
                            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Practical</div>
                            <div style="font-size:1.4rem;font-weight:600;color:#0F766E"><?= number_format($pHrs,1) ?> hrs</div>
                            <div class="text-xs" style="color:#0F766E"><?= formatINR($pAmt) ?></div>
                        </div>
                        <div style="text-align:center;padding:.9rem;background:#F5F3FF;border-radius:var(--radius)">
                            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Other</div>
                            <div style="font-size:1.4rem;font-weight:600;color:#6D28D9"><?= number_format($oHrs,1) ?> hrs</div>
                            <div class="text-xs" style="color:#6D28D9"><?= formatINR($oAmt) ?></div>
                        </div>
                        <div style="text-align:center;padding:.9rem;background:var(--primary);border-radius:var(--radius)">
                            <div class="text-xs" style="text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.7)">Total</div>
                            <div style="font-size:1.3rem;font-weight:600;color:#E2C97E"><?= formatINR($total) ?></div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>Date</th><th>Subject</th><th>Theory Hrs</th><th>Practical Hrs</th><th>Other Hrs</th></tr></thead>
                            <tbody>
                            <?php foreach($preview as $i=>$l): ?>
                            <tr>
                                <td class="text-muted"><?= $i+1 ?></td>
                                <td><?= fmtDate($l['lecture_date']) ?></td>
                                <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code']?'<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>':'' ?></td>
                                <td><?= number_format($l['theory_hours'],1) ?></td>
                                <td><?= number_format($l['practical_hours'],1) ?></td>
                                <td><?= number_format($l['other_hours'],1) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="divider">
                    <div class="alert alert-info"><?= svgIcon('info') ?> Bill will be created as <strong>Pending</strong> and appear in requests queue.</div>
                    <form method="POST">
                        <input type="hidden" name="teacher_id" value="<?= $selTeacher ?>">
                        <input type="hidden" name="bill_month" value="<?= $selMonth ?>">
                        <input type="hidden" name="bill_year"  value="<?= $selYear ?>">
                        <button type="submit" class="btn btn-primary" onclick="return confirmAction('Create this bill?')">
                            <?= svgIcon('upload') ?> Create Bill — <?= formatINR($total) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif($selTeacher && $selMonth && !$preview): ?>
            <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No unbilled lectures</h3><p>No entries for <?= date('F Y',mktime(0,0,0,$selMonth,1,$selYear)) ?> that haven't been billed.</p></div></div>
            <?php else: ?>
            <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>Select teacher and month</h3><p>Use the form on the left to preview.</p></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

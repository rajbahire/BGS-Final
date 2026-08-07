<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

$teacher = $pdo->prepare("SELECT * FROM users WHERE id=?"); $teacher->execute([$uid]); $teacher=$teacher->fetch();

// Which hour types apply to this teacher
$showTheory    = in_array($teacher['teacher_mode'], ['theory',    'theory & practical']);
$showPractical = in_array($teacher['teacher_mode'], ['practical', 'theory & practical']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)$_POST['bill_month'];
    $year  = (int)$_POST['bill_year'];

    $dup = $pdo->prepare("SELECT id FROM bills WHERE teacher_id=? AND MONTH(period_from)=? AND YEAR(period_from)=? AND status IN ('pending','approved')");
    $dup->execute([$uid,$month,$year]);
    if ($dup->fetch()) { setFlash('error','A bill already exists for this month.'); header('Location: generate-bill.php'); exit; }

    $lq = $pdo->prepare("SELECT * FROM lectures WHERE teacher_id=? AND MONTH(lecture_date)=? AND YEAR(lecture_date)=? AND id NOT IN (SELECT lecture_id FROM bill_lectures)");
    $lq->execute([$uid,$month,$year]); $lectures=$lq->fetchAll();

    if (!$lectures) { setFlash('error','No unbilled lecture entries for the selected month.'); header('Location: generate-bill.php'); exit; }

    $tHrs=array_sum(array_column($lectures,'theory_hours'));
    $pHrs=array_sum(array_column($lectures,'practical_hours'));
    $oHrs=array_sum(array_column($lectures,'other_hours'));
    $tAmt=$tHrs*(float)$teacher['rate_theory'];
    $pAmt=$pHrs*(float)$teacher['rate_practical'];
    $oAmt=$oHrs*(float)$teacher['rate_other'];
    $total=$tAmt+$pAmt+$oAmt;
    $from=date("$year-$month-01"); $to=date("$year-$month-t");
    $my=date('F Y',mktime(0,0,0,$month,1,$year));

    $ins=$pdo->prepare("INSERT INTO bills (teacher_id,month_year,period_from,period_to,total_theory_hrs,total_practical_hrs,total_other_hrs,rate_theory,rate_practical,rate_other,theory_amount,practical_amount,other_amount,total_amount,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
    $ins->execute([$uid,$my,$from,$to,$tHrs,$pHrs,$oHrs,$teacher['rate_theory'],$teacher['rate_practical'],$teacher['rate_other'],$tAmt,$pAmt,$oAmt,$total]);
    $billId=$pdo->lastInsertId();

    $link=$pdo->prepare("INSERT INTO bill_lectures (bill_id,lecture_id) VALUES (?,?)");
    foreach($lectures as $l) $link->execute([$billId,$l['id']]);

    logActivity($pdo,$uid,'submit_bill',"Submitted bill #$billId for $my — ".formatINR($total));
    setFlash('success',"Bill for $my submitted to HOD. Total: ".formatINR($total));
    header("Location: my-bills.php"); exit;
}

// Preview
$pm = (int)($_GET['month'] ?? 0);
$py = (int)($_GET['year']  ?? date('Y'));
$preview=[]; $pTotals=['t'=>0,'p'=>0,'o'=>0];

if ($pm) {
    $lq=$pdo->prepare("SELECT l.*,s.subject_name,s.subject_code FROM lectures l LEFT JOIN subjects s ON s.id=l.subject_id WHERE l.teacher_id=? AND MONTH(l.lecture_date)=? AND YEAR(l.lecture_date)=? AND l.id NOT IN (SELECT lecture_id FROM bill_lectures) ORDER BY l.lecture_date");
    $lq->execute([$uid,$pm,$py]); $preview=$lq->fetchAll();
    $pTotals=['t'=>array_sum(array_column($preview,'theory_hours')),'p'=>array_sum(array_column($preview,'practical_hours')),'o'=>array_sum(array_column($preview,'other_hours'))];
}

// Months with unbilled lectures
$avail=$pdo->prepare("SELECT MONTH(lecture_date) AS m, YEAR(lecture_date) AS y, SUM(theory_hours+practical_hours+other_hours) AS total FROM lectures WHERE teacher_id=? AND id NOT IN (SELECT lecture_id FROM bill_lectures) GROUP BY YEAR(lecture_date),MONTH(lecture_date) ORDER BY y DESC,m DESC");
$avail->execute([$uid]); $availMonths=$avail->fetchAll();

renderHead('Generate Bill');
?>
<div class="app-layout">
<?php renderSidebar('generate-bill','teacher',$user); ?>
<div class="main-content">
<?php renderTopbar('Generate Bill', [
    ['label' => 'Home',      'href' => 'dashboard.php'],
    ['label' => 'Generate Bill'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Generate Bill</h1><p>Select a month, preview, then submit to HOD</p></div>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start">

        <!-- Month selector -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= svgIcon('month') ?> Select Month</h3></div>
            <div class="card-body">
                <form method="GET">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>" <?= $pm==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" class="form-control">
                            <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                            <option value="<?= $y ?>" <?= $py==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Preview →</button>
                </form>

                <?php if($availMonths): ?>
                <hr class="divider">
                <div class="text-xs text-muted fw-500" style="text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Available Months</div>
                <?php foreach($availMonths as $am): ?>
                <a href="?month=<?= $am['m'] ?>&year=<?= $am['y'] ?>" class="btn btn-outline btn-sm" style="display:flex;justify-content:space-between;margin-bottom:5px">
                    <span><?= date('F Y',mktime(0,0,0,$am['m'],1,$am['y'])) ?></span>
                    <span class="text-muted"><?= number_format($am['total'],1) ?> hrs</span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preview panel -->
        <?php if($pm && $preview): ?>
        <?php
        $tAmt=$pTotals['t']*(float)$teacher['rate_theory'];
        $pAmt=$pTotals['p']*(float)$teacher['rate_practical'];
        $oAmt=$pTotals['o']*(float)$teacher['rate_other'];
        $total=$tAmt+$pAmt+$oAmt;
        $my=date('F Y',mktime(0,0,0,$pm,1,$py));
        ?>
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('receipt') ?> Bill Preview — <?= e($my) ?></h3></div>
            <div class="card-body">
                <!-- Summary -->
                <?php
                $colCount = 1 + ($showTheory ? 1 : 0) + ($showPractical ? 1 : 0) + 1; // other + total
                ?>
                <div style="display:grid;grid-template-columns:repeat(<?= $colCount ?>,1fr);gap:1rem;margin-bottom:1.5rem">
                    <?php if($showTheory): ?>
                    <div style="text-align:center;padding:.9rem;background:var(--primary-lt);border-radius:var(--radius)">
                        <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Theory Hrs</div>
                        <div style="font-size:1.4rem;font-weight:600;color:var(--primary)"><?= number_format($pTotals['t'],1) ?></div>
                        <div class="text-xs" style="color:var(--primary)">@ <?= formatINR($teacher['rate_theory']) ?>/hr = <?= formatINR($tAmt) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($showPractical): ?>
                    <div style="text-align:center;padding:.9rem;background:#F0FDFA;border-radius:var(--radius)">
                        <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Practical Hrs</div>
                        <div style="font-size:1.4rem;font-weight:600;color:#0F766E"><?= number_format($pTotals['p'],1) ?></div>
                        <div class="text-xs" style="color:#0F766E">@ <?= formatINR($teacher['rate_practical']) ?>/hr = <?= formatINR($pAmt) ?></div>
                    </div>
                    <?php endif; ?>
                    <div style="text-align:center;padding:.9rem;background:#F5F3FF;border-radius:var(--radius)">
                        <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Other Hrs</div>
                        <div style="font-size:1.4rem;font-weight:600;color:#6D28D9"><?= number_format($pTotals['o'],1) ?></div>
                        <div class="text-xs" style="color:#6D28D9">@ <?= formatINR($teacher['rate_other']) ?>/hr = <?= formatINR($oAmt) ?></div>
                    </div>
                    <div style="text-align:center;padding:.9rem;background:var(--primary);border-radius:var(--radius)">
                        <div class="text-xs" style="text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.7)">Total Payable</div>
                        <div style="font-size:1.3rem;font-weight:600;color:#E2C97E"><?= formatINR($total) ?></div>
                    </div>
                </div>

                <!-- Lecture table -->
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Date</th><th>Subject</th><?php if($showTheory): ?><th>Theory Hrs</th><?php endif; ?><?php if($showPractical): ?><th>Practical Hrs</th><?php endif; ?><th>Other Hrs</th></tr></thead>
                        <tbody>
                        <?php foreach($preview as $i=>$l): ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td><?= fmtDate($l['lecture_date']) ?></td>
                            <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code']?'<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>':'' ?></td>
                            <?php if($showTheory): ?><td><?= number_format($l['theory_hours'],1) ?></td><?php endif; ?>
                            <?php if($showPractical): ?><td><?= number_format($l['practical_hours'],1) ?></td><?php endif; ?>
                            <td><?= number_format($l['other_hours'],1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <hr class="divider">
                <div class="alert alert-warning"><?= svgIcon('warning') ?> Once submitted you cannot edit until HOD reviews it.</div>

                <form method="POST">
                    <input type="hidden" name="bill_month" value="<?= $pm ?>">
                    <input type="hidden" name="bill_year"  value="<?= $py ?>">
                    <button type="submit" class="btn btn-primary btn-lg"
                            onclick="return confirmAction('Submit bill for <?= e($my) ?> — <?= formatINR($total) ?>?')">
                        <?= svgIcon('upload') ?> Submit to HOD — <?= formatINR($total) ?>
                    </button>
                </form>
            </div>
        </div>

        <?php elseif($pm && !$preview): ?>
        <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No unbilled lectures</h3><p>No entries for <?= date('F Y',mktime(0,0,0,$pm,1,$py)) ?> or all already billed.</p><a href="lectures.php" class="btn btn-primary" style="margin-top:1rem">Add Lectures</a></div></div>

        <?php else: ?>
        <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>Select a month</h3><p>Choose a month from the left panel to preview your bill.</p></div></div>
        <?php endif; ?>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

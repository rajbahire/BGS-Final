<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();
$user = currentUser();
$uid  = $user['id'];
requireProfileForBilling();   // block billing until profile is complete

$student = $pdo->prepare("SELECT * FROM users WHERE id=?"); $student->execute([$uid]); $student=$student->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)$_POST['bill_month'];
    $year  = (int)$_POST['bill_year'];

    $dup = $pdo->prepare("SELECT id FROM student_bills WHERE student_id=? AND MONTH(period_from)=? AND YEAR(period_from)=? AND status IN ('pending','approved')");
    $dup->execute([$uid,$month,$year]);
    if ($dup->fetch()) { setFlash('error','A bill already exists for this month.'); header('Location: generate-bill.php'); exit; }

    $from = date('Y-m-01', mktime(0,0,0,$month,1,$year)); $to = date('Y-m-t', mktime(0,0,0,$month,1,$year));
    $wq = $pdo->prepare("SELECT COALESCE(SUM(hours),0) FROM student_work WHERE student_id=? AND work_date BETWEEN ? AND ?");
    $wq->execute([$uid,$from,$to]); $totalHrs=(float)$wq->fetchColumn();

    if ($totalHrs <= 0) { setFlash('error','No work hours recorded for the selected month.'); header('Location: generate-bill.php'); exit; }

    $rate  = (float)$student['rate_per_hour'];
    $total = $totalHrs * $rate;
    $my    = date('F Y',mktime(0,0,0,$month,1,$year));

    $pdo->prepare("INSERT INTO student_bills (student_id,month_year,period_from,period_to,total_hours,rate_per_hour,total_amount,status,submitted_at) VALUES (?,?,?,?,?,?,?,'pending',NOW())")
        ->execute([$uid,$my,$from,$to,$totalHrs,$rate,$total]);
    $billId = $pdo->lastInsertId();

    // Generate unique bill number (EL-YYYY-MM-NNNNN)
    $billNumber = generateStudentBillNumber($from, $billId);
    $pdo->prepare("UPDATE student_bills SET bill_number=? WHERE id=?")->execute([$billNumber, $billId]);

    logActivity($pdo,$uid,'submit_student_bill',"Submitted student bill $billNumber for $my — ".formatINR($total));
    setFlash('success',"Bill $billNumber for $my submitted. Total: ".formatINR($total));
    header('Location: my-bills.php'); exit;
}

$pm = (int)($_GET['month'] ?? 0);
$py = (int)($_GET['year']  ?? date('Y'));
$preview=[]; $totalHrs=0;

if ($pm) {
    $from=date('Y-m-01', mktime(0,0,0,$pm,1,$py)); $to=date('Y-m-t', mktime(0,0,0,$pm,1,$py));
    $lq=$pdo->prepare("SELECT * FROM student_work WHERE student_id=? AND work_date BETWEEN ? AND ? ORDER BY work_date");
    $lq->execute([$uid,$from,$to]); $preview=$lq->fetchAll();
    $totalHrs=array_sum(array_column($preview,'hours'));
}

renderHead('Generate Bill');
?>
<div class="app-layout">
<?php renderSidebar('generate-bill','student',$user); ?>
<div class="main-content">
<?php renderTopbar('Generate Bill', [
    ['label' => 'Home',      'href' => 'dashboard.php'],
    ['label' => 'Generate Bill'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Generate Bill</h1><p>Select a month to preview and submit your Earn &amp; Learn bill</p></div>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start">

        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= svgIcon('calendar') ?> Select Month</h3></div>
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
                            <?php for($y=date('Y');$y>=date('Y')-2;$y--): ?>
                            <option value="<?= $y ?>" <?= $py==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Preview →</button>
                </form>
            </div>
        </div>

        <div>
            <?php if($pm && $preview): $total=$totalHrs*(float)$student['rate_per_hour']; $my=date('F Y',mktime(0,0,0,$pm,1,$py)); ?>
            <div class="card">
                <div class="card-header"><h3><?= svgIcon('receipt') ?> Bill Preview — <?= e($my) ?></h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
                        <div style="text-align:center;padding:1rem;background:var(--bg);border-radius:var(--radius)">
                            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Total Hours</div>
                            <div style="font-size:1.6rem;font-weight:600;color:var(--text)"><?= number_format($totalHrs,1) ?></div>
                        </div>
                        <div style="text-align:center;padding:1rem;background:var(--bg);border-radius:var(--radius)">
                            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.05em">Rate / Hour</div>
                            <div style="font-size:1.6rem;font-weight:600;color:var(--text)"><?= formatINR($student['rate_per_hour']) ?></div>
                        </div>
                        <div style="text-align:center;padding:1rem;background:var(--primary);border-radius:var(--radius)">
                            <div class="text-xs" style="text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.7)">Total Amount</div>
                            <div style="font-size:1.4rem;font-weight:600;color:#E2C97E"><?= formatINR($total) ?></div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Date</th><th>Start Time</th><th>End Time</th><th>Hours</th><th>Description</th></tr></thead>
                            <tbody>
                            <?php foreach($preview as $w): ?>
                            <tr>
                                <td><?= fmtDate($w['work_date']) ?></td>
                                <td><?= ($w['start_time'] ?? '') ? date('h:i A', strtotime($w['start_time'])) : '—' ?></td>
                                <td><?= ($w['end_time'] ?? '') ? date('h:i A', strtotime($w['end_time'])) : '—' ?></td>
                                <td><?= number_format($w['hours'],1) ?></td>
                                <td class="text-sm text-muted"><?= e($w['description']?:'—') ?></td>
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
                        <button type="submit" class="btn btn-primary" onclick="return confirmAction('Submit bill for <?= e($my) ?> — <?= formatINR($total) ?>?')">
                            <?= svgIcon('upload') ?> Submit to HOD — <?= formatINR($total) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif($pm && !$preview): ?>
            <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No work hours recorded</h3><p>No entries for <?= date('F Y',mktime(0,0,0,$pm,1,$py)) ?>.</p><a href="add-work.php" class="btn btn-primary" style="margin-top:1rem">Add Work Hours</a></div></div>
            <?php else: ?>
            <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>Select a month</h3><p>Choose a month to preview your bill.</p></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

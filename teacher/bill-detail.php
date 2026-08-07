<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

$billId = (int)($_GET['id'] ?? 0);
$bill   = $pdo->prepare("SELECT b.*,u.name AS tname,u.email,u.department_id,u.teacher_type,u.teacher_mode,s.subject_name,s.subject_code,c.label AS class_label FROM bills b JOIN users u ON u.id=b.teacher_id LEFT JOIN subjects s ON s.id=u.subject_id LEFT JOIN classes c ON c.id=s.class_id WHERE b.id=? AND b.teacher_id=?");
$bill->execute([$billId,$uid]); $bill=$bill->fetch();
if (!$bill) { setFlash('error','Bill not found.'); header('Location: my-bills.php'); exit; }

$lectures = $pdo->prepare("SELECT l.*,s.subject_name,s.subject_code FROM lectures l JOIN bill_lectures bl ON bl.lecture_id=l.id LEFT JOIN subjects s ON s.id=l.subject_id WHERE bl.bill_id=? ORDER BY l.lecture_date");
$lectures->execute([$billId]); $lectures=$lectures->fetchAll();

renderHead('Bill Detail');
?>
<div class="app-layout">
<?php renderSidebar('my-bills','teacher',$user); ?>
<div class="main-content">
<?php renderTopbar('Bill Detail', [
    ['label' => 'Home',  'href' => 'dashboard.php'],
    ['label' => 'My Bills', 'href' => 'my-bills.php'],
    ['label' => 'Bill Detail'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="breadcrumb">
        <a href="my-bills.php">My Bills</a><span class="sep">›</span><span>Bill #<?= $billId ?></span>
    </div>

    <div class="d-flex justify-between align-center flex-wrap gap-10 mb-2">
        <div class="page-header" style="margin:0">
            <h1><?= e($bill['month_year']) ?> Bill</h1>
            <p>Submitted <?= fmtDate($bill['submitted_at'],'d F Y, h:i A') ?></p>
        </div>
        <div class="d-flex gap-8">
            <?php if($bill['status']==='approved'): ?>
            <a href="../pdf/generate.php?id=<?= $billId ?>" class="btn btn-success" target="_blank"><?= svgIcon('download') ?> Download PDF</a>
            <?php endif; ?>
            <?php if($bill['status']==='rejected'): ?>
            <a href="generate-bill.php" class="btn btn-primary"><?= svgIcon('refresh') ?> Generate New Bill</a>
            <?php endif; ?>
            <a href="my-bills.php" class="btn btn-outline">← Back</a>
        </div>
    </div>

    <?php if($bill['status']==='rejected' && $bill['rejection_reason']): ?>
    <div class="alert alert-error mb-2"><?= svgIcon('close') ?> <strong>Rejected:</strong> <?= e($bill['rejection_reason']) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
        <!-- Bill Summary -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('list') ?> Bill Summary</h3></div>
            <div class="card-body">
                <table style="font-size:.88rem;width:100%">
                    <tr><td class="text-muted" style="padding:5px 0;width:160px">Bill ID</td><td><strong>#<?= $billId ?></strong></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Month</td><td><?= e($bill['month_year']) ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Period</td><td><?= fmtDate($bill['period_from']) ?> – <?= fmtDate($bill['period_to']) ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Theory Hrs</td><td><?= number_format($bill['total_theory_hrs'],1) ?> @ <?= formatINR($bill['rate_theory']) ?>/hr = <strong><?= formatINR($bill['theory_amount']) ?></strong></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Practical Hrs</td><td><?= number_format($bill['total_practical_hrs'],1) ?> @ <?= formatINR($bill['rate_practical']) ?>/hr = <strong><?= formatINR($bill['practical_amount']) ?></strong></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Other Hrs</td><td><?= number_format($bill['total_other_hrs'],1) ?> @ <?= formatINR($bill['rate_other']) ?>/hr = <strong><?= formatINR($bill['other_amount']) ?></strong></td></tr>
                    <tr style="border-top:1px solid var(--border)"><td class="text-muted" style="padding:8px 0 5px"><strong>Total Amount</strong></td><td><strong style="font-size:1.1rem;color:var(--primary)"><?= formatINR($bill['total_amount']) ?></strong></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Status</td><td><?= statusBadge($bill['status']) ?></td></tr>
                    <?php if($bill['reviewed_at']): ?>
                    <tr><td class="text-muted" style="padding:5px 0">Reviewed</td><td><?= fmtDate($bill['reviewed_at'],'d M Y') ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <!-- Teacher Info -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('teacher') ?> Teacher Info</h3></div>
            <div class="card-body">
                <table style="font-size:.88rem;width:100%">
                    <tr><td class="text-muted" style="padding:5px 0;width:120px">Name</td><td><?= e($bill['tname']) ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Email</td><td><?= e($bill['email']) ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Type</td><td><?= teacherTypeBadge($bill['teacher_type']??'regular') ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Mode</td><td><?= modeBadge($bill['teacher_mode']??'theory') ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Subject</td><td><?= e($bill['subject_name']??'—') ?> <?= $bill['subject_code']?'<span class="badge badge-expert">'.e($bill['subject_code']).'</span>':'' ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Class</td><td><?= e($bill['class_label']??'—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Lecture Breakdown -->
    <div class="card">
        <div class="card-header"><h3><?= svgIcon('calendar') ?> Lecture Breakdown</h3></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Subject</th><th>Theory Hrs</th><th>Practical Hrs</th><th>Other Hrs</th><th>Amount</th></tr></thead>
                <tbody>
                <?php foreach($lectures as $i=>$l):
                    $amt=($l['theory_hours']*$bill['rate_theory'])+($l['practical_hours']*$bill['rate_practical'])+($l['other_hours']*$bill['rate_other']); ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td><?= fmtDate($l['lecture_date']) ?></td>
                    <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code']?'<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>':'' ?></td>
                    <td><?= number_format($l['theory_hours'],1) ?></td>
                    <td><?= number_format($l['practical_hours'],1) ?></td>
                    <td><?= number_format($l['other_hours'],1) ?></td>
                    <td><?= formatINR($amt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:var(--bg-light)">
                    <td colspan="3" style="text-align:right;font-weight:600;padding:11px 14px">Total</td>
                    <td class="fw-600"><?= number_format($bill['total_theory_hrs'],1) ?></td>
                    <td class="fw-600"><?= number_format($bill['total_practical_hrs'],1) ?></td>
                    <td class="fw-600"><?= number_format($bill['total_other_hrs'],1) ?></td>
                    <td class="fw-600"><?= formatINR($bill['total_amount']) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();
$user = currentUser();
$uid  = $user['id'];

$billId = (int)($_GET['id'] ?? 0);

// Scoped to the logged-in student's OWN bill (ownership check)
$bill = $pdo->prepare(
    "SELECT sb.*, u.name AS sname, u.email, u.phone,
            c.label AS class_label, d.name AS dept_name
     FROM student_bills sb
     JOIN users u ON u.id=sb.student_id
     LEFT JOIN classes c ON c.id=u.class_id
     LEFT JOIN departments d ON d.id=u.department_id
     WHERE sb.id=? AND sb.student_id=?"
);
$bill->execute([$billId, $uid]); $bill = $bill->fetch();
if (!$bill) { setFlash('error','Bill not found.'); header('Location: my-bills.php'); exit; }

// Work entries that make up this bill (within the bill period)
$work = $pdo->prepare(
    "SELECT * FROM student_work WHERE student_id=? AND work_date BETWEEN ? AND ? ORDER BY work_date ASC"
);
$work->execute([$bill['student_id'], $bill['period_from'], $bill['period_to']]); $work = $work->fetchAll();

renderHead('Bill Detail');
?>
<div class="app-layout">
<?php renderSidebar('my-bills','student',$user); ?>
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
            <a href="../pdf/student-bill.php?id=<?= $billId ?>" class="btn btn-success" target="_blank"><?= svgIcon('download') ?> Download PDF</a>
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
                    <tr><td class="text-muted" style="padding:5px 0">Total Hours</td><td><?= number_format($bill['total_hours'],1) ?> @ <?= formatINR($bill['rate_per_hour']) ?>/hr</td></tr>
                    <tr style="border-top:1px solid var(--border)"><td class="text-muted" style="padding:8px 0 5px"><strong>Total Amount</strong></td><td><strong style="font-size:1.1rem;color:var(--primary)"><?= formatINR($bill['total_amount']) ?></strong></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Status</td><td><?= statusBadge($bill['status']) ?></td></tr>
                    <?php if($bill['reviewed_at']): ?>
                    <tr><td class="text-muted" style="padding:5px 0">Reviewed</td><td><?= fmtDate($bill['reviewed_at'],'d M Y') ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <!-- Student Info -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('student') ?> Student Info</h3></div>
            <div class="card-body">
                <table style="font-size:.88rem;width:100%">
                    <tr><td class="text-muted" style="padding:5px 0;width:120px">Name</td><td><?= e($bill['sname']) ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Email</td><td><?= e($bill['email'] ?: '—') ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Class</td><td><?= e($bill['class_label'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted" style="padding:5px 0">Department</td><td><?= e($bill['dept_name'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Work Breakdown -->
    <div class="card">
        <div class="card-header"><h3><?= svgIcon('calendar') ?> Work Breakdown</h3></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Hours</th><th>Description</th><th>Amount</th></tr></thead>
                <tbody>
                <?php foreach($work as $i=>$w):
                    $amt = (float)$w['hours'] * (float)$bill['rate_per_hour'];
                ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td><?= fmtDate($w['work_date']) ?></td>
                    <td><?= number_format($w['hours'],1) ?></td>
                    <td class="text-sm text-muted"><?= e($w['description'] ?: '—') ?></td>
                    <td><?= formatINR($amt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:var(--bg-light)">
                    <td colspan="2" style="text-align:right;font-weight:600;padding:11px 14px">Total</td>
                    <td class="fw-600"><?= number_format($bill['total_hours'],1) ?></td>
                    <td></td>
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

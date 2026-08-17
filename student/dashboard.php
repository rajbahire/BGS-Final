<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();
$user = currentUser();
$uid  = $user['id'];

$q = function($sql,$p=[]) use($pdo){ $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };

$totalBills    = (int)$q("SELECT COUNT(*) FROM student_bills WHERE student_id=?",[$uid]);
$pendingBills  = (int)$q("SELECT COUNT(*) FROM student_bills WHERE student_id=? AND status='pending'",[$uid]);
$approvedBills = (int)$q("SELECT COUNT(*) FROM student_bills WHERE student_id=? AND status='approved'",[$uid]);
$totalEarned   = (float)$q("SELECT COALESCE(SUM(total_amount),0) FROM student_bills WHERE student_id=? AND status='approved'",[$uid]);
$hrsThisMonth  = (float)$q("SELECT COALESCE(SUM(hours),0) FROM student_work WHERE student_id=? AND MONTH(work_date)=MONTH(NOW()) AND YEAR(work_date)=YEAR(NOW())",[$uid]);

$recentBills = $pdo->prepare("SELECT * FROM student_bills WHERE student_id=? ORDER BY submitted_at DESC LIMIT 5"); $recentBills->execute([$uid]); $recentBills=$recentBills->fetchAll();
$recentWork  = $pdo->prepare("SELECT * FROM student_work WHERE student_id=? ORDER BY work_date DESC LIMIT 5"); $recentWork->execute([$uid]); $recentWork=$recentWork->fetchAll();

$student = $pdo->prepare("SELECT u.*,c.label AS class_label,d.name AS dept_name FROM users u LEFT JOIN classes c ON c.id=u.class_id LEFT JOIN departments d ON d.id=u.department_id WHERE u.id=?"); $student->execute([$uid]); $student=$student->fetch();

renderHead('Student Dashboard');
?>
<div class="app-layout">
<?php renderSidebar('dashboard','student',$user); ?>
<div class="main-content">
<?php renderTopbar('Dashboard', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => 'Dashboard'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Welcome, <?= e(explode(' ',$user['name'])[0]) ?></h1>
        <p>Earn &amp; Learn Scheme — <?= e($student['class_label']??'') ?> &nbsp;|&nbsp; <?= e($student['dept_name']??'') ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card--blue"><div class="stat-icon blue"><?= svgIcon('all-bills') ?></div><div><div class="stat-label">Total Bills</div><div class="stat-value"><?= $totalBills ?></div></div></div>
        <div class="stat-card stat-card--amber"><div class="stat-icon amber"><?= svgIcon('pending') ?></div><div><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingBills ?></div></div></div>
        <div class="stat-card stat-card--green"><div class="stat-icon green"><?= svgIcon('approved') ?></div><div><div class="stat-label">Approved</div><div class="stat-value"><?= $approvedBills ?></div></div></div>
        <div class="stat-card stat-card--purple"><div class="stat-icon purple"><?= svgIcon('month') ?></div><div><div class="stat-label">Hrs This Month</div><div class="stat-value"><?= number_format($hrsThisMonth,1) ?></div></div></div>
        <div class="stat-card stat-card--orange"><div class="stat-icon orange"><?= svgIcon('fund-requests') ?></div><div><div class="stat-label">Total Earned</div><div class="stat-value sm"><?= formatINR($totalEarned) ?></div></div></div>
    </div>

    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="my-bills.php"      class="btn btn-primary"><?= svgIcon('list') ?> My Bills</a>
        <a href="add-work.php"      class="btn btn-outline"><?= svgIcon('add') ?> Add Work Hours</a>
        <a href="generate-bill.php" class="btn btn-outline"><?= svgIcon('receipt') ?> Generate Bill</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="card">
            <div class="card-header"><h3>Recent Bills</h3><a href="my-bills.php" class="btn btn-outline btn-sm">View All</a></div>
            <?php if($recentBills): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Bill ID</th><th>Month</th><th>Hours</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($recentBills as $b): ?>
                    <tr>
                        <td class="fw-500" style="white-space:nowrap;font-size:.82rem"><?= e($b['bill_number'] ?? generateStudentBillNumber($b['period_from'], $b['id'])) ?></td>
                        <td class="fw-500"><?= e($b['month_year']) ?></td>
                        <td><?= number_format($b['total_hours'],1) ?></td>
                        <td><?= formatINR($b['total_amount']) ?></td>
                        <td><?= statusBadge($b['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No bills yet</h3></div><?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>Recent Work Log</h3><a href="add-work.php" class="btn btn-outline btn-sm">Add Work</a></div>
            <?php if($recentWork): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Hours</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php foreach($recentWork as $w): ?>
                    <tr>
                        <td><?= fmtDate($w['work_date'],'d M') ?></td>
                        <td class="fw-600"><?= number_format($w['hours'],1) ?></td>
                        <td class="text-sm text-muted"><?= e(mb_strimwidth($w['description']??'—',0,40,'…')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('pending') ?></div><h3>No work logged yet</h3></div><?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

$q = function($sql,$p=[]) use($pdo){ $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };

$totalBills   = (int)$q("SELECT COUNT(*) FROM bills WHERE teacher_id=?",[$uid]);
$pendingBills = (int)$q("SELECT COUNT(*) FROM bills WHERE teacher_id=? AND status='pending'",[$uid]);
$approvedBills= (int)$q("SELECT COUNT(*) FROM bills WHERE teacher_id=? AND status='approved'",[$uid]);
$rejectedBills= (int)$q("SELECT COUNT(*) FROM bills WHERE teacher_id=? AND status='rejected'",[$uid]);
$totalEarned  = (float)$q("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE teacher_id=? AND status='approved'",[$uid]);
$lecThisMonth = (float)$q("SELECT COALESCE(SUM(theory_hours+practical_hours+other_hours),0) FROM lectures WHERE teacher_id=? AND MONTH(lecture_date)=MONTH(NOW()) AND YEAR(lecture_date)=YEAR(NOW())",[$uid]);

$recentBills = $pdo->prepare("SELECT * FROM bills WHERE teacher_id=? ORDER BY submitted_at DESC LIMIT 5"); $recentBills->execute([$uid]); $recentBills=$recentBills->fetchAll();
$recentLecs  = $pdo->prepare("SELECT l.*,s.subject_name,s.subject_code FROM lectures l LEFT JOIN subjects s ON s.id=l.subject_id WHERE l.teacher_id=? ORDER BY l.lecture_date DESC LIMIT 5"); $recentLecs->execute([$uid]); $recentLecs=$recentLecs->fetchAll();

// Teacher info
$teacher = $pdo->prepare("SELECT u.*,s.subject_name,s.subject_code,c.label AS class_label FROM users u LEFT JOIN subjects s ON s.id=u.subject_id LEFT JOIN classes c ON c.id=s.class_id WHERE u.id=?"); $teacher->execute([$uid]); $teacher=$teacher->fetch();

renderHead('Teacher Dashboard');
?>
<div class="app-layout">
<?php renderSidebar('dashboard','teacher',$user); ?>
<div class="main-content">
<?php renderTopbar('Dashboard', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => 'Dashboard'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Welcome, <?= e(explode(' ',$user['name'])[0]) ?></h1>
        <p><?= e($teacher['subject_name']??'') ?> <?= $teacher['subject_code']?'<span class="badge badge-expert">'.e($teacher['subject_code']).'</span>':'' ?> &nbsp;<?= teacherTypeBadge($teacher['teacher_type']??'regular') ?> &nbsp;<?= modeBadge($teacher['teacher_mode']??'theory') ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card--amber"><div class="stat-icon amber"><?= svgIcon('pending') ?></div><div><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingBills ?></div></div></div>
        <div class="stat-card stat-card--green"><div class="stat-icon green"><?= svgIcon('approved') ?></div><div><div class="stat-label">Approved</div><div class="stat-value"><?= $approvedBills ?></div></div></div>
        <div class="stat-card stat-card--red"><div class="stat-icon red"><?= svgIcon('rejected') ?></div><div><div class="stat-label">Rejected</div><div class="stat-value"><?= $rejectedBills ?></div></div></div>
        <div class="stat-card stat-card--blue"><div class="stat-icon blue"><?= svgIcon('all-bills') ?></div><div><div class="stat-label">Total Bills</div><div class="stat-value"><?= $totalBills ?></div></div></div>
        <div class="stat-card stat-card--purple"><div class="stat-icon purple"><?= svgIcon('month') ?></div><div><div class="stat-label">Hrs This Month</div><div class="stat-value"><?= number_format($lecThisMonth,1) ?></div></div></div>
        <div class="stat-card stat-card--orange"><div class="stat-icon orange"><?= svgIcon('fund-requests') ?></div><div><div class="stat-label">Total Earned</div><div class="stat-value sm"><?= formatINR($totalEarned) ?></div></div></div>
    </div>

    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="my-bills.php"      class="btn btn-primary"><?= svgIcon('all-bills') ?> My Bills</a>
        <a href="lectures.php"      class="btn btn-outline"><?= svgIcon('lectures') ?> Add Lecture</a>
        <a href="generate-bill.php" class="btn btn-outline"><?= svgIcon('generate-bill') ?> Generate Bill</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="card">
            <div class="card-header"><h3>Recent Bills</h3><a href="my-bills.php" class="btn btn-outline btn-sm">View All</a></div>
            <?php if($recentBills): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Month</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($recentBills as $b): ?>
                    <tr>
                        <td class="fw-500"><?= e($b['month_year']) ?></td>
                        <td><?= formatINR($b['total_amount']) ?></td>
                        <td><?= statusBadge($b['status']) ?></td>
                        <td><a href="bill-detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No bills yet</h3></div><?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>Recent Lectures</h3><a href="lectures.php" class="btn btn-outline btn-sm">Manage</a></div>
            <?php if($recentLecs): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Subject</th><th>T hrs</th><th>P hrs</th></tr></thead>
                    <tbody>
                    <?php foreach($recentLecs as $l): ?>
                    <tr>
                        <td><?= fmtDate($l['lecture_date'],'d M') ?></td>
                        <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code']?'<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>':'' ?></td>
                        <td><?= number_format($l['theory_hours'],1) ?></td>
                        <td><?= number_format($l['practical_hours'],1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('calendar') ?></div><h3>No lectures recorded</h3></div><?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

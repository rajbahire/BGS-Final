<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// One-line helper for scalar counts/sums.
$q = function($sql, $p=[]) use($pdo){ $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };

// Approval-flow bills (the ones the HOD actively approves / rejects): teacher + student bills.
// other_bills are created already-finalized, so they're tracked separately as $otherBills.
$pending    = (int)$q("SELECT COUNT(*) FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='pending'  AND u.department_id=?",[$deptId])
           + (int)$q("SELECT COUNT(*) FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='pending'  AND u.department_id=?",[$deptId]);
$approved   = (int)$q("SELECT COUNT(*) FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='approved' AND u.department_id=?",[$deptId])
           + (int)$q("SELECT COUNT(*) FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='approved' AND u.department_id=?",[$deptId]);
$rejected   = (int)$q("SELECT COUNT(*) FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='rejected' AND u.department_id=?",[$deptId])
           + (int)$q("SELECT COUNT(*) FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='rejected' AND u.department_id=?",[$deptId]);
$otherBills = (int)$q("SELECT COUNT(*) FROM other_bills WHERE department_id=?",[$deptId]);

$teachers = (int)$q("SELECT COUNT(*) FROM users WHERE role='teacher' AND department_id=? AND is_active=1",[$deptId]);
$students = (int)$q("SELECT COUNT(*) FROM users WHERE role='student' AND department_id=? AND is_active=1",[$deptId]);

// Department outflows — every finalized/approved bill amount. Includes other_bills (finalized
// on creation, so the money is always disbursed) alongside the approved teacher + student
// bills the HOD signed off on. Teacher/student use reviewed_at; other_bills use created_at.
$totalPaid = (float)$q("SELECT COALESCE(SUM(b.total_amount),0) FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='approved' AND u.department_id=?",[$deptId])
           + (float)$q("SELECT COALESCE(SUM(sb.total_amount),0) FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='approved' AND u.department_id=?",[$deptId])
           + (float)$q("SELECT COALESCE(SUM(total_amount),0) FROM other_bills WHERE department_id=?",[$deptId]);
$monthPaid = (float)$q("SELECT COALESCE(SUM(b.total_amount),0) FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='approved' AND u.department_id=? AND MONTH(b.reviewed_at)=MONTH(NOW()) AND YEAR(b.reviewed_at)=YEAR(NOW())",[$deptId])
           + (float)$q("SELECT COALESCE(SUM(sb.total_amount),0) FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='approved' AND u.department_id=? AND MONTH(sb.reviewed_at)=MONTH(NOW()) AND YEAR(sb.reviewed_at)=YEAR(NOW())",[$deptId])
           + (float)$q("SELECT COALESCE(SUM(total_amount),0) FROM other_bills WHERE department_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())",[$deptId]);

$pendingBills = $pdo->prepare(
    "SELECT b.*, u.name AS tname, u.teacher_type FROM bills b
     JOIN users u ON u.id=b.teacher_id
     WHERE b.status='pending' AND u.department_id=?
     ORDER BY b.submitted_at ASC LIMIT 6"
); $pendingBills->execute([$deptId]); $pendingBills = $pendingBills->fetchAll();

$recentActivity = $pdo->query(
    "SELECT a.*, u.name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id
     WHERE a.action NOT IN ('login','logout')
     ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

renderHead('HOD Dashboard');
?>
<div class="app-layout">
<?php renderSidebar('dashboard','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Dashboard', [
    ['label' => 'Home',       'href' => 'dashboard.php'],
    ['label' => 'Dashboard'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome, <?= e($user['name']) ?> — <?= e($user['dept_name'] ?: 'Department') ?> — <?= date('F Y') ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card--amber"><div class="stat-icon amber"><?= svgIcon('pending') ?></div><div><div class="stat-label">Pending Requests</div><div class="stat-value"><?= $pending ?></div></div></div>
        <div class="stat-card stat-card--green"><div class="stat-icon green"><?= svgIcon('approved') ?></div><div><div class="stat-label">Approved Bills</div><div class="stat-value"><?= $approved ?></div></div></div>
        <div class="stat-card stat-card--red"><div class="stat-icon red"><?= svgIcon('rejected') ?></div><div><div class="stat-label">Rejected</div><div class="stat-value"><?= $rejected ?></div></div></div>
        <div class="stat-card stat-card--teal"><div class="stat-icon teal"><?= svgIcon('other-bills') ?></div><div><div class="stat-label">Other Bills</div><div class="stat-value"><?= $otherBills ?></div></div></div>
        <div class="stat-card stat-card--blue"><div class="stat-icon blue"><?= svgIcon('teacher') ?></div><div><div class="stat-label">Teachers</div><div class="stat-value"><?= $teachers ?></div></div></div>
        <div class="stat-card stat-card--purple"><div class="stat-icon purple"><?= svgIcon('student') ?></div><div><div class="stat-label">E&amp;L Students</div><div class="stat-value"><?= $students ?></div></div></div>
        <div class="stat-card stat-card--orange"><div class="stat-icon orange"><?= svgIcon('fund-requests') ?></div><div><div class="stat-label">This Month Paid</div><div class="stat-value sm"><?= formatINR($monthPaid) ?></div></div></div>
        <div class="stat-card stat-card--orange"><div class="stat-icon orange"><?= svgIcon('distributed') ?></div><div><div class="stat-label">Total Disbursed</div><div class="stat-value sm"><?= formatINR($totalPaid) ?></div></div></div>
    </div>

    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="requests.php" class="btn btn-primary">
            <?= svgIcon('pending') ?> Pending Requests
            <?php if($pending): ?><span style="background:#EF4444;color:#fff;font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:20px"><?= $pending ?></span><?php endif; ?>
        </a>
        <a href="manage-users.php" class="btn btn-outline"><?= svgIcon('manage-users') ?> Manage Users</a>
        <a href="all-bills.php"    class="btn btn-outline"><?= svgIcon('all-bills') ?> All Bills</a>
        <a href="other-bills.php"  class="btn btn-outline"><?= svgIcon('other-bills') ?> Other Bills</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="card">
            <div class="card-header"><h3>Pending Requests</h3><a href="requests.php" class="btn btn-outline btn-sm">View All</a></div>
            <?php if($pendingBills): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Teacher</th><th>Type</th><th>Month</th><th>Amount</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach($pendingBills as $b): ?>
                    <tr>
                        <td class="fw-500"><?= e($b['tname']) ?></td>
                        <td><?= teacherTypeBadge($b['teacher_type']??'regular') ?></td>
                        <td><?= e($b['month_year']) ?></td>
                        <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                        <td><a href="request-detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('check') ?></div><h3>No pending requests</h3><p>All caught up!</p></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>Recent Activity</h3></div>
            <?php $icons=['login'=>svgIcon('login'),'logout'=>svgIcon('logout'),'submit_bill'=>svgIcon('upload'),'submit_student_bill'=>svgIcon('upload'),'approve_bill'=>svgIcon('approved'),'reject_bill'=>svgIcon('rejected'),'add_lecture'=>svgIcon('calendar'),'add_teacher'=>svgIcon('add-user'),'add_student'=>svgIcon('add-user'),'add_subject'=>svgIcon('subjects'),'edit_subject'=>svgIcon('edit'),'delete_subject'=>svgIcon('delete'),'add_class'=>svgIcon('classes'),'edit_class'=>svgIcon('edit'),'delete_class'=>svgIcon('delete'),'edit_user'=>svgIcon('edit'),'deactivate_user'=>svgIcon('delete'),'activate_user'=>svgIcon('approved'),'delete_user'=>svgIcon('delete'),'manual_bill'=>svgIcon('manual-bill'),'fund_request'=>svgIcon('fund-requests'),'create_other_bill'=>svgIcon('other-bills'),'add_timetable'=>svgIcon('timetable'),'approve_fund'=>svgIcon('approved'),'reject_fund'=>svgIcon('rejected'),'approve_student_bill'=>svgIcon('approved'),'reject_student_bill'=>svgIcon('rejected')]; ?>
            <?php if($recentActivity): ?>
            <?php foreach($recentActivity as $a): $icon=$icons[$a['action']]??svgIcon('list'); ?>
            <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 1.3rem;border-bottom:1px solid var(--border)">
                <div style="width:30px;height:30px;background:var(--bg);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0"><?= $icon ?></div>
                <div>
                    <div class="fw-500 text-sm"><?= e($a['name']??'System') ?></div>
                    <div class="text-muted text-xs"><?= e($a['description']?:$a['action']) ?></div>
                    <div class="text-xs" style="color:var(--light)"><?= fmtDate($a['created_at'],'d M, h:i A') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('list') ?></div><h3>No activity yet</h3></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

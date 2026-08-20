<?php
// ============================================================
//  admin/dashboard.php — Super Admin Dashboard
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

// Stats
$totalDepts   = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE is_active=1")->fetchColumn();
$totalHODs    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='hod' AND is_active=1")->fetchColumn();
$totalTeachers= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn();
$totalStudents= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
$pendingFunds = (int)$pdo->query("SELECT COUNT(*) FROM fund_requests WHERE status='pending'")->fetchColumn();
$approvedFunds= (int)$pdo->query("SELECT COUNT(*) FROM fund_requests WHERE status='approved'")->fetchColumn();
$totalDisbursed=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fund_requests WHERE status='approved'")->fetchColumn();

// Approved / finalized bills across ALL three bill tables, so the "Approved Bills"
// count matches the All Bills page:
//   • teacher bills with status='approved'
//   • Earn & Learn student bills with status='approved'
//   • all other_bills (no approval workflow — every row is finalized on creation)
$totalBills   = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='approved'")->fetchColumn()
              + (int)$pdo->query("SELECT COUNT(*) FROM student_bills WHERE status='approved'")->fetchColumn()
              + (int)$pdo->query("SELECT COUNT(*) FROM other_bills")->fetchColumn();

// Total billed amount across the same three tables (approved teacher + approved student + all other)
$totalBilled  = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE status='approved'")->fetchColumn()
              + (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM student_bills WHERE status='approved'")->fetchColumn()
              + (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM other_bills")->fetchColumn();

// Recent fund requests
$recentFunds = $pdo->query(
    "SELECT fr.*, u.name AS hod_name, d.name AS dept_name
     FROM fund_requests fr
     JOIN users u ON u.id=fr.hod_id
     JOIN departments d ON d.id=fr.department_id
     ORDER BY fr.requested_at DESC LIMIT 6"
)->fetchAll();

// Recent activity
$activity = $pdo->query(
    "SELECT a.*, u.name FROM activity_log a
     LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

renderHead('Admin Dashboard');
?>
<div class="app-layout">
<?php renderSidebar('dashboard', 'admin', $user); ?>
<div class="main-content">
<?php renderTopbar('Admin Dashboard', [
    ['label' => 'Home',     'href' => 'dashboard.php'],
    ['label' => 'Dashboard'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= e($user['name']) ?> — System overview for <?= date('F Y') ?></p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-card--blue">
            <div class="stat-icon blue"><?= svgIcon('departments') ?></div>
            <div><div class="stat-label">Departments</div><div class="stat-value"><?= $totalDepts ?></div></div>
        </div>
        <div class="stat-card stat-card--purple">
            <div class="stat-icon purple"><?= svgIcon('manage-hods') ?></div>
            <div><div class="stat-label">Active HODs</div><div class="stat-value"><?= $totalHODs ?></div></div>
        </div>
        <div class="stat-card stat-card--indigo">
            <div class="stat-icon indigo"><?= svgIcon('teacher') ?></div>
            <div><div class="stat-label">Teachers</div><div class="stat-value"><?= $totalTeachers ?></div></div>
        </div>
        <div class="stat-card stat-card--teal">
            <div class="stat-icon teal"><?= svgIcon('student') ?></div>
            <div><div class="stat-label">E&L Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
        </div>
        <div class="stat-card stat-card--amber">
            <div class="stat-icon amber"><?= svgIcon('pending') ?></div>
            <div><div class="stat-label">Pending Fund Req.</div><div class="stat-value"><?= $pendingFunds ?></div></div>
        </div>
        <div class="stat-card stat-card--green">
            <div class="stat-icon green"><?= svgIcon('approved') ?></div>
            <div><div class="stat-label">Approved Bills</div><div class="stat-value"><?= $totalBills ?></div></div>
        </div>
        <div class="stat-card stat-card--red">
            <div class="stat-icon red"><?= svgIcon('receipt') ?></div>
            <div><div class="stat-label">Total Billed</div><div class="stat-value sm"><?= formatINR($totalBilled) ?></div></div>
        </div>
        <div class="stat-card stat-card--orange">
            <div class="stat-icon orange"><?= svgIcon('fund-requests') ?></div>
            <div><div class="stat-label">Total Disbursed</div><div class="stat-value sm"><?= formatINR($totalDisbursed) ?></div></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="fund-requests.php"    class="btn btn-primary">
            <?= svgIcon('fund-requests') ?> Fund Requests
            <?php if ($pendingFunds): ?>
            <span style="background:#EF4444;color:#fff;font-size:.68rem;font-weight:700;
                  padding:1px 6px;border-radius:20px"><?= $pendingFunds ?></span>
            <?php endif; ?>
        </a>
        <a href="all-bills.php"        class="btn btn-outline"><?= svgIcon('all-bills') ?> All Bills</a>
        <a href="departments.php"   class="btn btn-outline"><?= svgIcon('departments') ?> Departments</a>
        <a href="classes.php"       class="btn btn-outline"><?= svgIcon('classes') ?> Classes</a>
        <a href="subjects.php"      class="btn btn-outline"><?= svgIcon('subjects') ?> Subjects</a>
        <a href="manage-hods.php"   class="btn btn-outline"><?= svgIcon('manage-hods') ?> Manage HODs</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        <!-- Fund Requests -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Fund Requests</h3>
                <a href="fund-requests.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if ($recentFunds): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>HOD</th><th>Department</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFunds as $fr): ?>
                    <tr>
                        <td class="fw-500"><?= e($fr['hod_name']) ?></td>
                        <td><?= e($fr['dept_name']) ?></td>
                        <td class="fw-600"><?= formatINR($fr['amount']) ?></td>
                        <td><?= statusBadge($fr['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('fund-requests') ?></div><h3>No fund requests yet</h3></div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header"><h3>Recent Activity</h3></div>
            <?php if ($activity): ?>
            <div>
                <?php
                $icons = ['login'=>svgIcon('login'),'logout'=>svgIcon('logout'),'submit_bill'=>svgIcon('upload'),'submit_student_bill'=>svgIcon('upload'),
                          'approve_bill'=>svgIcon('approved'),'reject_bill'=>svgIcon('rejected'),'create_other_bill'=>svgIcon('other-bills'),
                          'add_lecture'=>svgIcon('calendar'),'add_teacher'=>svgIcon('add-user'),'add_hod'=>svgIcon('add-user'),'add_student'=>svgIcon('add-user'),
                          'edit_hod'=>svgIcon('edit'),'deactivate_hod'=>svgIcon('delete'),'delete_hod'=>svgIcon('delete'),'activate_hod'=>svgIcon('approved'),
                          'add_subject'=>svgIcon('subjects'),'edit_subject'=>svgIcon('edit'),'delete_subject'=>svgIcon('delete'),
                          'add_class'=>svgIcon('classes'),'edit_class'=>svgIcon('edit'),'delete_class'=>svgIcon('delete'),
                          'add_department'=>svgIcon('departments'),'edit_department'=>svgIcon('edit'),'delete_department'=>svgIcon('delete'),
                          'approve_fund'=>svgIcon('approved'),'reject_fund'=>svgIcon('rejected'),
                          'add_work'=>svgIcon('add-work')];
                foreach ($activity as $a):
                    $icon = $icons[$a['action']] ?? svgIcon('list');
                ?>
                <div style="display:flex;gap:10px;align-items:flex-start;
                            padding:9px 1.3rem;border-bottom:1px solid var(--border)">
                    <div style="width:30px;height:30px;background:var(--bg);border-radius:7px;
                                display:flex;align-items:center;justify-content:center;
                                font-size:.88rem;flex-shrink:0"><?= $icon ?></div>
                    <div>
                        <div class="fw-500" style="font-size:.82rem"><?= e($a['name'] ?? 'System') ?></div>
                        <div class="text-muted text-xs"><?= e($a['description'] ?: $a['action']) ?></div>
                        <div class="text-xs" style="color:var(--light)"><?= fmtDate($a['created_at'],'d M, h:i A') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('list') ?></div><h3>No activity yet</h3></div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

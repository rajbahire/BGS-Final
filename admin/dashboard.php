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
$pendingBillsCount = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='pending'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM student_bills WHERE status='pending'")->fetchColumn();
$rejectedBillsCount= (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='rejected'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM student_bills WHERE status='rejected'")->fetchColumn();

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

// Recent bills (latest 6 across all bill types)
$recentBills = $pdo->query(
    "(SELECT 'teacher' AS source, b.id, b.bill_number, b.period_from, b.total_amount, b.month_year,
             u.name AS pname, u.teacher_type, COALESCE(b.submitted_at, b.created_at) AS sort_date
      FROM bills b JOIN users u ON u.id=b.teacher_id WHERE b.status='approved'
      ORDER BY sort_date DESC LIMIT 6)
     UNION ALL
     (SELECT 'student' AS source, sb.id, sb.bill_number, sb.period_from, sb.total_amount, sb.month_year,
             u.name AS pname, NULL AS teacher_type, sb.submitted_at AS sort_date
      FROM student_bills sb JOIN users u ON u.id=sb.student_id WHERE sb.status='approved'
      ORDER BY sort_date DESC LIMIT 6)
     UNION ALL
     (SELECT 'other' AS source, ob.id, ob.bill_number, ob.bill_date AS period_from, ob.total_amount, DATE_FORMAT(ob.bill_date,'%M %Y') AS month_year,
             ob.claimant_name AS pname, ob.bill_type AS teacher_type, ob.created_at AS sort_date
      FROM other_bills ob ORDER BY ob.created_at DESC LIMIT 6)
     ORDER BY sort_date DESC LIMIT 6"
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
            <div><div class="stat-label">Pending Bills</div><div class="stat-value"><?= $pendingBillsCount ?></div></div>
        </div>
        <div class="stat-card stat-card--green">
            <div class="stat-icon green"><?= svgIcon('approved') ?></div>
            <div><div class="stat-label">Approved Bills</div><div class="stat-value"><?= $totalBills ?></div></div>
        </div>
        <div class="stat-card stat-card--red">
            <div class="stat-icon red"><?= svgIcon('rejected') ?></div>
            <div><div class="stat-label">Rejected Bills</div><div class="stat-value"><?= $rejectedBillsCount ?></div></div>
        </div>
        <div class="stat-card stat-card--orange">
            <div class="stat-icon orange"><?= svgIcon('distributed') ?></div>
            <div><div class="stat-label">Total Billed</div><div class="stat-value sm"><?= formatINR($totalBilled) ?></div></div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="all-bills.php"        class="btn btn-primary"><?= svgIcon('all-bills') ?> All Bills</a>
        <a href="departments.php"   class="btn btn-outline"><?= svgIcon('departments') ?> Departments</a>
        <a href="classes.php"       class="btn btn-outline"><?= svgIcon('classes') ?> Classes</a>
        <a href="subjects.php"      class="btn btn-outline"><?= svgIcon('subjects') ?> Subjects</a>
        <a href="manage-hods.php"   class="btn btn-outline"><?= svgIcon('manage-hods') ?> Manage HODs</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        <!-- Recent Bills -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Bills</h3>
                <a href="all-bills.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if ($recentBills): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Month</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentBills as $rb):
                        if ($rb['source'] === 'student') {
                            $badge = '<span class="badge" style="background:#F0FDFA;color:#0F766E;border:1px solid #99F6E4">Earn & Learn</span>';
                        } elseif ($rb['source'] === 'other') {
                            $typeLabels = ['practical'=>'Practical','earn_learn'=>'Earn & Learn','seminar'=>'Seminar'];
                            $badge = '<span class="badge" style="background:#FFF7ED;color:#C2410C;border:1px solid #FDBA74">' . e($typeLabels[$rb['teacher_type']] ?? ucfirst($rb['teacher_type'] ?? 'Other')) . '</span>';
                        } else {
                            $badge = teacherTypeBadge($rb['teacher_type'] ?? 'regular');
                        }
                    ?>
                    <tr>
                        <td class="fw-500">
                            <div><?= e($rb['pname']) ?></div>
                            <?php
                            $billNum = null;
                            if ($rb['source'] === 'teacher') {
                                $billNum = $rb['bill_number'] ?? generateTeacherBillNumber($rb['period_from'], $rb['id']);
                            } elseif ($rb['source'] === 'student') {
                                $billNum = $rb['bill_number'] ?? generateStudentBillNumber($rb['period_from'], $rb['id']);
                            } elseif ($rb['source'] === 'other') {
                                $billNum = $rb['bill_number'] ?? generateOtherBillNumber($rb['period_from'], $rb['id']);
                            }
                            ?>
                            <?php if ($billNum): ?>
                            <div class="text-xs fw-600" style="color:var(--primary);margin-top:2px"><?= e($billNum) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $badge ?></td>
                        <td><?= e($rb['month_year']) ?></td>
                        <td class="fw-600"><?= formatINR($rb['total_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon"><?= svgIcon('all-bills') ?></div><h3>No bills yet</h3></div>
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

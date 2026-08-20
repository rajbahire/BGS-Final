<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

// Admin sees every department, so there is no dept scoping by default.
// Filters — note the Department filter replaces the HOD page's single-dept Teacher filter.
$fStatus  = $_GET['status']  ?? '';
$fType    = $_GET['type']    ?? '';          // '' | teacher | student | other
$fDept    = (int)($_GET['dept']    ?? 0);
$fMonth   = (int)($_GET['month']   ?? 0);
$fYear    = (int)($_GET['year']    ?? 0);

$typeLabels = ['practical'=>'Practical Exam','earn_learn'=>'Earn & Learn','seminar'=>'Seminar'];

// ── Build a single, normalized list drawn from all three bill tables ──
// Each row is tagged with a `source` so the view can render it uniformly.
$rows = [];

// 1) Teacher bills (bills)
if ($fType === '' || $fType === 'teacher') {
    $sql = "SELECT b.id, b.month_year, b.period_from,
                   b.total_theory_hrs, b.total_practical_hrs, b.total_other_hrs,
                   b.total_amount, b.status, b.submitted_at, b.created_at,
                   u.name AS pname, u.teacher_type, d.name AS dept_name,
                   COALESCE(b.submitted_at, b.created_at) AS sort_date
            FROM bills b
            JOIN users u ON u.id=b.teacher_id
            LEFT JOIN departments d ON d.id=u.department_id
            WHERE 1=1";
    $params = [];
    if ($fDept)   { $sql .= " AND u.department_id=?";      $params[] = $fDept;   }
    if ($fStatus) { $sql .= " AND b.status=?";            $params[] = $fStatus; }
    if ($fMonth)  { $sql .= " AND MONTH(b.period_from)=?"; $params[] = $fMonth;  }
    if ($fYear)   { $sql .= " AND YEAR(b.period_from)=?";  $params[] = $fYear;   }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll() as $b) {
        $rows[] = [
            'source'    => 'teacher',
            'dept'      => $b['dept_name'] ?? '—',
            'name'      => $b['pname'],
            'sub'       => teacherTypeBadge($b['teacher_type'] ?? 'regular'),
            'period'    => e($b['month_year']),
            'hours'     => (float)$b['total_theory_hrs'] + (float)$b['total_practical_hrs'] + (float)$b['total_other_hrs'],
            'amount'    => (float)$b['total_amount'],
            'status'    => $b['status'],
            'date'      => $b['submitted_at'],
            'sort_date' => $b['sort_date'],
            'pdf'       => '../pdf/generate.php?id=' . $b['id'],
            'pdf_show'  => $b['status'] === 'approved',
        ];
    }
}

// 2) Earn & Learn student bills (student_bills)
if ($fType === '' || $fType === 'student') {
    $sql = "SELECT sb.id, sb.bill_number, sb.month_year, sb.period_from, sb.total_hours,
                   sb.total_amount, sb.status, sb.submitted_at,
                   u.name AS pname, c.label AS class_label, d.name AS dept_name,
                   COALESCE(sb.submitted_at, '1970-01-01 00:00:00') AS sort_date
            FROM student_bills sb
            JOIN users u ON u.id=sb.student_id
            LEFT JOIN classes c ON c.id=u.class_id
            LEFT JOIN departments d ON d.id=u.department_id
            WHERE 1=1";
    $params = [];
    if ($fDept)   { $sql .= " AND u.department_id=?";       $params[] = $fDept;   }
    if ($fStatus) { $sql .= " AND sb.status=?";            $params[] = $fStatus; }
    if ($fMonth) { $sql .= " AND MONTH(sb.period_from)=?"; $params[] = $fMonth;  }
    if ($fYear)  { $sql .= " AND YEAR(sb.period_from)=?";   $params[] = $fYear;   }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll() as $b) {
        $sbBillNum = $b['bill_number'] ?? generateStudentBillNumber($b['period_from'], $b['id']);
        $rows[] = [
            'source'    => 'student',
            'dept'      => $b['dept_name'] ?? '—',
            'name'      => $b['pname'],
            'sub'       => '<span class="text-sm fw-500" style="color:var(--primary)">' . e($sbBillNum) . '</span> <span class="text-sm text-muted">' . e($b['class_label'] ?: '—') . '</span>',
            'period'    => e($b['month_year']),
            'hours'     => (float)$b['total_hours'],
            'amount'    => (float)$b['total_amount'],
            'status'    => $b['status'],
            'date'      => $b['submitted_at'],
            'sort_date' => $b['sort_date'],
            'pdf'       => '../pdf/student-bill.php?id=' . $b['id'],
            'pdf_show'  => $b['status'] === 'approved',
        ];
    }
}

// 3) HOD-created other bills (other_bills) — no approval workflow, always finalized
if ($fType === '' || $fType === 'other') {
    $sql = "SELECT ob.id, ob.bill_type, ob.title, ob.claimant_name, ob.bill_date,
                   ob.total_amount, ob.created_at, d.name AS dept_name
            FROM other_bills ob
            LEFT JOIN departments d ON d.id=ob.department_id
            WHERE 1=1";
    $params = [];
    if ($fDept)   { $sql .= " AND ob.department_id=?"; $params[] = $fDept;  }
    if ($fMonth)  { $sql .= " AND MONTH(ob.bill_date)=?"; $params[] = $fMonth; }
    if ($fYear)   { $sql .= " AND YEAR(ob.bill_date)=?";  $params[] = $fYear;  }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll() as $b) {
        $otype = $typeLabels[$b['bill_type']] ?? ucfirst($b['bill_type']);
        $rows[] = [
            'source'    => 'other',
            'dept'      => $b['dept_name'] ?? '—',
            'name'      => $b['claimant_name'],
            'sub'       => '<span class="text-sm text-muted">' . e($otype . ' — ' . $b['title']) . '</span>',
            'period'    => fmtDate($b['bill_date'], 'M Y'),
            'hours'     => null,
            'amount'    => (float)$b['total_amount'],
            'status'    => 'finalized',
            'date'      => $b['created_at'],
            'sort_date' => $b['created_at'],
            'pdf'       => '../pdf/other-bill.php?id=' . $b['id'],
            'pdf_show'  => true,
        ];
    }
}

// Newest first across all sources
usort($rows, function ($a, $b) { return strcmp($b['sort_date'], $a['sort_date']); });

// Pagination config
$perPage = 15;
$page    = currentPage();
$offset  = paginationOffset($page, $perPage);

$totalRecords = count($rows);
$totalPages   = totalPages($totalRecords, $perPage);

// Paginate the merged array
$rows = array_slice($rows, $offset, $perPage);

$departments = $pdo->query("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$totalAmt = array_sum(array_column($rows, 'amount'));

renderHead('All Bills');
?>
<div class="app-layout">
<?php renderSidebar('all-bills','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('All Bills', [
    ['label' => 'Home',      'href' => 'dashboard.php'],
    ['label' => 'All Bills'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>All Bills</h1><p>Teacher, Earn & Learn student, and other bills across all departments</p></div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:1.2rem">
        <div class="card-body" style="padding:.9rem">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="margin:0">
                    <label>Type</label>
                    <select name="type" class="form-control" style="width:200px">
                        <option value="">All Bills</option>
                        <option value="teacher" <?= $fType==='teacher'?'selected':'' ?>>Teacher Bills</option>
                        <option value="student" <?= $fType==='student'?'selected':'' ?>>Earn & Learn (Students)</option>
                        <option value="other"   <?= $fType==='other'  ?'selected':'' ?>>Other Bills</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Status</label>
                    <select name="status" class="form-control" style="width:180px">
                        <option value="">All</option>
                        <option value="draft"    <?= $fStatus==='draft'   ?'selected':'' ?>>Draft</option>
                        <option value="pending"  <?= $fStatus==='pending' ?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $fStatus==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $fStatus==='rejected'?'selected':'' ?>>Rejected</option>
                        <option value="finalized" <?= $fStatus==='finalized'?'selected':'' ?>>Finalized</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Department</label>
                    <select name="dept" class="form-control" style="width:200px">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $fDept==$dept['id']?'selected':'' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Month</label>
                    <select name="month" class="form-control" style="width:180px">
                        <option value="">All Months</option>
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>" <?= $fMonth==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Year</label>
                    <select name="year" class="form-control" style="width:180px">
                        <option value="">All</option>
                        <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
                        <option value="<?= $y ?>" <?= $fYear==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 10px 20px;">Filter</button>
                <a href="all-bills.php" class="btn btn-outline btn-sm" style="padding: 7px 15px;">Clear</a>
            </form>
        </div>
    </div>

    <?php if($rows): ?>
    <div class="d-flex gap-10 align-center mb-2 text-sm text-muted">
        <span>Showing <strong style="color:var(--text)"><?= count($rows) ?></strong> bill<?= count($rows)!=1?'s':'' ?></span>
        <span>·</span>
        <span>Total: <strong style="color:var(--text)"><?= formatINR($totalAmt) ?></strong></span>
    </div>
    <?php endif; ?>

    <div class="card">
        <?php if($rows): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Type</th><th>Department</th><th>Payee / Title</th><th>Period</th><th>Hours</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($rows as $i => $r): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><?= billTypeBadge($r['source']) ?></td>
                    <td class="fw-500"><?= e($r['dept']) ?></td>
                    <td>
                        <div class="fw-500"><?= e($r['name']) ?></div>
                        <div><?= $r['sub'] ?></div>
                    </td>
                    <td class="fw-500"><?= $r['period'] ?></td>
                    <td><?= $r['hours'] === null ? '<span class="text-muted">—</span>' : number_format($r['hours'],1) ?></td>
                    <td class="fw-600"><?= formatINR($r['amount']) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($r['date'],'d M Y') ?></td>
                    <td>
                        <?php if(!empty($r['pdf_show'])): ?>
                        <a href="<?= $r['pdf'] ?>" class="btn btn-success btn-sm" target="_blank">PDF</a>
                        <?php else: ?>
                        <span class="text-muted text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="icon"><?= svgIcon('check') ?></div><h3>No bills found</h3><p>Try adjusting your filters.</p></div>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
            <?= renderPagination($page, $totalPages, $totalRecords, $perPage, $offset, 'bills', 'All bills pagination') ?>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter();

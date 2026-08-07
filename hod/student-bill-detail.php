<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];
$billId = (int)($_GET['id'] ?? 0);

// Where we arrived from — All Bills (full history) or Pending Requests (review queue).
$from     = $_GET['from'] ?? 'requests';
$listPage = $from === 'all-bills' ? 'all-bills.php' : 'requests.php';
$listLbl  = $from === 'all-bills' ? 'All Bills' : 'Pending Requests';

// Fetch this student bill — scoped to the HOD's own department (ownership check)
$bill = $pdo->prepare(
    "SELECT sb.*, u.name AS sname, u.email, u.phone,
            c.label AS class_label, d.name AS dept_name
     FROM student_bills sb
     JOIN users u ON u.id=sb.student_id
     LEFT JOIN classes c ON c.id=u.class_id
     LEFT JOIN departments d ON d.id=u.department_id
     WHERE sb.id=? AND u.department_id=?"
);
$bill->execute([$billId, $deptId]);
$bill = $bill->fetch();
if (!$bill) { setFlash('error','Bill not found.'); header('Location: ' . $listPage); exit; }

// Work entries that make up this bill (within the bill period)
$work = $pdo->prepare(
    "SELECT * FROM student_work WHERE student_id=? AND work_date BETWEEN ? AND ? ORDER BY work_date ASC"
);
$work->execute([$bill['student_id'], $bill['period_from'], $bill['period_to']]);
$work = $work->fetchAll();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_student') {
        $pdo->prepare("UPDATE student_bills SET status='approved',reviewed_at=NOW(),reviewed_by=? WHERE id=?")
            ->execute([$user['id'], $billId]);
        logActivity($pdo,$user['id'],'approve_student_bill',"Approved Earn & Learn bill #$billId for {$bill['sname']}");
        setFlash('success',"Earn & Learn bill #$billId approved successfully.");
        header('Location: ' . $listPage); exit;
    }
    if ($action === 'reject_student') {
        $reason = trim($_POST['reason'] ?? '');
        if (!$reason) { setFlash('error','Please provide a rejection reason.'); header("Location: student-bill-detail.php?id=$billId&from=$from"); exit; }
        $pdo->prepare("UPDATE student_bills SET status='rejected',rejection_reason=?,reviewed_at=NOW(),reviewed_by=? WHERE id=?")
            ->execute([$reason, $user['id'], $billId]);
        logActivity($pdo,$user['id'],'reject_student_bill',"Rejected Earn & Learn bill #$billId: $reason");
        setFlash('success',"Earn & Learn bill #$billId rejected.");
        header('Location: ' . $listPage); exit;
    }
}

renderHead('Review Earn & Learn Bill');
?>
<div class="app-layout">
<?php renderSidebar('requests','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Review Earn & Learn Bill', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => $listLbl,  'href' => $listPage],
    ['label' => 'Review Bill'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="breadcrumb">
        <a href="<?= $listPage ?>"><?= $listLbl ?></a>
        <span class="sep">›</span>
        <span>Earn & Learn Bill #<?= $billId ?></span>
    </div>

    <div class="d-flex justify-between align-center flex-wrap gap-10 mb-2">
        <div class="page-header" style="margin:0">
            <h1><?= e($bill['month_year']) ?> — <?= e($bill['sname']) ?></h1>
            <p>Submitted <?= fmtDate($bill['submitted_at'],'d F Y, h:i A') ?></p>
        </div>
        <a href="<?= $listPage ?>" class="btn btn-outline">← Back</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

        <!-- Left: Bill Details -->
        <div>
            <!-- Summary boxes -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
                <?php
                $summaries = [
                    ['Total Hours',  number_format($bill['total_hours'],1), '#F0FDFA','#0F766E'],
                    ['Rate / Hour',  formatINR($bill['rate_per_hour']),       '#EFF6FF','#1D4ED8'],
                    ['Work Days',    count($work),                            '#F5F3FF','#6D28D9'],
                    ['Total Amount', formatINR($bill['total_amount']),         '#244B86','#E2C97E'],
                ];
                foreach($summaries as [$lbl,$val,$bg,$clr]):
                ?>
                <div style="background:<?= $bg ?>;border-radius:var(--radius);padding:1rem;text-align:center">
                    <div style="font-size:.7rem;font-weight:500;text-transform:uppercase;letter-spacing:.05em;color:<?= $clr ?>;opacity:.8;margin-bottom:4px"><?= $lbl ?></div>
                    <div style="font-size:1.3rem;font-weight:600;color:<?= $clr ?>"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Student Info -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-header"><h3><?= svgIcon('student') ?> Student Info</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;font-size:.88rem">
                        <div><span class="text-muted">Name:</span> <strong><?= e($bill['sname']) ?></strong></div>
                        <div><span class="text-muted">Email:</span> <?= e($bill['email'] ?: '—') ?></div>
                        <div><span class="text-muted">Class:</span> <?= e($bill['class_label'] ?? '—') ?></div>
                        <div><span class="text-muted">Department:</span> <?= e($bill['dept_name'] ?? '—') ?></div>
                        <div><span class="text-muted">Rate / Hour:</span> <?= formatINR($bill['rate_per_hour']) ?></div>
                        <div><span class="text-muted">Period:</span> <?= fmtDate($bill['period_from'],'d M Y') ?> – <?= fmtDate($bill['period_to'],'d M Y') ?></div>
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

        <!-- Right: Action Panel -->
        <?php if($bill['status']==='pending'): ?>
        <div>
            <div class="card" style="margin-bottom:1rem;border-color:var(--approved-bdr)">
                <div class="card-header" style="background:var(--approved-bg)"><h3 style="color:var(--approved)"><?= svgIcon('check') ?> Approve Bill</h3></div>
                <div class="card-body">
                    <p class="text-sm text-muted" style="margin-bottom:1rem">Approve this Earn & Learn bill for <strong><?= formatINR($bill['total_amount']) ?></strong>.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="approve_student">
                        <button type="submit" class="btn btn-success" style="width:100%"
                                onclick="return confirmAction('Approve this bill for <?= formatINR($bill['total_amount']) ?>?')">
                            <?= svgIcon('check') ?> Approve Bill
                        </button>
                    </form>
                </div>
            </div>
            <div class="card" style="border-color:var(--rejected-bdr)">
                <div class="card-header" style="background:var(--rejected-bg)"><h3 style="color:var(--rejected)"><?= svgIcon('close') ?> Reject Bill</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="reject_student">
                        <div class="form-group">
                            <label>Reason for rejection <span style="color:red">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required
                                      placeholder="Explain reason clearly…"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger" style="width:100%"
                                onclick="return confirmAction('Reject this bill?')">
                            <?= svgIcon('close') ?> Reject Bill
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:2rem">
                <div style="font-size:2.5rem;margin-bottom:.8rem;display: flex;justify-content: center;"><?= $bill['status']==='approved'?svgIcon('check'):svgIcon('close') ?></div>
                <div class="fw-600"><?= ucfirst($bill['status']) ?></div>
                <div class="text-muted text-sm">Reviewed <?= fmtDate($bill['reviewed_at'],'d M Y') ?></div>
                <?php if($bill['rejection_reason']): ?>
                <div class="alert alert-error" style="text-align:left;margin-top:1rem"><?= e($bill['rejection_reason']) ?></div>
                <?php endif; ?>
                <?php if($bill['status']==='approved'): ?>
                <a href="../pdf/student-bill.php?id=<?= $billId ?>" class="btn btn-success" style="margin-top:1rem" target="_blank"><?= svgIcon('download') ?> Download PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

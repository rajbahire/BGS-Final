<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];
$billId = (int)($_GET['id'] ?? 0);

// Where we arrived from — All Bills (full history) or Pending Requests (review queue).
// Carried through View links so the Back button returns to the list the HOD came from.
$from     = $_GET['from'] ?? 'requests';
$listPage = $from === 'all-bills' ? 'all-bills.php' : 'requests.php';
$listLbl  = $from === 'all-bills' ? 'All Bills' : 'Pending Requests';

$bill = $pdo->prepare(
    "SELECT b.*, u.name AS tname, u.email, u.phone, u.teacher_type, u.teacher_mode,
            u.rate_theory, u.rate_practical, u.rate_other,
            s.subject_name, s.subject_code, s.mode AS subject_mode,
            c.label AS class_label, d.name AS dept_name
     FROM bills b
     JOIN users u ON u.id=b.teacher_id
     LEFT JOIN subjects s ON s.id=u.subject_id
     LEFT JOIN classes c ON c.id=u.class_id
     LEFT JOIN departments d ON d.id=u.department_id
     WHERE b.id=? AND u.department_id=?"
);
$bill->execute([$billId, $deptId]);
$bill = $bill->fetch();
if (!$bill) { setFlash('error','Bill not found.'); header('Location: ' . $listPage); exit; }

// Lecture entries for this bill
$lectures = $pdo->prepare(
    "SELECT l.*, s.subject_name, s.subject_code
     FROM lectures l
     JOIN bill_lectures bl ON bl.lecture_id=l.id
     LEFT JOIN subjects s ON s.id=l.subject_id
     WHERE bl.bill_id=? ORDER BY l.lecture_date ASC"
);
$lectures->execute([$billId]); $lectures = $lectures->fetchAll();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $pdo->prepare("UPDATE bills SET status='approved',reviewed_at=NOW(),reviewed_by=? WHERE id=?")
            ->execute([$user['id'], $billId]);
        logActivity($pdo,$user['id'],'approve_bill',"Approved bill #$billId for {$bill['tname']}");
        setFlash('success',"Bill #$billId approved successfully.");
        header('Location: ' . $listPage); exit;
    }
    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if (!$reason) { setFlash('error','Please provide a rejection reason.'); header("Location: request-detail.php?id=$billId&from=$from"); exit; }
        $pdo->prepare("UPDATE bills SET status='rejected',rejection_reason=?,reviewed_at=NOW(),reviewed_by=? WHERE id=?")
            ->execute([$reason, $user['id'], $billId]);
        logActivity($pdo,$user['id'],'reject_bill',"Rejected bill #$billId: $reason");
        setFlash('success',"Bill #$billId rejected.");
        header('Location: ' . $listPage); exit;
    }
}

renderHead('Review Bill');
?>
<div class="app-layout">
<?php renderSidebar('requests','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Review Bill', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => $listLbl,  'href' => $listPage],
    ['label' => 'Review Bill'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="breadcrumb">
        <a href="<?= $listPage ?>"><?= $listLbl ?></a>
        <span class="sep">›</span>
        <span>Bill #<?= $billId ?></span>
    </div>

    <div class="d-flex justify-between align-center flex-wrap gap-10 mb-2">
        <div class="page-header" style="margin:0">
            <h1><?= e($bill['month_year']) ?> — <?= e($bill['tname']) ?></h1>
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
                    ['Theory Hrs',    number_format($bill['total_theory_hrs'],1),    '#EFF6FF','#1D4ED8'],
                    ['Practical Hrs', number_format($bill['total_practical_hrs'],1), '#F0FDFA','#0F766E'],
                    ['Other Hrs',     number_format($bill['total_other_hrs'],1),     '#F5F3FF','#6D28D9'],
                    ['Total Amount',  formatINR($bill['total_amount']),              '#244B86','#E2C97E'],
                ];
                foreach($summaries as [$lbl,$val,$bg,$clr]):
                ?>
                <div style="background:<?= $bg ?>;border-radius:var(--radius);padding:1rem;text-align:center">
                    <div style="font-size:.7rem;font-weight:500;text-transform:uppercase;letter-spacing:.05em;color:<?= $clr ?>;opacity:.8;margin-bottom:4px"><?= $lbl ?></div>
                    <div style="font-size:1.3rem;font-weight:600;color:<?= $clr ?>"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Teacher Info -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-header"><h3><?= svgIcon('teacher') ?> Teacher Info</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;font-size:.88rem">
                        <div><span class="text-muted">Name:</span> <strong><?= e($bill['tname']) ?></strong></div>
                        <div><span class="text-muted">Email:</span> <?= e($bill['email']) ?></div>
                        <div><span class="text-muted">Type:</span> <?= teacherTypeBadge($bill['teacher_type']??'regular') ?></div>
                        <div><span class="text-muted">Mode:</span> <?= modeBadge($bill['teacher_mode']??'theory') ?></div>
                        <div><span class="text-muted">Subject:</span> <?= e($bill['subject_name']??'—') ?> <?= $bill['subject_code'] ? '<span class="badge badge-expert">'.e($bill['subject_code']).'</span>' : '' ?></div>
                        <div><span class="text-muted">Class:</span> <?= e($bill['class_label']??'—') ?></div>
                        <div><span class="text-muted">Rate/Theory hr:</span> <?= formatINR($bill['rate_theory']) ?></div>
                        <div><span class="text-muted">Rate/Practical hr:</span> <?= formatINR($bill['rate_practical']) ?></div>
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
                            $amt = ($l['theory_hours']*$bill['rate_theory'])
                                 + ($l['practical_hours']*$bill['rate_practical'])
                                 + ($l['other_hours']*$bill['rate_other']);
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i+1 ?></td>
                            <td><?= fmtDate($l['lecture_date']) ?></td>
                            <td><?= e($l['subject_name']??'—') ?> <?= $l['subject_code'] ? '<span class="badge badge-expert" style="font-size:.66rem">'.e($l['subject_code']).'</span>' : '' ?></td>
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

        <!-- Right: Action Panel -->
        <?php if($bill['status']==='pending'): ?>
        <div>
            <div class="card" style="margin-bottom:1rem;border-color:var(--approved-bdr)">
                <div class="card-header" style="background:var(--approved-bg)"><h3 style="color:var(--approved)"><?= svgIcon('check') ?> Approve Bill</h3></div>
                <div class="card-body">
                    <p class="text-sm text-muted" style="margin-bottom:1rem">Approve this bill for <strong><?= formatINR($bill['total_amount']) ?></strong>.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="approve">
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
                        <input type="hidden" name="action" value="reject">
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
                <a href="../pdf/generate.php?id=<?= $billId ?>" class="btn btn-success" style="margin-top:1rem" target="_blank"><?= svgIcon('download') ?> Download PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

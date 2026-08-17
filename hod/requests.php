<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// Pending teacher bills
$bills = $pdo->prepare(
    "SELECT b.*, u.name AS tname, u.teacher_type, u.teacher_mode, s.subject_name, s.subject_code
     FROM bills b
     JOIN users u ON u.id=b.teacher_id
     LEFT JOIN subjects s ON s.id=u.subject_id
     WHERE b.status='pending' AND u.department_id=?
     ORDER BY b.submitted_at ASC"
); $bills->execute([$deptId]); $bills = $bills->fetchAll();

// Pending Earn & Learn (student) bills — scoped to this HOD's department
$sbills = $pdo->prepare(
    "SELECT sb.*, u.name AS sname, c.label AS class_label
     FROM student_bills sb
     JOIN users u ON u.id=sb.student_id
     LEFT JOIN classes c ON c.id=u.class_id
     WHERE sb.status='pending' AND u.department_id=?
     ORDER BY sb.submitted_at ASC"
); $sbills->execute([$deptId]); $sbills = $sbills->fetchAll();

// ── Merge teacher + student bills into a single review queue (oldest first) ──
$queue = [];
foreach ($bills as $b) {
    $queue[] = [
        'kind'         => 'teacher',
        'name'         => $b['tname'],
        'submitted_at' => $b['submitted_at'],
        'detail'       => $b['subject_name'] ?? '',
        'detail_code'  => $b['subject_code'] ?? '',
        'month_year'   => $b['month_year'],
        'hours'        => number_format((float)$b['total_theory_hrs'] + (float)$b['total_practical_hrs'] + (float)$b['total_other_hrs'], 1),
        'amount'       => (float)$b['total_amount'],
        'badge'        => '<span style="display:inline-flex;gap:4px;flex-wrap:wrap">'
                       . teacherTypeBadge($b['teacher_type'] ?? 'regular')
                       . modeBadge($b['teacher_mode'] ?? 'theory')
                       . '</span>',
        'href'         => 'request-detail.php?id=' . (int)$b['id'],
    ];
}
foreach ($sbills as $sb) {
    $sbBillNumber = $sb['bill_number'] ?? generateStudentBillNumber($sb['period_from'], $sb['id']);
    $queue[] = [
        'kind'         => 'student',
        'name'         => $sb['sname'],
        'submitted_at' => $sb['submitted_at'],
        'detail'       => $sb['class_label'] ?? '',
        'detail_code'  => $sbBillNumber,
        'month_year'   => $sb['month_year'],
        'hours'        => number_format((float)$sb['total_hours'], 1),
        'amount'       => (float)$sb['total_amount'],
        'badge'        => '<span class="badge" style="background:#F0FDFA;color:#0F766E;border:1px solid #99F6E4">Earn & Learn</span>',
        'href'         => 'student-bill-detail.php?id=' . (int)$sb['id'],
    ];
}
usort($queue, function ($a, $b) {
    return strcmp($a['submitted_at'] ?? '', $b['submitted_at'] ?? '');
});

renderHead('Pending Requests');
?>
<div class="app-layout">
<?php renderSidebar('requests','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Pending Requests', [
    ['label' => 'Home',              'href' => 'dashboard.php'],
    ['label' => 'Pending Requests'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Pending Requests</h1>
        <p><?= count($queue) ?> bill<?= count($queue) != 1 ? 's' : '' ?> awaiting review — <?= count($bills) ?> teacher · <?= count($sbills) ?> Earn & Learn</p>
    </div>

    <?php if ($queue): ?>
    <div class="card">
        <div class="card-header"><h3>Pending Bills (<?= count($queue) ?>)</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Person</th><th>Subject / Class</th><th>Month</th><th>Hours</th><th>Amount</th><th>Submitted</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($queue as $i => $row): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-500"><?= e($row['name']) ?></div>
                        <div class="text-sm" style="margin-top:3px"><?= $row['badge'] ?></div>
                    </td>
                    <td class="text-sm">
                        <?= e($row['detail'] ?: '—') ?>
                        <?php if ($row['detail_code']): ?>
                        <br><span class="badge badge-expert" style="font-size:.66rem"><?= e($row['detail_code']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-500"><?= e($row['month_year']) ?></td>
                    <td><?= $row['hours'] ?> hrs</td>
                    <td class="fw-600"><?= formatINR($row['amount']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($row['submitted_at'], 'd M Y') ?></td>
                    <td><a href="<?= e($row['href']) ?>" class="btn btn-primary btn-sm">Review →</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="empty-state"><div class="icon"><?= svgIcon('check') ?></div><h3>No pending requests</h3><p>All bills have been reviewed.</p></div></div>
    <?php endif; ?>
</div>
</div>
</div>
<?php renderFooter(); ?>

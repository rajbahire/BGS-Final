<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();
$user = currentUser();
$uid  = $user['id'];

$fStatus = $_GET['status'] ?? '';
$sql     = "SELECT * FROM student_bills WHERE student_id=?";
$params  = [$uid];
if ($fStatus) { $sql.=" AND status=?"; $params[]=$fStatus; }
$sql .= " ORDER BY submitted_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $bills=$stmt->fetchAll();

renderHead('My Bills');
?>
<div class="app-layout">
<?php renderSidebar('my-bills','student',$user); ?>
<div class="main-content">
<?php renderTopbar('My Bills', [
    ['label' => 'Home',  'href' => 'dashboard.php'],
    ['label' => 'My Bills'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>My Bills</h1>
            <p>All your Earn &amp; Learn bills and their status</p>
        </div>
        <a href="generate-bill.php" class="btn btn-primary"><?= svgIcon('add') ?> New Bill</a>
    </div>

    <div class="d-flex gap-8 flex-wrap mb-2">
        <a href="my-bills.php"     class="btn <?= !$fStatus         ?'btn-primary':'btn-outline' ?> btn-sm">All</a>
        <a href="?status=pending"  class="btn <?= $fStatus==='pending'  ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('pending') ?> Pending</a>
        <a href="?status=approved" class="btn <?= $fStatus==='approved' ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('approved') ?> Approved</a>
        <a href="?status=rejected" class="btn <?= $fStatus==='rejected' ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('rejected') ?> Rejected</a>
    </div>

    <div class="card">
        <?php if($bills): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Month</th><th>Period</th><th>Hours</th><th>Rate</th><th>Amount</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($bills as  $i => $b): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-500"><?= e($b['month_year']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($b['period_from'],'d M') ?> – <?= fmtDate($b['period_to'],'d M') ?></td>
                    <td><?= number_format($b['total_hours'],1) ?></td>
                    <td><?= formatINR($b['rate_per_hour']) ?></td>
                    <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                    <td>
                        <?= statusBadge($b['status']) ?>
                        <?php if($b['status']==='rejected' && $b['rejection_reason']): ?>
                        <div class="text-xs text-muted" style="margin-top:3px"><?= e($b['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= fmtDate($b['submitted_at'],'d M Y') ?></td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="bill-detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <?php if($b['status']==='approved'): ?>
                            <a href="../pdf/student-bill.php?id=<?= $b['id'] ?>" class="btn btn-success btn-sm" target="_blank">PDF</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon"><?= svgIcon('list') ?></div>
            <h3>No bills found</h3>
            <p><?= $fStatus ? "No $fStatus bills." : 'You have not submitted any bills yet.' ?></p>
            <a href="generate-bill.php" class="btn btn-primary" style="margin-top:1rem">Generate First Bill</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

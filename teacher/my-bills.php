<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

$fStatus = $_GET['status'] ?? '';

// Pagination settings
$perPage = 10;
$page    = currentPage();
$offset  = paginationOffset($page, $perPage);

// Count query
$countSql = "SELECT COUNT(*) FROM bills WHERE teacher_id=?";
$countParams = [$uid];
if ($fStatus) { $countSql .= " AND status=?"; $countParams[] = $fStatus; }
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = totalPages($totalRecords, $perPage);

// Main query with pagination
$sql     = "SELECT * FROM bills WHERE teacher_id=?";
$params  = [$uid];
if ($fStatus) { $sql .= " AND status=?"; $params[] = $fStatus; }
$sql .= " ORDER BY submitted_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$bills = $stmt->fetchAll();

renderHead('My Bills');
?>
<div class="app-layout">
<?php renderSidebar('my-bills','teacher',$user); ?>
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
            <p>All submitted bills and their approval status</p>
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
                <thead>
                    <tr><th>#</th><th>Month</th><th>Theory Hrs</th><th>Practical Hrs</th><th>Other Hrs</th><th>Amount</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach($bills as  $i => $b): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-500"><?= e($b['month_year']) ?></td>
                    <td><?= number_format($b['total_theory_hrs'],1) ?></td>
                    <td><?= number_format($b['total_practical_hrs'],1) ?></td>
                    <td><?= number_format($b['total_other_hrs'],1) ?></td>
                    <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($b['submitted_at'],'d M Y') ?></td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="bill-detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <?php if($b['status']==='approved'): ?>
                            <a href="../pdf/generate.php?id=<?= $b['id'] ?>" class="btn btn-success btn-sm" target="_blank">PDF</a>
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
        <?php if ($totalPages > 1): ?>
            <?= renderPagination($page, $totalPages, $totalRecords, $perPage, $offset, 'bills', 'My bills pagination', ['status' => $fStatus]) ?>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $pdo->prepare("UPDATE fund_requests SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")
        ->execute([$status, $note, $user['id'], $id]);
    logActivity($pdo, $user['id'], $action.'_fund', "Fund request #$id $status");
    setFlash('success', "Fund request #$id has been $status.");
    header('Location: fund-requests.php'); exit;
}

$filter = $_GET['status'] ?? '';
$sql    = "SELECT fr.*, u.name AS hod_name, d.name AS dept_name
           FROM fund_requests fr
           JOIN users u ON u.id=fr.hod_id
           JOIN departments d ON d.id=fr.department_id
           WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND fr.status=?"; $params[] = $filter; }
$sql .= " ORDER BY fr.requested_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$requests = $stmt->fetchAll();

renderHead('Fund Requests');
?>
<div class="app-layout">
<?php renderSidebar('fund-requests','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('Fund Requests', [
    ['label' => 'Home',         'href' => 'dashboard.php'],
    ['label' => 'Fund Requests'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Fund Requests</h1>
        <p>Review and approve HOD fund requests</p>
    </div>

    <div class="d-flex gap-8 flex-wrap mb-2">
        <a href="fund-requests.php" class="btn <?= !$filter?'btn-primary':'btn-outline' ?> btn-sm">All</a>
        <a href="?status=pending"  class="btn <?= $filter==='pending' ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('pending') ?> Pending</a>
        <a href="?status=approved" class="btn <?= $filter==='approved'?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('approved') ?> Approved</a>
        <a href="?status=rejected" class="btn <?= $filter==='rejected'?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('rejected') ?> Rejected</a>
    </div>

    <div class="card">
        <?php if ($requests): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>HOD</th><th>Department</th><th>Amount</th><th>Purpose</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $i => $r): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-500"><?= e($r['hod_name']) ?></td>
                    <td><?= e($r['dept_name']) ?></td>
                    <td class="fw-600"><?= formatINR($r['amount']) ?></td>
                    <td class="text-sm" style="max-width:200px"><?= e(mb_strimwidth($r['purpose'],0,60,'…')) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($r['requested_at'],'d M Y') ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <button class="btn btn-activate btn-sm"
                                onclick="openModal('modal-fr-<?= $r['id'] ?>-approve')"><?= svgIcon('check') ?></button>
                        <button class="btn btn-delete btn-sm"
                                onclick="openModal('modal-fr-<?= $r['id'] ?>-reject')"><?= svgIcon('close') ?></button>
                        <?php else: ?>
                        <span class="text-muted text-xs"><?= e($r['admin_note'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="icon"><?= svgIcon('fund-requests') ?></div><h3>No fund requests found</h3></div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<?php
// ── Modals rendered OUTSIDE the table so display:none ancestors don't trap them ──
foreach ($requests as $r):
    if ($r['status'] !== 'pending') continue;
?>
<!-- Approve Modal: fr-<?= $r['id'] ?> -->
<div class="modal-backdrop" id="modal-fr-<?= $r['id'] ?>-approve">
    <div class="modal">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('check') ?><h3>Approve Fund Request</h3></span>
            <button class="modal-close" onclick="closeModal('modal-fr-<?= $r['id'] ?>-approve')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <div class="modal-body">
                <p style="margin-bottom:1rem">Approve <strong><?= formatINR($r['amount']) ?></strong> for <strong><?= e($r['dept_name']) ?></strong>?</p>
                <div class="form-group">
                    <label>Admin Note (optional)</label>
                    <textarea name="admin_note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-fr-<?= $r['id'] ?>-approve')">Cancel</button>
                <button type="submit" class="btn btn-success">Approve</button>
            </div>
        </form>
    </div>
</div>
<!-- Reject Modal: fr-<?= $r['id'] ?> -->
<div class="modal-backdrop" id="modal-fr-<?= $r['id'] ?>-reject">
    <div class="modal">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('close') ?><h3>Reject Fund Request</h3></span>
            <button class="modal-close" onclick="closeModal('modal-fr-<?= $r['id'] ?>-reject')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <div class="modal-body">
                <div class="form-group">
                    <label>Reason for rejection <span style="color:red">*</span></label>
                    <textarea name="admin_note" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-fr-<?= $r['id'] ?>-reject')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>

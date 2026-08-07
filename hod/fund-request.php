<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount  = (float)($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    if ($amount > 0 && $purpose) {
        $pdo->prepare("INSERT INTO fund_requests (hod_id,department_id,amount,purpose) VALUES (?,?,?,?)")
            ->execute([$user['id'], $user['dept_id'], $amount, $purpose]);
        logActivity($pdo, $user['id'], 'fund_request', "Requested funds: " . formatINR($amount));
        setFlash('success', 'Fund request submitted to Admin.');
    } else {
        setFlash('error', 'Amount and purpose are required.');
    }
    header('Location: fund-request.php'); exit;
}

// Fetch this HOD's own requests
$stmt = $pdo->prepare(
    "SELECT * FROM fund_requests WHERE hod_id=? ORDER BY requested_at DESC"
);
$stmt->execute([$user['id']]);
$myRequests = $stmt->fetchAll();

renderHead('Request Funds');
?>
<div class="app-layout">
<?php renderSidebar('fund-request', 'hod', $user); ?>
<div class="main-content">
<?php renderTopbar('Request Funds', [
    ['label' => 'Home',          'href' => 'dashboard.php'],
    ['label' => 'Request Funds'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
        <div>
            <h1>Request Funds</h1>
            <p>Submit a fund request and track its status</p>
        </div>
        <button type="button" class="btn btn-primary"
                onclick="openModal('modal-fund-add')"><?= svgIcon('add') ?> New Fund Request</button>
    </div>

    <!-- Tips card
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><h3><?= svgIcon('info') ?> How it works</h3></div>
        <div class="card-body">
            <ul style="display:flex;flex-wrap:wrap;gap:1rem 2rem;padding-left:1.1rem;color:var(--text-muted);font-size:.92rem">
                <li>Fill in the amount and a clear purpose for the request.</li>
                <li>Your request is sent to the <strong>Admin</strong> for review.</li>
                <li>The Admin will approve or reject it and may leave a note.</li>
                <li>Track the status of all your requests in the history below.</li>
            </ul>
        </div>
    </div> -->

    <!-- History Table -->
    <div class="card">
        <div class="card-header"><h3><?= svgIcon('fund-requests') ?> My Request History</h3></div>
        <?php if ($myRequests): ?>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>#</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Admin Note</th>
                </tr></thead>
                <tbody>
                <?php foreach ($myRequests as $i => $r): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-600"><?= formatINR($r['amount']) ?></td>
                    <td class="text-sm" style="max-width:260px"><?= e(mb_strimwidth($r['purpose'], 0, 80, '…')) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($r['requested_at'], 'd M Y') ?></td>
                    <td class="text-sm text-muted"><?= e($r['admin_note'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon"><?= svgIcon('fund-requests') ?></div>
            <h3>No requests yet</h3>
            <p>Submit your first fund request using the button above.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
</div>

<?php
// ── Modal (rendered outside the layout so display:none ancestors don't trap it) ──
?>
<!-- New Fund Request Modal -->
<div class="modal-backdrop" id="modal-fund-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('fund-requests') ?><h3>New Fund Request</h3></span>
            <button class="modal-close" onclick="closeModal('modal-fund-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Amount (₹) <span style="color:red">*</span></label>
                    <input type="number" name="amount" class="form-control"
                           step="0.01" min="1" placeholder="e.g. 50000" required>
                </div>
                <div class="form-group">
                    <label>Purpose <span style="color:red">*</span></label>
                    <textarea name="purpose" class="form-control" rows="4"
                              placeholder="Explain the purpose of this fund request…" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-fund-add')">Cancel</button>
                <button type="submit" class="btn btn-primary"
                        onclick="return confirmAction('Submit this fund request?')">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>
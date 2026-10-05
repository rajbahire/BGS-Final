<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type      = $_POST['bill_type'] ?? 'practical';
    $allowed   = ['practical','seminar'];
    if (!in_array($type,$allowed,true)) { setFlash('error','Invalid bill type.'); header('Location: other-bills.php'); exit; }

    $data      = $_POST;
    unset($data['bill_type']);
    $billDate  = $data['bill_date'] ?? date('Y-m-d');

    switch($type) {
        case 'practical':
            $claimant  = trim($data['faculty_name'] ?? '');
            $students  = (int)($data['students']     ?? 0);
            $rate      = (float)($data['rate']        ?? 0);
            $other     = (float)($data['other_amount']?? 0);
            $amount    = ($students*$rate)+$other;
            $title     = 'Practical Exam — '.($data['subject']??'');
            break;
        case 'seminar':
            $claimant  = trim($data['speaker_name'] ?? '');
            $amount    = (float)($data['honorarium']  ??0)+(float)($data['ta_da']??0)+(float)($data['other_amount']??0);
            $title     = 'Seminar — '.($data['seminar_title']??'');
            break;
        default:
            $claimant=''; $amount=0; $title='';
    }

    if (!$claimant) { setFlash('error','Claimant name is required.'); header("Location: other-bills.php?type=$type"); exit; }

    $pdo->prepare("INSERT INTO other_bills (bill_type,created_by,title,claimant_name,department_id,bill_date,total_amount,bill_data) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$type,$user['id'],$title,$claimant,$deptId,$billDate,$amount,json_encode($data,JSON_UNESCAPED_UNICODE)]);
    $newId = (int)$pdo->lastInsertId();
    $billNumber = generateOtherBillNumber($billDate, $newId);
    $pdo->prepare("UPDATE other_bills SET bill_number=? WHERE id=?")->execute([$billNumber, $newId]);

    logActivity($pdo,$user['id'],'create_other_bill',"Created $type bill $billNumber for $claimant — ".formatINR($amount));
    header("Location: ../pdf/other-bill.php?id=$newId"); exit;
}

$billType  = $_GET['type'] ?? '';
$tab       = $_GET['tab']  ?? 'list';
$typeLabels= ['practical'=>'Practical Exam Bill','seminar'=>'Seminar Bill'];

// Existing other bills
$existing = $pdo->prepare("SELECT * FROM other_bills WHERE created_by=? ORDER BY created_at DESC LIMIT 50");
$existing->execute([$user['id']]); $existing=$existing->fetchAll();

renderHead('Other Bills');
?>
<div class="app-layout">
<?php renderSidebar('other-bills','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Other Bills', [
    ['label' => 'Home',        'href' => 'dashboard.php'],
    ['label' => 'Other Bills'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Other Bills</h1><p>Practical exam and Seminar bills</p></div>

    <!-- Tab bar -->
    <div class="d-flex gap-8 flex-wrap mb-2">
        <a href="?tab=list"             class="btn <?= $tab==='list'?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('other-bills') ?> All Other Bills</a>
        <a href="?tab=create&type=practical"  class="btn <?= $tab==='create'&&$billType==='practical' ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('add') ?> Practical Exam</a>
        <a href="?tab=create&type=seminar"    class="btn <?= $tab==='create'&&$billType==='seminar'   ?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('add') ?> Seminar</a>
    </div>

    <?php if($tab==='list'): ?>
    <div class="card">
        <?php if($existing): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Bill ID</th><th>Type</th><th>Title</th><th>Claimant</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($existing as $i => $b):
                    $obBillNum = $b['bill_number'] ?? generateOtherBillNumber($b['bill_date'], $b['id']);
                ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-500" style="white-space:nowrap;color:var(--primary)"><?= e($obBillNum) ?></td>
                    <td class="fw-600"><?= $typeLabels[$b['bill_type']]??$b['bill_type'] ?></td>
                    <td class="fw-500"><?= e($b['title']) ?></td>
                    <td><?= e($b['claimant_name']) ?></td>
                    <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($b['bill_date']) ?></td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="other-bill-detail.php?id=<?= $b['id'] ?>&from=other-bills" class="btn btn-outline btn-sm">View</a>
                            <a href="../pdf/other-bill.php?id=<?= $b['id'] ?>" class="btn btn-success btn-sm" target="_blank">PDF</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="icon"><?= svgIcon('document') ?></div><h3>No other bills yet</h3><p>Create a practical exam or seminar bill using the buttons above.</p></div>
        <?php endif; ?>
    </div>

    <?php elseif($tab==='create'): ?>
    <div class="card">
        <div class="card-header"><h3><?= e($typeLabels[$billType]??'Other Bill') ?></h3></div>
        <div class="card-body">
            <form method="POST" action="other-bills.php?type=<?= e($billType) ?>">
                <input type="hidden" name="bill_type" value="<?= e($billType) ?>">

                <?php if($billType==='practical'): ?>
                <div class="form-grid">
                    <div class="form-group"><label for="prac_faculty_name">Examiner / Faculty Name <span style="color:red">*</span></label><input type="text" id="prac_faculty_name" name="faculty_name" class="form-control" required></div>
                    <div class="form-group"><label for="prac_subject">Subject</label><input type="text" id="prac_subject" name="subject" class="form-control" placeholder="e.g. Data Structures Lab"></div>
                    <div class="form-group"><label for="prac_program">Programme / Class</label><input type="text" id="prac_program" name="program" class="form-control" placeholder="e.g. SE CSE"></div>
                    <div class="form-group"><label for="prac_exam_name">Examination</label><input type="text" id="prac_exam_name" name="exam_name" class="form-control" placeholder="Winter Exam 2025-26"></div>
                    <div class="form-group"><label for="prac_exam_date">Exam Date</label><input type="date" id="prac_exam_date" name="exam_date" class="form-control" data-today></div>
                    <div class="form-group"><label for="prac_students">No. of Students</label><input type="number" id="prac_students" name="students" class="form-control" min="0" value="0"></div>
                    <div class="form-group"><label for="prac_rate">Rate per Student (₹)</label><input type="number" id="prac_rate" name="rate" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="prac_other_amount">Other Amount (₹)</label><input type="number" id="prac_other_amount" name="other_amount" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="prac_academic_year">Academic Year</label><input type="text" id="prac_academic_year" name="academic_year" class="form-control" value="<?= date('Y').'-'.(date('y')+1) ?>"></div>
                    <div class="form-group"><label for="prac_bill_date">Bill Date</label><input type="date" id="prac_bill_date" name="bill_date" class="form-control" data-today></div>
                </div>
                <hr class="divider">
                <div class="form-grid">
                    <div class="form-group"><label for="prac_bank_name">Bank Name</label><input type="text" id="prac_bank_name" name="bank_name" class="form-control"></div>
                    <div class="form-group"><label for="prac_account_no">Account No.</label><input type="text" id="prac_account_no" name="account_no" class="form-control"></div>
                    <div class="form-group"><label for="prac_ifsc">IFSC</label><input type="text" id="prac_ifsc" name="ifsc" class="form-control"></div>
                    <div class="form-group"><label for="prac_pan">PAN</label><input type="text" id="prac_pan" name="pan" class="form-control"></div>
                </div>

                <?php elseif($billType==='seminar'): ?>
                <div class="form-grid">
                    <div class="form-group"><label for="sem_speaker_name">Speaker / Faculty Name <span style="color:red">*</span></label><input type="text" id="sem_speaker_name" placeholder="Name of a speaker" name="speaker_name" class="form-control" required></div>
                    <div class="form-group"><label for="sem_seminar_title">Seminar Title <span style="color:red">*</span></label><input type="text" id="sem_seminar_title" placeholder="Title of the seminar" name="seminar_title" class="form-control" required></div>
                    <div class="form-group"><label for="sem_topic">Topic <span style="color:red">*</span></label><input type="text" id="sem_topic" placeholder="Topic of the seminar" name="topic" class="form-control" required></div>
                    <div class="form-group"><label for="sem_seminar_date">Date <span style="color:red">*</span></label><input type="date" id="sem_seminar_date" name="seminar_date" class="form-control" data-today></div>
                    <div class="form-group"><label for="sem_duration">Duration <span style="color:red">*</span></label><input type="text" id="sem_duration" name="duration" class="form-control" placeholder="e.g. 2 hours" required></div>
                    <div class="form-group"><label for="sem_honorarium">Honorarium (₹)</label><input type="number" id="sem_honorarium" name="honorarium" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="sem_ta_da">TA / DA (₹)</label><input type="number" id="sem_ta_da" name="ta_da" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="sem_other_amount">Other Amount (₹)</label><input type="number" id="sem_other_amount" name="other_amount" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="sem_bill_date">Bill Date</label><input type="date" id="sem_bill_date" name="bill_date" class="form-control" data-today></div>
                </div>
                <hr class="divider">
                <div class="form-grid">
                    <div class="form-group"><label for="sem_bank_name">Bank Name</label><input type="text" id="sem_bank_name" name="bank_name" class="form-control"></div>
                    <div class="form-group"><label for="sem_account_no">Account No.</label><input type="text" id="sem_account_no" name="account_no" class="form-control"></div>
                    <div class="form-group"><label for="sem_ifsc">IFSC</label><input type="text" id="sem_ifsc" name="ifsc" class="form-control"></div>
                    <div class="form-group"><label for="sem_pan">PAN</label><input type="text" id="sem_pan" name="pan" class="form-control"></div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning"><?= svgIcon('warning') ?> Unknown bill type. Please select from the tabs above.</div>
                <?php return; ?>
                <?php endif; ?>

                <hr class="divider">
                <div style="display:flex;gap:8px;align-items:center">
                    <button type="submit" class="btn btn-primary">Generate & Save Bill</button>
                    <a href="other-bills.php?tab=list" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
</div>
<?php renderFooter(); ?>

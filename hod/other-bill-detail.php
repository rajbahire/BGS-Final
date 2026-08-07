<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];
$billId = (int)($_GET['id'] ?? 0);

// Where we arrived from — All Bills (full history) or Other Bills.
$from     = $_GET['from'] ?? 'all-bills';
$listPage = $from === 'other-bills' ? 'other-bills.php?tab=list' : 'all-bills.php';
$listLbl  = $from === 'other-bills' ? 'Other Bills' : 'All Bills';

// Fetch this other bill — scoped to the HOD's own department (ownership check)
$bill = $pdo->prepare("SELECT * FROM other_bills WHERE id=? AND department_id=?");
$bill->execute([$billId, $deptId]);
$bill = $bill->fetch();
if (!$bill) { setFlash('error','Bill not found.'); header('Location: ' . $listPage); exit; }

$typeLabels = ['practical'=>'Practical Exam','earn_learn'=>'Earn & Learn','seminar'=>'Seminar'];
$otype      = $typeLabels[$bill['bill_type']] ?? ucfirst($bill['bill_type']);
$d          = json_decode($bill['bill_data'], true) ?? [];

// Per-type detail fields (pulled from the saved bill_data JSON)
$detailFields = [
    'practical' => [
        ['Examiner / Faculty', $d['faculty_name'] ?? ''],
        ['Subject',           $d['subject'] ?? ''],
        ['Programme / Class', $d['program'] ?? ''],
        ['Examination',       $d['exam_name'] ?? ''],
        ['Exam Date',         fmtDate($d['exam_date'] ?? '')],
        ['No. of Students',   $d['students'] ?? ''],
        ['Rate per Student',  formatINR((float)($d['rate'] ?? 0))],
        ['Other Amount',      formatINR((float)($d['other_amount'] ?? 0))],
        ['Academic Year',     $d['academic_year'] ?? ''],
    ],
    'earn_learn' => [
        ['Student Name',   $d['student_name'] ?? ''],
        ['Class / Year',   $d['class_year'] ?? ''],
        ['Month',          !empty($d['month']) ? date('F', mktime(0,0,0,(int)$d['month'],1)) : ''],
        ['Year',           $d['year'] ?? ''],
        ['Work Assigned',  $d['work_assigned'] ?? ''],
        ['Working Days',   $d['working_days'] ?? ''],
        ['Hours per Day',  $d['hours_per_day'] ?? ''],
        ['Rate per Hour',   formatINR((float)($d['rate'] ?? 0))],
    ],
    'seminar' => [
        ['Speaker / Faculty', $d['speaker_name'] ?? ''],
        ['Seminar Title',     $d['seminar_title'] ?? ''],
        ['Topic',             $d['topic'] ?? ''],
        ['Date',              fmtDate($d['seminar_date'] ?? '')],
        ['Duration',          $d['duration'] ?? ''],
        ['Honorarium',        formatINR((float)($d['honorarium'] ?? 0))],
        ['TA / DA',           formatINR((float)($d['ta_da'] ?? 0))],
        ['Other Amount',      formatINR((float)($d['other_amount'] ?? 0))],
    ],
];
$fields = $detailFields[$bill['bill_type']] ?? [];

// Bank / payment fields (common across types — shown only if any is filled)
$bankFields = [
    ['Bank Name',   $d['bank_name']  ?? ''],
    ['Account No.', $d['account_no'] ?? ''],
    ['IFSC',        $d['ifsc']       ?? ''],
    ['PAN',         $d['pan']        ?? ''],
    ['Mobile',      $d['mobile']     ?? ''],
];
$hasBank = false;
foreach ($bankFields as [, $v]) { if ($v !== '') { $hasBank = true; break; } }

function fld(string $label, $v): string {
    $out = ($v === '' || $v === null || $v === '—') ? '—' : e((string)$v);
    return '<tr><td class="text-muted" style="padding:5px 0;width:180px">' . e($label) . '</td><td>' . $out . '</td></tr>';
}

renderHead('Other Bill Detail');
?>
<div class="app-layout">
<?php renderSidebar('other-bills','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Other Bill Detail', [
    ['label' => 'Home',  'href' => 'dashboard.php'],
    ['label' => $listLbl, 'href' => $listPage],
    ['label' => 'Bill Detail'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="breadcrumb">
        <a href="<?= $listPage ?>"><?= $listLbl ?></a>
        <span class="sep">›</span>
        <span><?= e($otype) ?> #<?= $billId ?></span>
    </div>

    <div class="d-flex justify-between align-center flex-wrap gap-10 mb-2">
        <div class="page-header" style="margin:0">
            <h1><?= e($bill['title']) ?></h1>
            <p>Created <?= fmtDate($bill['created_at'],'d F Y, h:i A') ?></p>
        </div>
        <div class="d-flex gap-8">
            <a href="../pdf/other-bill.php?id=<?= $billId ?>" class="btn btn-success" target="_blank"><?= svgIcon('download') ?> Download PDF</a>
            <a href="<?= $listPage ?>" class="btn btn-outline">← Back</a>
        </div>
    </div>

    <!-- Summary boxes -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
        <?php
        $summaries = [
            ['Bill Type',    $otype,                       '#EFF6FF','#1D4ED8'],
            ['Claimant',     $bill['claimant_name'],        '#F0FDFA','#0F766E'],
            ['Bill Date',    fmtDate($bill['bill_date']),   '#FFFBEB','#B45309'],
            ['Total Amount', formatINR($bill['total_amount']), '#244B86','#E2C97E'],
        ];
        foreach($summaries as [$lbl,$val,$bg,$clr]):
        ?>
        <div style="background:<?= $bg ?>;border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:.7rem;font-weight:500;text-transform:uppercase;letter-spacing:.05em;color:<?= $clr ?>;opacity:.8;margin-bottom:4px"><?= $lbl ?></div>
            <div style="font-size:1.15rem;font-weight:600;color:<?= $clr ?>"><?= e($val) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
        <!-- Bill Details -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('document') ?> Bill Details</h3></div>
            <div class="card-body">
                <table style="font-size:.88rem;width:100%">
                    <?= fld('Claimant', $bill['claimant_name']) ?>
                    <?= fld('Bill Date', fmtDate($bill['bill_date'])) ?>
                    <?php foreach($fields as [$label,$value]): ?>
                    <?= fld($label, $value) ?>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div>
            <!-- Amount breakdown -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-header"><h3><?= svgIcon('list') ?> Amount</h3></div>
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0">
                        <span class="text-muted">Status</span>
                        <?= statusBadge('finalized') ?>
                    </div>
                    <div style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:flex;justify-content:space-between;align-items:center">
                        <strong>Total Amount</strong>
                        <strong style="font-size:1.1rem;color:var(--primary)"><?= formatINR($bill['total_amount']) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Bank / Payment info (only if any field present) -->
            <?php if($hasBank): ?>
            <div class="card">
                <div class="card-header"><h3><?= svgIcon('profile') ?> Payment Details</h3></div>
                <div class="card-body">
                    <table style="font-size:.88rem;width:100%">
                        <?php foreach($bankFields as [$label,$value]): ?>
                        <?= fld($label, $value) ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>

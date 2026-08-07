<?php
// ============================================================
//  pdf/student-bill.php — Printable Earn & Learn Student Bill
//  Reads a REAL student_bills row + its student_work entries.
//  Access: student (own, approved) | hod (own dept) | admin (any)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$user   = currentUser();
$billId = (int)($_GET['id'] ?? 0);

$select =
    "SELECT sb.*, u.name AS sname, u.email, u.phone,
            u.bank_name, u.account_no, u.ifsc,
            c.label AS class_label, d.name AS dept_name
     FROM student_bills sb
     JOIN users u ON u.id=sb.student_id
     LEFT JOIN classes c ON c.id=u.class_id
     LEFT JOIN departments d ON d.id=u.department_id";

if ($user['role'] === 'student') {
    // Student may only print their OWN approved bill
    $q = $pdo->prepare("$select WHERE sb.id=? AND sb.student_id=? AND sb.status='approved'");
    $q->execute([$billId, $user['id']]);
} elseif ($user['role'] === 'hod') {
    // HOD may print any bill within their own department
    $q = $pdo->prepare("$select WHERE sb.id=? AND u.department_id=?");
    $q->execute([$billId, $user['dept_id']]);
} else {
    // Admin: any bill
    $q = $pdo->prepare("$select WHERE sb.id=?");
    $q->execute([$billId]);
}
$bill = $q->fetch();
if (!$bill) { die('<p style="font-family:sans-serif;padding:2rem">Bill not found or not yet approved.</p>'); }

// Work entries that make up this bill (within the bill period)
$wq = $pdo->prepare(
    "SELECT * FROM student_work WHERE student_id=? AND work_date BETWEEN ? AND ? ORDER BY work_date ASC"
);
$wq->execute([$bill['student_id'], $bill['period_from'], $bill['period_to']]);
$work = $wq->fetchAll();

// Derived figures (from the stored bill, not recomputed)
$rate        = (float)$bill['rate_per_hour'];
$totalHours  = (float)$bill['total_hours'];
$amount      = (float)$bill['total_amount'];
$workingDays = count($work);

$ts          = strtotime($bill['period_from']);
$monthName   = $ts ? date('F', $ts) : '';
$year        = $ts ? (int)date('Y', $ts) : '';
$daysInMonth = $ts ? (int)date('t', $ts) : 31;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v){ return number_format((float)$v, 2); }
function showDate($d){ if(!$d || $d==='0000-00-00') return ''; $ts=strtotime($d); return $ts?date('d / m / Y',$ts):h($d); }

function amountWords(float $number): string {
    $number = (int)round($number);
    if ($number === 0) return 'Zero Rupees Only';
    $words = [0=>'',1=>'One',2=>'Two',3=>'Three',4=>'Four',5=>'Five',6=>'Six',7=>'Seven',8=>'Eight',9=>'Nine',10=>'Ten',
              11=>'Eleven',12=>'Twelve',13=>'Thirteen',14=>'Fourteen',15=>'Fifteen',16=>'Sixteen',17=>'Seventeen',18=>'Eighteen',
              19=>'Nineteen',20=>'Twenty',30=>'Thirty',40=>'Forty',50=>'Fifty',60=>'Sixty',70=>'Seventy',80=>'Eighty',90=>'Ninety'];
    $under100  = function($n) use ($words){ return $n<21 ? $words[$n] : trim($words[((int)($n/10))*10].' '.$words[$n%10]); };
    $under1000 = function($n) use ($under100,$words){ return $n<100 ? $under100($n) : trim($words[(int)($n/100)].' Hundred '.$under100($n%100)); };
    $parts=[];
    $cr=intdiv($number,10000000); $number%=10000000;
    $lk=intdiv($number,100000);   $number%=100000;
    $th=intdiv($number,1000);     $number%=1000;
    if($cr) $parts[]=$under1000($cr).' Crore';
    if($lk) $parts[]=$under1000($lk).' Lakh';
    if($th) $parts[]=$under1000($th).' Thousand';
    if($number) $parts[]=$under1000($number);
    return trim(implode(' ',$parts)).' Rupees Only';
}

$college='GOVERNMENT COLLEGE OF ENGINEERING AURANGABAD';
$city='CHHATRAPATI SAMBHAJINAGAR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Earn & Learn Bill #<?= str_pad($billId,5,'0',STR_PAD_LEFT) ?> — <?= h($bill['sname']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html{font-size:10pt}
body{font-family:"Times New Roman",Times,serif;color:#000;background:#ccc}
.pbar{position:fixed;top:0;left:0;right:0;z-index:1000;background:#1a3a6e;color:#fff;display:flex;align-items:center;gap:12px;padding:9px 18px;font-family:Arial,sans-serif;font-size:12px}
.pbar button{background:#fff;color:#1a3a6e;border:0;border-radius:4px;padding:6px 16px;font-weight:700;cursor:pointer}
.pbar a{color:rgba(255,255,255,.78);text-decoration:none;margin-left:auto}
.pbar .bill-ref{font-size:11px;opacity:.8}
.pbar button svg{width:16px;height:16px;display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:4px}
.page{width:210mm;min-height:297mm;background:#fff;margin:0 auto 14px;padding:12mm 14mm;page-break-after:always;position:relative}
@media screen{body{padding-top:50px}.page{box-shadow:0 2px 10px rgba(0,0,0,.3)}}
@media print{body{background:#fff;padding-top:0}.pbar{display:none!important}.page{box-shadow:none;margin:0;break-after:page}}
.c{text-align:center}.r{text-align:right}.b{font-weight:bold}
.u{text-decoration:underline}.j{text-align:justify}
.hdr h1{font-size:12pt;line-height:1.35}
.hdr h2{font-size:10pt;line-height:1.45}
.hdr p{font-size:9pt}
hr.thick{border:0;border-top:2px solid #000;margin:2.2mm 0}
hr.thin{border:0;border-top:1px solid #000;margin:1.7mm 0}
.title{font-size:12pt;font-weight:bold;text-align:center;text-decoration:underline;margin:4mm 0 5mm}
.row{line-height:2.1;font-size:10pt}
.fl{display:inline-block;border-bottom:1px solid #000;min-width:35mm;padding:0 1mm;vertical-align:bottom}
table{width:100%;border-collapse:collapse;font-size:9.5pt}
th,td{border:1px solid #000;padding:1.6mm 2mm;vertical-align:middle}
th{font-weight:bold;text-align:center;background:#e5e5e5}
.tl{text-align:left}.tr{text-align:right}
.mt{margin-top:5mm}.mb{margin-bottom:5mm}
.sign-grid{display:grid;grid-template-columns:1fr 1fr;gap:10mm;margin-top:12mm;font-size:10pt;line-height:2}
.sign{text-align:center;padding-top:14mm}
.cert{font-size:10pt;line-height:1.7;text-align:justify;margin:5mm 0}
.box{border:1px solid #000;padding:4mm;margin:4mm 0}
.stamp-line{display:inline-block;border-bottom:1px solid #000;min-width:55mm;height:5mm}
.office{min-height:40mm}
.bill-id{font-size:8pt;color:#555;text-align:right;margin-bottom:2mm}
</style>
</head>
<body>

<div class="pbar">
    <button onclick="window.print()"><?= svgIcon('printer') ?> Print / Save as PDF</button>
    <span>Earn & Learn Bill — <?= h($bill['sname']) ?></span>
    <span class="bill-ref">Bill #<?= str_pad($billId,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; <?= h($bill['month_year']) ?></span>
    <a href="javascript:history.back()">← Back</a>
</div>

<!-- ════════════════ PAGE 1: BILL ════════════════ -->
<div class="page">
    <div class="bill-id">Bill #<?= str_pad($billId,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?></div>
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1><h2>(An Autonomous Institute of Government of Maharashtra)</h2></div>
    <hr class="thick">
    <div class="title">EARN AND LEARN STUDENT BILL</div>

    <div class="row">Name of Student: <span class="fl" style="min-width:95mm"><?= h($bill['sname']) ?></span></div>
    <div class="row">Department: <span class="fl" style="min-width:70mm"><?= h($bill['dept_name'] ?? '') ?></span>
        &nbsp; Class / Year: <span class="fl" style="min-width:42mm"><?= h($bill['class_label'] ?? '') ?></span></div>
    <div class="row">Bill for the month of: <span class="fl" style="min-width:50mm"><?= h($bill['month_year']) ?></span>
        &nbsp; Period: <span class="fl" style="min-width:55mm"><?= showDate($bill['period_from']) ?> – <?= showDate($bill['period_to']) ?></span></div>

    <table class="mt">
        <thead><tr><th style="width:14mm">Sr.No.</th><th>Particulars</th><th style="width:28mm">Days</th><th style="width:28mm">Hours</th><th style="width:28mm">Rate (Rs.)</th><th style="width:38mm">Amount Rs.</th></tr></thead>
        <tbody>
            <tr><td class="c">1</td><td class="tl">Earn and Learn work done during the month</td><td class="c"><?= $workingDays ?></td><td class="c"><?= money($totalHours) ?></td><td class="tr"><?= money($rate) ?></td><td class="tr"><?= money($amount) ?></td></tr>
            <tr><td colspan="5" class="tr b">Total Amount</td><td class="tr b"><?= money($amount) ?></td></tr>
        </tbody>
    </table>

    <div class="row mt">Amount in words: <span class="fl" style="min-width:128mm"><?= h(amountWords($amount)) ?></span></div>

    <p class="cert">Certified that the student has worked under the Earn and Learn scheme for the above duration and the amount claimed is correct according to the attendance and departmental record.</p>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= date('d / m / Y') ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Student<br><span class="stamp-line"></span></div>
    </div>
    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- ════════════════ PAGE 2: ATTENDANCE SHEET (actual logged work) ════════════════ -->
<div class="page">
    <div class="title">ATTENDANCE SHEET — EARN AND LEARN SCHEME</div>
    <div class="row">Student Name: <span class="fl" style="min-width:78mm"><?= h($bill['sname']) ?></span>
        &nbsp; Month: <span class="fl" style="min-width:42mm"><?= h($bill['month_year']) ?></span></div>

    <table class="mt">
        <thead><tr><th style="width:14mm">Sr.No.</th><th style="width:32mm">Date</th><th>Nature of Work</th><th style="width:28mm">Hours</th><th style="width:42mm">Signature</th></tr></thead>
        <tbody>
        <?php foreach ($work as $i => $w): ?>
            <tr>
                <td class="c"><?= $i + 1 ?></td>
                <td class="c"><?= showDate($w['work_date']) ?></td>
                <td class="tl"><?= h($w['description'] ?: 'Earn and Learn work') ?></td>
                <td class="c"><?= money($w['hours']) ?></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$work): ?>
            <tr><td></td><td></td><td></td><td></td><td></td></tr>
        <?php endif; ?>
        <tr><td colspan="3" class="tr b">Total Hours</td><td class="c b"><?= money($totalHours) ?></td><td></td></tr>
        </tbody>
    </table>

    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- ════════════════ PAGE 3: CERTIFICATE + BANK ════════════════ -->
<div class="page">
    <div class="title">CERTIFICATE</div>
    <p class="cert">I certify that the above bill claimed by me for the said duration of work under Earn and Learn scheme is actually completed by me and is in accordance with attendance register and department record. I know that I will be responsible and accountable for any wrongful claim and will return any excess amount disbursed, if found in future.</p>

    <div class="box">
        <div class="b c mb">Bank Details of Student</div>
        <div class="row">Name of Bank: <span class="fl" style="min-width:95mm"><?= h($bill['bank_name'] ?? '') ?></span></div>
        <div class="row">A/C No.: <span class="fl" style="min-width:70mm"><?= h($bill['account_no'] ?? '') ?></span>
            &nbsp; IFSC: <span class="fl" style="min-width:45mm"><?= h($bill['ifsc'] ?? '') ?></span></div>
        <div class="row">Mobile No.: <span class="fl" style="min-width:65mm"><?= h($bill['phone'] ?? '') ?></span></div>
    </div>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= date('d / m / Y') ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Student<br><span class="stamp-line"></span></div>
    </div>
    <p class="cert">Certified that the work and attendance stated above have been verified and the amount claimed is correct and submitted for sanction.</p>
    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- ════════════════ PAGE 4: OFFICE USE / SANCTION ════════════════ -->
<div class="page">
    <div class="title">OFFICE USE / SANCTION</div>
    <table>
        <tbody>
            <tr><td style="width:75mm">Name of Student</td><td><?= h($bill['sname']) ?></td></tr>
            <tr><td>Department</td><td><?= h($bill['dept_name'] ?? '') ?></td></tr>
            <tr><td>Class / Year</td><td><?= h($bill['class_label'] ?? '') ?></td></tr>
            <tr><td>Month</td><td><?= h($bill['month_year']) ?></td></tr>
            <tr><td>Total Hours</td><td><?= money($totalHours) ?></td></tr>
            <tr><td>Rate per Hour</td><td>Rs. <?= money($rate) ?></td></tr>
            <tr><td class="b">Amount Sanctioned</td><td class="b">Rs. <?= money($amount) ?></td></tr>
            <tr><td>Amount in Words</td><td><?= h(amountWords($amount)) ?></td></tr>
            <?php if (!empty($bill['reviewed_at']) && $bill['reviewed_at'] !== '0000-00-00 00:00:00'): ?>
            <tr><td>Approved On</td><td><?= showDate($bill['reviewed_at']) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="box office mt">Remarks:</div>
    <div class="sign-grid">
        <div class="sign">Accounts Section</div>
        <div class="sign">Registrar / Principal</div>
    </div>
</div>

<script>
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
}
</script>
</body>
</html>

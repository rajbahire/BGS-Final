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
    "SELECT sb.*, u.name AS sname, u.email, u.phone, u.enrollment_number,
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
function showTime($t){ return ($t && $t!=='00:00:00') ? date('h:i A', strtotime($t)) : ''; }

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
.sign{text-align:center;padding-top:6mm}
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

<!-- ════════════════ PAGE 1: DAILY WORK RECORD (Annexure-2) ════════════════ -->
<div class="page">
    <div class="bill-id">Bill #<?= str_pad($billId,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?></div>
    <div class="c" style="font-style:italic;font-size:11pt;margin-bottom:3mm">Annexure -2</div>
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1></div>
    <hr class="thin">
    <div class="title">EARN & LEARN SCHEME<br><span style="font-weight:normal;font-size:10pt;text-decoration:none">Daily Work Record</span></div>

    <div class="row">Name of the Student: <span class="fl" style="min-width:90mm"><?= h($bill['sname']) ?></span></div>
    <div class="row">Enrollment No: <span class="fl" style="min-width:90mm;margin-left: 31px;"><?= h($bill['enrollment_number'] ?? '') ?></span></div>
    <div class="row">Department /Section: <span class="fl" style="min-width:90mm"><?= h($bill['dept_name'] ?? '') ?></span></div>
    <div class="row">Class: <span class="fl" style="min-width:90mm;margin-left: 81px;"><?= h($bill['class_label'] ?? '') ?></span></div>

    <table class="mt">
        <thead>
            <tr>
                <th style="width:24mm" rowspan="2">Date</th>
                <th rowspan="2">Particulars of Work</th>
                <th colspan="2">Time Duration</th>
                <th style="width:18mm" rowspan="2">No. of<br>Hours</th>
                <th style="width:28mm" rowspan="2">Signature of<br>Student</th>
            </tr>
            <tr>
                <th style="width:22mm">From</th>
                <th style="width:22mm">To</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($work as $i => $w): ?>
            <tr>
                <td class="c" style="font-size:8.5pt"><?= showDate($w['work_date']) ?></td>
                <td class="tl"><?= h($w['description'] ?: 'Earn and Learn work') ?></td>
                <td class="c" style="font-size:8.5pt"><?= showTime($w['start_time'] ?? '') ?></td>
                <td class="c" style="font-size:8.5pt"><?= showTime($w['end_time'] ?? '') ?></td>
                <td class="c"><?= money($w['hours']) ?></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        <?php
            // Pad empty rows up to 15 minimum for the official format
            $minRows = 15;
            for ($r = count($work); $r < $minRows; $r++):
        ?>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <div style="text-align:right;margin-top:40mm;font-size:10pt">
        <strong>(Name and signature)</strong><br>
        Faculty/Head of the department/Section in Charge
    </div>
</div>

<!-- ════════════════ PAGE 2: BILL ════════════════ -->
<div class="page">
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1><h2>(An Autonomous Institute of Government of Maharashtra)</h2></div>
    <hr class="thick">
    <div class="title">EARN AND LEARN STUDENT BILL</div>

    <div class="row">Name of Student: <span class="fl" style="min-width: 81mm;margin-left: 5mm;"><?= h($bill['sname']) ?></span></div>
    <div class="row">Enrollment Number: <span class="fl" style="margin-left: 2px;min-width: 81mm;"><?= h($bill['enrollment_number'] ?? '') ?></span></div>
    <div class="row">Class and Branch: <span class="fl" style="margin-left: 16px;min-width: 60mm;"><?= h($bill['class_label'] ?? '') ?></span>
        &nbsp; Department/Section: <span class="fl" style="min-width:50mm"><?= h($bill['dept_name'] ?? '') ?></span></div>
    <div class="row">Bill for the Month of: <span class="fl" style="min-width:59mm"><?= h($bill['month_year']) ?></span>
        &nbsp; Selection Order No.(If any): <span class="fl" style="min-width:40mm"></span></div>

    <table class="mt">
        <thead>
            <tr>
                <th style="width:12mm" rowspan="2">SR.<br>No</th>
                <th style="width:26mm" rowspan="2">Day &amp;<br>Date</th>
                <th rowspan="2">Particulars of work</th>
                <th style="width:22mm">Start<br>Time</th>
                <th style="width:22mm">End<br>Time</th>
                <th style="width:22mm">Working<br>Hours</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($work as $i => $w): ?>
            <tr>
                <td class="c"><?= $i + 1 ?></td>
                <td class="c" style="font-size:8.5pt"><?= showDate($w['work_date']) ?></td>
                <td class="tl"><?= h($w['description'] ?: 'Earn and Learn work') ?></td>
                <td class="c" style="font-size:8.5pt"><?= showTime($w['start_time'] ?? '') ?></td>
                <td class="c" style="font-size:8.5pt"><?= showTime($w['end_time'] ?? '') ?></td>
                <td class="c"><?= money($w['hours']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($r = count($work); $r < $minRows; $r++): ?>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
        <?php endfor; ?>
            <tr><td colspan="5" class="tr b">Total Number of working hours</td><td class="c b"><?= money($totalHours) ?></td></tr>
        </tbody>
    </table>

    <p class="cert">Certified that the above work have actually been done by me & is in accordance with attendance register maintained, & the bill claimed herewith is correct according to the rates as per institute norms.</p>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= date('d / m / Y') ?></span></div>
        <div class="r">Signature of Student<br><span class="stamp-line"></span></div>
    </div>
    <div class="sign-grid">
        <div class="sign">Signature of Concern Faculty</div>
        <div class="sign">Signature of Head of Department/Section</div>
    </div>
</div>

<!-- ════════════════ PAGE 3: ABSTRACT ════════════════ -->
<div class="page">
    <div class="title">ABSTRACT</div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Particulars of work</th>
                <th rowspan="2">Total Hours worked</th>
                <th>Rate of<br>Remuneration</th>
                <th>Total<br>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="tl">Earn and Learn work during <?= h($bill['month_year']) ?></td>
                <td class="c"><?= money($totalHours) ?></td>
                <td class="c"><?= money($rate) ?>/- per hour</td>
                <td class="tr"><?= money($amount) ?></td>
            </tr>
        </tbody>
    </table>

    <p class="cert">Certified that the work under earn and learn scheme as stated above has been checked and amount claimed is correct and hence submitted for sanction.</p>

    <span style="display: flex;justify-content: space-between;">
    <div class="row mt">Date: <span class="fl"></span></div>
    <div style="text-align:right;margin-top:5mm;font-size:10pt">Signature of Head of Dept./Section</div>
    </span>

    <table style="margin-top:6mm;border-collapse:collapse;width:100%">
        <tr>
            <td style="border:1px solid #000;width:55%;vertical-align:middle;padding:3mm 4mm" class="tl">
                <div class="b">Name of department: Dean SA</div>
            </td>
            <td style="border:1px solid #000;vertical-align:top;padding:3mm 4mm" class="tl">
                <div class="b">Head of Account:</div>
                <div>Activities of the functionaries (E-08)</div>
                <div class="b">Sub Head of Account:</div>
                <div>Earn and Learn Scheme</div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border:1px solid #000;padding:3mm 4mm">
                <div style="font-size:9.5pt;line-height:2.2">
                    <div style="display:flex;align-items:baseline;gap:2mm">
                        <span style="white-space:nowrap">1- Sanctioned budget for the year <?= $year ?> :</span>
                        <span style="flex:1;border-bottom:1px solid #000;min-width:20mm;margin-bottom:2px"></span>
                    </div>
                    <div style="display:flex;align-items:baseline;gap:2mm">
                        <span style="white-space:nowrap">2- Expenditure in this bill :</span>
                        <span style="flex:1;border-bottom:1px solid #000;min-width:20mm;margin-bottom:2px"></span>
                    </div>
                    <div style="display:flex;align-items:baseline;gap:2mm">
                        <span style="white-space:nowrap">3- Expenditure including this bill :</span>
                        <span style="flex:1;border-bottom:1px solid #000;min-width:20mm;margin-bottom:2px"></span>
                    </div>
                    <div style="display:flex;align-items:baseline;gap:2mm">
                        <span style="white-space:nowrap">4- Remaining budget :</span>
                        <span style="flex:1;border-bottom:1px solid #000;min-width:20mm;margin-bottom:2px"></span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <p class="cert">Bills verified by me & found correct.</p>

    <div class="row mt">Bill Entry Register Page No <span class="stamp-line" style="min-width:30mm"></span> Sr.No <span class="stamp-line" style="min-width:30mm"></span></div>

    <div style="text-align:right;margin-top:15mm;font-size:10pt">Storekeeper/Instructor (Dean SA register)</div>

    <div class="sign-grid" style="margin-top:20mm">
        <div>Date: <span class="fl"></span></div>
        <div class="r">Signature of Dean (Students'Activities)</div>
    </div>

    <div class="row mt">Passed for payment: Rs.<span class="stamp-line" style="min-width:100mm"></span></div>
    <div class="row">In words Rs. <span class="stamp-line" style="min-width:115mm"></span></div>

    <div class="c" style="margin-top:25mm;font-size:10pt">
        <div class="b">Principal</div>
        <div><?= $college ?></div>
        <div><?= $city ?></div>
    </div>
</div>

<!-- ════════════════ PAGE 4: CERTIFICATE + BANK ════════════════ -->
<!-- <div class="page">
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
</div> -->

<!-- ════════════════ PAGE 4: OFFICE USE / SANCTION ════════════════ -->
<!-- <div class="page">
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
</div> -->

<script>
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
}
</script>
</body>
</html>
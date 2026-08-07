<?php
// ============================================================
//  pdf/other-bill.php — Printable Other Bills
//  practical | earn_learn | seminar
//  Reads saved bill from other_bills table by ?id=
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdminOrHOD();
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { die('<p style="font-family:sans-serif;padding:2rem">Invalid bill ID.</p>'); }

// Admin: any bill. HOD: only bills within their own department.
if ($user['role'] === 'hod') {
    $row = $pdo->prepare("SELECT * FROM other_bills WHERE id=? AND department_id=?");
    $row->execute([$id, $user['dept_id']]);
} else {
    $row = $pdo->prepare("SELECT * FROM other_bills WHERE id=?");
    $row->execute([$id]);
}
$bill = $row->fetch();
if (!$bill) { die('<p style="font-family:sans-serif;padding:2rem">Bill not found.</p>'); }

$type = $bill['bill_type'];
$data = json_decode($bill['bill_data'], true) ?? [];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function p(array $d,string $k,string $def=''){ return trim((string)($d[$k] ?? $def)); }
function money($v){ return number_format((float)$v, 2); }
function showDate($d){ if(!$d) return ''; $ts=strtotime($d); return $ts?date('d / m / Y',$ts):h($d); }

function amountWords(float $number): string {
    $number = (int)round($number);
    if ($number === 0) return 'Zero Rupees Only';
    $words = [0=>'',1=>'One',2=>'Two',3=>'Three',4=>'Four',5=>'Five',6=>'Six',7=>'Seven',8=>'Eight',9=>'Nine',10=>'Ten',
              11=>'Eleven',12=>'Twelve',13=>'Thirteen',14=>'Fourteen',15=>'Fifteen',16=>'Sixteen',17=>'Seventeen',18=>'Eighteen',
              19=>'Nineteen',20=>'Twenty',30=>'Thirty',40=>'Forty',50=>'Fifty',60=>'Sixty',70=>'Seventy',80=>'Eighty',90=>'Ninety'];
    $under100 = function($n) use ($words){ return $n<21 ? $words[$n] : trim($words[((int)($n/10))*10].' '.$words[$n%10]); };
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
$typeLabels=['practical'=>'Practical Examination Bill','earn_learn'=>'Earn and Learn Student Bill','seminar'=>'Seminar / Expert Lecture Bill'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill #<?= $id ?> — <?= h($bill['title']) ?></title>
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
    <span><?= h($bill['title']) ?></span>
    <span class="bill-ref">Bill #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; <?= h($bill['claimant_name']) ?></span>
    <a href="javascript:history.back()">← Back</a>
</div>

<?php
// ═══════════════════════════════════════════════════
// PRACTICAL EXAMINATION BILL
// ═══════════════════════════════════════════════════
if ($type === 'practical'):
    $students=(int)p($data,'students','0');
    $rate=(float)p($data,'rate','0');
    $other=(float)p($data,'other_amount','0');
    $amount=($students*$rate)+$other;
?>
<div class="page">
    <div class="bill-id">Bill #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?></div>
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1><h2>(An Autonomous Institute of Government of Maharashtra)</h2></div>
    <hr class="thick">
    <div class="title">PRACTICAL EXAMINATION REMUNERATION BILL</div>

    <div class="row">Name of Examiner / Faculty: <span class="fl" style="min-width:139mm"><?= h(p($data,'faculty_name')) ?></span></div>
    <div class="row">Department: <span class="fl" style="min-width: 70mm;margin-left: 53px;"><?= h(p($data,'department')) ?></span>
        &nbsp; Academic Year: <span class="fl" style="min-width: 50mm;margin-left: 10px;"><?= h(p($data,'academic_year')) ?></span></div>
    <div class="row">Programme / Class: <span class="fl" style="min-width: 70mm;margin-left: 15px;"><?= h(p($data,'program')) ?></span>
        &nbsp; Semester: <span class="fl" style="min-width: 50mm;margin-left: 41px;"><?= h(p($data,'semester')) ?></span></div>
    <div class="row">Examination: <span class="fl" style="min-width: 71mm;margin-left: 46px;"><?= h(p($data,'exam_name')) ?></span>
        &nbsp; Date: <span class="fl" style="min-width: 50mm;margin-left: 63px;"><?= showDate(p($data,'exam_date')) ?></span></div>
    <div class="row mb">Subject / Course: <span class="fl" style="min-width: 72mm;margin-left: 25px;"><?= h(p($data,'subject')) ?></span></div>

    <table>
        <thead><tr><th style="width:14mm">Sr.No.</th><th>Particulars</th><th style="width:34mm">No.</th><th style="width:34mm">Rate (Rs.)</th><th style="width:38mm">Amount Rs.</th></tr></thead>
        <tbody>
            <tr><td class="c">1</td><td class="tl">Practical examination work</td><td class="c"><?= $students ?></td><td class="tr"><?= money($rate) ?></td><td class="tr"><?= money($students*$rate) ?></td></tr>
            <tr><td class="c">2</td><td class="tl">Other admissible charges</td><td></td><td></td><td class="tr"><?= money($other) ?></td></tr>
            <tr><td colspan="4" class="tr b">Total Amount</td><td class="tr b"><?= money($amount) ?></td></tr>
        </tbody>
    </table>

    <div class="row mt">Amount in words: <span class="fl" style="min-width:128mm"><?= h(amountWords($amount)) ?></span></div>

    <div class="box">
        <div class="b c mb">Bank Details of Claimant</div>
        <div class="row">Name of Bank: <span class="fl" style="min-width: 136mm;margin-left: 10px;"><?= h(p($data,'bank_name')) ?></span></div>
        <div class="row">A/C No.: <span class="fl" style="min-width: 70mm;margin-left: 41px;"><?= h(p($data,'account_no')) ?></span>
            &nbsp; IFSC: <span class="fl" style="min-width:55mm"><?= h(p($data,'ifsc')) ?></span></div>
        <div class="row">PAN: <span class="fl" style="min-width: 70mm;margin-left: 60px;"><?= h(p($data,'pan')) ?></span></div>
    </div>

    <p class="cert">Certified that the above practical examination work has been carried out and the bill claimed is correct as per the applicable rules and rates of the institute.</p>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= showDate(p($data,'bill_date')) ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Examiner / Faculty<br><span class="stamp-line"></span></div>
    </div>
    <div class="sign-grid">
        <div class="sign">Department Exam In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
    <div class="sign c">Principal</div>
</div>

<?php
// ═══════════════════════════════════════════════════
// EARN AND LEARN BILL
// ═══════════════════════════════════════════════════
elseif ($type === 'earn_learn'):
    $month=max(1,min(12,(int)p($data,'month',(string)date('n'))));
    $year=(int)p($data,'year',(string)date('Y'));
    $workingDays=max(0,(int)p($data,'working_days','0'));
    $hoursPerDay=max(0,(float)p($data,'hours_per_day','0'));
    $rate=max(0,(float)p($data,'rate','0'));
    $totalHours=$workingDays*$hoursPerDay;
    $amount=$totalHours*$rate;
    $monthName=date('F',mktime(0,0,0,$month,1));
    $daysInMonth=cal_days_in_month(CAL_GREGORIAN,$month,$year);
?>
<!-- Page 1: Bill -->
<div class="page">
    <div class="bill-id">Bill #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?></div>
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1><h2>(An Autonomous Institute of Government of Maharashtra)</h2></div>
    <hr class="thick">
    <div class="title">EARN AND LEARN STUDENT BILL</div>

    <div class="row">Name of Student: <span class="fl" style="min-width:95mm"><?= h(p($data,'student_name')) ?></span></div>
    <div class="row">Department: <span class="fl" style="min-width:70mm"><?= h(p($data,'department')) ?></span>
        &nbsp; Class / Year: <span class="fl" style="min-width:42mm"><?= h(p($data,'class_year')) ?></span></div>
    <div class="row">Bill for the month of: <span class="fl" style="min-width:50mm"><?= h($monthName.' '.$year) ?></span>
        &nbsp; Work Assigned: <span class="fl" style="min-width:55mm"><?= h(p($data,'work_assigned')) ?></span></div>

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
        <div>Date: <span class="fl"><?= showDate(p($data,'bill_date')) ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Student<br><span class="stamp-line"></span></div>
    </div>
    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- Page 2: Attendance Sheet -->
<div class="page">
    <div class="title">ATTENDANCE SHEET — EARN AND LEARN SCHEME</div>
    <div class="row">Student Name: <span class="fl" style="min-width:78mm"><?= h(p($data,'student_name')) ?></span>
        &nbsp; Month: <span class="fl" style="min-width:42mm"><?= h($monthName.' '.$year) ?></span></div>

    <table class="mt">
        <thead><tr><th style="width:14mm">Sr.No.</th><th style="width:28mm">Date</th><th>Nature of Work</th><th style="width:28mm">Hours</th><th style="width:42mm">Signature</th></tr></thead>
        <tbody>
        <?php for($i=1;$i<=31;$i++): ?>
            <tr style="height:7mm">
                <td class="c"><?= $i ?></td>
                <td class="c"><?= $i<=$daysInMonth?sprintf('%02d/%02d/%04d',$i,$month,$year):'' ?></td>
                <td class="tl"><?= $i<=$workingDays?h(p($data,'work_assigned')):'' ?></td>
                <td class="c"><?= $i<=$workingDays?money($hoursPerDay):'' ?></td>
                <td></td>
            </tr>
        <?php endfor; ?>
        <tr><td colspan="3" class="tr b">Total Hours</td><td class="c b"><?= money($totalHours) ?></td><td></td></tr>
        </tbody>
    </table>

    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- Page 3: Certificate + Bank -->
<div class="page">
    <div class="title">CERTIFICATE</div>
    <p class="cert">I certify that the above bill claimed by me for the said duration of work under Earn and Learn scheme is actually completed by me and is in accordance with attendance register and department record. I know that I will be responsible and accountable for any wrongful claim and will return any excess amount disbursed, if found in future.</p>

    <div class="box">
        <div class="b c mb">Bank Details of Student</div>
        <div class="row">Name of Bank: <span class="fl" style="min-width:95mm"><?= h(p($data,'bank_name')) ?></span></div>
        <div class="row">A/C No.: <span class="fl" style="min-width:70mm"><?= h(p($data,'account_no')) ?></span>
            &nbsp; IFSC: <span class="fl" style="min-width:45mm"><?= h(p($data,'ifsc')) ?></span></div>
        <div class="row">Mobile No.: <span class="fl" style="min-width:65mm"><?= h(p($data,'mobile')) ?></span></div>
    </div>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= showDate(p($data,'bill_date')) ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Student<br><span class="stamp-line"></span></div>
    </div>
    <p class="cert">Certified that the work and attendance stated above have been verified and the amount claimed is correct and submitted for sanction.</p>
    <div class="sign-grid">
        <div class="sign">Faculty / Staff In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
</div>

<!-- Page 4: Office Sanction -->
<div class="page">
    <div class="title">OFFICE USE / SANCTION</div>
    <table>
        <tbody>
            <tr><td style="width:75mm">Name of Student</td><td><?= h(p($data,'student_name')) ?></td></tr>
            <tr><td>Department</td><td><?= h(p($data,'department')) ?></td></tr>
            <tr><td>Month</td><td><?= h($monthName.' '.$year) ?></td></tr>
            <tr><td>Total Hours</td><td><?= money($totalHours) ?></td></tr>
            <tr><td>Rate per Hour</td><td>Rs. <?= money($rate) ?></td></tr>
            <tr><td class="b">Amount Sanctioned</td><td class="b">Rs. <?= money($amount) ?></td></tr>
            <tr><td>Amount in Words</td><td><?= h(amountWords($amount)) ?></td></tr>
        </tbody>
    </table>
    <div class="box office mt">Remarks:</div>
    <div class="sign-grid">
        <div class="sign">Accounts Section</div>
        <div class="sign">Registrar / Principal</div>
    </div>
</div>

<?php
// ═══════════════════════════════════════════════════
// SEMINAR BILL
// ═══════════════════════════════════════════════════
else:
    $honorarium=(float)p($data,'honorarium','0');
    $taDa=(float)p($data,'ta_da','0');
    $other=(float)p($data,'other_amount','0');
    $amount=$honorarium+$taDa+$other;
?>
<div class="page">
    <div class="bill-id">Bill #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?></div>
    <div class="hdr c"><h1><?= $college ?><br><?= $city ?></h1><h2>(An Autonomous Institute of Government of Maharashtra)</h2></div>
    <hr class="thick">
    <div class="title">SEMINAR / EXPERT LECTURE BILL</div>

    <div class="row">Name of Speaker / Faculty: <span class="fl" style="min-width:92mm"><?= h(p($data,'speaker_name')) ?></span></div>
    <div class="row">Department: <span class="fl" style="min-width:70mm"><?= h(p($data,'department')) ?></span>
        &nbsp; Date: <span class="fl" style="min-width:38mm"><?= showDate(p($data,'seminar_date')) ?></span></div>
    <div class="row">Seminar / Event Title: <span class="fl" style="min-width:100mm"><?= h(p($data,'seminar_title')) ?></span></div>
    <div class="row">Topic: <span class="fl" style="min-width:113mm"><?= h(p($data,'topic')) ?></span></div>
    <div class="row mb">Duration: <span class="fl" style="min-width:48mm"><?= h(p($data,'duration')) ?></span></div>

    <table>
        <thead><tr><th style="width:14mm">Sr.No.</th><th>Particulars</th><th style="width:44mm">Amount Rs.</th></tr></thead>
        <tbody>
            <tr><td class="c">1</td><td class="tl">Honorarium for seminar / expert lecture</td><td class="tr"><?= money($honorarium) ?></td></tr>
            <tr><td class="c">2</td><td class="tl">TA / DA</td><td class="tr"><?= money($taDa) ?></td></tr>
            <tr><td class="c">3</td><td class="tl">Other admissible charges</td><td class="tr"><?= money($other) ?></td></tr>
            <tr><td colspan="2" class="tr b">Total Amount</td><td class="tr b"><?= money($amount) ?></td></tr>
        </tbody>
    </table>

    <div class="row mt">Amount in words: <span class="fl" style="min-width:128mm"><?= h(amountWords($amount)) ?></span></div>

    <div class="box">
        <div class="b c mb">Bank Details of Claimant</div>
        <div class="row">Name of Bank: <span class="fl" style="min-width:95mm"><?= h(p($data,'bank_name')) ?></span></div>
        <div class="row">A/C No.: <span class="fl" style="min-width:70mm"><?= h(p($data,'account_no')) ?></span>
            &nbsp; IFSC: <span class="fl" style="min-width:45mm"><?= h(p($data,'ifsc')) ?></span></div>
        <div class="row">PAN: <span class="fl" style="min-width:55mm"><?= h(p($data,'pan')) ?></span></div>
    </div>

    <p class="cert">Certified that the above seminar / expert lecture was conducted and the payment claimed is correct as per institute rules and approval.</p>

    <div class="sign-grid">
        <div>Date: <span class="fl"><?= showDate(p($data,'bill_date')) ?></span><br>Place: Chhatrapati Sambhajinagar</div>
        <div class="r">Signature of Speaker / Faculty<br><span class="stamp-line"></span></div>
    </div>
    <div class="sign-grid">
        <div class="sign">Coordinator / Faculty In-charge</div>
        <div class="sign">Signature of HoD with Stamp</div>
    </div>
    <div class="sign c">Principal</div>
</div>
<?php endif; ?>

<script>
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
}
</script>
</body>
</html>
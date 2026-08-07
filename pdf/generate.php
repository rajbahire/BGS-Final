<?php
// ============================================================
//  pdf/generate.php — Official GCEA Bill (4 pages)
//  Sections fill based on teacher_type:
//    regular           -> Section 1 (Full-time PoP / Adjunct)
//    expert            -> Section 2 (Hourly Visiting/Expert)
//    sectional_expert  -> Section 2 (Hourly, practical-based)
//    adjunct           -> Section 3 (Adjunct Credit Based)
//  Inactive sections are crossed with a diagonal X.
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$user   = currentUser();
$billId = (int)($_GET['id'] ?? 0);

// Access control: teacher can only view own approved bill; admin/hod can view any
if ($user['role'] === 'teacher') {
    $q = $pdo->prepare(
        "SELECT b.*, u.name AS tname, u.email, u.phone, u.teacher_type, u.teacher_mode,
                u.appointment_order_no, u.bank_name, u.ifsc, u.account_no, u.pan,
                d.name AS dept_name, s.subject_name, s.subject_code, c.label AS class_label
         FROM bills b
         JOIN users u ON u.id=b.teacher_id
         LEFT JOIN departments d ON d.id=u.department_id
         LEFT JOIN subjects s ON s.id=u.subject_id
         LEFT JOIN classes c ON c.id=s.class_id
         WHERE b.id=? AND b.teacher_id=? AND b.status='approved'"
    );
    $q->execute([$billId, $user['id']]);
} else {
    $q = $pdo->prepare(
        "SELECT b.*, u.name AS tname, u.email, u.phone, u.teacher_type, u.teacher_mode,
                u.appointment_order_no, u.bank_name, u.ifsc, u.account_no, u.pan,
                d.name AS dept_name, s.subject_name, s.subject_code, c.label AS class_label
         FROM bills b
         JOIN users u ON u.id=b.teacher_id
         LEFT JOIN departments d ON d.id=u.department_id
         LEFT JOIN subjects s ON s.id=u.subject_id
         LEFT JOIN classes c ON c.id=s.class_id
         WHERE b.id=?"
    );
    $q->execute([$billId]);
}
$bill = $q->fetch();
if (!$bill) { die('<p style="font-family:sans-serif;padding:2rem">Bill not found or not yet approved.</p>'); }

// Lecture entries for Annexure I
$lq = $pdo->prepare(
    "SELECT l.*, s.subject_name, s.subject_code FROM lectures l
     JOIN bill_lectures bl ON bl.lecture_id=l.id
     LEFT JOIN subjects s ON s.id=l.subject_id
     WHERE bl.bill_id=? ORDER BY l.lecture_date ASC"
);
$lq->execute([$billId]); $lectures=$lq->fetchAll();

$type   = $bill['teacher_type'] ?? 'regular';
$month  = $bill['month_year'];

$tHrs=(float)$bill['total_theory_hrs']; $pHrs=(float)$bill['total_practical_hrs']; $oHrs=(float)$bill['total_other_hrs'];
$rT=(float)$bill['rate_theory']; $rP=(float)$bill['rate_practical']; $rO=(float)$bill['rate_other'];
$tAmt=(float)$bill['theory_amount']; $pAmt=(float)$bill['practical_amount']; $oAmt=(float)$bill['other_amount'];
$total=(float)$bill['total_amount'];

// Section mapping
$isSection1 = ($type === 'regular');                              // Full-time PoP/Adjunct
$isSection2 = in_array($type, ['expert','sectional_expert'], true); // Hourly Visiting/Expert
$isSection3 = ($type === 'adjunct');                                // Adjunct Credit Based

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n($v){ return number_format((float)$v, 2); }
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
function cross(){
    return '<div style="position:absolute;inset:0;pointer-events:none;z-index:8;overflow:hidden">'
          .'<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" '
          .'style="position:absolute;top:0;left:0;width:100%;height:100%">'
          .'<line x1="0" y1="0" x2="100%" y2="100%" stroke="#888" stroke-width="1.5"/>'
          .'<line x1="100%" y1="0" x2="0" y2="100%" stroke="#888" stroke-width="1.5"/>'
          .'</svg></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill <?= h($month) ?> — <?= h($bill['tname']) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:9.5pt}
body{font-family:'Times New Roman',Times,serif;color:#000;background:#ccc}
.pbar{position:fixed;top:0;left:0;right:0;z-index:999;background:#1a3a6e;color:#fff;display:flex;align-items:center;gap:12px;padding:9px 18px;font-family:Arial,sans-serif;font-size:12px}
.pbar button{background:#fff;color:#1a3a6e;border:none;border-radius:4px;padding:6px 16px;font-weight:700;font-size:12px;cursor:pointer}
.pbar button svg{width:16px;height:16px;display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:4px}
.pbar a{color:rgba(255,255,255,.7);text-decoration:none;margin-left:auto}
.page{width:210mm;min-height:297mm;padding:13mm 14mm 12mm;margin:0 auto 14px;background:#fff;page-break-after:always;position:relative}
@media screen{body{padding-top:50px}.page{box-shadow:0 2px 10px rgba(0,0,0,.3)}}
@media print{.pbar{display:none!important}body{background:#fff;padding-top:0}.page{box-shadow:none;margin:0}}
.c{text-align:center}.b{font-weight:bold}.u{text-decoration:underline}.j{text-align:justify}
.hdr h1{font-size:11.5pt;font-weight:bold;text-align:center;line-height:1.4}
.hdr h2{font-size:10pt;font-weight:bold;text-align:center}
.hdr p{font-size:8.5pt;text-align:center}
hr.thick{border:none;border-top:2.5px solid #000;margin:2.5mm 0}
hr.thin{border:none;border-top:1.2px solid #000;margin:1.5mm 0}
.fl{display:inline-block;border-bottom:1px solid #000;min-width:40mm;padding:0 1mm;vertical-align:bottom}
.binfo{font-size:9.5pt;line-height:2.1;margin:3mm 0}
.sec{position:relative;margin-bottom:4mm;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:9pt}
th,td{border:1px solid #000;padding:1.5mm 2mm;vertical-align:top}
.sh{background:#d0d0d0;font-weight:bold;font-size:10pt}
.ac{width:22mm;text-align:right}
.lc{width:76%}
.tb{font-weight:bold}
.at th,.at td{border:1px solid #000;padding:1.5mm 2mm;text-align:center;vertical-align:middle}
.at th{font-weight:bold}
.at .tl{text-align:left}
.cpara{font-size:9.5pt;text-align:justify;line-height:1.7;margin-bottom:4mm}
.bbox{border:1.5px solid #000;padding:3mm 4mm;margin:4mm 0;font-size:9.5pt;line-height:2}
.bbox h3{font-size:10pt;font-weight:bold;text-align:center;margin-bottom:2mm}
.brow{display:flex;}
.sgg{display:grid;grid-template-columns:1fr 1fr;gap:50mm;font-size:9.5pt;line-height:1.9;margin-top:8mm}
.sr{text-align:left}
.prin{text-align:center;margin-top:16mm;font-size:9.5pt;line-height:1.9}
</style>
</head>
<body>

<div class="pbar">
  <button onclick="window.print()"><?= svgIcon('printer') ?> Print / Save as PDF</button>
  <span>Bill #<?= str_pad($billId,5,'0',STR_PAD_LEFT) ?> &nbsp;|&nbsp; <?= h($month) ?> &nbsp;|&nbsp; <?= h($bill['tname']) ?></span>
  <a href="javascript:history.back()">← Back</a>
</div>

<!-- ======================================================
     PAGE 1 — MAIN BILL
     ====================================================== -->
<div class="page">
  <div class="hdr">
    <h1>GOVERNMENT COLLEGE OF ENGINEERING AURANGABAD<br>CHHATRAPATI SAMBHAJINAGAR</h1>
    <h2>(An Autonomous Institute of Government of Maharashtra)</h2>
  </div>
  <hr class="thick">
  <p class="b" style="font-size:9.5pt">Details of bill for PoP/Adjunct Faculty/ Sessional Instructor/ Visiting Faculty/Expert Faculty</p>
  <hr class="thin">

  <p class="c b" style="font-size:11pt;margin:3mm 0">
    BILL FOR THE MONTH OF &nbsp;<span class="fl">&nbsp;<?= h($month) ?>&nbsp;</span>
  </p>

  <div class="binfo">
    <div><span class="b">Name of the Faculty:</span> <span class="fl" style="min-width:151mm">&nbsp;<?= h($bill['tname']) ?>&nbsp;</span></div>
    <div><span class="b">Department:</span> <span class="fl" style="min-width:80mm;margin-left: 44px;">&nbsp;<?= h($bill['dept_name']??'') ?>&nbsp;</span>
         &nbsp;<span class="b">Subject:</span> <span class="fl" style="min-width:56mm">&nbsp;<?= h($bill['subject_name']??'') ?>&nbsp;</span></div>
    <div><span class="b">Appointment order number:</span>
      <span class="fl" style="min-width:69mm">&nbsp;<?= h($bill['appointment_order_no']??'') ?>&nbsp;</span>
      &nbsp;<span class="b">Type:</span> <span class="fl" style="min-width: 56mm;margin-left: 4mm;">&nbsp;<?= h(ucwords(str_replace('_',' ',$type))) ?>&nbsp;</span>
    </div>
  </div>

  <p class="b" style="font-size:9pt;margin-bottom:2mm">Note: Section applicable to teacher type is filled; others are crossed.</p>

  <!-- ── SECTION 1: Regular / Full-time PoP / Adjunct ── -->
  <div class="sec">
    <?php if (!$isSection1) echo cross(); ?>
    <table>
      <tr><td colspan="2" class="sh">For Full-time Professor of Practice/ Adjunct Faculty (Regular)</td></tr>
      <tr><td class="lc">Fixed Emoluments per month (as per office order)</td>
          <td class="ac">Rs.&nbsp;<?= $isSection1 ? n($total) : '' ?></td></tr>
      <tr><td>a) Total Theory Hours in the month</td>
          <td class="ac"><?= $isSection1 ? n($tHrs) : '' ?></td></tr>
      <tr><td>b) Total Practical Hours in the month</td>
          <td class="ac"><?= $isSection1 ? n($pHrs) : '' ?></td></tr>
      <tr><td>c) Total Other Hours in the month</td>
          <td class="ac"><?= $isSection1 ? n($oHrs) : '' ?></td></tr>
      <tr><td class="tb">Bill claimed <?php if($isSection1 && $total > 0): ?>(<?= amountWords($total) ?>)</span><?php endif; ?></td>
           <td class="ac tb">Rs.&nbsp;<?= $isSection1 ? n($total) : '' ?></td></tr>
    </table>
  </div>

  <!-- ── SECTION 2: Hourly Expert / Sectional Expert ── -->
  <div class="sec">
    <?php if (!$isSection2) echo cross(); ?>
    <table>
      <tr><td colspan="2" class="sh">For Hourly based Visiting faculty / Sessional Instructor / Expert Faculty</td></tr>
      <tr><td class="lc">Permissible rate /hour (as per office order) for-</td><td class="ac"></td></tr>
      <tr><td style="padding-left:8mm">1.&nbsp; Theory/ Tutorials</td><td class="ac">Rs.&nbsp;<?= $isSection2 ? n($rT) : '' ?></td></tr>
      <tr><td style="padding-left:8mm">2.&nbsp; Practical/ Project</td><td class="ac">Rs.&nbsp;<?= $isSection2 ? n($rP) : '' ?></td></tr>
      <tr><td style="padding-left:8mm">3.&nbsp; Other works</td><td class="ac">Rs.&nbsp;<?= $isSection2 ? n($rO) : '' ?></td></tr>
      <tr><td>Subject / Course</td>
          <td class="ac"><?= $isSection2 ? h(($bill['subject_name']??'').' ('.($bill['subject_code']??'').')') : '' ?></td></tr>
      <tr><td>Total no. of hours in the month (details as per Annexure I attached)</td><td class="ac"></td></tr>
      <tr><td style="padding-left:8mm">1.&nbsp; Theory and Tutorials</td><td class="ac">&nbsp;<?= $isSection2 ? n($tHrs).' hrs' : '' ?></td></tr>
      <tr><td style="padding-left:8mm">2.&nbsp; Practical / Project</td><td class="ac">&nbsp;<?= $isSection2 ? n($pHrs).' hrs' : '' ?></td></tr>
      <tr><td style="padding-left:8mm">3.&nbsp; Other works</td><td class="ac">&nbsp;<?= $isSection2 ? n($oHrs).' hrs' : '' ?></td></tr>
      <tr><td>Bill claimed for-</td><td class="ac"></td></tr>
      <tr>
        <td style="padding-left:8mm">a.&nbsp; Theory and Tutorials (for
          <span class="fl" style="min-width:10mm">&nbsp;<?= $isSection2 ? n($tHrs) : '' ?>&nbsp;</span> hrs @ Rs.
          <span class="fl" style="min-width:12mm">&nbsp;<?= $isSection2 ? n($rT) : '' ?>&nbsp;</span> per hour)</td>
        <td class="ac">Rs.&nbsp;<?= $isSection2 ? n($tAmt) : '' ?></td>
      </tr>
      <tr>
        <td style="padding-left:8mm">b.&nbsp; Practical / Project (for
          <span class="fl" style="min-width:10mm">&nbsp;<?= $isSection2 ? n($pHrs) : '' ?>&nbsp;</span> hrs @ Rs.
          <span class="fl" style="min-width:12mm">&nbsp;<?= $isSection2 ? n($rP) : '' ?>&nbsp;</span> per hour)</td>
        <td class="ac">Rs.&nbsp;<?= $isSection2 ? n($pAmt) : '' ?></td>
      </tr>
      <tr>
        <td style="padding-left:8mm">c.&nbsp; Other works (for
          <span class="fl" style="min-width:10mm">&nbsp;<?= $isSection2 ? n($oHrs) : '' ?>&nbsp;</span> hrs @ Rs.
          <span class="fl" style="min-width:14mm">&nbsp;<?= $isSection2 ? n($rO) : '' ?>&nbsp;</span> per hour)</td>
        <td class="ac">Rs.&nbsp;<?= $isSection2 ? n($oAmt) : '' ?></td>
      </tr>
      <tr><td class="tb">Total bill claimed <?php if($isSection2 && $total > 0): ?>(<?= amountWords($total) ?>)<?php endif; ?></td><td class="ac tb">Rs.&nbsp;<?= $isSection2 ? n($total) : '' ?></td></tr>
    </table>
  </div>

  <!-- ── SECTION 3: Adjunct Faculty (Credit Based) ── -->
  <div class="sec">
    <?php if (!$isSection3) echo cross(); ?>
    <table>
      <tr><td colspan="2" class="sh">For Adjunct Faculty (Credit Based)</td></tr>
      <tr><td class="lc">Permissible rate per credit hour (as per office order)</td>
          <td class="ac">Rs.&nbsp;<?= $isSection3 ? n($rT) : '' ?></td></tr>
      <tr><td>Subject / Course</td>
          <td class="ac"><?= $isSection3 ? h(($bill['subject_name']??'').' ('.($bill['subject_code']??'').')') : '' ?></td></tr>
      <tr><td>Total no. of credit hours in the semester</td>
          <td class="ac"><?= $isSection3 ? n($tHrs+$pHrs+$oHrs) : '' ?></td></tr>
      <tr>
        <td class="tb">Bill claimed for
          <span class="fl" style="min-width:16mm">&nbsp;<?= $isSection3 ? n($tHrs+$pHrs+$oHrs) : '' ?>&nbsp;</span>
          credit hrs @ Rs.
          <span class="fl" style="min-width:16mm">&nbsp;<?= $isSection3 ? n($rT) : '' ?>&nbsp;</span> per credit hr</td>
        <td class="ac tb">Rs.&nbsp;<?= $isSection3 ? n($total) : '' ?></td>
      </tr>
    </table>
  </div>
</div>


<!-- ======================================================
     PAGE 2 — CERTIFICATE + BANK DETAILS + SIGNATURES
     ====================================================== -->
<div class="page">
  <p class="c b u" style="font-size:11pt;margin-bottom:5mm">CERTIFICATE</p>

  <p class="cpara">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I certify that the above bill claimed by me for said duration of academic load which is actually
    engaged by me and is in accordance with attendance register and record of department. The bill claimed
    herewith is correct according to the rates as prescribed in the order received from Govt. College of
    Engineering Aurangabad, Chhatrapati Sambhajinagar. I know that I will be responsible and accountable for
    any wrongful claim. I will return any excess amount disbursed, if found in future.
  </p>

  <div class="bbox">
    <h3>Bank Details of Claimant</h3>
    <div>Name of Bank:&nbsp; <span class="fl" style="min-width:126mm">&nbsp;<?= h($bill['bank_name']??'') ?>&nbsp;</span></div>
    <div>IFSC :&nbsp; <span class="fl" style="min-width: 126mm;margin-left: 44px;">&nbsp;<?= h($bill['ifsc']??'') ?>&nbsp;</span></div>
    <div class="brow">
      <div>A/C No.&nbsp;<span class="fl" style="min-width: 63mm;margin-left: 10mm;">&nbsp;<?= h($bill['account_no']??'') ?>&nbsp;</span></div>
      <div>PAN:&nbsp;<span class="fl" style="min-width:55mm">&nbsp;<?= h($bill['pan']??'') ?>&nbsp;</span></div>
    </div>
    <div>Mobile No.&nbsp;<span class="fl" style="min-width: 63mm;margin-left: 22px;">&nbsp;<?= h($bill['phone']??'') ?>&nbsp;</span></div>
  </div>

  <div class="sgg" style="margin-top:10mm">
    <div>
      Date:&nbsp;<span class="fl" style="min-width:35mm">&nbsp;<?= date('d / m / Y') ?>&nbsp;</span><br>
      Place: Chhatrapati Sambhaji Nagar
    </div>
    <div class="sr">
      Signature of faculty:<br>
      <span style="display:inline-block;margin-top:12mm">Name:&nbsp;<span class="fl" style="min-width:50mm;text-align: center;">&nbsp;<?= h($bill['tname']) ?>&nbsp;</span></span>
    </div>
  </div>

  <p class="cpara" style="margin-top:8mm">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I certify that the work load as stated above has been checked and amount claimed is correct and hence
    submitted to sanction. Certified that the amount is not exceeding the ceiling of 20% earned during the
    academic session / year.
  </p>

  <div style="margin-top:8mm;font-size:9.5pt;line-height:2">
    Signature of Dept. Faculty I/C<br>Date:
  </div>

  <div class="sgg" style="margin-top:6mm">
    <div></div>
    <div style="text-align: right;">
      Signature of HoD with Stamp<br>
      Date:&nbsp;<span class="fl" style="min-width:35mm">&nbsp;</span>
    </div>
  </div>

  <div class="prin">
    Principal<br>
    Govt. College of Engineering Aurangabad<br>
    Chhatrapati Sambhajinagar
  </div>
</div>


<!-- ======================================================
     PAGE 3 — ANNEXURE I (Lecture Details)
     ====================================================== -->
<div class="page">
  <p class="c b u" style="font-size:11pt;margin-bottom:1mm">ANNEXURE I</p>
  <p class="c b" style="font-size:10pt;margin-bottom:3mm">(For Visiting Faculty/ Expert faculty / Sessional Instructor)</p>

  <p class="b" style="margin-bottom:3mm">
    Details of academics and other works for the month of
    <span class="fl" style="min-width:40mm">&nbsp;<?= h($month) ?>&nbsp;</span>
    &nbsp;&nbsp; Class: <span class="fl" style="min-width:40mm">&nbsp;<?= h($bill['class_label']??'') ?>&nbsp;</span>
  </p>

  <table class="at">
    <thead>
      <tr>
        <th style="width:9mm">Sr.<br>No.</th>
        <th style="width:30mm">Date</th>
        <th>Subject</th>
        <th style="width:24mm">Theory<br>Hrs</th>
        <th style="width:24mm">Practical<br>Hrs</th>
        <th style="width:24mm">Other<br>Hrs</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $dateSpans = [];
      $nLec = count($lectures);
      for ($i = 0; $i < $nLec; ) {
          $dateVal = $lectures[$i]['lecture_date'];
          $j = $i + 1;
          while ($j < $nLec && $lectures[$j]['lecture_date'] === $dateVal) {
              $j++;
          }
          $span = $j - $i;
          for ($k = $i; $k < $j; $k++) {
              $dateSpans[$k] = ($k === $i) ? $span : 0;
          }
          $i = $j;
      }
      $rowsUsed=0;
      $srNo=1;
      foreach($lectures as $idx=>$l):
          $rowsUsed++;
      ?>
      <tr>
        <?php if ($dateSpans[$idx] > 0): ?>
        <td rowspan="<?= $dateSpans[$idx] ?>"><?= $srNo++ ?></td>
        <td rowspan="<?= $dateSpans[$idx] ?>"><?= date('d/m/Y',strtotime($l['lecture_date'])) ?></td>
        <?php endif; ?>
        <td class="tl"><?= h($l['subject_name']??'').' ('.h($l['subject_code']??'').')' ?></td>
        <td><?= n($l['theory_hours']) ?></td>
        <td><?= n($l['practical_hours']) ?></td>
        <td><?= n($l['other_hours']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php for($r=$rowsUsed;$r<18;$r++): ?>
      <tr style="height:7mm"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
      <?php endfor; ?>
      <tr>
        <td colspan="3" class="b">Total Hrs.</td>
        <td class="b"><?= n($tHrs) ?></td>
        <td class="b"><?= n($pHrs) ?></td>
        <td class="b"><?= n($oHrs) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="sgg" style="margin-top:8mm">
    <div style="font-size:9.5pt;line-height:2">Signature of Dept. Faculty I/C<br>Date:</div>
    <div class="sr" style="font-size:9.5pt;line-height:2">Signature &amp; Name of Faculty:</div>
  </div>
  <div style="text-align:right;margin-top:10mm;font-size:9.5pt;line-height:2">
    Signature of HoD with Stamp<br>Date:&nbsp;<span class="fl" style="min-width:35mm">&nbsp;</span>
  </div>
</div>


<!-- ======================================================
     PAGE 4 — ANNEXURE II (Regular / Full-time)
     ====================================================== -->
<div class="page">
  <p class="c b u" style="font-size:11pt;margin-bottom:1mm">ANNEXURE II</p>
  <p class="c b" style="font-size:10pt;margin-bottom:3mm">(For Full-Time PoP/ Regular/ Adjunct Faculty)</p>

  <p class="b" style="margin-bottom:3mm">
    Details of academics and other engagements for the month/semester
    <span class="fl" style="min-width:40mm">&nbsp;<?= h($month) ?>&nbsp;</span>
  </p>

  <table class="at">
    <thead><tr><th style="width:14mm">Sr.No.</th><th>Activities</th><th style="width:60mm">Particulars of participation</th></tr></thead>
    <tbody>
      <tr>
        <td>1</td>
        <td class="tl"><span class="b">Department Level</span><br>(e.g. Academic, Curriculum development,<br>T &amp; P activities, Faculty Development<br>Programme, Research &amp; Development etc.)</td>
        <td style="height:20mm"></td>
      </tr>
      <tr>
        <td>2</td>
        <td class="tl"><span class="b">Institute Level</span><br>(e.g. NBA, administrative work, Institute<br>level portfolio etc.)</td>
        <td style="height:20mm"></td>
      </tr>
      <tr>
        <td>3</td>
        <td class="tl"><span class="b">Any Other</span><br>(e.g. social events etc.)</td>
        <td style="height:18mm"></td>
      </tr>
    </tbody>
  </table>

  <div class="sgg" style="margin-top:12mm">
    <div style="font-size:9.5pt;line-height:2">Signature of Dept. Faculty I/C<br>Date:</div>
    <div class="sr" style="font-size:9.5pt;line-height:2">Signature &amp; Name of Faculty:</div>
  </div>
  <div style="text-align:right;margin-top:20mm;font-size:9.5pt;line-height:2">
    Signature of HoD with Stamp<br>Date:&nbsp;<span class="fl" style="min-width:35mm">&nbsp;</span>
  </div>
</div>

<script>
if (new URLSearchParams(window.location.search).get('print')==='1')
  window.addEventListener('load',()=>setTimeout(()=>window.print(),600));
</script>
</body>
</html>
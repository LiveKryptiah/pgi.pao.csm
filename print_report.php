<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// ---------- MONTH SELECTION ----------
$month = $_GET['month'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$month_label = date('F Y', strtotime($month . '-01'));

$stmt = $pdo->prepare("SELECT * FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = ?");
$stmt->execute([$month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

// ---------- NORMALIZERS (accept both Filipino form values and bulk-encoder values) ----------
function norm_age($v) {
    $v = strtolower(trim((string)$v));
    if ($v === '') return 6;
    if (strpos($v, 'below') !== false || strpos($v, 'lower') !== false) return 1;
    if (strpos($v, 'above') !== false || strpos($v, 'higher') !== false || strpos($v, '+') !== false) return 5;
    if (preg_match('/(\d+)\s*-\s*(\d+)/', $v, $m)) {
        $lo = (int)$m[1];
        if ($lo < 20) return 1;
        if ($lo < 35) return 2;
        if ($lo < 50) return 3;
        if ($lo < 65) return 4;
        return 5;
    }
    return 6;
}
function norm_sex($v) {
    $v = strtolower(trim((string)$v));
    if ($v === 'male' || $v === 'lalake') return 1;
    if ($v === 'female' || $v === 'babae') return 2;
    return 3;
}
function norm_client($v) {
    $v = strtolower(trim((string)$v));
    if ($v === 'citizen' || $v === 'mamamayan') return 1;
    if ($v === 'business' || $v === 'negosyo') return 2;
    if ($v === 'government' || $v === 'gobyerno') return 3;
    return 4;
}
function norm_cc1($v) {
    $v = trim((string)$v);
    return in_array($v, ['1','2','3','4']) ? (int)$v : 0;
}
function norm_cc2($v) {
    $v = strtolower(trim((string)$v));
    if (in_array($v, ['1','2','3','4','5'])) return (int)$v;
    if ($v === 'madaling makita') return 1;
    if ($v === 'medyo madaling makita') return 2;
    if ($v === 'mahirap makita') return 3;
    if ($v === 'hindi makita') return 4;
    if ($v === 'n/a') return 5;
    return 0;
}
function norm_cc3($v) {
    $v = strtolower(trim((string)$v));
    if (in_array($v, ['1','2','3','4'])) return (int)$v;
    if ($v === 'sobrang nakakatulong') return 1;
    if ($v === 'nakakatulong naman') return 2;
    if ($v === 'hindi nakatulong') return 3;
    if ($v === 'n/a') return 4;
    return 0;
}
function norm_trans($v) {
    $v = strtolower((string)$v);
    if (strpos($v, 'agri') !== false) return 3;
    if (strpos($v, 'urban to urban') !== false || strpos($v, 'reclassification') !== false) return 4;
    if (strpos($v, 'subdivision') !== false) return 2;
    if (strpos($v, 'transfer') !== false) return 1;
    if (strpos($v, 'reassessment') !== false) return 5;
    if (strpos($v, 'discovery') !== false) return 6;
    if (strpos($v, 'certification') !== false) return 7;
    if (strpos($v, 'verification') !== false) return 8;
    if (strpos($v, 'annotation') !== false) return 9;
    if (strpos($v, 'ocular') !== false) return 10;
    if (strpos($v, 'cancellation of td') !== false) return 11;
    if (strpos($v, 'tmcr') !== false || strpos($v, 'section map') !== false) return 12;
    if (strpos($v, 'posting') !== false) return 13;
    return 0;
}
function norm_sqd($v) {
    $v = strtoupper(trim((string)$v));
    if (in_array($v, ['5','4','3','2','1'])) return $v;
    return 'NA';
}

// ---------- TALLIES ----------
$age = array_fill(1, 6, 0);
$sex = array_fill(1, 3, 0);
$client = array_fill(1, 4, 0);
$cc1 = array_fill(1, 4, 0);
$cc2 = array_fill(1, 5, 0);
$cc3 = array_fill(1, 4, 0);
$trans = array_fill(1, 13, 0);

$sqd_keys = ['5','4','3','2','1','NA'];
$sqd_rows = [
    'sqd0' => 'SQD0',
    'sqd1' => 'Responsiveness',
    'sqd2' => 'Reliability',
    'sqd3' => 'Access and Facilities',
    'sqd4' => 'Communication',
    'sqd5' => 'Costs',
    'sqd6' => 'Integrity',
    'sqd7' => 'Assurance',
    'sqd8' => 'Outcome',
];
$sqd = [];
foreach ($sqd_rows as $k => $_) $sqd[$k] = array_fill_keys($sqd_keys, 0);

foreach ($rows as $r) {
    $age[norm_age($r['age_group'] ?? '')]++;
    $sex[norm_sex($r['gender'] ?? '')]++;
    $client[norm_client($r['client_type'] ?? '')]++;
    $a = norm_cc1($r['cc1'] ?? ''); if ($a) $cc1[$a]++;
    $a = norm_cc2($r['cc2'] ?? ''); if ($a) $cc2[$a]++;
    $a = norm_cc3($r['cc3'] ?? ''); if ($a) $cc3[$a]++;
    $t = norm_trans($r['transaction_type'] ?? ''); if ($t) $trans[$t]++;
    foreach ($sqd_rows as $k => $_) $sqd[$k][norm_sqd($r[$k] ?? '')]++;
}

$services = [
    1  => 'Pagpasya sa Pagsasagawa ng Tasasyon Mula sa Tanggapan ng Pang-Munisipyong Tagatasa at mga Tasasyong Gawa sa Opisina',
    2  => 'Pagpasya sa Pagsasagawa ng Tasasyon mula sa Tanggapan ng Pang-Munisipyong Tagatasa - Paghati-hati / Pagkonsolida o Kung May Maiiwang Parte sa Ilibipat',
    3  => 'Pagpasya sa pagsasagawa ng tasasyon mula sa tanggapan ng Pang-Munisipyong Tagatasa - sa mga pagreklasipika ng lupa mula sa agrikultural tungo sa pagkaresidensiyal, komersyal, industriyal, o espesyal',
    4  => 'Pagpasya sa pagsasagawa ng Tasasyon mula sa tanggapan ng Pang-Munisipyong Tagatasa (Municipal Assessor\'s Office) sa mga pagreklasipika ng lupa mula sa residensiyal, komersyal, industriyal o espesyal tungo sa pagka residensiyal, agrikultural, komersyal, industriyal, o espesyal',
    5  => 'Pagbabago ng detalye sa Deklarasyon',
    6  => 'Pagpasya sa pagsasagawa ng tasasyon mula sa tanggapan ng pang-munisipyong tagatasa – pagdeklara ng ari-arian sa unang pagkakataon',
    7  => 'Paghingi/pagkuha ng sertipikasyon, sertipikong kopya ng deklarasyon o pagpapakopya (photocopy) ng deklarasyon o dokumento ng ibat-ibang uri ng mga ari-ariang hindi natitinag (bago o luma)',
    8  => 'Pagberipika ng kasaysayan o nangyari sa ari-arian na hindi natitinag',
    9  => 'Anotasyon o kanselasyon ng pagkakasanla, piyansa at paghahabol ng mga ari-arian na hindi natitinag',
    10 => 'Pag inspeksyon/pagsusuri sa mga ari-arian hindi natitinag ng mga kasapi sa panlalawigang lupon ng tagatasa',
    11 => 'Kanselasyon ng nadobleng ari-ariang hindi natitinag, kanselasyon ng ginibang gusali, o pagretiro ng makinarya',
    12 => 'Paghingi/pagkuha ng sertipikadong kopya ng Tax Map Control Roll o Section Map',
    13 => 'Pagpaskil sa bulletin board ng Opisina ng Panlalawigang Tagatasa',
];

$letter_date = date('F j, Y');
$h = function($s){ return htmlspecialchars((string)$s); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CSM Report - <?= $h($month_label) ?></title>
<link rel="icon" type="image/x-icon" href="pao.ico">
<style>
    @page { size: 8.5in 13in; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 11pt; }

    .toolbar { position: sticky; top: 0; background: #0f172a; color: #fff; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 10; font-size: 13px; }
    .toolbar a, .toolbar button, .toolbar select { font: inherit; padding: 6px 14px; border-radius: 6px; border: 0; cursor: pointer; text-decoration: none; }
    .toolbar button { background: #059669; color: #fff; font-weight: 700; }
    .toolbar a { background: #334155; color: #fff; }
    .toolbar .hint { color: #94a3b8; margin-left: auto; }

    .page { width: 8.5in; height: 13in; margin: 16px auto; background: #fff; padding: 0.9in 0.9in 0.7in; position: relative; page-break-after: always; box-shadow: 0 2px 10px rgba(0,0,0,.2); overflow: hidden; }
    .page:last-child { page-break-after: auto; }

    /* LETTER */
    .letterhead { text-align: center; line-height: 1.2; }
    .letterhead img { width: 85px; height: 85px; object-fit: contain; }
    .letterhead .small { font-size: 9.5pt; }
    .letterhead .prov { font-size: 11pt; }
    .office-title { text-align: center; font-weight: 800; font-size: 14pt; margin: 22px 0 18px; letter-spacing: .3px; }
    .letter p { margin: 0 0 14px; line-height: 1.4; }
    .letter .sig { margin-top: 44px; line-height: 1.2; }

    /* REPORT HEADINGS */
    .rep-head { text-align: center; margin-bottom: 18px; line-height: 1.25; }
    .rep-head .o { font-weight: 800; font-size: 15pt; }
    .rep-head .s { font-size: 12.5pt; }
    .rep-title { text-align: center; font-weight: 800; font-size: 14pt; margin: 14px 0 16px; }

    table.rep { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 22px; }
    table.rep th, table.rep td { border: 1px solid #555; padding: 2px 6px; }
    table.rep th { background: #ddd5b0; font-weight: 700; text-align: center; }
    table.rep td.n { text-align: right; width: 38%; }
    table.rep tr.total td { background: #d0d0d0; font-weight: 700; }
    table.rep tr.q td { background: #fff; font-weight: 700; font-style: italic; }
    table.rep.center td.c, table.rep td.c { text-align: center; }
    table.rep .lbl { font-weight: 500; }

    table.sqd th { background: #ececec; font-weight: 500; }
    table.sqd td { text-align: center; }
    table.sqd td.l { text-align: left; border-left: 0; }
    .sec-label { font-size: 9pt; margin: 4px 0 2px; text-decoration: underline; }

    table.svc { font-size: 9pt; font-family: 'Cambria', 'Times New Roman', serif; }
    table.svc th { background: #ddd5b0; font-family: Arial, sans-serif; }
    table.svc td.num { text-align: center; width: 15%; }
    table.svc td.svc-name { padding: 3px 8px; }
    [contenteditable]:focus { outline: 2px solid #059669; background: #ecfdf5; }

    .signatories { display: flex; justify-content: space-between; margin-top: 26px; font-size: 10pt; }
    .signatories .col { width: 46%; line-height: 1.2; }
    .signatories .lab { color: #333; margin-bottom: 42px; }

    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .page { margin: 0; box-shadow: none; }
        [contenteditable]:focus { outline: none; background: none; }
    }
</style>
</head>
<body>

<div class="toolbar">
    <a href="dashboard.php?month=<?= $h($month) ?>">&larr; Back</a>
    <form method="GET" style="margin:0">
        <input type="month" name="month" value="<?= $h($month) ?>" onchange="this.form.submit()" style="padding:5px 8px;border-radius:6px;border:0;">
    </form>
    <button onclick="window.print()">Print / Save as PDF</button>
    <span class="hint"><?= $total ?> responses · Click Letter / Total Transactions cells to edit before printing</span>
</div>

<!-- ============ PAGE 1: COVER LETTER ============ -->
<section class="page letter">
    <div class="letterhead">
        <img src="image_4c6ce6.jpg" alt="Seal">
        <div class="small" style="margin-top:6px;">Republic of the Philippines</div>
        <div class="prov">PROVINCE OF ISABELA</div>
        <div class="small">City of Ilagan Isabela</div>
    </div>
    <div class="office-title">OFFICE OF THE PROVINCIAL ASSESSOR</div>

    <p contenteditable="true"><?= $h($letter_date) ?></p>

    <p style="margin-top:26px; line-height:1.25;">
        <strong contenteditable="true">MS. JANETTE A. CASTILLEJO</strong><br>
        <span contenteditable="true">Provincial Human Resource Management Officer</span><br>
        <span contenteditable="true">Province of Isabela</span>
    </p>

    <p>Dear Ma'am:</p>
    <p><em>Greetings!</em></p>
    <p style="text-align: justify;">We are submitting herewith our office's Client Satisfaction Measurement (CSM) Report, for the month of <?= $h($month_label) ?>, for your information and reference.</p>
    <p>Please acknowledge receipt hereof.</p>
    <p>Thank you.</p>
    <p>Very truly yours,</p>

    <div class="sig">
        <strong>ZENAIDA A. GABRIEL</strong><br>
        Provincial Assessor
    </div>
</section>

<!-- ============ PAGE 2: AGE, SEX & CUSTOMER TYPE ============ -->
<section class="page">
    <div class="rep-head">
        <div class="o">PROVINCIAL ASSESSOR'S OFFICE</div>
        <div class="s">Client Satisfaction Measurement Report</div>
        <div class="s"><?= $h($month_label) ?></div>
    </div>
    <div class="rep-title">AGE, SEX &amp; CUSTOMER TYPE</div>

    <?php
    $age_labels = [1=>'1. 19 or lower',2=>'2. 20-34',3=>'3. 35-49',4=>'4. 50-64',5=>'5. 65 or higher',6=>'6. Did not specify'];
    $sex_labels = [1=>'1. Male',2=>'2. Female',3=>'3. Did not specify'];
    $cl_labels  = [1=>'D4. Citizen',2=>'D4. Business',3=>'D4. Government',4=>'D4. Did not specify'];
    ?>
    <table class="rep">
        <tr><th style="width:24%; text-align:left;">D1. Age and D2. Sex</th><th>EXTERNAL</th><th>OVERALL RESPONSES</th></tr>
        <?php foreach ($age_labels as $i => $l): ?>
        <tr><td><?= $l ?></td><td class="c"><?= $age[$i] ?></td><td class="c"><?= $age[$i] ?></td></tr>
        <?php endforeach; ?>
        <tr class="total"><td>TOTAL</td><td class="c"><?= array_sum($age) ?></td><td class="c"><?= array_sum($age) ?></td></tr>
        <tr><th style="text-align:center;">Sex</th><th></th><th></th></tr>
        <?php foreach ($sex_labels as $i => $l): ?>
        <tr><td><?= $l ?></td><td class="c"><?= $sex[$i] ?></td><td class="c"><?= $sex[$i] ?></td></tr>
        <?php endforeach; ?>
        <tr class="total"><td>TOTAL</td><td class="c"><?= array_sum($sex) ?></td><td class="c"><?= array_sum($sex) ?></td></tr>
    </table>

    <table class="rep">
        <tr><th style="width:24%; text-align:left;">Customer Type</th><th>EXTERNAL</th><th>OVERALL RESPONSES</th></tr>
        <?php foreach ($cl_labels as $i => $l): ?>
        <tr><td><?= $l ?></td><td class="c"><?= $client[$i] ?></td><td class="c"><?= $client[$i] ?></td></tr>
        <?php endforeach; ?>
        <tr class="total"><td>TOTAL</td><td class="c"><?= array_sum($client) ?></td><td class="c"><?= array_sum($client) ?></td></tr>
    </table>
</section>

<!-- ============ PAGE 3: MONTHLY CC & SQD ============ -->
<section class="page">
    <div class="rep-head" style="margin-bottom:12px;">
        <div class="o">MONTHLY CC &amp; SQD</div>
        <div class="s"><?= $h($month_label) ?></div>
    </div>

    <?php
    $cc_blocks = [
        ['q' => 'CC1. Which of the following describes your awareness of the CC?', 'data' => $cc1, 'labels' => [
            1=>'1. I know what a CC is and I saw this office\'s CC',
            2=>'2. I know what a CC is but I did not see this office\'s CC',
            3=>'3. I learned of the CC only when I saw this office\'s CC',
            4=>'4. I do not know what CC is and I did not see this office\'s CC']],
        ['q' => 'CC2. If aware of CC, would you say that the CC of this office was...?', 'data' => $cc2, 'labels' => [
            1=>'1. Easy to see', 2=>'2. Somewhat easy to see', 3=>'3. Difficult to see', 4=>'4. Not visible at all', 5=>'5. Not applicable']],
        ['q' => 'CC3. If aware of CC, how much did the CC help you in your transaction?', 'data' => $cc3, 'labels' => [
            1=>'1. Helped very much', 2=>'2. Somewhat helped', 3=>'3. Did not help', 4=>'4. Not applicable']],
    ];
    ?>
    <table class="rep">
        <tr><th>Citizen's Charter Answers</th><th style="width:14%;">Overall Responses</th></tr>
        <?php foreach ($cc_blocks as $b): ?>
        <tr class="q"><td colspan="2"><?= $h($b['q']) ?></td></tr>
        <?php foreach ($b['labels'] as $i => $l): ?>
        <tr><td class="lbl"><?= $h($l) ?></td><td class="c"><?= $b['data'][$i] ?></td></tr>
        <?php endforeach; ?>
        <tr class="total"><td>TOTAL</td><td class="c"><?= array_sum($b['data']) ?></td></tr>
        <?php endforeach; ?>
    </table>

    <div class="sec-label">EXTERNAL SERVICES</div>
    <table class="rep sqd" style="margin-bottom:14px;">
        <tr style="height:52px;">
            <th style="width:13%;background:#fff;"></th><th>Strongly Agree</th><th>Agree</th><th>Neither Agree nor Disagree</th><th>Disagree</th><th>Strongly Disagree</th><th>N/A</th><th>Total Responses</th>
        </tr>
        <?php $r0 = $sqd['sqd0']; ?>
        <tr>
            <td class="l" style="border-left:1px solid #555;">SQD0</td>
            <td><?= $r0['5'] ?></td><td><?= $r0['4'] ?></td><td><?= $r0['3'] ?></td><td><?= $r0['2'] ?></td><td><?= $r0['1'] ?></td><td><?= $r0['NA'] ?></td><td><?= array_sum($r0) ?></td>
        </tr>
    </table>

    <table class="rep sqd">
        <tr style="height:52px;">
            <th style="width:20%;">Service Quality Dimensions</th><th>Strongly Agree</th><th>Agree</th><th>Neither Agree nor Disagree</th><th>Disagree</th><th>Strongly Disagree</th><th>N/A</th><th>Total Responses</th>
        </tr>
        <?php
        $col_tot = array_fill_keys($sqd_keys, 0); $grand = 0;
        foreach ($sqd_rows as $k => $label):
            if ($k === 'sqd0') continue;
            $rw = $sqd[$k]; $rt = array_sum($rw);
            foreach ($sqd_keys as $kk) $col_tot[$kk] += $rw[$kk];
            $grand += $rt;
        ?>
        <tr>
            <td class="l" style="border-left:1px solid #555;"><?= $label ?></td>
            <td><?= $rw['5'] ?></td><td><?= $rw['4'] ?></td><td><?= $rw['3'] ?></td><td><?= $rw['2'] ?></td><td><?= $rw['1'] ?></td><td><?= $rw['NA'] ?></td><td><?= number_format($rt) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total">
            <td class="l" style="border-left:1px solid #555; background:#d0d0d0;">Overall</td>
            <td><?= $col_tot['5'] ?></td><td><?= $col_tot['4'] ?></td><td><?= $col_tot['3'] ?></td><td><?= $col_tot['2'] ?></td><td><?= $col_tot['1'] ?></td><td><?= $col_tot['NA'] ?></td><td><?= number_format($grand) ?></td>
        </tr>
    </table>
</section>

<!-- ============ PAGE 4: OVERALL RESPONSES & TOTAL TRANSACTION ============ -->
<section class="page">
    <div class="rep-head" style="margin-bottom:14px;">
        <div class="o">OVERALL RESPONSES &amp; TOTAL TRANSACTION</div>
        <div class="s"><?= $h($month_label) ?></div>
    </div>

    <table class="rep svc" id="svcTable">
        <tr style="height:42px;"><th>External Services</th><th style="width:15%;">Responses</th><th style="width:15%;">Total Transactions</th></tr>
        <?php foreach ($services as $i => $name): ?>
        <tr>
            <td class="svc-name"><?= $i ?>.&nbsp; <?= $h($name) ?></td>
            <td class="num"><?= $trans[$i] ?></td>
            <td class="num tt" contenteditable="true"><?= $trans[$i] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total"><td style="font-family:Arial;">EXTERNAL SERVICE TOTAL</td><td class="c"><?= array_sum($trans) ?></td><td class="c" id="tt1"><?= array_sum($trans) ?></td></tr>
        <tr class="total"><td style="font-family:Arial;">OVERALL TOTAL</td><td class="c"><?= array_sum($trans) ?></td><td class="c" id="tt2"><?= array_sum($trans) ?></td></tr>
    </table>

    <div class="signatories">
        <div class="col">
            <div class="lab">Prepared by:</div>
            <strong contenteditable="true">ROWENA T. SEGUI</strong><br>
            <span contenteditable="true">LAOO III</span>
        </div>
        <div class="col">
            <div class="lab">Noted:</div>
            <strong contenteditable="true">ZENAIDA A. GABRIEL</strong><br>
            Provincial Assessor
        </div>
    </div>
</section>

<script>
    // Recompute Total Transactions when the editable column changes
    function recalc() {
        let sum = 0;
        document.querySelectorAll('#svcTable td.tt').forEach(td => {
            sum += parseInt(td.innerText.replace(/[^0-9]/g, '')) || 0;
        });
        const f = sum.toLocaleString();
        document.getElementById('tt1').innerText = f;
        document.getElementById('tt2').innerText = f;
    }
    document.querySelectorAll('#svcTable td.tt').forEach(td => td.addEventListener('input', recalc));
    recalc();
</script>
</body>
</html>

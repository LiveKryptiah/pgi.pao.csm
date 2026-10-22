<?php
// Start output buffering immediately so no warning or whitespace breaks JSON responses
ob_start();

// ==========================================
// CONFIGURATION & ERROR HANDLING
// ==========================================
error_reporting(E_ALL);
// Prevent raw HTML errors from corrupting JSON payloads
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SECURITY CHECK (JSON-aware for AJAX calls)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_GET['action']) && in_array($_GET['action'], ['get_csm_export', 'download_csm_csv'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Your administrator session has expired. Please refresh the page and sign in again.'
        ]);
        exit;
    }
    header("Location: login.php");
    exit;
}

require 'db.php'; 

$transactionTypes = [
    "Transfer",
    "Subdivision/Consolidation",
    "Reclassification (Agri to Urban)",
    "Reclassification (Urban to Urban)",
    "Reassessment (DP/PC/DT)",
    "Discovery/New Declaration",
    "Certification/CTC/CPC",
    "Verification/Back Tracking",
    "Annotation/Cancellation of Annotation",
    "Ocular Inspection",
    "Cancellation of TD",
    "TMCR/Section Map/s",
    "Posting"
];

$servicesMeta = [
    1  => [
        'name'  => 'Transfer',
        'title' => 'Transfer / Paglilipat',
        'desc'  => 'Pagpasya sa Pagsasagawa ng Tasasyon mula sa MAO at Opisina',
        'color' => '#2563eb'
    ],
    2  => [
        'name'  => 'Subdivision/Consolidation',
        'title' => 'Subdivision / Consolidation',
        'desc'  => 'Paghati-hati / Pagkonsolida o Maiiwang Parte sa Ililipat',
        'color' => '#0284c7'
    ],
    3  => [
        'name'  => 'Reclassification (Agri to Urban)',
        'title' => 'Reclassification (Agri to Urban)',
        'desc'  => 'Pagreklasipika ng Lupa mula Agrikultural tungo sa Residensiyal / Komersyal',
        'color' => '#16a34a'
    ],
    4  => [
        'name'  => 'Reclassification (Urban to Urban)',
        'title' => 'Reclassification (Urban to Urban)',
        'desc'  => 'Pagreklasipika mula Residensiyal tungo sa Komersyal / Industriyal / Espesyal',
        'color' => '#0d9488'
    ],
    5  => [
        'name'  => 'Reassessment (DP/PC/DT)',
        'title' => 'Reassessment (DP / PC / DT)',
        'desc'  => 'Pagbabago ng Detalye sa Deklarasyon ng Ari-arian',
        'color' => '#e11d48'
    ],
    6  => [
        'name'  => 'Discovery/New Declaration',
        'title' => 'Discovery / New Declaration',
        'desc'  => 'Pagdeklara ng Ari-arian sa Unang Pagkakataon',
        'color' => '#d97706'
    ],
    7  => [
        'name'  => 'Certification/CTC/CPC',
        'title' => 'Certification / CTC / CPC',
        'desc'  => 'Paghingi / Pagkuha ng Sertipikasyon o Sertipikadong Kopya / Photocopy',
        'color' => '#7c3aed'
    ],
    8  => [
        'name'  => 'Verification/Back Tracking',
        'title' => 'Verification / Back Tracking',
        'desc'  => 'Pagberipika ng Kasaysayan o Nangyari sa Ari-ariang Hindi Natitinag',
        'color' => '#4f46e5'
    ],
    9  => [
        'name'  => 'Annotation/Cancellation of Annotation',
        'title' => 'Annotation / Cancellation',
        'desc'  => 'Anotasyon o Kanselasyon ng Pagkakasanla, Piyansa at Paghahabol',
        'color' => '#9333ea'
    ],
    10 => [
        'name'  => 'Ocular Inspection',
        'title' => 'Ocular Inspection',
        'desc'  => 'Pag-inspeksyon / Pagsusuri sa mga Ari-arian ng Kasapi sa Panlalawigan',
        'color' => '#ca8a04'
    ],
    11 => [
        'name'  => 'Cancellation of TD',
        'title' => 'Cancellation of TD',
        'desc'  => 'Kanselasyon ng Nadobleng Ari-arian, Ginibang Gusali, o Retiradong Makinarya',
        'color' => '#dc2626'
    ],
    12 => [
        'name'  => 'TMCR/Section Map/s',
        'title' => 'TMCR / Section Maps',
        'desc'  => 'Paghingi ng Sertipikadong Kopya ng Tax Map Control Roll o Section Map',
        'color' => '#0891b2'
    ],
    13 => [
        'name'  => 'Posting',
        'title' => 'Posting sa Bulletin Board',
        'desc'  => 'Pagpaskil sa Bulletin Board ng Opisina ng Panlalawigang Tagatasa',
        'color' => '#475569'
    ]
];

$isabelaLocations = [
    "Alicia", "Angadanan", "Aurora", "Benito Soliven", "Burgos", "Cabagan", "Cabatuan", "Cauayan City", "Cordon", "Delfin Albano", 
    "Dinapigue", "Divilacan", "Echague", "Gamu", "Ilagan City", "Jones", "Luna", "Maconacon", "Mallig", "Naguilian", "Palanan", 
    "Quezon", "Quirino", "Ramon", "Reina Mercedes", "Roxas", "San Agustin", "San Guillermo", "San Isidro", "San Manuel", 
    "San Mariano", "San Mateo", "San Pablo", "Santa Maria", "Santiago City", "Santo Tomas", "Tumauini", "Outside Isabela"
];
sort($isabelaLocations);

$success_msg = "";
$error_msg = "";
$last_gen_month = "";

// ==========================================
// CSM EXCEL & CSV EXPORT GENERATOR
// Formatted matching Provincial Assessor's Office (PAO Isabela) CSM sheet
// ==========================================
function buildCsmExportData($pdo, $month) {
    $driver = 'mysql';
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {}

    $month_sql = ($driver === 'pgsql') ? "TO_CHAR(survey_date, 'YYYY-MM')" : "DATE_FORMAT(survey_date, '%Y-%m')";
    $stmt = $pdo->prepare("SELECT * FROM responses WHERE {$month_sql} = :month ORDER BY survey_date ASC, id ASC");
    $stmt->execute([':month' => $month]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $month_ts = strtotime($month . '-01');
    $month_abbr = strtoupper(date('M', $month_ts));
    $month_full = date('F Y', $month_ts);
    $month_2digit = date('m', $month_ts);

    $map_client = function($ctype) {
        $c = strtolower(trim($ctype ?? ''));
        if (strpos($c, 'citizen') !== false || strpos($c, 'mamayan') !== false || strpos($c, 'mamamayan') !== false) return 1;
        if (strpos($c, 'business') !== false || strpos($c, 'negosyo') !== false) return 2;
        if (strpos($c, 'government') !== false || strpos($c, 'gobyerno') !== false) return 3;
        return 4;
    };

    $map_gender = function($gender) {
        $g = strtolower(trim($gender ?? ''));
        if ($g === 'male' || $g === 'lalaki' || $g === 'lalake') return 1;
        if ($g === 'female' || $g === 'babae') return 2;
        return 3;
    };

    $map_age = function($age) {
        $a = strtolower(trim($age ?? ''));
        if ($a === '15-19' || strpos($a, '19') !== false) return 1;
        if ($a === '20-29' || $a === '20-34' || $a === '20-30') return 2;
        if ($a === '30-39' || $a === '35-49') return 3;
        if ($a === '40-49' || $a === '50-64' || $a === '50-59') return 4;
        if (strpos($a, '60+') !== false || strpos($a, '65') !== false) return 5;
        return 6;
    };

    $map_service = function($trans) {
        $t = strtolower(trim($trans ?? ''));
        if (strpos($t, 'transfer') !== false) return 1;
        if (strpos($t, 'subdivision') !== false || strpos($t, 'consolidation') !== false) return 2;
        if (strpos($t, 'agri to urban') !== false) return 3;
        if (strpos($t, 'urban to urban') !== false) return 4;
        if (strpos($t, 'reassessment') !== false || strpos($t, 'dp') !== false || strpos($t, 'pc') !== false) return 5;
        if (strpos($t, 'discovery') !== false || strpos($t, 'declaration') !== false) return 6;
        if (strpos($t, 'certification') !== false || strpos($t, 'ctc') !== false || strpos($t, 'cpc') !== false || strpos($t, 'photocopy') !== false || strpos($t, 'certificate') !== false) return 7;
        if (strpos($t, 'verification') !== false || strpos($t, 'back') !== false || strpos($t, 'tracking') !== false || strpos($t, 'tracing') !== false) return 8;
        if (strpos($t, 'annotation') !== false) return 9;
        if (strpos($t, 'ocular') !== false) return 10;
        if (strpos($t, 'cancellation of td') !== false) return 11;
        if (strpos($t, 'tmcr') !== false || strpos($t, 'map') !== false) return 12;
        if (strpos($t, 'posting') !== false) return 13;
        return 7;
    };

    $map_cc1 = function($v) {
        $v = strtolower(trim((string)$v));
        if ($v === '1' || (strpos($v, 'nakita') !== false && strpos($v, 'alam') !== false)) return 1;
        if ($v === '2' || (strpos($v, 'hindi') !== false && strpos($v, 'nakita') !== false)) return 2;
        if ($v === '3' || strpos($v, 'nalaman') !== false) return 3;
        if ($v === '4' || strpos($v, 'hindi alam') !== false) return 4;
        return 1;
    };

    $map_cc2 = function($v) {
        $v = strtolower(trim((string)$v));
        if ($v === '1' || (strpos($v, 'madaling') !== false && strpos($v, 'medyo') === false) || strpos($v, 'easy') !== false) return 1;
        if ($v === '2' || strpos($v, 'medyo') !== false) return 2;
        if ($v === '3' || strpos($v, 'mahirap') !== false || strpos($v, 'diffic') !== false) return 3;
        if ($v === '4' || strpos($v, 'not visible') !== false || (strpos($v, 'hindi') !== false && strpos($v, 'makita') !== false)) return 4;
        if ($v === '5' || $v === 'n/a') return 5;
        return 1;
    };

    $map_cc3 = function($v) {
        $v = strtolower(trim((string)$v));
        if ($v === '1' || strpos($v, 'sobrang') !== false || strpos($v, 'very') !== false) return 1;
        if ($v === '2' || strpos($v, 'naman') !== false || strpos($v, 'somewhat') !== false) return 2;
        if ($v === '3' || strpos($v, 'hindi') !== false || strpos($v, 'did not') !== false) return 3;
        if ($v === '4' || $v === 'n/a') return 4;
        return 1;
    };

    $map_sqd = function($v) {
        $v = trim((string)$v);
        if ($v === '5') return 5;
        if ($v === '4') return 4;
        if ($v === '3') return 3;
        if ($v === '2') return 2;
        if ($v === '1') return 1;
        if ($v === '6' || strtoupper($v) === 'N/A') return 6;
        return 5;
    };

    $total_cnt = count($records);
    $tally_client = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $tally_gender = [1 => 0, 2 => 0, 3 => 0];
    $tally_age = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
    $tally_serv = array_fill(1, 13, 0);
    $tally_cc1 = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $tally_cc2 = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $tally_cc3 = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $sqd_keys = ['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8'];
    $tally_sqd = [];
    foreach ($sqd_keys as $sk) {
        $tally_sqd[$sk] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
    }

    $raw_data_rows = [];
    $seq = 1;

    foreach ($records as $row) {
        $c_code = $map_client($row['client_type'] ?? '');
        $g_code = $map_gender($row['gender'] ?? '');
        $a_code = $map_age($row['age_group'] ?? '');
        $s_code = $map_service($row['transaction_type'] ?? '');
        $cc1_c  = $map_cc1($row['cc1'] ?? '1');
        $cc2_c  = $map_cc2($row['cc2'] ?? '1');
        $cc3_c  = $map_cc3($row['cc3'] ?? '1');

        $tally_client[$c_code]++;
        $tally_gender[$g_code]++;
        $tally_age[$a_code]++;
        $tally_serv[$s_code]++;
        $tally_cc1[$cc1_c]++;
        $tally_cc2[$cc2_c]++;
        $tally_cc3[$cc3_c]++;

        $sqd_codes = [];
        foreach ($sqd_keys as $sk) {
            $sc = $map_sqd($row[$sk] ?? 5);
            $sqd_codes[$sk] = $sc;
            $tally_sqd[$sk][$sc]++;
        }

        $s_date = $row['survey_date'] ?? '';
        $date_day = !empty($s_date) && $s_date !== '0000-00-00' ? (int)date('j', strtotime($s_date)) : 1;
        $row_month = !empty($s_date) && $s_date !== '0000-00-00' ? strtoupper(date('M', strtotime($s_date))) : $month_abbr;

        $comments = preg_replace('/[\r\n\t]+/', ' ', trim($row['suggestions'] ?? ''));

        // Standardized Control Number starting from E{MM}-001, E{MM}-002 ...
        // strictly matching Provincial Assessor's Office (PAO Isabela) CSM spreadsheet standard
        $formatted_ctrl_num = sprintf("E%s-%03d", $month_2digit, $seq);

        $raw_data_rows[] = [
            $formatted_ctrl_num,
            'E',
            $row_month,
            $date_day,
            $seq,
            '',
            '1',
            $c_code,
            $g_code,
            $a_code,
            '',
            $s_code,
            $cc1_c,
            $cc2_c,
            $cc3_c,
            $sqd_codes['sqd0'],
            $sqd_codes['sqd1'],
            $sqd_codes['sqd2'],
            $sqd_codes['sqd3'],
            $sqd_codes['sqd4'],
            $sqd_codes['sqd5'],
            $sqd_codes['sqd6'],
            $sqd_codes['sqd7'],
            $sqd_codes['sqd8'],
            $comments
        ];
        $seq++;
    }

    $summary_rows = [
        ['Internal or External', '', ''],
        ['1', 'External', $total_cnt],
        ['2', 'Internal', 0],
        ['', '', ''],
        ['Uri ng Kliyente', '', ''],
        ['1', 'Citizen/Mamayan', $tally_client[1]],
        ['2', 'Business/Negosyo', $tally_client[2]],
        ['3', 'Government/Gobyerno', $tally_client[3]],
        ['4', 'Did not specify', $tally_client[4]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['Kasarian', '', ''],
        ['1', 'Lalaki', $tally_gender[1]],
        ['2', 'Babae', $tally_gender[2]],
        ['3', 'Did not specify', $tally_gender[3]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['Edad', '', ''],
        ['1', '19 or lower', $tally_age[1]],
        ['2', '20-34', $tally_age[2]],
        ['3', '35-49', $tally_age[3]],
        ['4', '50-64', $tally_age[4]],
        ['5', '65 or higher', $tally_age[5]],
        ['6', 'Did not specify', $tally_age[6]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['Services Availed', '', ''],
        ['1', 'TR', $tally_serv[1]],
        ['2', 'SD / CS', $tally_serv[2]],
        ['3', 'RC (Agri to Urban)', $tally_serv[3]],
        ['4', 'RC (Urban to Urban)', $tally_serv[4]],
        ['5', 'DP / PC / DT', $tally_serv[5]],
        ['6', 'DC', $tally_serv[6]],
        ['7', 'Certification / Certified Photocopy / Certified True Copy', $tally_serv[7]],
        ['8', 'Verification / Back Tracing', $tally_serv[8]],
        ['9', 'Annotation / Cancellation of Annotation', $tally_serv[9]],
        ['10', 'Ocular Inspection', $tally_serv[10]],
        ['11', 'Cancellation of TD', $tally_serv[11]],
        ['12', 'TMCR/Section Map/s', $tally_serv[12]],
        ['13', 'Posting', $tally_serv[13]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['CC1', '', ''],
        ['1', "I know what a CC is and I saw this office's CC", $tally_cc1[1]],
        ['2', "I know what a CC but I did not not see this office's CC", $tally_cc1[2]],
        ['3', "I learned of the CC only when I saw this office's CC", $tally_cc1[3]],
        ['4', "I do not know what a CC is and I did not see this office's CC", $tally_cc1[4]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['CC2', '', ''],
        ['1', "Easy to see", $tally_cc2[1]],
        ['2', "Somewhat easy to see", $tally_cc2[2]],
        ['3', "Difficukt to see", $tally_cc2[3]],
        ['4', "Not visible at all", $tally_cc2[4]],
        ['5', "N/A", $tally_cc2[5]],
        ['', 'TOTAL', $total_cnt],
        ['', '', ''],
        ['CC3', '', ''],
        ['1', "Helped very much", $tally_cc3[1]],
        ['2', "Somewhat helped", $tally_cc3[2]],
        ['3', "Did not helped", $tally_cc3[3]],
        ['4', "N/A", $tally_cc3[4]],
        ['', 'TOTAL', $total_cnt],
    ];

    foreach ($sqd_keys as $sk) {
        $summary_rows[] = ['', '', ''];
        $summary_rows[] = [strtoupper($sk), '', ''];
        $summary_rows[] = ['1', 'Strongly Disagree', $tally_sqd[$sk][1]];
        $summary_rows[] = ['2', 'Disagree', $tally_sqd[$sk][2]];
        $summary_rows[] = ['3', 'Neither agree nor disagree', $tally_sqd[$sk][3]];
        $summary_rows[] = ['4', 'Agree', $tally_sqd[$sk][4]];
        $summary_rows[] = ['5', 'Strongly Agree', $tally_sqd[$sk][5]];
        $summary_rows[] = ['6', 'Not Applicable', $tally_sqd[$sk][6]];
        $summary_rows[] = ['', 'TOTAL', $total_cnt];
    }

    $make_30_cols = function($cells) {
        $row = array_fill(0, 30, '');
        foreach ($cells as $col_idx => $val) {
            $row[$col_idx] = (string)$val;
        }
        return $row;
    };

    $top_rows = [
        $make_30_cols([12 => 'Services Availed', 20 => 'Edad']),
        $make_30_cols([12 => '1', 13 => 'TR', 20 => '1', 21 => '19 or lower']),
        $make_30_cols([12 => '2', 13 => 'SD / CS', 20 => '2', 21 => '20-34']),
        $make_30_cols([12 => '3', 13 => 'RC (Agri to Urban)', 20 => '3', 21 => '35-49']),
        $make_30_cols([12 => '4', 13 => 'RC (Urban to Urban)', 20 => '4', 21 => '50-64']),
        $make_30_cols([12 => '5', 13 => 'DP / PC / DT', 20 => '5', 21 => '65 or higher']),
        $make_30_cols([12 => '6', 13 => 'DC', 20 => '6', 21 => 'did not specify']),
        $make_30_cols([12 => '7', 13 => 'Certification / Certified Photocopy / Certified True Copy']),
        $make_30_cols([12 => '8', 13 => 'Verification / Back Tracing']),
        $make_30_cols([12 => '9', 13 => 'Annotation / Cancellation of Annotation']),
        $make_30_cols([12 => '10', 13 => 'Ocular Inspection']),
        $make_30_cols([1 => 'SUMMARY OF CSM', 12 => 'CLIENT SATISFACTION MEASUREMENT (CSM)']),
        $make_30_cols([]),
        [
            '', 'DATA ENTRY SUMMARY', '', '', '',
            'CSM Control No.', 'Internal or External', 'Month', 'Date', 'No.', '', 'Internal or External',
            'Uri ng Kliyente', 'Kasarian', 'Edad', 'Rehiyon ng Tirahan', 'Services Availed',
            'CC1', 'CC2', 'CC3', 'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8',
            'Suggestions/Comments'
        ]
    ];

    $full_rows = $top_rows;
    $max_data_len = max(count($summary_rows), count($raw_data_rows));

    for ($i = 0; $i < $max_data_len; $i++) {
        $s = $summary_rows[$i] ?? ['', '', ''];
        $d = $raw_data_rows[$i] ?? array_fill(0, 25, '');

        $row = array_merge(
            ['', $s[0], $s[1], $s[2], ''],
            $d
        );
        $full_rows[] = $row;
    }

    $tsv_lines = [];
    foreach ($full_rows as $r) {
        $tsv_lines[] = implode("\t", $r);
    }
    $tsv_full = implode("\r\n", $tsv_lines);

    $data_only_headers = [
        'CSM Control No.', 'Internal or External', 'Month', 'Date', 'No.', '', 'Internal or External',
        'Uri ng Kliyente', 'Kasarian', 'Edad', 'Rehiyon ng Tirahan', 'Services Availed',
        'CC1', 'CC2', 'CC3', 'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8', 'Suggestions/Comments'
    ];
    // In copy, exclude the table title/header so clipboard begins directly on line 1 with e.g. E10-001
    $data_only_lines = [];
    foreach ($raw_data_rows as $dr) {
        $data_only_lines[] = implode("\t", $dr);
    }
    $tsv_data_only = implode("\r\n", $data_only_lines);

    // Provide headers-included version for optional preview toggle
    $data_with_headers_lines = array_merge([implode("\t", $data_only_headers)], $data_only_lines);
    $tsv_data_with_headers = implode("\r\n", $data_with_headers_lines);

    $summary_headers = ['Category / Code', 'Description', 'Total'];
    $summary_lines = [implode("\t", $summary_headers)];
    foreach ($summary_rows as $sr) {
        $summary_lines[] = implode("\t", $sr);
    }
    $tsv_summary_only = implode("\r\n", $summary_lines);

    $csv_buffer = fopen('php://memory', 'r+');
    fputcsv($csv_buffer, $data_only_headers);
    foreach ($raw_data_rows as $dr) {
        fputcsv($csv_buffer, $dr);
    }
    rewind($csv_buffer);
    $csv_data_only = stream_get_contents($csv_buffer);
    fclose($csv_buffer);

    $csv_full_buffer = fopen('php://memory', 'r+');
    foreach ($full_rows as $r) {
        fputcsv($csv_full_buffer, $r);
    }
    rewind($csv_full_buffer);
    $csv_full = stream_get_contents($csv_full_buffer);
    fclose($csv_full_buffer);

    return [
        'month'                 => $month,
        'month_label'           => $month_full,
        'total_records'         => $total_cnt,
        'tsv_full'              => $tsv_full,
        'tsv_data_only'         => $tsv_data_only,
        'tsv_data_with_headers' => $tsv_data_with_headers,
        'tsv_summary_only'      => $tsv_summary_only,
        'csv_full'              => $csv_full,
        'csv_data_only'         => $csv_data_only
    ];
}

// 1b. HANDLE AJAX COPY & EXPORT REQUESTS (EXCEL TSV & CSV)
if (isset($_GET['action']) && in_array($_GET['action'], ['get_csm_export', 'download_csm_csv']) && !empty($_GET['month'])) {
    if (ob_get_length()) ob_clean(); // Discard any prior output or notices
    $req_month = trim($_GET['month']);
    if (!preg_match('/^\d{4}-\d{2}$/', $req_month)) {
        if ($_GET['action'] === 'get_csm_export') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid month format (YYYY-MM).']);
            exit;
        } else {
            die('Invalid month format.');
        }
    }

    try {
        $export_data = buildCsmExportData($pdo, $req_month);

        if ($_GET['action'] === 'download_csm_csv') {
            $filename = 'CSM_DataTable_' . $req_month . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $export_data['csv_data_only'];
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'                => 'success',
            'month'                 => $export_data['month'],
            'month_label'           => $export_data['month_label'],
            'total_records'         => $export_data['total_records'],
            'tsv_full'              => $export_data['tsv_full'],
            'tsv_data_only'         => $export_data['tsv_data_only'],
            'tsv_data_with_headers' => $export_data['tsv_data_with_headers'],
            'tsv_summary_only'      => $export_data['tsv_summary_only']
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    } catch (Throwable $e) {
        if ($_GET['action'] === 'get_csm_export') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Export error: ' . $e->getMessage()]);
            exit;
        } else {
            die('Export error: ' . $e->getMessage());
        }
    }
}

// Helper to ensure monthly_service_transactions table exists for storing single-digit quota numbers
function ensure_monthly_transactions_table($pdo) {
    if (!$pdo) return;
    static $ensured = false;
    if ($ensured) return;
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_service_transactions (
                target_month VARCHAR(7) NOT NULL,
                service_id INT NOT NULL,
                service_name VARCHAR(100) NOT NULL,
                total_transactions INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (target_month, service_id)
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_service_transactions (
                target_month VARCHAR(7) NOT NULL,
                service_id INT NOT NULL,
                service_name VARCHAR(100) NOT NULL,
                total_transactions INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (target_month, service_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $ensured = true;
    } catch (Throwable $e) {}
}

// 2. HANDLE DELETE ALL BULK DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bulk_data'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM responses WHERE control_number LIKE '%B' OR control_number LIKE 'BLK-%'");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        if ($deleted_count > 0) {
            $success_msg = "Success! Permanently deleted " . number_format($deleted_count) . " bulk-generated records across all months.";
        } else {
            $error_msg = "No bulk-generated records found in the database to delete.";
        }
    } catch (Exception $e) {
        $error_msg = "Database Deletion Error: " . $e->getMessage();
    }
}

// 2b. HANDLE WIPE DATA BASED ON MONTH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wipe_month_data'])) {
    $wipe_month = trim($_POST['wipe_month'] ?? '');
    $wipe_scope = trim($_POST['wipe_scope'] ?? 'bulk_only');

    if (!preg_match('/^\d{4}-\d{2}$/', $wipe_month)) {
        $error_msg = "Please specify a valid month (YYYY-MM).";
    } else {
        try {
            $month_label = date('F Y', strtotime($wipe_month . '-01'));
            $driver = $pdo ? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : '';
            if ($driver === 'pgsql') {
                if ($wipe_scope === 'all') {
                    $stmt = $pdo->prepare("DELETE FROM responses WHERE TO_CHAR(survey_date, 'YYYY-MM') = :month");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM responses WHERE TO_CHAR(survey_date, 'YYYY-MM') = :month AND (control_number LIKE '%B' OR control_number LIKE 'BLK-%')");
                }
            } else {
                if ($wipe_scope === 'all') {
                    $stmt = $pdo->prepare("DELETE FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = :month");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = :month AND (control_number LIKE '%B' OR control_number LIKE 'BLK-%')");
                }
            }
            $stmt->execute([':month' => $wipe_month]);
            $deleted_count = $stmt->rowCount();
            if ($deleted_count > 0) {
                $scope_text = ($wipe_scope === 'all') ? "all" : "bulk";
                $success_msg = "Success! Permanently deleted " . number_format($deleted_count) . " {$scope_text} records for " . $month_label . ".";
            } else {
                $error_msg = "No records found for " . $month_label . " to delete.";
            }
        } catch (Exception $e) {
            $error_msg = "Database Deletion Error: " . $e->getMessage();
        }
    }
}

// 2c. HANDLE SAVING OFFICIAL SERVICE QUOTAS (SAVED AS SINGLE DATA CELL DIGITS IN DATABASE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_monthly_quotas'])) {
    $target_month = trim($_POST['quota_target_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $target_month)) {
        $error_msg = "Please specify a valid month format (YYYY-MM).";
    } else {
        ensure_monthly_transactions_table($pdo);
        if ($pdo) {
            try {
                $quotas = $_POST['quotas'] ?? [];
                $month_label = date('F Y', strtotime($target_month . '-01'));
                $total_sum = 0;

                foreach ($servicesMeta as $svc_id => $s_meta) {
                    $val = isset($quotas[$svc_id]) ? (int)$quotas[$svc_id] : 0;
                    if ($val < 0) $val = 0;
                    $total_sum += $val;
                    $svc_name = $s_meta['name'] ?? ('Service ' . $svc_id);

                    $chk = $pdo->prepare("SELECT COUNT(*) FROM monthly_service_transactions WHERE target_month = ? AND service_id = ?");
                    $chk->execute([$target_month, $svc_id]);
                    if ($chk->fetchColumn() > 0) {
                        $upd = $pdo->prepare("UPDATE monthly_service_transactions SET total_transactions = ?, service_name = ?, updated_at = CURRENT_TIMESTAMP WHERE target_month = ? AND service_id = ?");
                        $upd->execute([$val, $svc_name, $target_month, $svc_id]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO monthly_service_transactions (target_month, service_id, service_name, total_transactions, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                        $ins->execute([$target_month, $svc_id, $svc_name, $val]);
                    }
                }
                $success_msg = "Success! Saved official transaction quotas for " . $month_label . " to database as general digits (Total: " . number_format($total_sum) . " transactions; zero dummy metadata rows created).";
                $active_rep_month = $target_month;
            } catch (Throwable $e) {
                $error_msg = "Failed to save monthly transaction quotas: " . $e->getMessage();
            }
        } else {
            $error_msg = "Database connection unavailable.";
        }
    }
}

// 2d. AJAX HANDLER FOR SAVING SERVICE QUOTAS AS DIGITS
if (isset($_GET['action']) && $_GET['action'] === 'save_monthly_quotas_ajax' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $target_month = trim($_POST['target_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $target_month)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid month format (YYYY-MM).']);
        exit;
    }
    ensure_monthly_transactions_table($pdo);
    if (!$pdo) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable.']);
        exit;
    }
    try {
        $quotas = $_POST['quotas'] ?? [];
        $total_sum = 0;
        foreach ($servicesMeta as $svc_id => $s_meta) {
            $val = isset($quotas[$svc_id]) ? (int)$quotas[$svc_id] : 0;
            if ($val < 0) $val = 0;
            $total_sum += $val;
            $svc_name = $s_meta['name'] ?? ('Service ' . $svc_id);

            $chk = $pdo->prepare("SELECT COUNT(*) FROM monthly_service_transactions WHERE target_month = ? AND service_id = ?");
            $chk->execute([$target_month, $svc_id]);
            if ($chk->fetchColumn() > 0) {
                $upd = $pdo->prepare("UPDATE monthly_service_transactions SET total_transactions = ?, service_name = ?, updated_at = CURRENT_TIMESTAMP WHERE target_month = ? AND service_id = ?");
                $upd->execute([$val, $svc_name, $target_month, $svc_id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO monthly_service_transactions (target_month, service_id, service_name, total_transactions, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $ins->execute([$target_month, $svc_id, $svc_name, $val]);
            }
        }
        $month_label = date('F Y', strtotime($target_month . '-01'));
        echo json_encode([
            'status' => 'success',
            'message' => "Saved official quotas for {$month_label} to database as digits (Total: " . number_format($total_sum) . ").",
            'total_sum' => $total_sum,
            'target_month' => $target_month
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. HANDLE BULK SUBMISSION (Single JSON payload bypasses PHP max_input_vars limit entirely)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['bulk_json']) || isset($_POST['survey_date']))) {
    if (empty($_POST['save_to_database'])) {
        $error_msg = "Database insert was skipped because 'Save to Database (Neon)' was disabled.";
    } else {
        try {
        $records = [];
        if (!empty($_POST['bulk_json'])) {
            $decoded = json_decode($_POST['bulk_json'], true);
            if (is_array($decoded)) {
                $records = $decoded;
            }
        } elseif (isset($_POST['survey_date']) && is_array($_POST['survey_date'])) {
            $count = count($_POST['survey_date']);
            for ($i = 0; $i < $count; $i++) {
                $records[] = [
                    'survey_date'      => $_POST['survey_date'][$i] ?? '',
                    'client_type'      => $_POST['client_type'][$i] ?? 'Citizen',
                    'gender'           => $_POST['gender'][$i] ?? 'Male',
                    'age_group'        => $_POST['age_group'][$i] ?? '20-29',
                    'region'           => $_POST['region'][$i] ?? 'Region II',
                    'province'         => $_POST['province'][$i] ?? 'Isabela',
                    'municipality'     => $_POST['municipality'][$i] ?? 'Ilagan City',
                    'transaction_type' => $_POST['transaction_type'][$i] ?? 'Certification/CTC/CPC',
                    'cc1'              => $_POST['cc1'][$i] ?? '3',
                    'cc2'              => $_POST['cc2'][$i] ?? '3',
                    'cc3'              => $_POST['cc3'][$i] ?? '3',
                    'sqd0'             => $_POST['sqd0'][$i] ?? '5',
                    'sqd1'             => $_POST['sqd1'][$i] ?? '5',
                    'sqd2'             => $_POST['sqd2'][$i] ?? '5',
                    'sqd3'             => $_POST['sqd3'][$i] ?? '5',
                    'sqd4'             => $_POST['sqd4'][$i] ?? '5',
                    'sqd5'             => $_POST['sqd5'][$i] ?? '5',
                    'sqd6'             => $_POST['sqd6'][$i] ?? '5',
                    'sqd7'             => $_POST['sqd7'][$i] ?? '5',
                    'sqd8'             => $_POST['sqd8'][$i] ?? '5',
                ];
            }
        }

        if (empty($records)) {
            throw new Exception("No valid records staged for submission.");
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO responses 
            (survey_date, client_type, gender, age_group, region, province, municipality, transaction_type, 
             cc1, cc2, cc3, sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, control_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $count = count($records);
        $seq_tracker = []; 
        
        foreach ($records as $row) {
            $row_date = !empty($row['survey_date']) ? $row['survey_date'] : date('Y-m-d');
            $month = date('m', strtotime($row_date)); 
            $prefix = "E" . $month . "-";             
            
            if (!isset($seq_tracker[$month])) {
                $stmt_seq = $pdo->prepare("SELECT control_number FROM responses WHERE control_number LIKE ?");
                $stmt_seq->execute([$prefix . '%']);
                $all_ctrls = $stmt_seq->fetchAll(PDO::FETCH_COLUMN);
                
                $max_num = 0;
                foreach($all_ctrls as $ctrl) {
                    $num_part = (int) preg_replace('/[^0-9]/', '', str_replace($prefix, '', $ctrl));
                    if($num_part > $max_num) {
                        $max_num = $num_part;
                    }
                }
                $seq_tracker[$month] = $max_num;
            }
            
            $seq_tracker[$month]++;
            $control_num = $prefix . str_pad($seq_tracker[$month], 3, '0', STR_PAD_LEFT) . "B";
            
            $stmt->execute([
                $row_date,
                !empty($row['client_type']) ? $row['client_type'] : 'Citizen',
                !empty($row['gender']) ? $row['gender'] : 'Male',
                !empty($row['age_group']) ? $row['age_group'] : '20-29',
                !empty($row['region']) ? $row['region'] : 'Region II',
                !empty($row['province']) ? $row['province'] : 'Isabela',
                !empty($row['municipality']) ? $row['municipality'] : 'Ilagan City',
                !empty($row['transaction_type']) ? $row['transaction_type'] : 'Certification/CTC/CPC',
                !empty($row['cc1']) ? $row['cc1'] : '3',
                !empty($row['cc2']) ? $row['cc2'] : '3',
                !empty($row['cc3']) ? $row['cc3'] : '3',
                !empty($row['sqd0']) ? $row['sqd0'] : '5', 
                !empty($row['sqd1']) ? $row['sqd1'] : '5',
                !empty($row['sqd2']) ? $row['sqd2'] : '5',
                !empty($row['sqd3']) ? $row['sqd3'] : '5',
                !empty($row['sqd4']) ? $row['sqd4'] : '5',
                !empty($row['sqd5']) ? $row['sqd5'] : '5',
                !empty($row['sqd6']) ? $row['sqd6'] : '5',
                !empty($row['sqd7']) ? $row['sqd7'] : '5',
                !empty($row['sqd8']) ? $row['sqd8'] : '5',
                $control_num
            ]);
        }
        
        $pdo->commit();
        $last_gen_month = !empty($records[0]['survey_date']) ? date('Y-m', strtotime($records[0]['survey_date'])) : '';
        $success_msg = "Successfully generated and saved " . $count . " records to the database!";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
}

// Normalizer for transaction types to service indices 1-13
function norm_trans_php($v) {
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
    return 7;
}

// 4. RETRIEVE MONTH TALLIES & ACTIVE SERVICE BREAKDOWN (Neon PostgreSQL & MySQL compatible)
$month_tallies = [];
$active_rep_month = $_GET['report_month'] ?? '';
$db_service_counts = array_fill(1, 13, 0);
$db_active_total = 0;
$db_active_real = 0;
$db_active_bulk = 0;

if ($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $mt_stmt = $pdo->query("SELECT TO_CHAR(survey_date, 'YYYY-MM') as ym, TO_CHAR(survey_date, 'FMMonth YYYY') as m_label, COUNT(*) as total_cnt, SUM(CASE WHEN control_number LIKE '%B' OR control_number LIKE 'BLK-%' THEN 1 ELSE 0 END) as bulk_cnt FROM responses WHERE survey_date IS NOT NULL GROUP BY ym, m_label ORDER BY ym DESC");
        } else {
            $mt_stmt = $pdo->query("SELECT DATE_FORMAT(survey_date, '%Y-%m') as ym, DATE_FORMAT(survey_date, '%M %Y') as m_label, COUNT(*) as total_cnt, SUM(CASE WHEN control_number LIKE '%B' OR control_number LIKE 'BLK-%' THEN 1 ELSE 0 END) as bulk_cnt FROM responses WHERE survey_date IS NOT NULL AND survey_date != '0000-00-00' GROUP BY ym, m_label ORDER BY ym DESC");
        }
        $month_tallies = $mt_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $month_tallies = [];
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $active_rep_month)) {
        $active_rep_month = !empty($month_tallies[0]['ym']) ? $month_tallies[0]['ym'] : date('Y-m');
    }

    // Query active month responses breakdown across the 13 services
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $s_stmt = $pdo->prepare("SELECT transaction_type, control_number FROM responses WHERE TO_CHAR(survey_date, 'YYYY-MM') = ?");
        } else {
            $s_stmt = $pdo->prepare("SELECT transaction_type, control_number FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = ?");
        }
        $s_stmt->execute([$active_rep_month]);
        $active_rows = $s_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($active_rows as $arow) {
            $db_active_total++;
            $ctrl = $arow['control_number'] ?? '';
            if (substr($ctrl, -1) === 'B' || strpos($ctrl, 'BLK-') === 0) {
                $db_active_bulk++;
            } else {
                $db_active_real++;
            }
            $t = norm_trans_php($arow['transaction_type'] ?? '');
            if ($t >= 1 && $t <= 13) {
                $db_service_counts[$t]++;
            }
        }
    } catch (Throwable $e) {}

    // Query saved official total transactions digits for this month from monthly_service_transactions
    $db_saved_quotas = [];
    ensure_monthly_transactions_table($pdo);
    try {
        $q_stmt = $pdo->prepare("SELECT service_id, total_transactions FROM monthly_service_transactions WHERE target_month = ?");
        $q_stmt->execute([$active_rep_month]);
        while ($qrow = $q_stmt->fetch(PDO::FETCH_ASSOC)) {
            $db_saved_quotas[(int)$qrow['service_id']] = (int)$qrow['total_transactions'];
        }
    } catch (Throwable $e) {}
}

if (!preg_match('/^\d{4}-\d{2}$/', $active_rep_month)) {
    $active_rep_month = date('Y-m');
}
$active_month_label = date('F Y', strtotime($active_rep_month . '-01'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Auto-Encoder & Generator | CSM System</title>
    
    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Phosphor Icons (Regular, Bold, Fill) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

    <!-- Immediate Theme Initialization (Prevents FOUT) -->
    <script>
        (function() {
            var t = localStorage.getItem('csm_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
            document.documentElement.setAttribute('data-theme', t);
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    <link rel="stylesheet" href="darkmode.css">

    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --border-subtle: #e2e8f0;
            --border-light: #f1f5f9;
            
            --primary: #059669;
            --primary-light: #ecfdf5;
            --primary-hover: #047857;
            
            --color-blue: #2563eb;
            --color-yellow: #f59e0b;
            --color-red: #dc2626;
            
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            
            --sidebar-width: 260px;
            --radius-card: 16px;
            --radius-btn: 10px;
            --shadow-card: 0 1px 3px 0 rgb(0 0 0 / 0.04), 0 1px 2px -1px rgb(0 0 0 / 0.02);
            --shadow-hover: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-body);
            overflow-x: hidden;
            letter-spacing: -0.01em;
            -webkit-font-smoothing: antialiased;
        }

        /* SIDEBAR (MATCHING DASHBOARD STYLE) */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--bg-surface);
            border-right: 1px solid var(--border-subtle);
            padding: 24px 16px;
            z-index: 1040;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        #sidebar.collapsed {
            transform: translateX(-100%);
        }

        .brand-badge {
            font-size: 0.65rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--primary);
            background: transparent;
            padding: 0;
        }

        .sidebar-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-subtle);
            padding: 14px 14px 6px;
            margin: 0;
        }

        #sidebar .nav-link {
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 500;
            padding: 10px 14px;
            margin-bottom: 3px;
            border-radius: var(--radius-btn);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.15s ease;
            text-decoration: none;
            cursor: pointer;
            background-color: transparent;
        }

        /* ZERO BACKGROUND BOXES ON ICONS */
        #sidebar .nav-link i,
        i.ph, i.ph-bold, i.ph-fill {
            background: transparent !important;
            background-color: transparent !important;
            box-shadow: none !important;
        }

        #sidebar .nav-link i {
            font-size: 1.35rem;
            line-height: 1;
            color: var(--text-subtle);
            transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.15s ease;
        }

        #sidebar .nav-link:hover {
            color: var(--text-heading);
            background-color: #f1f5f9 !important;
        }

        #sidebar .nav-link:hover i {
            color: var(--text-heading);
            transform: scale(1.12);
        }

        #sidebar .nav-link.active {
            color: var(--primary);
            background-color: var(--primary-light) !important;
            font-weight: 700;
        }

        #sidebar .nav-link.active i {
            color: var(--primary);
            transform: scale(1.08);
        }

        /* MAIN CONTENT */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        #main-content.expanded {
            margin-left: 0;
        }

        /* TOPBAR */
        .topbar {
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 32px;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* MODERN MINIMAL CARDS */
        .card-minimal {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .card-minimal:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header-clean {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .card-title-clean {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
        }

        /* PILLS & BADGES */
        .pill-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }

        .pill-emerald { background-color: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .pill-amber   { background-color: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }
        .pill-blue    { background-color: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
        .pill-slate   { background-color: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .pill-rose    { background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        /* BUTTONS */
        .btn-modern-primary {
            background-color: var(--primary);
            color: #ffffff;
            border: 1px solid transparent;
            font-weight: 600;
            border-radius: var(--radius-btn);
            padding: 8px 16px;
            font-size: 0.85rem;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-modern-primary:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        .btn-modern-outline {
            background-color: transparent;
            color: var(--text-body);
            border: 1px solid var(--border-subtle);
            font-weight: 600;
            border-radius: var(--radius-btn);
            padding: 8px 16px;
            font-size: 0.85rem;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-modern-outline:hover {
            background-color: var(--border-light);
            color: var(--text-heading);
            border-color: #cbd5e1;
        }

        /* SUMMARY TABLE STYLING */
        .summary-table-container {
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow-x: auto;
            background: #ffffff;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .summary-table thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap;
        }

        .summary-table tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            color: var(--text-heading);
            font-size: 0.88rem;
        }

        .summary-table tbody tr:last-child td {
            border-bottom: none;
        }

        .summary-table tbody tr:hover td {
            background-color: #fbfcfe;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        .spin { animation: spin 1s linear infinite; display: inline-block; }

        /* Dark mode overrides for summary table */
        [data-bs-theme="dark"] .summary-table-container {
            background: var(--bg-surface);
            border-color: var(--border-subtle);
        }
        [data-bs-theme="dark"] .summary-table thead th {
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-muted);
            border-bottom-color: var(--border-subtle);
        }
        [data-bs-theme="dark"] .summary-table tbody td {
            border-bottom-color: var(--border-light);
            color: var(--text-heading);
        }
        [data-bs-theme="dark"] .summary-table tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.03);
        }

        /* EXPORT PREVIEW MODAL & DROPDOWN STYLING */
        .export-preview-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.76rem;
            line-height: 1.45;
            background-color: #f8fafc;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            white-space: pre;
            overflow-x: auto;
            color: var(--text-body);
        }

        [data-bs-theme="dark"] .export-preview-box {
            background-color: #0b1120 !important;
            color: #cbd5e1 !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .modal-content {
            background-color: #1e293b;
            color: #f1f5f9;
        }

        [data-bs-theme="dark"] .modal-header,
        [data-bs-theme="dark"] .modal-footer {
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .modal-title,
        [data-bs-theme="dark"] .modal-body label {
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .dropdown-menu {
            background-color: #1e293b;
            border-color: #334155;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
        }

        [data-bs-theme="dark"] .dropdown-item {
            color: #cbd5e1;
        }

        [data-bs-theme="dark"] .dropdown-item:hover,
        [data-bs-theme="dark"] .dropdown-item:focus {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        [data-bs-theme="dark"] .dropdown-item .text-dark {
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .dropdown-divider {
            border-color: #334155;
        }

        /* FORM CONTROLS IN SMART PANEL */
        .form-control-sm, .form-select-sm {
            border-radius: 8px;
            border-color: var(--border-subtle);
            font-size: 0.84rem;
            padding: 6px 10px;
        }

        .form-control-sm:focus, .form-select-sm:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        /* SERVICE TABLE INSERTION STYLES */
        .generator-nav-pills .nav-link {
            background-color: var(--bg-surface);
            color: var(--text-body);
            border: 1px solid var(--border-subtle);
            transition: all 0.15s ease;
        }
        .generator-nav-pills .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            border-color: var(--primary) !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25);
        }
        .generator-nav-pills .nav-link.active i,
        .generator-nav-pills .nav-link.active span {
            color: #ffffff !important;
        }
        .generator-nav-pills .nav-link:hover:not(.active) {
            background-color: var(--border-light);
            color: var(--text-heading);
        }

        .svc-card-row {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle) !important;
            transition: all 0.15s ease;
        }
        .svc-card-row:hover {
            border-color: var(--primary) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        [data-bs-theme="dark"] .svc-card-row {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .svc-card-row:hover {
            border-color: #10b981 !important;
        }
        [data-bs-theme="dark"] .svc-quota-input {
            background-color: #0f172a !important;
            border-color: #334155 !important;
            color: #f1f5f9 !important;
        }
        [data-bs-theme="dark"] .svc-share-pct {
            background-color: #0f172a !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .generator-nav-pills .nav-link:not(.active) {
            background-color: #1e293b !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .generator-nav-pills .nav-link:hover:not(.active) {
            color: #f1f5f9 !important;
            background-color: #334155 !important;
        }

        /* OVERALL RESPONSES & TOTAL TRANSACTION TABLE STYLING */
        #overallResponsesSvcTable {
            border-collapse: separate;
            border-spacing: 0;
        }
        #overallResponsesSvcTable tbody tr {
            transition: background-color 0.15s ease;
        }
        #overallResponsesSvcTable tbody tr:hover {
            background-color: rgba(5, 150, 105, 0.04);
        }
        .overall-tt-input {
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            font-weight: 700;
            transition: all 0.15s ease;
        }
        .overall-tt-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            background-color: #f0fdf4;
        }
        [data-bs-theme="dark"] #overallResponsesSvcTable tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }
        [data-bs-theme="dark"] .overall-tt-input {
            background-color: #0f172a !important;
            border-color: #334155 !important;
            color: #f1f5f9 !important;
        }
        [data-bs-theme="dark"] .overall-tt-input:focus {
            border-color: #10b981 !important;
            background-color: #1e293b !important;
        }

        /* CUSTOM SCROLLBAR */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.show-mobile {
                transform: translateX(0);
            }
            #main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR (CONSISTENT WITH DASHBOARD DESIGN) -->
<aside id="sidebar">
    <!-- Brand Header -->
    <div class="d-flex align-items-center gap-3 px-2 mb-4">
        <img src="image_4c6ce6.jpg" alt="PAO Logo" style="width: 38px; height: 38px; object-fit: contain; border-radius: 8px;">
        <div class="d-flex flex-column">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark fs-6">CSM Portal</span>
                <span class="brand-badge">Admin</span>
            </div>
            <span class="text-muted" style="font-size: 0.72rem;">Provincial Assessor · Isabela</span>
        </div>
    </div>

    <!-- Navigation List -->
    <p class="sidebar-section-title">Navigation</p>
    <a href="dashboard.php" class="nav-link">
        <i class="ph-bold ph-squares-four"></i> Dashboard Overview
    </a>
    <a href="admin_bulk.php" class="nav-link active">
        <i class="ph-fill ph-sparkle"></i> Bulk Auto-Encoder
    </a>
    <a href="index.php" target="_blank" class="nav-link">
        <i class="ph-bold ph-arrow-square-out"></i> Live Survey Form
    </a>

    <!-- Footer User Session -->
    <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between px-2">
        <div class="d-flex align-items-center gap-2">
            <div class="text-success fs-5">
                <i class="ph-bold ph-user-circle"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="fw-semibold text-dark" style="font-size: 0.8rem;">Administrator</span>
                <span class="text-muted" style="font-size: 0.7rem;">Session Active</span>
            </div>
        </div>
        <a href="logout.php" class="text-danger p-1" title="Sign out" style="font-size: 1.25rem;">
            <i class="ph-bold ph-sign-out"></i>
        </a>
    </div>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div id="main-content">
    
    <!-- TOPBAR -->
    <header class="topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-light border p-2 rounded-3 text-muted" aria-label="Toggle navigation">
                <i class="ph-bold ph-list fs-5"></i>
            </button>
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">Bulk Generator &amp; Quotas</h5>
                <span class="text-muted small">Generate survey responses and manage monthly service quotas.</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Theme Toggle Button -->
            <button type="button" class="theme-toggle-btn" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
                <i class="ph-bold ph-moon fs-5 theme-icon-moon"></i>
                <i class="ph-bold ph-sun fs-5 theme-icon-sun d-none"></i>
            </button>

            <a href="dashboard.php" class="btn-modern-outline">
                <i class="ph-bold ph-squares-four"></i> Dashboard
            </a>
            <a href="logout.php" class="btn-modern-outline text-danger border-danger-subtle">
                <i class="ph-bold ph-sign-out"></i> Logout
            </a>
        </div>
    </header>

    <!-- CONTENT BODY -->
    <div class="container-fluid px-4 py-4 pb-5">
        
        <?php if($success_msg): ?> 
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4 flex-wrap" style="background-color: #ecfdf5; color: #065f46; border-radius: 12px;">
                <i class="ph-bold ph-check-circle fs-5 text-success"></i>
                <div class="flex-grow-1 fw-semibold"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php if(!empty($last_gen_month)): ?>
                    <button type="button" class="btn btn-sm btn-success fw-bold px-3 py-1.5 rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm" onclick="copyMonthData('<?= htmlspecialchars($last_gen_month) ?>', 'data_only', this)">
                        <i class="ph-bold ph-copy"></i> Copy to Excel
                    </button>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div> 
        <?php endif; ?>

        <?php if($error_msg): ?> 
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="background-color: #fef2f2; color: #991b1b; border-radius: 12px;">
                <i class="ph-bold ph-warning-circle fs-5 text-danger"></i>
                <div class="flex-grow-1 fw-semibold"><?php echo htmlspecialchars($error_msg); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div> 
        <?php endif; ?>

        <!-- SMART AUTO-GENERATOR & SERVICE TABLE INSERTION PANEL -->
        <div class="card-minimal mb-4">
            <div class="card-header-clean flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="text-primary fs-4 d-flex align-items-center">
                        <i class="ph-bold ph-sparkle"></i>
                    </div>
                    <div>
                        <h6 class="card-title-clean">Assessment Generator &amp; Quotas</h6>
                        <span class="text-muted" style="font-size: 0.78rem;">Generate sample survey responses or configure monthly service quotas. Excludes weekends and official holidays.</span>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 fw-semibold py-1.5 px-3 d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#wipeMonthModal" title="Wipe records for a target month">
                        <i class="ph-bold ph-calendar-x"></i> Wipe Month
                    </button>

                    <form method="POST" action="" onsubmit="return confirm('Permanently delete all generated bulk records across all months? Real citizen submissions will not be affected.');" style="display:inline;">
                        <button type="submit" name="delete_bulk_data" value="1" class="btn btn-sm btn-outline-secondary text-danger rounded-3 fw-semibold py-1.5 px-3 d-inline-flex align-items-center gap-1.5" title="Wipe all bulk records across all months">
                            <i class="ph-bold ph-trash"></i> Wipe Bulk Data
                        </button>
                    </form>
                </div>
            </div>

            <!-- MODE NAVIGATION TABS -->
            <ul class="nav nav-pills generator-nav-pills gap-2 mb-3 border-bottom pb-3" id="genModeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold px-3 py-2 d-inline-flex align-items-center gap-2 rounded-3" id="tab-service-table-btn" data-bs-toggle="pill" data-bs-target="#tab-service-table-pane" type="button" role="tab" aria-controls="tab-service-table-pane" aria-selected="true">
                        <i class="ph-bold ph-table fs-5"></i>
                        <span>Service Quotas</span>
                        <span class="badge bg-white text-primary rounded-pill px-2 py-0.5 shadow-xs" style="font-size: 0.68rem; font-weight: 800;">Recommended</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-3 py-2 d-inline-flex align-items-center gap-2 rounded-3 text-secondary" id="tab-quick-random-btn" data-bs-toggle="pill" data-bs-target="#tab-quick-random-pane" type="button" role="tab" aria-controls="tab-quick-random-pane" aria-selected="false">
                        <i class="ph-bold ph-sparkle fs-5"></i>
                        <span>Random Generator</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="genModeTabContent">
                <!-- ================= TAB 1: SERVICE TABLE INSERTION (EXACT QUOTAS) ================= -->
                <div class="tab-pane fade show active" id="tab-service-table-pane" role="tabpanel" aria-labelledby="tab-service-table-btn">
                    
                    <!-- Row 1: Target Month, Client Type, SQD Quality, Municipality -->
                    <div class="row g-3 align-items-end pt-1">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted small fw-bold mb-1">Month</label>
                            <input type="month" id="svcTableMonth" class="form-control form-control-sm fw-semibold" value="<?= date('Y-m') ?>" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted small fw-bold mb-1">Client Type</label>
                            <select id="svcTableClient" class="form-select form-select-sm fw-semibold">
                                <option value="Random" selected>Randomized (Weighted)</option>
                                <option value="Citizen">Citizen Only</option>
                                <option value="Business">Business Only</option>
                                <option value="Government">Government Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted small fw-bold mb-1">SQD Quality</label>
                            <select id="svcTableSqd" class="form-select form-select-sm fw-semibold">
                                <option value="Prioritize45" selected>Prioritize 4 &amp; 5 (Standard)</option>
                                <option value="NaturalMix">Natural Mix (3–5)</option>
                                <option value="Balanced45">Balanced 4 &amp; 5</option>
                                <option value="All5">All 5 (Strongly Agree)</option>
                                <option value="All4">All 4 (Agree)</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted small fw-bold mb-1">Municipality</label>
                            <select id="svcTableMuni" class="form-select form-select-sm fw-semibold">
                                <option value="Random" selected>All Isabela (Random)</option>
                                <?php foreach ($isabelaLocations as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Preset & Total Quota Bar -->
                    <div class="d-flex align-items-center justify-content-between p-2.5 my-3 rounded-3 border bg-light flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold small text-muted text-uppercase tracking-wider" style="font-size:0.7rem;">Presets:</span>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold rounded-2 px-2.5 py-1 d-inline-flex align-items-center gap-1.5" onclick="presetExampleSvc()" title="Pre-fill sample distribution">
                                <i class="ph-bold ph-lightbulb text-warning"></i>
                                <span>Sample Quotas</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold rounded-2 px-2.5 py-1 d-inline-flex align-items-center gap-1.5" onclick="preset500Svc()" title="Pre-fill 500 standard distribution">
                                <i class="ph-bold ph-target text-success"></i>
                                <span>500 Standard</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold rounded-2 px-2 py-1 text-muted" onclick="clearAllSvcTable()" title="Reset all inputs to 0">
                                <i class="ph-bold ph-arrow-counter-clockwise"></i> Reset All
                            </button>
                        </div>

                        <div class="d-flex align-items-center gap-3 ms-auto flex-wrap">
                            <div id="svcTableArtaNotice" class="small fw-semibold text-muted">
                                <i class="ph-bold ph-target"></i> Target: 500 responses
                            </div>
                            <div class="d-flex align-items-center gap-1.5 bg-white border px-3 py-1 rounded-2 shadow-xs">
                                <span class="text-muted small fw-bold">Total:</span>
                                <span id="svcTableTotalBadge" class="fw-bolder text-primary fs-6">0</span>
                                <span class="text-muted small">responses</span>
                            </div>
                        </div>
                    </div>

                    <?php
                    $renderSvcCard = function($idx, $s) {
                    ?>
                        <div class="p-2.5 rounded-3 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-xs svc-card-row">
                            <div class="d-flex align-items-center gap-2.5 text-truncate" style="min-width: 0;">
                                <div class="svc-icon-badge flex-shrink-0 d-flex align-items-center justify-content-center rounded-2 fw-bold" style="width:34px; height:34px; background-color: <?= $s['color'] ?>15; color: <?= $s['color'] ?>; font-size: 0.8rem;">
                                    #<?= $idx ?>
                                </div>
                                <div class="text-truncate">
                                    <div class="fw-bold text-dark small text-truncate" title="<?= htmlspecialchars($s['title']) ?>">
                                        <?= htmlspecialchars($s['title']) ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size: 0.71rem;" title="<?= htmlspecialchars($s['desc']) ?>">
                                        <?= htmlspecialchars($s['desc']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <div class="input-group input-group-sm" style="width: 140px;">
                                    <button type="button" class="btn btn-outline-secondary px-2 fw-bold" onclick="stepSvcInput(<?= $idx ?>, -10)" title="Subtract 10">-</button>
                                    <input type="number" min="0" max="5000" class="form-control text-center fw-bold svc-quota-input" id="svcQuota_<?= $idx ?>" data-service-idx="<?= $idx ?>" data-service-name="<?= htmlspecialchars($s['name']) ?>" value="0" oninput="updateSvcTableTotal()">
                                    <button type="button" class="btn btn-outline-secondary px-2 fw-bold" onclick="stepSvcInput(<?= $idx ?>, 10)" title="Add 10">+</button>
                                </div>
                                <span class="badge bg-light text-secondary border small svc-share-pct" id="svcShare_<?= $idx ?>" style="min-width: 44px; text-align: center;">0%</span>
                            </div>
                        </div>
                    <?php
                    };
                    ?>

                    <!-- 13 Services Quota Grid: Left (1-6) and Right (7-13) -->
                    <div class="row g-2.5" id="svcGridContainer">
                        <!-- Left Column: Services 1 to 6 -->
                        <div class="col-lg-6 col-12 d-flex flex-column gap-2">
                            <?php for ($idx = 1; $idx <= 6; $idx++): 
                                if (isset($servicesMeta[$idx])) $renderSvcCard($idx, $servicesMeta[$idx]);
                            endfor; ?>
                        </div>

                        <!-- Right Column: Services 7 to 13 -->
                        <div class="col-lg-6 col-12 d-flex flex-column gap-2">
                            <?php for ($idx = 7; $idx <= 13; $idx++): 
                                if (isset($servicesMeta[$idx])) $renderSvcCard($idx, $servicesMeta[$idx]);
                            endfor; ?>
                        </div>
                    </div>

                    <!-- Bottom Action & DB Protection Bar -->
                    <div class="p-3 bg-light rounded-3 border mt-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <!-- Database Protection Switch -->
                            <div class="form-check form-switch mb-0 d-inline-flex align-items-center gap-2 bg-white px-3 py-2 rounded-3 border shadow-sm">
                                <input class="form-check-input ms-0 mt-0" type="checkbox" id="svcTableSaveToDbToggle" style="cursor: pointer; width: 2.2em; height: 1.2em;">
                                <label class="form-check-label fw-bold small text-dark" for="svcTableSaveToDbToggle" style="cursor: pointer;">
                                    Save to Database
                                </label>
                            </div>
                            <div id="svcTableModeNotice" class="d-flex align-items-center gap-1.5 text-success small fw-semibold">
                                <i class="ph-bold ph-shield-check fs-5"></i>
                                <span>In-memory mode: Generates data for export and printing without database insertion.</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                            <!-- Action 1: Copy for Excel -->
                            <button type="button" class="btn btn-outline-success fw-semibold py-2 px-3 rounded-3 d-inline-flex align-items-center gap-2 shadow-xs" id="svcCopyExcelBtn" onclick="copyServiceTableData(this)" title="Generate responses and copy 25 columns for cell F15">
                                <i class="ph-bold ph-copy fs-5"></i>
                                <span>Copy for Excel</span>
                            </button>

                            <!-- Action 2: Save Quotas as Digits in Database -->
                            <button type="button" class="btn btn-outline-primary fw-semibold py-2 px-3 rounded-3 d-inline-flex align-items-center gap-2 shadow-xs" id="svcSaveDigitsBtn" onclick="saveServiceTableQuotasAsDigits(this)" title="Save entered quotas as single numeric digits in database">
                                <i class="ph-bold ph-floppy-disk fs-5"></i>
                                <span>Save Quotas to DB</span>
                            </button>

                            <!-- Action 3: Print Official CSM Report -->
                            <button type="button" class="btn btn-primary fw-semibold py-2 px-3 rounded-3 shadow-sm d-inline-flex align-items-center gap-2" id="svcPrintReportBtn" onclick="printServiceTableReport(this)" title="Open official 4-page ARTA CSM Report in new window">
                                <i class="ph-bold ph-printer fs-5"></i>
                                <span>Print Report</span>
                            </button>

                            <!-- Action 4: Save to Database (shown when toggle is ON) -->
                            <button type="button" class="btn btn-warning fw-semibold py-2 px-3 rounded-3 d-none align-items-center gap-2 shadow-sm" id="svcSaveDbBtn" onclick="saveServiceTableToDatabase(this)">
                                <i class="ph-bold ph-cloud-arrow-up fs-5"></i>
                                <span>Save to Database</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: RANDOM GENERATOR ================= -->
                <div class="tab-pane fade" id="tab-quick-random-pane" role="tabpanel" aria-labelledby="tab-quick-random-btn">
                    <form id="generatorForm" method="POST" action="admin_bulk.php">
                        <input type="hidden" name="bulk_json" id="bulkJsonInput" value="">
                        
                        <!-- Row 1: Month, Record Count, Client Type, SQD Quality -->
                        <div class="row g-3 align-items-end pt-2">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-1">Month</label>
                                <input type="month" id="genMonth" class="form-control form-control-sm fw-semibold" value="<?= date('Y-m') ?>" required>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-1">Record Count</label>
                                <input type="number" id="genCount" class="form-control form-control-sm fw-semibold" value="10" min="1" max="500" required>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-1">Client Type</label>
                                <select id="genClient" class="form-select form-select-sm fw-semibold">
                                    <option value="Random">Randomized (Weighted)</option>
                                    <option value="Citizen">Citizen Only</option>
                                    <option value="Business">Business Only</option>
                                    <option value="Government">Government Only</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-1">SQD Quality</label>
                                <select id="genSqd" class="form-select form-select-sm fw-semibold">
                                    <option value="Prioritize45" selected>Prioritize 4 &amp; 5 (Standard)</option>
                                    <option value="NaturalMix">Natural Mix (3–5)</option>
                                    <option value="Balanced45">Balanced 4 &amp; 5</option>
                                    <option value="All5">All 5 (Strongly Agree)</option>
                                    <option value="All4">All 4 (Agree)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Municipality & Transaction Type -->
                        <div class="row g-3 align-items-end pt-2 mt-0">
                            <div class="col-md-6 col-sm-12">
                                <label class="form-label text-muted small fw-bold mb-1">Municipality</label>
                                <select id="genMuni" class="form-select form-select-sm fw-semibold">
                                    <option value="Random">All Isabela (Random)</option>
                                    <?php foreach ($isabelaLocations as $m): ?>
                                        <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <label class="form-label text-muted small fw-bold mb-1">Transaction Type</label>
                                <select id="genTransaction" class="form-select form-select-sm fw-semibold">
                                    <option value="Random">Randomized (All 13 Services)</option>
                                    <option value="RandomWeighted">Weighted (Cert / Transfer / Sub)</option>
                                    <optgroup label="Specific Service">
                                    <?php foreach ($transactionTypes as $t): ?>
                                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-3 mt-3 border-top flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <!-- Database Protection Toggle Switch -->
                                <div class="form-check form-switch mb-0 d-inline-flex align-items-center gap-2 bg-light px-3 py-2 rounded-3 border shadow-sm">
                                    <input class="form-check-input ms-0 mt-0" type="checkbox" id="saveToDbToggle" name="save_to_database" value="1" style="cursor: pointer; width: 2.2em; height: 1.2em;">
                                    <label class="form-check-label fw-bold small text-dark" for="saveToDbToggle" style="cursor: pointer;">
                                        Save to Database
                                    </label>
                                </div>
                                <div id="genModeNotice" class="d-flex align-items-center gap-1.5 text-success small fw-semibold">
                                    <i class="ph-bold ph-shield-check fs-5"></i>
                                    <span>In-memory mode: Generates data for export without database insertion.</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                                <button type="button" class="btn btn-outline-success fw-semibold py-2 px-3 rounded-3 d-inline-flex align-items-center gap-2" onclick="copyCurrentTargetMonth(this)" title="Copy 25-column table for selected month">
                                    <i class="ph-bold ph-copy fs-5"></i>
                                    <span>Copy Month Data</span>
                                </button>
                                <button type="submit" id="generateBtn" class="btn-modern-primary py-2 px-4 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                                    <i id="generateBtnIcon" class="ph-bold ph-sparkle text-warning fs-5"></i>
                                    <span id="generateBtnText">Generate &amp; Copy</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- OVERALL RESPONSES & TOTAL TRANSACTION CARD -->
        <div class="card-minimal" id="overallResponsesCard">
            <div class="card-header-clean flex-wrap gap-2 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="text-primary fs-4 d-flex align-items-center">
                        <i class="ph-bold ph-chart-bar"></i>
                    </div>
                    <div>
                        <h6 class="card-title-clean">Overall Responses &amp; Transactions</h6>
                        <span class="text-muted" style="font-size: 0.78rem;">Monthly breakdown of survey responses and transaction quotas for the 13 PAO services.</span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <!-- Active Month Selector Dropdown -->
                    <form method="GET" action="admin_bulk.php#overallResponsesCard" class="d-inline-flex align-items-center gap-1.5 m-0">
                        <label class="text-muted small fw-bold mb-0">Month:</label>
                        <select name="report_month" class="form-select form-select-sm fw-bold border" onchange="this.form.submit()" style="min-width: 175px;">
                            <?php if (!empty($month_tallies)): ?>
                                <?php foreach ($month_tallies as $mt): ?>
                                    <option value="<?= htmlspecialchars($mt['ym']) ?>" <?= ($mt['ym'] === $active_rep_month) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mt['m_label']) ?> (<?= number_format($mt['total_cnt']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="<?= htmlspecialchars($active_rep_month) ?>" selected><?= htmlspecialchars($active_month_label) ?></option>
                            <?php endif; ?>
                        </select>
                    </form>

                    <!-- Quick Action: Print Official CSM Report -->
                    <a href="print_report.php?month=<?= urlencode($active_rep_month) ?>" target="_blank" class="btn btn-sm btn-primary fw-semibold rounded-3 py-1.5 px-3 d-inline-flex align-items-center gap-1.5 shadow-sm" title="Open official 4-page ARTA CSM Report in new window">
                        <i class="ph-bold ph-printer"></i> Print Report
                    </a>

                    <!-- Quick Action: Copy 25-Col Data Table -->
                    <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded-3 py-1.5 px-3 d-inline-flex align-items-center gap-1.5" onclick="copyMonthData('<?= htmlspecialchars($active_rep_month) ?>', 'data_only', this)" title="Copy 25-column table for Excel">
                        <i class="ph-bold ph-copy"></i> Copy Table
                    </button>

                    <!-- Dropdown for more options (Download CSV, Preview, Wipe Month) -->
                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-sm btn-light border rounded-3 py-1.5 px-2 text-muted" data-bs-toggle="dropdown" aria-expanded="false" title="More options">
                            <i class="ph-bold ph-dots-three-vertical fs-6"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1.5 rounded-3" style="min-width: 230px; font-size: 0.85rem;">
                            <li class="dropdown-header text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                <?= htmlspecialchars($active_month_label) ?>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="copyMonthData('<?= htmlspecialchars($active_rep_month) ?>', 'full', this)">
                                    <i class="ph-bold ph-microsoft-excel-logo text-success fs-5"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Full Template</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">Summary and data rows</div>
                                    </div>
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="copyMonthData('<?= htmlspecialchars($active_rep_month) ?>', 'summary_only', this)">
                                    <i class="ph-bold ph-chart-bar text-warning fs-5"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Summary Tallies</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">ARTA category breakdown</div>
                                    </div>
                                </button>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="admin_bulk.php?action=download_csm_csv&month=<?= urlencode($active_rep_month) ?>">
                                    <i class="ph-bold ph-file-csv text-info fs-5"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Download CSV</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">Spreadsheet file</div>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="openPreviewExportModal('<?= htmlspecialchars($active_rep_month) ?>', '<?= htmlspecialchars($active_month_label) ?>', <?= $db_active_total ?>)">
                                    <i class="ph-bold ph-eye text-secondary fs-5"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Preview Export</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">Inspect columns before copying</div>
                                    </div>
                                </button>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" type="button" onclick="openWipeForMonth('<?= htmlspecialchars($active_rep_month) ?>')">
                                    <i class="ph-bold ph-trash fs-5"></i>
                                    <div>
                                        <div class="fw-semibold">Wipe Month Data</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">Delete records for month</div>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Month Summary Badges Bar -->
            <div class="d-flex align-items-center justify-content-between pt-2 pb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="pill-badge pill-slate">Month: <strong><?= htmlspecialchars($active_month_label) ?></strong></span>
                    <span class="pill-badge pill-blue">Responses: <strong id="activeMonthTotalRespBadge"><?= number_format($db_active_total) ?></strong></span>
                    <span class="pill-badge pill-emerald">Real: <strong><?= number_format($db_active_real) ?></strong></span>
                    <?php if ($db_active_bulk > 0): ?>
                        <span class="pill-badge pill-amber">Bulk: <strong><?= number_format($db_active_bulk) ?></strong></span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small d-inline-flex align-items-center gap-1.5">
                    <i class="ph-bold ph-pencil-simple text-primary"></i>
                    <span>"Total Transactions" values can be edited directly to match registry volume.</span>
                </div>
            </div>

            <!-- Official 13-Service Breakdown Table Form -->
            <form method="POST" action="admin_bulk.php#overallResponsesCard" id="overallQuotasForm">
                <input type="hidden" name="save_monthly_quotas" value="1">
                <input type="hidden" name="quota_target_month" value="<?= htmlspecialchars($active_rep_month) ?>">

                <div class="summary-table-container border rounded-3 overflow-hidden shadow-xs mb-3">
                    <table class="table table-hover align-middle mb-0" id="overallResponsesSvcTable">
                        <thead class="table-light border-bottom">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>External Service</th>
                                <th style="width: 170px;" class="text-center">Responses</th>
                                <th style="width: 200px;" class="text-center">Total Transactions</th>
                                <th style="width: 110px;" class="text-center">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $sum_resp = array_sum($db_service_counts);
                                $sum_tt = 0;
                                foreach ($servicesMeta as $idx => $s): 
                                    $rCount = $db_service_counts[$idx] ?? 0;
                                    $hasSavedDigit = isset($db_saved_quotas[$idx]);
                                    $savedTt = $hasSavedDigit ? (int)$db_saved_quotas[$idx] : $rCount;
                                    $sum_tt += $savedTt;
                                    $sharePct = $sum_resp > 0 ? number_format(($rCount / $sum_resp) * 100, 1) : '0.0';
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-light text-primary border fw-bold px-2 py-1 rounded-2">
                                            #<?= $idx ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">
                                            <?= htmlspecialchars($s['title']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.74rem;">
                                            <?= htmlspecialchars($s['desc']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark fs-6 card-resp-val" id="cardResp_<?= $idx ?>">
                                            <?= number_format($rCount) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1.5">
                                            <input type="number" min="0" max="999999" 
                                                   name="quotas[<?= $idx ?>]" 
                                                   class="form-control form-control-sm text-center fw-bold overall-tt-input" 
                                                   id="cardTt_<?= $idx ?>" 
                                                   data-idx="<?= $idx ?>" 
                                                   value="<?= $savedTt ?>" 
                                                   style="width: 120px; font-size: 0.88rem;" 
                                                   oninput="recalcCardOverallTotal()">
                                            <?php if ($hasSavedDigit): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1" style="font-size: 0.65rem;" title="Saved in database">DB</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-secondary border small card-share-val" id="cardShare_<?= $idx ?>">
                                            <?= $sharePct ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr class="table-light fw-bold" style="border-top: 2px solid var(--border-subtle);">
                                <td colspan="2" class="ps-3 text-uppercase tracking-wider small fw-bold">
                                    EXTERNAL SERVICE TOTAL
                                </td>
                                <td class="text-center fs-6 text-primary fw-bold" id="cardExternalTotalResp">
                                    <?= number_format($sum_resp) ?>
                                </td>
                                <td class="text-center fs-6 text-success fw-bold" id="cardExternalTotalTt">
                                    <?= number_format($sum_tt) ?>
                                </td>
                                <td class="text-center text-muted small">
                                    100.0%
                                </td>
                            </tr>
                            <tr class="fw-bold bg-dark text-white">
                                <td colspan="2" class="ps-3 text-uppercase tracking-wider small fw-bold text-white">
                                    OVERALL TOTAL
                                </td>
                                <td class="text-center fs-6 text-white fw-bold" id="cardOverallFinalResp">
                                    <?= number_format($sum_resp) ?>
                                </td>
                                <td class="text-center fs-6 text-warning fw-bold" id="cardOverallFinalTt">
                                    <?= number_format($sum_tt) ?>
                                </td>
                                <td class="text-center text-white small">
                                    100.0%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Action Bar for Saving Quotas as Digits -->
                <div class="d-flex align-items-center justify-content-between p-3 bg-light border rounded-3 mb-3 flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2 small text-muted">
                        <i class="ph-bold ph-database text-primary fs-5"></i>
                        <span>Quotas are stored as monthly single-cell values in the database without adding artificial survey rows.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold py-1.5 px-3 rounded-3 d-inline-flex align-items-center gap-1.5" onclick="copyServiceQuotasFromTop()" title="Load values entered in the top panel into this table">
                            <i class="ph-bold ph-arrow-down"></i> Sync from Top
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold py-1.5 px-3 rounded-3 d-inline-flex align-items-center gap-1.5" onclick="saveQuotasAjax(this)" title="Save quotas via AJAX">
                            <i class="ph-bold ph-lightning"></i> Quick Save
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold py-1.5 px-3 rounded-3 shadow-sm d-inline-flex align-items-center gap-1.5" id="saveQuotasDbBtn">
                            <i class="ph-bold ph-floppy-disk"></i>
                            <span>Save Quotas</span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Collapsible: View All Monthly Records Archive & History -->
            <div class="pt-2 border-top">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-3 py-1.5 px-3 fw-semibold d-inline-flex align-items-center gap-1.5" type="button" data-bs-toggle="collapse" data-bs-target="#monthArchiveCollapse" aria-expanded="false" aria-controls="monthArchiveCollapse">
                        <i class="ph-bold ph-caret-down"></i>
                        <span>Monthly Records Archive (<?= count($month_tallies) ?> Months)</span>
                    </button>
                    <span class="text-muted small">Historical database counts and records by month.</span>
                </div>

                <div class="collapse mt-3" id="monthArchiveCollapse">
                    <?php if (!empty($month_tallies)): ?>
                        <div class="summary-table-container border rounded-3 overflow-hidden">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th>Target Month</th>
                                        <th class="text-center">Bulk</th>
                                        <th class="text-center">Real</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($month_tallies as $mt): 
                                        $bulkCount = (int)$mt['bulk_cnt'];
                                        $totalCount = (int)$mt['total_cnt'];
                                        $realCount = max(0, $totalCount - $bulkCount);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph-bold ph-calendar text-muted fs-6"></i>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($mt['m_label']) ?></span>
                                                    <span class="text-muted small">(<?= htmlspecialchars($mt['ym']) ?>)</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="pill-badge pill-blue fw-bold">
                                                    <?= number_format($bulkCount) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="pill-badge pill-emerald fw-bold">
                                                    <?= number_format($realCount) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="pill-badge pill-slate fw-bold">
                                                    <?= number_format($totalCount) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex align-items-center gap-1.5 flex-wrap justify-content-end">
                                                    <a href="admin_bulk.php?report_month=<?= urlencode($mt['ym']) ?>#overallResponsesCard" class="btn btn-sm btn-outline-primary rounded-3 py-1 px-2.5 d-inline-flex align-items-center gap-1 fw-semibold" title="View in table">
                                                        <i class="ph-bold ph-arrow-up-right"></i> View
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-success rounded-3 py-1 px-2.5 d-inline-flex align-items-center gap-1.5 fw-semibold" 
                                                            onclick="copyMonthData('<?= htmlspecialchars($mt['ym']) ?>', 'data_only', this)" 
                                                            title="Copy table">
                                                        <i class="ph-bold ph-copy fs-6"></i>
                                                        <span>Copy</span>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 py-1 px-2 d-inline-flex align-items-center gap-1 text-danger" onclick="openWipeForMonth('<?= htmlspecialchars($mt['ym']) ?>')" title="Wipe month records">
                                                        <i class="ph-bold ph-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIPE MONTH DATA MODAL -->
<div class="modal fade" id="wipeMonthModal" tabindex="-1" aria-labelledby="wipeMonthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 pt-4 pb-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger-subtle text-danger" style="width: 40px; height: 40px;">
                        <i class="ph-bold ph-calendar-x fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="wipeMonthModalLabel">Wipe Month Data</h5>
                        <p class="text-muted small mb-0">Permanently delete records for a selected month.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" onsubmit="return confirmWipeMonth();">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold small">Month</label>
                        <select name="wipe_month" id="modalWipeMonthSelect" class="form-select fw-semibold" onchange="syncWipeMonthInput(this.value)">
                            <?php if (!empty($month_tallies)): ?>
                                <?php foreach ($month_tallies as $mt): ?>
                                    <option value="<?= htmlspecialchars($mt['ym']) ?>">
                                        <?= htmlspecialchars($mt['m_label']) ?> &mdash; <?= number_format($mt['bulk_cnt']) ?> bulk / <?= number_format($mt['total_cnt']) ?> total
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="<?= date('Y-m') ?>"><?= date('F Y') ?> (0 records)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Custom Month</label>
                        <input type="month" id="modalWipeMonthInput" class="form-control form-control-sm fw-semibold" value="<?= !empty($month_tallies[0]['ym']) ? htmlspecialchars($month_tallies[0]['ym']) : date('Y-m') ?>" onchange="syncWipeMonthSelect(this.value)">
                    </div>

                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label text-dark fw-bold small mb-2">Scope</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="wipe_scope" id="scopeBulkOnly" value="bulk_only" checked>
                            <label class="form-check-label text-dark small fw-semibold" for="scopeBulkOnly">
                                <span class="d-block text-dark fw-bold">Bulk records only (Recommended)</span>
                                <span class="text-muted" style="font-size: 0.8rem;">Deletes only generated bulk entries (BLK-* / *B). Real responses are kept.</span>
                            </label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="wipe_scope" id="scopeAll" value="all">
                            <label class="form-check-label text-danger small fw-semibold" for="scopeAll">
                                <span class="d-block text-danger fw-bold">All records</span>
                                <span class="text-muted" style="font-size: 0.8rem;">Permanently deletes all responses for this month.</span>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-0" style="border-radius: 10px;">
                        <i class="ph-bold ph-warning text-warning fs-5 flex-shrink-0"></i>
                        <span>This action cannot be undone.</span>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-sm btn-light border fw-semibold px-3 py-2 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="wipe_month_data" value="1" class="btn btn-sm btn-danger fw-semibold px-4 py-2 rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm">
                        <i class="ph-bold ph-trash"></i> Delete Records
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EXPORT PREVIEW & COPY MODAL -->
<div class="modal fade" id="exportPreviewModal" tabindex="-1" aria-labelledby="exportPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 pt-4 pb-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success-subtle text-success" style="width: 42px; height: 42px;">
                        <i class="ph-bold ph-microsoft-excel-logo fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="exportPreviewModalLabel">
                            CSM Export Preview
                        </h5>
                        <p class="text-muted small mb-0" id="exportModalSubtitle">
                            Review and copy 25-column CSM data formatted for Excel.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <!-- Nav tabs -->
                <ul class="nav nav-pills mb-3 gap-2 border-bottom pb-2" id="exportTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill fw-semibold py-1.5 px-3 small" id="tab-data-btn" data-bs-toggle="pill" data-bs-target="#tab-data" type="button" role="tab" onclick="switchPreviewMode('data_only')">
                            <i class="ph-bold ph-table me-1"></i> Data Table (25 Columns)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill fw-semibold py-1.5 px-3 small" id="tab-full-btn" data-bs-toggle="pill" data-bs-target="#tab-full" type="button" role="tab" onclick="switchPreviewMode('full')">
                            <i class="ph-bold ph-microsoft-excel-logo me-1"></i> Full Template
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill fw-semibold py-1.5 px-3 small" id="tab-summary-btn" data-bs-toggle="pill" data-bs-target="#tab-summary" type="button" role="tab" onclick="switchPreviewMode('summary_only')">
                            <i class="ph-bold ph-chart-bar me-1"></i> Summary Tallies
                        </button>
                    </li>
                </ul>

                <!-- Stats banner & Options -->
                <div class="d-flex align-items-center justify-content-between p-2.5 mb-3 bg-light rounded-3 border flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success fw-bold px-2.5 py-1.5" id="exportModalBadgeMonth">Month</span>
                        <span class="badge bg-secondary-subtle text-secondary fw-bold px-2.5 py-1.5" id="exportModalBadgeCount">0 Records</span>
                        <span class="text-muted small d-none d-sm-inline">Separated by tabs for Excel paste</span>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="includeHeadersToggle" onchange="toggleHeadersPreview()">
                        <label class="form-check-label small fw-semibold text-muted" for="includeHeadersToggle">
                            Include header row
                        </label>
                    </div>
                </div>

                <!-- Preview Area -->
                <div class="position-relative">
                    <div id="exportLoadingSpinner" class="text-center py-5 d-none">
                        <i class="ph-bold ph-spinner-gap spin text-primary fs-1 mb-2"></i>
                        <p class="text-muted small fw-semibold">Preparing export data...</p>
                    </div>
                    <textarea id="exportPreviewTextarea" class="form-control export-preview-box text-nowrap" rows="14" readonly style="resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    <i class="ph-bold ph-info text-primary me-1"></i> Tip: Select cell <strong>F15</strong> in your Excel template before pressing <kbd>Ctrl+V</kbd>.
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light border fw-semibold px-3 py-2 rounded-3" data-bs-dismiss="modal">Close</button>
                    <a id="modalDownloadCsvBtn" href="#" class="btn btn-sm btn-outline-primary fw-semibold px-3 py-2 rounded-3 d-inline-flex align-items-center gap-1.5">
                        <i class="ph-bold ph-download-simple"></i> Download CSV
                    </a>
                    <button type="button" id="modalCopyBtn" class="btn btn-sm btn-success fw-semibold px-4 py-2 rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm" onclick="copyModalCurrentContent()">
                        <i class="ph-bold ph-copy"></i>
                        <span id="modalCopyBtnText">Copy to Clipboard</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COPY SUCCESS TOAST NOTIFICATION -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="csmCopyToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border-radius: 12px;">
        <div class="d-flex align-items-center">
            <div class="toast-body d-flex align-items-center gap-2.5 py-2.5 px-3">
                <i class="ph-bold ph-check-circle text-success fs-5"></i>
                <div>
                    <div class="fw-bold text-white small" id="csmCopyToastTitle">Copied to Clipboard</div>
                    <div class="text-white-50 small" id="csmCopyToastMsg" style="font-size: 0.78rem;">Ready to paste with Ctrl+V</div>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LIBRARIES -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const clientChoices = ["Citizen", "Business", "Government"];
    const isabelaLocations = [
        "Alicia", "Angadanan", "Aurora", "Benito Soliven", "Burgos", "Cabagan", "Cabatuan", "Cauayan City", "Cordon", "Delfin Albano", 
        "Dinapigue", "Divilacan", "Echague", "Gamu", "Ilagan City", "Jones", "Luna", "Maconacon", "Mallig", "Naguilian", "Palanan", 
        "Quezon", "Quirino", "Ramon", "Reina Mercedes", "Roxas", "San Agustin", "San Guillermo", "San Isidro", "San Manuel", 
        "San Mariano", "San Mateo", "San Pablo", "Santa Maria", "Santiago City", "Santo Tomas", "Tumauini", "Outside Isabela"
    ].sort();

    const transactionTypes = [
        "Transfer",
        "Subdivision/Consolidation",
        "Reclassification (Agri to Urban)",
        "Reclassification (Urban to Urban)",
        "Reassessment (DP/PC/DT)",
        "Discovery/New Declaration",
        "Certification/CTC/CPC",
        "Verification/Back Tracking",
        "Annotation/Cancellation of Annotation",
        "Ocular Inspection",
        "Cancellation of TD",
        "TMCR/Section Map/s",
        "Posting"
    ];

    // WORKING DATES (Excludes Holidays, Fri, Sat, Sun - strictly Monday to Thursday)
    function getAllValidWorkingDates(year, monthIndex) {
        const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
        let validDates = [];

        // Common fixed holidays in the Philippines (MM-DD format)
        const holidays = [
            "01-01", "04-09", "05-01", "06-12", "08-21", 
            "11-01", "11-30", "12-25", "12-30", "12-31"
        ];

        for (let d = 1; d <= daysInMonth; d++) {
            let tempDate = new Date(year, monthIndex, d);
            let dayOfWeek = tempDate.getDay(); 
            
            // 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
            // Strictly only allow Monday to Thursday (1 to 4)
            if (dayOfWeek >= 1 && dayOfWeek <= 4) {
                let mm = String(monthIndex + 1).padStart(2, '0');
                let dd = String(d).padStart(2, '0');
                let mmdd = mm + "-" + dd;
                
                // If it's not a holiday, add to valid choices
                if (!holidays.includes(mmdd)) {
                    validDates.push(`${year}-${mm}-${dd}`);
                }
            }
        }
        
        // Failsafe in case a month has zero valid days (very rare)
        if (validDates.length === 0) validDates.push(`${year}-${String(monthIndex+1).padStart(2,'0')}-01`); 
        
        return validDates;
    }

    function getRandomValidDate(year, monthIndex) {
        const validDates = getAllValidWorkingDates(year, monthIndex);
        return validDates[Math.floor(Math.random() * validDates.length)];
    }

    // WEIGHTED RANDOM GENERATORS
    function getWeightedClient() {
        const r = Math.random();
        if (r < 0.85) return "Citizen";      
        if (r < 0.95) return "Business";     
        return "Government";                 
    }

    function getWeightedTransaction() {
        const r = Math.random();
        if (r < 0.50) return "Certification/CTC/CPC";        
        if (r < 0.70) return "Transfer";                     
        if (r < 0.85) return "Subdivision/Consolidation";    
        const others = transactionTypes.filter(t => t !== "Certification/CTC/CPC" && t !== "Transfer" && t !== "Subdivision/Consolidation");
        return others[Math.floor(Math.random() * others.length)];
    }

    // WEIGHTED RANDOM GENERATOR FOR SQD0 TO SQD8 (PRIORITIZING 4 AND 5)
    function generateSqdScores(sqdMode) {
        if (sqdMode === "All5") {
            return { sqd0: "5", sqd1: "5", sqd2: "5", sqd3: "5", sqd4: "5", sqd5: "5", sqd6: "5", sqd7: "5", sqd8: "5" };
        }
        if (sqdMode === "All4") {
            return { sqd0: "4", sqd1: "4", sqd2: "4", sqd3: "4", sqd4: "4", sqd5: "4", sqd6: "4", sqd7: "4", sqd8: "4" };
        }

        // Establish respondent baseline profile for realistic human survey coherence
        let primaryScore, secondaryScore;
        const roll = Math.random();

        if (sqdMode === "Balanced45") {
            // 50% 5s, 50% 4s
            primaryScore = roll < 0.50 ? "5" : "4";
            secondaryScore = primaryScore === "5" ? "4" : "5";
        } else if (sqdMode === "NaturalMix") {
            // 60% 5s, 35% 4s, 5% 3s
            primaryScore = roll < 0.60 ? "5" : (roll < 0.95 ? "4" : "3");
            secondaryScore = primaryScore === "5" ? "4" : "5";
        } else {
            // Default "Prioritize45": Prioritize 4 and 5 with strong 5/4 weighting (~65% 5s, ~35% 4s)
            primaryScore = roll < 0.65 ? "5" : "4";
            secondaryScore = primaryScore === "5" ? "4" : "5";
        }

        // Helper to roll each dimension with high coherence towards the respondent's primary rating
        const getScore = () => {
            const r = Math.random();
            if (sqdMode === "NaturalMix" && primaryScore !== "3") {
                if (r < 0.82) return primaryScore;
                if (r < 0.96) return secondaryScore;
                return "3";
            }
            return r < 0.85 ? primaryScore : secondaryScore;
        };

        return {
            sqd0: primaryScore,
            sqd1: getScore(),
            sqd2: getScore(),
            sqd3: getScore(),
            sqd4: getScore(),
            sqd5: getScore(),
            sqd6: getScore(),
            sqd7: getScore(),
            sqd8: getScore()
        };
    }

    // Helper to download a CSV string in-memory without contacting server
    function downloadCsvString(filename, csvContent) {
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            a.remove();
            URL.revokeObjectURL(url);
        }, 100);
    }

    // Value mapping helpers matching PAO Isabela Excel CSM rules
    function mapClient(ctype) {
        const c = String(ctype || '').toLowerCase().trim();
        if (c.includes('citizen') || c.includes('mamayan') || c.includes('mamamayan')) return 1;
        if (c.includes('business') || c.includes('negosyo')) return 2;
        if (c.includes('government') || c.includes('gobyerno')) return 3;
        return 4;
    }

    function mapGender(gender) {
        const g = String(gender || '').toLowerCase().trim();
        if (g === 'male' || g.includes('lalak')) return 1;
        if (g === 'female' || g.includes('babae')) return 2;
        return 3;
    }

    function mapAge(age) {
        const a = String(age || '').toLowerCase().trim();
        if (a === '15-19' || a.includes('19')) return 1;
        if (a === '20-29' || a === '20-34' || a === '20-30') return 2;
        if (a === '30-39' || a === '35-49') return 3;
        if (a === '40-49' || a === '50-64' || a === '50-59') return 4;
        if (a.includes('60+') || a.includes('65')) return 5;
        return 6;
    }

    function mapService(trans) {
        const t = String(trans || '').toLowerCase().trim();
        if (t.includes('transfer')) return 1;
        if (t.includes('subdivision') || t.includes('consolidation')) return 2;
        if (t.includes('agri to urban')) return 3;
        if (t.includes('urban to urban')) return 4;
        if (t.includes('reassessment') || t.includes('dp') || t.includes('pc')) return 5;
        if (t.includes('discovery') || t.includes('declaration')) return 6;
        if (t.includes('certification') || t.includes('ctc') || t.includes('cpc') || t.includes('photocopy') || t.includes('certificate')) return 7;
        if (t.includes('verification') || t.includes('back') || t.includes('tracking') || t.includes('tracing')) return 8;
        if (t.includes('annotation')) return 9;
        if (t.includes('ocular')) return 10;
        if (t.includes('cancellation of td')) return 11;
        if (t.includes('tmcr') || t.includes('map')) return 12;
        if (t.includes('posting')) return 13;
        return 7;
    }

    function mapCc1(v) {
        const s = String(v || '').toLowerCase().trim();
        if (s === '1' || (s.includes('nakita') && s.includes('alam'))) return 1;
        if (s === '2' || (s.includes('hindi') && s.includes('nakita'))) return 2;
        if (s === '3' || s.includes('nalaman')) return 3;
        if (s === '4' || s.includes('hindi alam')) return 4;
        return 1;
    }

    function mapCc2(v) {
        const s = String(v || '').toLowerCase().trim();
        if (s === '1' || (s.includes('madaling') && !s.includes('medyo')) || s.includes('easy')) return 1;
        if (s === '2' || s.includes('medyo')) return 2;
        if (s === '3' || s.includes('mahirap') || s.includes('diffic')) return 3;
        if (s === '4' || s.includes('not visible') || (s.includes('hindi') && s.includes('makita'))) return 4;
        if (s === '5' || s === 'n/a') return 5;
        return 1;
    }

    function mapCc3(v) {
        const s = String(v || '').toLowerCase().trim();
        if (s === '1' || s.includes('sobrang') || s.includes('very')) return 1;
        if (s === '2' || s.includes('naman') || s.includes('somewhat')) return 2;
        if (s === '3' || s.includes('hindi') || s.includes('did not')) return 3;
        if (s === '4' || s === 'n/a') return 4;
        return 1;
    }

    function mapSqd(v) {
        const s = String(v || '').trim();
        if (s === '5') return 5;
        if (s === '4') return 4;
        if (s === '3') return 3;
        if (s === '2') return 2;
        if (s === '1') return 1;
        if (s === '6' || s.toUpperCase() === 'N/A') return 6;
        return 5;
    }

    // Build virtual CSM Export (In-memory, Zero Neon Inserts)
    function buildVirtualCsmExport(monthVal, rawRecords) {
        const sorted = [...rawRecords].sort((a, b) => (a.survey_date || '').localeCompare(b.survey_date || ''));
        const parts = monthVal.split('-');
        const year = parts[0];
        const monthNum = parts[1];
        const monthNames = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
        const monthFullNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        const mIdx = parseInt(monthNum, 10) - 1;
        const monthAbbr = monthNames[mIdx] || "OCT";
        const monthFull = `${monthFullNames[mIdx] || "October"} ${year}`;

        const dataOnlyHeaders = [
            'CSM Control No.', 'Internal or External', 'Month', 'Date', 'No.', '', 'Internal or External',
            'Uri ng Kliyente', 'Kasarian', 'Edad', 'Rehiyon ng Tirahan', 'Services Availed',
            'CC1', 'CC2', 'CC3', 'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8', 'Suggestions/Comments'
        ];

        const dataRows = [];
        const csvRows = [dataOnlyHeaders];

        sorted.forEach((r, idx) => {
            const seq = idx + 1;
            const ctrlNo = `E${monthNum}-${String(seq).padStart(3, '0')}`;
            let dateDay = 1;
            if (r.survey_date) {
                const dp = r.survey_date.split('-');
                if (dp.length === 3) dateDay = parseInt(dp[2], 10) || 1;
            }

            const comments = (r.suggestions || '').replace(/[\r\n\t]+/g, ' ').trim();

            const row = [
                ctrlNo,
                'E',
                monthAbbr,
                dateDay,
                seq,
                '',
                '1',
                mapClient(r.client_type),
                mapGender(r.gender),
                mapAge(r.age_group),
                '',
                mapService(r.transaction_type),
                mapCc1(r.cc1),
                mapCc2(r.cc2),
                mapCc3(r.cc3),
                mapSqd(r.sqd0),
                mapSqd(r.sqd1),
                mapSqd(r.sqd2),
                mapSqd(r.sqd3),
                mapSqd(r.sqd4),
                mapSqd(r.sqd5),
                mapSqd(r.sqd6),
                mapSqd(r.sqd7),
                mapSqd(r.sqd8),
                comments
            ];
            dataRows.push(row);
            csvRows.push(row);
        });

        const dataOnlyLines = dataRows.map(r => r.join("\t"));
        const tsvDataOnly = dataOnlyLines.join("\r\n");
        const tsvDataWithHeaders = [dataOnlyHeaders.join("\t"), ...dataOnlyLines].join("\r\n");

        const csvContent = csvRows.map(r => r.map(val => {
            const s = String(val ?? '');
            if (s.includes(',') || s.includes('"') || s.includes("\n") || s.includes("\r")) {
                return `"${s.replace(/"/g, '""')}"`;
            }
            return s;
        }).join(',')).join("\r\n");

        return {
            status: 'success',
            is_virtual: true,
            month: monthVal,
            month_label: monthFull,
            total_records: sorted.length,
            tsv_full: tsvDataWithHeaders,
            tsv_data_only: tsvDataOnly,
            tsv_data_with_headers: tsvDataWithHeaders,
            tsv_summary_only: '',
            csv_data_only: csvContent
        };
    }

    // TOGGLE SAVE TO DATABASE SWITCH
    $('#saveToDbToggle').on('change', function() {
        const isSaveToDb = $(this).is(':checked');
        if (isSaveToDb) {
            $('#generateBtnText').text('Generate & Save');
            $('#generateBtnIcon').removeClass('ph-sparkle text-warning').addClass('ph-cloud-arrow-up text-white');
            $('#genModeNotice').removeClass('text-success').addClass('text-warning-emphasis').html(
                '<i class="ph-bold ph-warning fs-5 text-warning"></i> <span>Database mode: Inserts generated responses into Neon database.</span>'
            );
        } else {
            $('#generateBtnText').text('Generate & Copy');
            $('#generateBtnIcon').removeClass('ph-cloud-arrow-up text-white').addClass('ph-sparkle text-warning');
            $('#genModeNotice').removeClass('text-warning-emphasis').addClass('text-success').html(
                '<i class="ph-bold ph-shield-check fs-5"></i> <span>In-memory mode: Generates data for export without database insertion.</span>'
            );
        }
    });

    // DIRECT GENERATOR HANDLER (VIRTUAL OR DATABASE)
    $('#generatorForm').on('submit', async function(e) {
        e.preventDefault();

        const count = parseInt($('#genCount').val()) || 10;
        const monthVal = $('#genMonth').val(); // e.g. "2026-10"
        const clientMode = $('#genClient').val();
        const sqdMode = $('#genSqd').val() || 'Prioritize45';
        const transMode = $('#genTransaction').val();
        const muniMode = $('#genMuni').val();
        const isSaveToDb = $('#saveToDbToggle').is(':checked');

        if (!monthVal) {
            alert('Please select a Target Month first.');
            return false;
        }

        if (count < 1 || count > 500) {
            alert('Please enter a record count between 1 and 500.');
            return false;
        }

        const year = parseInt(monthVal.split('-')[0]);
        const monthIndex = parseInt(monthVal.split('-')[1]) - 1; // JS months are 0-indexed

        const genders = ["Male", "Female"]; 
        const ages = ["15-19", "20-29", "30-39", "40-49", "50-59", "60+"];
        const ccScores = ["3", "4"];

        const records = [];
        for (let i = 0; i < count; i++) {
            const rDate = getRandomValidDate(year, monthIndex);
            const rClient = clientMode === "Random" ? getWeightedClient() : clientMode;
            
            let rTrans;
            if (transMode === "Random") {
                rTrans = transactionTypes[Math.floor(Math.random() * transactionTypes.length)];
            } else if (transMode === "RandomWeighted") {
                rTrans = getWeightedTransaction();
            } else {
                rTrans = transMode;
            }

            const rMuni = muniMode === "Random" ? isabelaLocations[Math.floor(Math.random() * isabelaLocations.length)] : muniMode;
            const rGender = genders[Math.floor(Math.random() * genders.length)];
            const rAge = ages[Math.floor(Math.random() * ages.length)];
            const getRandCc = () => ccScores[Math.floor(Math.random() * ccScores.length)];
            const sqd = generateSqdScores(sqdMode);

            records.push({
                survey_date:      rDate,
                client_type:      rClient,
                gender:           rGender,
                age_group:        rAge,
                region:           'Region II',
                province:         'Isabela',
                municipality:     rMuni,
                transaction_type: rTrans,
                cc1:              getRandCc(),
                cc2:              getRandCc(),
                cc3:              getRandCc(),
                sqd0:             sqd.sqd0,
                sqd1:             sqd.sqd1,
                sqd2:             sqd.sqd2,
                sqd3:             sqd.sqd3,
                sqd4:             sqd.sqd4,
                sqd5:             sqd.sqd5,
                sqd6:             sqd.sqd6,
                sqd7:             sqd.sqd7,
                sqd8:             sqd.sqd8
            });
        }

        if (isSaveToDb) {
            // DATABASE SUBMISSION TO NEON
            $('#bulkJsonInput').val(JSON.stringify(records));
            const btn = $('#generateBtn');
            btn.prop('disabled', true).css('opacity', '0.8');
            $('#generateBtnText').text('Generating & Saving ' + count + ' Records...');
            $('#generateBtnIcon').removeClass('ph-cloud-arrow-up text-white').addClass('ph-spinner-gap spin');
            HTMLFormElement.prototype.submit.call(this);
            return;
        }

        // VIRTUAL IN-MEMORY GENERATION (NO NEON INSERTS)
        const btn = $('#generateBtn');
        btn.prop('disabled', true).css('opacity', '0.8');
        $('#generateBtnText').text('Formatting ' + count + ' Records...');
        $('#generateBtnIcon').removeClass('ph-sparkle text-warning').addClass('ph-spinner-gap spin');

        const virtualExport = buildVirtualCsmExport(monthVal, records);
        csmExportCache[monthVal] = virtualExport;

        // Auto-copy to clipboard
        try {
            await copyToClipboard(virtualExport.tsv_data_only);
            const firstRowSample = (virtualExport.tsv_data_only.split('\n')[0] || '').split('\t')[0] || 'E10-001';
            showCopyToast(
                `Generated & Copied ${count} Records (${virtualExport.month_label})!`,
                `Ready to paste into cell F15 in Excel (starts with ${firstRowSample}). Zero records inserted into Neon.`
            );
        } catch (copyErr) {
            console.error('Clipboard copy error:', copyErr);
        }

        // Open preview modal
        openPreviewExportModal(monthVal, virtualExport.month_label, virtualExport.total_records);

        setTimeout(() => {
            btn.prop('disabled', false).css('opacity', '1');
            $('#generateBtnText').text('Generate & Copy for Excel');
            $('#generateBtnIcon').removeClass('ph-spinner-gap spin').addClass('ph-sparkle text-warning');
        }, 600);
    });

    function openWipeForMonth(ym) {
        if (ym) {
            syncWipeMonthSelect(ym);
            syncWipeMonthInput(ym);
            const modalEl = document.getElementById('wipeMonthModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    // WIPE DATA BY MONTH HELPERS
    function syncWipeMonthInput(val) {
        if (val) document.getElementById('modalWipeMonthInput').value = val;
    }

    function syncWipeMonthSelect(val) {
        if (val) {
            const sel = document.getElementById('modalWipeMonthSelect');
            let found = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === val) {
                    sel.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (!found) {
                const opt = new Option(val + ' (Custom)', val, true, true);
                sel.add(opt);
            }
        }
    }

    $('#wipeMonthModal').on('show.bs.modal', function () {
        const curTargetMonth = $('#genMonth').val();
        if (curTargetMonth) {
            syncWipeMonthSelect(curTargetMonth);
            $('#modalWipeMonthInput').val(curTargetMonth);
        }
    });

    function confirmWipeMonth() {
        const month = $('#modalWipeMonthInput').val() || $('#modalWipeMonthSelect').val();
        const isAll = $('#scopeAll').is(':checked');
        const scopeText = isAll ? "ALL records (including real citizen submissions)" : "only bulk-generated records";
        return confirm("CONFIRMATION:\n\nYou are about to permanently delete " + scopeText + " for month: " + month + ".\n\nAre you sure you want to proceed?");
    }

    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        $('#sidebar').toggleClass('collapsed show-mobile');
        $('#main-content').toggleClass('expanded');
    });

    // ==========================================
    // CSM EXCEL EXPORT & CLIPBOARD SYSTEM
    // ==========================================
    let csmExportCache = {};
    let currentModalYm = '';
    let currentPreviewMode = 'full';

    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return await navigator.clipboard.writeText(text);
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            return new Promise((resolve, reject) => {
                document.execCommand('copy') ? resolve() : reject(new Error('Copy failed'));
                textArea.remove();
            });
        }
    }

    function showCopyToast(title, message) {
        $('#csmCopyToastTitle').text(title || 'Copied to Clipboard!');
        $('#csmCopyToastMsg').text(message || 'Ready to paste into Excel (Ctrl+V)');
        const toastEl = document.getElementById('csmCopyToast');
        if (toastEl) {
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
            toast.show();
        }
    }

    async function fetchMonthExport(ym) {
        if (csmExportCache[ym]) {
            return csmExportCache[ym];
        }
        const response = await fetch(`admin_bulk.php?action=get_csm_export&month=${encodeURIComponent(ym)}`);
        const text = await response.text();

        if (text.includes('login.php') || text.includes('Sign In') || text.includes('administrator session has expired')) {
            throw new Error('Your session has expired. Please refresh the page and sign in again.');
        }

        let data;
        try {
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');
            if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                data = JSON.parse(text.substring(jsonStart, jsonEnd + 1));
            } else {
                data = JSON.parse(text);
            }
        } catch (e) {
            const cleanErr = text.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
            throw new Error(cleanErr.substring(0, 150) || 'Invalid server response');
        }

        if (data.status !== 'success') {
            throw new Error(data.message || 'Export error');
        }
        csmExportCache[ym] = data;
        return data;
    }

    async function copyMonthData(ym, mode, btn) {
        mode = mode || 'data_only';
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="ph-bold ph-spinner-gap spin fs-6"></i> <span>Copying...</span>`;
        }

        try {
            const data = await fetchMonthExport(ym);
            let textToCopy = '';
            let label = '';
            if (mode === 'full') {
                textToCopy = data.tsv_full;
                label = 'Full Template';
            } else if (mode === 'summary_only') {
                textToCopy = data.tsv_summary_only;
                label = 'Summary Breakdown';
            } else {
                textToCopy = data.tsv_data_only;
                label = 'Data Table (25 Columns)';
            }

            await copyToClipboard(textToCopy);

            if (btn) {
                btn.innerHTML = `<i class="ph-bold ph-check text-success fs-6"></i> <span class="text-success">Copied!</span>`;
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }, 2500);
            }

            const firstRowSample = (textToCopy.split('\n')[0] || '').split('\t')[0] || 'E10-001';
            showCopyToast(
                `Copied ${data.month_label} Data (starts with ${firstRowSample})!`,
                `Ready to paste into cell F15 in your PAO Excel template (Ctrl+V).`
            );
        } catch (err) {
            console.error(err);
            if (btn) {
                btn.innerHTML = `<i class="ph-bold ph-warning text-danger fs-6"></i> <span>Failed</span>`;
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }, 2500);
            }
            alert('Could not copy export data: ' + err.message);
        }
    }

    async function openPreviewExportModal(ym, label, count) {
        currentModalYm = ym;
        currentPreviewMode = 'data_only';

        $('#exportModalBadgeMonth').text(label || ym);
        $('#exportModalBadgeCount').text((count || 0) + ' Records');
        $('#modalDownloadCsvBtn').attr('href', `admin_bulk.php?action=download_csm_csv&month=${encodeURIComponent(ym)}`);
        $('#modalDownloadCsvBtn').off('click').on('click', function(e) {
            if (csmExportCache[ym] && csmExportCache[ym].is_virtual && csmExportCache[ym].csv_data_only) {
                e.preventDefault();
                downloadCsvString(`CSM_DataTable_${ym}.csv`, csmExportCache[ym].csv_data_only);
            }
        });
        $('#includeHeadersToggle').prop('checked', false);

        // Reset tabs to Data Only
        const tabBtn = document.getElementById('tab-data-btn');
        if (tabBtn) {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }

        const modalEl = document.getElementById('exportPreviewModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        $('#exportLoadingSpinner').removeClass('d-none');
        $('#exportPreviewTextarea').addClass('d-none');

        try {
            const data = await fetchMonthExport(ym);
            $('#exportLoadingSpinner').addClass('d-none');
            $('#exportPreviewTextarea').removeClass('d-none');
            updateModalPreviewText(data);
        } catch (err) {
            $('#exportLoadingSpinner').addClass('d-none');
            $('#exportPreviewTextarea').removeClass('d-none').val('Error loading export preview: ' + err.message);
        }
    }

    function switchPreviewMode(mode) {
        currentPreviewMode = mode;
        if (csmExportCache[currentModalYm]) {
            updateModalPreviewText(csmExportCache[currentModalYm]);
        }
    }

    function updateModalPreviewText(data) {
        let txt = '';
        if (currentPreviewMode === 'full') {
            txt = data.tsv_full;
        } else if (currentPreviewMode === 'summary_only') {
            txt = data.tsv_summary_only;
        } else {
            const includeHeaders = $('#includeHeadersToggle').is(':checked');
            txt = includeHeaders ? (data.tsv_data_with_headers || data.tsv_data_only) : data.tsv_data_only;
        }
        $('#exportPreviewTextarea').val(txt);
    }

    function toggleHeadersPreview() {
        if (csmExportCache[currentModalYm]) {
            updateModalPreviewText(csmExportCache[currentModalYm]);
        }
    }

    async function copyModalCurrentContent() {
        const txt = $('#exportPreviewTextarea').val();
        if (!txt) return;

        const btn = $('#modalCopyBtn');
        const originalText = $('#modalCopyBtnText').text();
        btn.prop('disabled', true);
        $('#modalCopyBtnText').text('Copied!');
        btn.find('i').removeClass('ph-copy').addClass('ph-check');

        await copyToClipboard(txt);
        const firstRowSample = (txt.split('\n')[0] || '').split('\t')[0] || 'E10-001';
        showCopyToast('Copied to Clipboard!', `Ready to paste at cell F15 (starts with ${firstRowSample}).`);

        setTimeout(() => {
            $('#modalCopyBtnText').text(originalText);
            btn.find('i').removeClass('ph-check').addClass('ph-copy');
            btn.prop('disabled', false);
        }, 2000);
    }

    // ==========================================
    // SERVICE TABLE INSERTION (EXACT QUOTAS) LOGIC
    // ==========================================

    function updateSvcTableTotal() {
        let total = 0;
        const values = {};
        $('.svc-quota-input').each(function() {
            const idx = $(this).data('service-idx');
            const val = parseInt($(this).val(), 10) || 0;
            values[idx] = val;
            total += val;
        });

        $('#svcTableTotalBadge').text(total.toLocaleString());

        // Update percentage shares for each service row
        for (let i = 1; i <= 13; i++) {
            const val = values[i] || 0;
            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0';
            $(`#svcShare_${i}`).text(pct + '%');
        }

        // Live sync with Overall Responses & Total Transaction card below
        if (total > 0) {
            for (let i = 1; i <= 13; i++) {
                const val = values[i] || 0;
                $(`#cardResp_${i}`).text(val.toLocaleString());
                $(`#cardTt_${i}`).val(val);
            }
            $('#activeMonthTotalRespBadge').text(total.toLocaleString());
            recalcCardOverallTotal();
        }

        // Target 500 status indicator
        const noticeEl = $('#svcTableArtaNotice');
        if (total >= 500) {
            noticeEl.html(`<span class="text-success fw-bold"><i class="ph-bold ph-check-circle"></i> Meets Target (${total.toLocaleString()} / 500)</span>`);
        } else if (total > 0) {
            noticeEl.html(`<span class="text-warning-emphasis fw-bold"><i class="ph-bold ph-info"></i> ${total.toLocaleString()} responses (Target: 500)</span>`);
        } else {
            noticeEl.html(`<span class="text-muted"><i class="ph-bold ph-target"></i> Target: 500 responses</span>`);
        }
    }

    function recalcCardOverallTotal() {
        let sumTt = 0;
        let sumResp = 0;

        $('.overall-tt-input').each(function() {
            const val = parseInt($(this).val(), 10) || 0;
            sumTt += val;
        });

        for (let i = 1; i <= 13; i++) {
            const rTxt = $(`#cardResp_${i}`).text().replace(/[^0-9]/g, '');
            const rVal = parseInt(rTxt, 10) || 0;
            sumResp += rVal;
        }

        $('#cardExternalTotalResp').text(sumResp.toLocaleString());
        $('#cardOverallFinalResp').text(sumResp.toLocaleString());
        $('#cardExternalTotalTt').text(sumTt.toLocaleString());
        $('#cardOverallFinalTt').text(sumTt.toLocaleString());

        // Update share percentages in Overall Responses card
        for (let i = 1; i <= 13; i++) {
            const rTxt = $(`#cardResp_${i}`).text().replace(/[^0-9]/g, '');
            const rVal = parseInt(rTxt, 10) || 0;
            const pct = sumResp > 0 ? ((rVal / sumResp) * 100).toFixed(1) : '0.0';
            $(`#cardShare_${i}`).text(pct + '%');
        }
    }

    function presetExampleSvc() {
        const exampleCounts = {
            1: 242, 2: 35, 3: 10, 4: 8, 5: 18, 6: 12, 7: 443,
            8: 22, 9: 9, 10: 6, 11: 4, 12: 11, 13: 5
        };
        for (let i = 1; i <= 13; i++) {
            $(`#svcQuota_${i}`).val(exampleCounts[i] || 0);
        }
        updateSvcTableTotal();
        showCopyToast('Preset Loaded', 'Sample distribution applied (825 responses).');
    }

    function preset500Svc() {
        const artaCounts = {
            1: 140, 2: 45, 3: 6, 4: 5, 5: 25, 6: 10, 7: 230,
            8: 20, 9: 7, 10: 3, 11: 2, 12: 5, 13: 2
        };
        for (let i = 1; i <= 13; i++) {
            $(`#svcQuota_${i}`).val(artaCounts[i] || 0);
        }
        updateSvcTableTotal();
        showCopyToast('Preset Loaded', 'Standard distribution applied (500 responses).');
    }

    function clearAllSvcTable() {
        for (let i = 1; i <= 13; i++) {
            $(`#svcQuota_${i}`).val(0);
            $(`#cardResp_${i}`).text('0');
            $(`#cardTt_${i}`).val(0);
        }
        updateSvcTableTotal();
        recalcCardOverallTotal();
    }

    function stepSvcInput(idx, step) {
        const input = $(`#svcQuota_${idx}`);
        let val = (parseInt(input.val(), 10) || 0) + step;
        if (val < 0) val = 0;
        input.val(val);
        updateSvcTableTotal();
    }

    // Toggle Save to Database in Service Table
    $('#svcTableSaveToDbToggle').on('change', function() {
        const isSaveToDb = $(this).is(':checked');
        if (isSaveToDb) {
            $('#svcSaveDbBtn').removeClass('d-none').addClass('d-inline-flex');
            $('#svcTableModeNotice').removeClass('text-success').addClass('text-warning-emphasis').html(
                '<i class="ph-bold ph-warning fs-5 text-warning"></i> <span>Database mode: Inserts generated responses into Neon database.</span>'
            );
        } else {
            $('#svcSaveDbBtn').addClass('d-none').removeClass('d-inline-flex');
            $('#svcTableModeNotice').removeClass('text-warning-emphasis').addClass('text-success').html(
                '<i class="ph-bold ph-shield-check fs-5"></i> <span>In-memory mode: Generates data for export and printing without database insertion.</span>'
            );
        }
    });

    function generateServiceTableRecords() {
        const monthVal = $('#svcTableMonth').val(); // e.g. "2026-10"
        if (!monthVal) {
            alert('Please select a Target Month first.');
            return null;
        }

        const clientMode = $('#svcTableClient').val();
        const sqdMode = $('#svcTableSqd').val() || 'Prioritize45';
        const muniMode = $('#svcTableMuni').val();

        // Collect quotas
        const quotas = [];
        const quotasMap = {};
        let totalCount = 0;
        $('.svc-quota-input').each(function() {
            const idx = parseInt($(this).data('service-idx'), 10);
            const name = $(this).data('service-name');
            const count = parseInt($(this).val(), 10) || 0;
            quotasMap[idx] = count;
            if (count > 0) {
                quotas.push({ idx, name, count });
                totalCount += count;
            }
        });

        if (totalCount === 0) {
            alert('Please enter at least one service quota count greater than 0.');
            return null;
        }

        const year = parseInt(monthVal.split('-')[0], 10);
        const monthIndex = parseInt(monthVal.split('-')[1], 10) - 1;
        const workingDates = getAllValidWorkingDates(year, monthIndex);

        const genders = ["Male", "Female"];
        const records = [];
        let datePointer = 0;

        quotas.forEach(q => {
            for (let i = 0; i < q.count; i++) {
                const rDate = workingDates[datePointer % workingDates.length];
                datePointer++;

                const rClient = clientMode === "Random" ? getWeightedClient() : clientMode;
                const rMuni = muniMode === "Random" ? isabelaLocations[Math.floor(Math.random() * isabelaLocations.length)] : muniMode;
                const rGender = genders[Math.floor(Math.random() * genders.length)];

                // Age group distribution
                const ar = Math.random() * 100;
                let rAge = '20-29';
                if (ar < 4) rAge = '15-19';
                else if (ar < 45) rAge = '20-29';
                else if (ar < 75) rAge = '30-39';
                else if (ar < 92) rAge = '40-49';
                else if (ar < 97) rAge = '50-59';
                else rAge = '60+';

                // Realistic Citizen Charter responses
                const cc1Roll = Math.random() * 100;
                const rCc1 = (cc1Roll < 85) ? '1' : ((cc1Roll < 95) ? '3' : '2');
                
                const cc2Roll = Math.random() * 100;
                const rCc2 = (cc2Roll < 75) ? '1' : ((cc2Roll < 95) ? '2' : '5');

                const cc3Roll = Math.random() * 100;
                const rCc3 = (cc3Roll < 80) ? '1' : ((cc3Roll < 95) ? '2' : '4');

                const sqd = generateSqdScores(sqdMode);

                records.push({
                    survey_date:      rDate,
                    client_type:      rClient,
                    gender:           rGender,
                    age_group:        rAge,
                    region:           'Region II',
                    province:         'Isabela',
                    municipality:     rMuni,
                    transaction_type: q.name,
                    cc1:              rCc1,
                    cc2:              rCc2,
                    cc3:              rCc3,
                    sqd0:             sqd.sqd0,
                    sqd1:             sqd.sqd1,
                    sqd2:             sqd.sqd2,
                    sqd3:             sqd.sqd3,
                    sqd4:             sqd.sqd4,
                    sqd5:             sqd.sqd5,
                    sqd6:             sqd.sqd6,
                    sqd7:             sqd.sqd7,
                    sqd8:             sqd.sqd8,
                    suggestions:      ''
                });
            }
        });

        // Sort chronologically by date
        records.sort((a, b) => (a.survey_date || '').localeCompare(b.survey_date || ''));

        return {
            monthVal,
            records,
            totalCount: records.length,
            quotasMap
        };
    }

    async function copyServiceTableData(btn) {
        const genData = generateServiceTableRecords();
        if (!genData) return;

        const { monthVal, records, totalCount } = genData;
        const isSaveToDb = $('#svcTableSaveToDbToggle').is(':checked');

        if (isSaveToDb) {
            if (!confirm(`Save to Database is ENABLED. This will permanently insert ${totalCount} records into your Neon cloud database. Proceed?`)) {
                return;
            }
            $('#bulkJsonInput').val(JSON.stringify(records));
            $('#saveToDbToggle').prop('checked', true);
            HTMLFormElement.prototype.submit.call(document.getElementById('generatorForm'));
            return;
        }

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="ph-bold ph-spinner-gap spin fs-5"></i> <span>Formatting ${totalCount} Records...</span>`;

        const virtualExport = buildVirtualCsmExport(monthVal, records);
        csmExportCache[monthVal] = virtualExport;

        try {
            await copyToClipboard(virtualExport.tsv_data_only);
            const firstRowSample = (virtualExport.tsv_data_only.split('\n')[0] || '').split('\t')[0] || 'E10-001';
            showCopyToast(
                'Copied to Clipboard',
                `${totalCount} records formatted for cell F15 (${virtualExport.month_label}).`
            );
        } catch (copyErr) {
            console.error('Clipboard copy error:', copyErr);
        }

        // Open preview modal
        openPreviewExportModal(monthVal, virtualExport.month_label, virtualExport.total_records);

        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }, 600);
    }

    function printServiceTableReport(btn) {
        const genData = generateServiceTableRecords();
        if (!genData) return;

        const { monthVal, records, totalCount } = genData;

        // Submit custom records to print_report.php via hidden POST form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'print_report.php';
        form.target = '_blank';
        form.style.display = 'none';

        const mInput = document.createElement('input');
        mInput.type = 'hidden';
        mInput.name = 'month';
        mInput.value = monthVal;
        form.appendChild(mInput);

        const jsonInput = document.createElement('input');
        jsonInput.type = 'hidden';
        jsonInput.name = 'custom_records_json';
        jsonInput.value = JSON.stringify(records);
        form.appendChild(jsonInput);

        const quotasInput = document.createElement('input');
        quotasInput.type = 'hidden';
        quotasInput.name = 'custom_quotas_json';
        quotasInput.value = JSON.stringify(genData.quotasMap || {});
        form.appendChild(quotasInput);

        document.body.appendChild(form);
        form.submit();
        setTimeout(() => form.remove(), 1000);

        showCopyToast(
            'Report Generated',
            `Official CSM report opened for ${monthVal} (${totalCount} records).`
        );
    }

    function saveServiceTableToDatabase(btn) {
        const genData = generateServiceTableRecords();
        if (!genData) return;

        const { records, totalCount } = genData;
        if (!confirm(`Are you sure you want to permanently insert ${totalCount} records into your Neon cloud database?`)) {
            return;
        }

        $('#bulkJsonInput').val(JSON.stringify(records));
        $('#saveToDbToggle').prop('checked', true);
        HTMLFormElement.prototype.submit.call(document.getElementById('generatorForm'));
    }

    // Save Top Service Table Quotas directly as single digits in database
    async function saveServiceTableQuotasAsDigits(btn) {
        const monthVal = $('#svcTableMonth').val();
        if (!monthVal) {
            alert('Please select a Target Month first.');
            return;
        }

        const quotas = {};
        let totalCount = 0;
        $('.svc-quota-input').each(function() {
            const idx = $(this).data('service-idx');
            const count = parseInt($(this).val(), 10) || 0;
            quotas[idx] = count;
            totalCount += count;
        });

        if (totalCount === 0) {
            alert('Please enter at least one service quota count greater than 0.');
            return;
        }

        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="ph-bold ph-spinner-gap spin fs-5"></i> <span>Saving Quotas...</span>`;
        }

        try {
            const formData = new FormData();
            formData.append('target_month', monthVal);
            for (const [k, v] of Object.entries(quotas)) {
                formData.append(`quotas[${k}]`, v);
            }

            const res = await fetch('admin_bulk.php?action=save_monthly_quotas_ajax', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                showCopyToast(
                    'Quotas Saved',
                    `Stored ${totalCount.toLocaleString()} total transactions for ${monthVal}.`
                );
                // Sync with overall responses table below
                for (let i = 1; i <= 13; i++) {
                    $(`#cardTt_${i}`).val(quotas[i] || 0);
                }
                recalcCardOverallTotal();
            } else {
                alert('Error saving quotas: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Network/server error while saving quotas: ' + err.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    // Pull Quotas from Top Panel into Overall Responses Table
    function copyServiceQuotasFromTop() {
        let pulledCount = 0;
        for (let i = 1; i <= 13; i++) {
            const val = parseInt($(`#svcQuota_${i}`).val(), 10) || 0;
            $(`#cardTt_${i}`).val(val);
            pulledCount += val;
        }
        recalcCardOverallTotal();
        showCopyToast('Quotas Synced', `Loaded ${pulledCount.toLocaleString()} transactions into table.`);
    }

    // Save Overall Responses Table Quotas via AJAX
    async function saveQuotasAjax(btn) {
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="ph-bold ph-spinner-gap spin fs-6"></i> <span>Saving...</span>`;
        }

        const targetMonth = $('input[name="quota_target_month"]').val();
        const quotas = {};
        let totalSum = 0;
        $('.overall-tt-input').each(function() {
            const idx = $(this).data('idx');
            const val = parseInt($(this).val(), 10) || 0;
            quotas[idx] = val;
            totalSum += val;
        });

        try {
            const formData = new FormData();
            formData.append('target_month', targetMonth);
            for (const [k, v] of Object.entries(quotas)) {
                formData.append(`quotas[${k}]`, v);
            }

            const res = await fetch('admin_bulk.php?action=save_monthly_quotas_ajax', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                showCopyToast(
                    'Quotas Saved',
                    `Stored ${totalSum.toLocaleString()} total transactions for ${targetMonth}.`
                );
                recalcCardOverallTotal();
            } else {
                alert('Error saving quotas: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Network/Server error: ' + err.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    // Initialize Service Table live counter and Overall Responses table on page load
    $(document).ready(function() {
        updateSvcTableTotal();
        recalcCardOverallTotal();
    });

    function copyCurrentTargetMonth(btn) {
        const ym = $('#genMonth').val();
        if (!ym) {
            alert('Please select a Target Month first.');
            return;
        }
        copyMonthData(ym, 'data_only', btn);
    }
</script>
<script src="darkmode.js"></script>
</body>
</html>
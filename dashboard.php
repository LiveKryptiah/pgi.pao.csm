<?php
// MUST BE AT THE VERY TOP
session_start();

// SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require 'db.php';

// ==========================================
// AI CHATBOT BACKEND 
// ==========================================
if (isset($_POST['chat_message'])) {
    $user_message = strtolower(trim($_POST['chat_message']));
    $response = "I am a local system assistant. I can answer questions about your <strong>total records</strong>, <strong>suggestions</strong>, <strong>SQD meanings</strong>, or <strong>how to use features</strong> like export!";
    try {
        if (strpos($user_message, 'hello') !== false || strpos($user_message, 'hi') !== false) {
            $response = "Hello! How can I help you analyze the dashboard today?";
        } 
        elseif (strpos($user_message, 'total') !== false || strpos($user_message, 'how many') !== false) {
            $total_responses = $pdo->query("SELECT COUNT(*) FROM responses")->fetchColumn();
            $avg_rating = $pdo->query("SELECT ROUND(AVG(sqd0), 2) FROM responses")->fetchColumn();
            $response = "Currently, you have <strong>$total_responses</strong> total client responses with an average satisfaction score of <strong>$avg_rating/5.0</strong>.";
        } 
        elseif (strpos($user_message, 'sqd') !== false) {
            $response = "SQD stands for <strong>Service Quality Dimensions</strong>. It measures things like Responsiveness, Reliability, Facilities, Communication, Costs, Integrity (Walang Palakasan), Professionalism, and Rules.";
        } 
        elseif (strpos($user_message, 'export') !== false || strpos($user_message, 'print') !== false) {
            $response = "To export data, click 'Export & Print' on the sidebar. You can use the 'Filter' at the top to pick a date range, and the table will update for you to export to Excel, CSV, or PDF/Print!";
        } 
        elseif (strpos($user_message, 'suggestion') !== false || strpos($user_message, 'feedback') !== false) {
            $total_suggestions = $pdo->query("SELECT COUNT(*) FROM responses WHERE suggestions IS NOT NULL AND suggestions != '' AND suggestions != 'N/A' AND suggestions != 'None'")->fetchColumn();
            $response = "There are currently <strong>$total_suggestions</strong> actual written suggestions. You can view them in the 'Suggestions' tab.";
        }
    } catch (Exception $e) {
        $response = "Sorry, I encountered a database error while checking that for you.";
    }
    echo $response;
    exit; 
}
// ==========================================

// Helper function to check if a suggestion is valid (ignoring empty, none, n/a, etc.)
function is_valid_suggestion($text) {
    if ($text === null) return false;
    $s = trim($text);
    if ($s === '') return false;
    $s_lower = strtolower($s);
    if (in_array($s_lower, ['none', 'n/a', 'na', 'wala', '-', 'none.', 'n/a.', 'walang mungkahi', 'no suggestion', 'none po', 'wala po'])) return false;
    if (preg_match('/^(none|n\/a|na|wala)[\.\!\s]*$/i', $s)) return false;
    return true;
}

// --- GLOBAL MONTH / DATE FILTER LOGIC ---
$selected_month = $_GET['month'] ?? '';
$start_date     = $_GET['start_date'] ?? '';
$end_date       = $_GET['end_date'] ?? '';

// Build available month choices (All months of current year + historical months from database)
$available_months = [];
try {
    $m_stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(survey_date, '%Y-%m') as ym, DATE_FORMAT(survey_date, '%M %Y') as m_label FROM responses WHERE survey_date IS NOT NULL AND CAST(survey_date AS VARCHAR) NOT IN ('', '0000-00-00') ORDER BY ym DESC");
    while ($m_row = $m_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($m_row['ym'])) {
            $available_months[$m_row['ym']] = $m_row['m_label'];
        }
    }
} catch (Exception $e) {}

// Include all months of the current year for quick selection
$cur_year = date('Y');
for ($m = 12; $m >= 1; $m--) {
    $ym = sprintf("%s-%02d", $cur_year, $m);
    if (!isset($available_months[$ym])) {
        $available_months[$ym] = date('F Y', strtotime($ym . '-01'));
    }
}
krsort($available_months);

$query = "SELECT * FROM responses WHERE 1=1";
$params = [];

if (!empty($selected_month)) {
    $query .= " AND DATE_FORMAT(survey_date, '%Y-%m') = :selected_month";
    $params[':selected_month'] = $selected_month;
} elseif (!empty($start_date) || !empty($end_date)) {
    if (!empty($start_date)) {
        $query .= " AND survey_date >= :start_date";
        $params[':start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $query .= " AND survey_date <= :end_date";
        $params[':end_date'] = $end_date;
    }
}
$query .= " ORDER BY survey_date DESC, id DESC";

// Fetching filtered data
$responses = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- METRICS & CALCULATION ---
$total_clients = count($responses);
$sum_rating = 0;
$rated_count = 0;
$positive_rating_count = 0;
$written_suggestions_count = 0;

$sqd_labels = [
    'sqd0' => 'SQD0: Pangkalahatang Kasiyahan (Overall Satisfaction)',
    'sqd1' => 'SQD1: Oras na ginugol (Responsiveness)',
    'sqd2' => 'SQD2: Pagsunod sa hakbang (Reliability)',
    'sqd3' => 'SQD3: Madali at simple (Access & Facilities)',
    'sqd4' => 'SQD4: Madaling nakahanap ng info (Communication)',
    'sqd5' => 'SQD5: Makatwirang halaga (Costs)',
    'sqd6' => 'SQD6: Patas na transaksyon (Integrity / Walang Palakasan)',
    'sqd7' => 'SQD7: Magalang na tauhan (Assurance / Professionalism)',
    'sqd8' => 'SQD8: Nakuha ang kailangan (Outcome / Rules Explained)'
];

$sqd_short_labels = [
    'sqd0' => 'SQD0: Overall',
    'sqd1' => 'SQD1: Responsiveness',
    'sqd2' => 'SQD2: Reliability',
    'sqd3' => 'SQD3: Facilities',
    'sqd4' => 'SQD4: Communication',
    'sqd5' => 'SQD5: Costs',
    'sqd6' => 'SQD6: Integrity',
    'sqd7' => 'SQD7: Professionalism',
    'sqd8' => 'SQD8: Outcome'
];

$sqd_arta_names = [
    'sqd0' => 'Overall Satisfaction',
    'sqd1' => 'Responsiveness',
    'sqd2' => 'Reliability',
    'sqd3' => 'Access & Facilities',
    'sqd4' => 'Communication',
    'sqd5' => 'Costs',
    'sqd6' => 'Integrity',
    'sqd7' => 'Professionalism',
    'sqd8' => 'Outcome'
];

$sqd_tally = [];
foreach ($sqd_labels as $key => $label) {
    $sqd_tally[$key] = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0, 'N/A' => 0];
}

$services_tally = [];
$client_type_tally = [];
$gender_tally = [];
$age_tally = [];
$province_tally = [];
$municipality_tally = [];
$date_tally = [];

foreach ($responses as $row) {
    if (isset($row['sqd0']) && is_numeric($row['sqd0'])) {
        $r = (float)$row['sqd0'];
        $sum_rating += $r;
        $rated_count++;
        if ($r >= 4) {
            $positive_rating_count++;
        }
    }

    $sug = $row['suggestions'] ?? '';
    if (is_valid_suggestion($sug)) {
        $written_suggestions_count++;
    }

    foreach ($sqd_labels as $key => $label) {
        $answer = isset($row[$key]) ? trim($row[$key]) : null;
        if (isset($sqd_tally[$key][$answer])) { $sqd_tally[$key][$answer]++; } 
        elseif ($answer === 'N/A') { $sqd_tally[$key]['N/A']++; }
    }

    // TRANSACTION NORMALIZATION (Mapping to the 13 official service transactions)
    $raw_trans = isset($row['transaction_type']) && !empty(trim($row['transaction_type'])) ? trim($row['transaction_type']) : 'Unspecified';
    $trans = $raw_trans;
    if (stripos($raw_trans, 'reassessment') !== false) {
        $trans = 'Reassessment (DP/PC/DT)';
    } elseif (stripos($raw_trans, 'urban to urban') !== false) {
        $trans = 'Reclassification (Urban to Urban)';
    } elseif (stripos($raw_trans, 'agri to urban') !== false || stripos($raw_trans, 'agrikultural') !== false) {
        $trans = 'Reclassification (Agri to Urban)';
    } elseif (stripos($raw_trans, 'subdivision') !== false || stripos($raw_trans, 'consolidation') !== false) {
        $trans = 'Subdivision/Consolidation';
    } elseif (stripos($raw_trans, 'annotation') !== false) {
        $trans = 'Annotation/Cancellation of Annotation';
    } elseif (stripos($raw_trans, 'tmcr') !== false || stripos($raw_trans, 'section map') !== false) {
        $trans = 'TMCR/Section Map/s';
    } elseif (stripos($raw_trans, 'cancellation of td') !== false) {
        $trans = 'Cancellation of TD';
    } elseif (stripos($raw_trans, 'ocular') !== false) {
        $trans = 'Ocular Inspection';
    } elseif (stripos($raw_trans, 'discovery') !== false) {
        $trans = 'Discovery/New Declaration';
    } elseif (stripos($raw_trans, 'certification') !== false || stripos($raw_trans, 'certificate') !== false || stripos($raw_trans, 'ctc') !== false) {
        $trans = 'Certification/CTC/CPC';
    } elseif (stripos($raw_trans, 'verification') !== false || stripos($raw_trans, 'back tracking') !== false) {
        $trans = 'Verification/Back Tracking';
    } elseif (stripos($raw_trans, 'posting') !== false) {
        $trans = 'Posting';
    } elseif (stripos($raw_trans, 'transfer') !== false) {
        $trans = 'Transfer';
    }

    if (!isset($services_tally[$trans])) $services_tally[$trans] = 0;
    $services_tally[$trans]++;

    // CLIENT TYPE NORMALIZATION (Combining Mamamayan & Citizen, Negosyo & Business, Gobyerno & Government)
    $raw_ctype = strtolower(trim($row['client_type'] ?? ''));
    if ($raw_ctype === 'mamamayan' || $raw_ctype === 'citizen') {
        $ctype = 'Citizen (Mamamayan)';
    } elseif ($raw_ctype === 'negosyo' || $raw_ctype === 'business') {
        $ctype = 'Business (Negosyo)';
    } elseif ($raw_ctype === 'gobyerno' || $raw_ctype === 'government') {
        $ctype = 'Government (Gobyerno)';
    } elseif (!empty($raw_ctype)) {
        $ctype = ucfirst($raw_ctype);
    } else {
        $ctype = 'Unspecified';
    }
    if (!isset($client_type_tally[$ctype])) $client_type_tally[$ctype] = 0;
    $client_type_tally[$ctype]++;

    // GENDER NORMALIZATION (Combining Babae & Female, Lalake & Male)
    $raw_gender = strtolower(trim($row['gender'] ?? ''));
    if ($raw_gender === 'babae' || $raw_gender === 'female') {
        $gender = 'Babae (Female)';
    } elseif ($raw_gender === 'lalake' || $raw_gender === 'male') {
        $gender = 'Lalake (Male)';
    } elseif (!empty($raw_gender)) {
        $gender = ucfirst($raw_gender);
    } else {
        $gender = 'Unspecified';
    }
    if (!isset($gender_tally[$gender])) $gender_tally[$gender] = 0;
    $gender_tally[$gender]++;

    $age = isset($row['age_group']) && !empty(trim($row['age_group'])) ? trim($row['age_group']) : 'Unspecified';
    if (!isset($age_tally[$age])) $age_tally[$age] = 0;
    $age_tally[$age]++;

    // PROVINCE
    $prov = isset($row['province']) && !empty(trim($row['province'])) ? trim($row['province']) : 'Unspecified';
    if (!isset($province_tally[$prov])) $province_tally[$prov] = 0;
    $province_tally[$prov]++;

    // MUNICIPALITY
    $muni = isset($row['municipality']) && !empty(trim($row['municipality'])) ? trim($row['municipality']) : 'Outside Isabela';
    if (!isset($municipality_tally[$muni])) $municipality_tally[$muni] = 0;
    $municipality_tally[$muni]++;

    $sdate = isset($row['survey_date']) && !empty(trim($row['survey_date'])) ? trim($row['survey_date']) : 'Unspecified Date';
    if (!isset($date_tally[$sdate])) $date_tally[$sdate] = 0;
    $date_tally[$sdate]++;
}

$master_transaction_types = [
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
foreach ($master_transaction_types as $mtt) {
    if (!isset($services_tally[$mtt])) {
        $services_tally[$mtt] = 0;
    }
}
arsort($services_tally);
arsort($client_type_tally);

$average_rating = $rated_count > 0 ? round($sum_rating / $rated_count, 2) : 0;
$satisfaction_pct = $rated_count > 0 ? round(($positive_rating_count / $rated_count) * 100, 1) : 0;

// Sort Municipalities (all locations preserved for complete reporting)
arsort($municipality_tally);

// Greeting & Philippine Time Calculations
date_default_timezone_set('Asia/Manila');
$current_hour = (int)date('G');
if ($current_hour >= 5 && $current_hour < 12) {
    $greeting_text = "Good morning";
    $greeting_icon = "ph-sun";
} elseif ($current_hour >= 12 && $current_hour < 18) {
    $greeting_text = "Good afternoon";
    $greeting_icon = "ph-sun-dim";
} else {
    $greeting_text = "Good evening";
    $greeting_icon = "ph-moon-stars";
}

$admin_display_name = !empty($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Administrator';

// Today's Submissions Count from Database
$today_submissions = 0;
try {
    $t_stmt = $pdo->query("SELECT COUNT(*) FROM responses WHERE DATE(survey_date) = CURDATE()");
    $today_submissions = (int)$t_stmt->fetchColumn();
} catch (Exception $e) {}

// Active submission dates for mini calendar
$calendar_active_dates = [];
foreach ($date_tally as $dt => $cnt) {
    if (!empty($dt) && $dt !== 'Unspecified Date') {
        $clean_d = substr($dt, 0, 10);
        $calendar_active_dates[$clean_d] = ($calendar_active_dates[$clean_d] ?? 0) + $cnt;
    }
}
$js_active_dates = json_encode($calendar_active_dates);

// ==========================================
// SQD ADVANCED METRICS & ARTA COMPLIANCE
// ==========================================
$sqd_stats = [];
$total_sqd_rated = 0;
$total_sqd_pos = 0;
$total_sqd_neg = 0;

foreach ($sqd_labels as $key => $label) {
    $c5 = $sqd_tally[$key]['5'];
    $c4 = $sqd_tally[$key]['4'];
    $c3 = $sqd_tally[$key]['3'];
    $c2 = $sqd_tally[$key]['2'];
    $c1 = $sqd_tally[$key]['1'];
    $cna = $sqd_tally[$key]['N/A'];
    $rated = $c5 + $c4 + $c3 + $c2 + $c1;
    $positive = $c5 + $c4;
    $negative = $c2 + $c1;
    
    $mean = $rated > 0 ? round((5 * $c5 + 4 * $c4 + 3 * $c3 + 2 * $c2 + 1 * $c1) / $rated, 2) : 0;
    $sat_pct = $rated > 0 ? round(($positive / $rated) * 100, 1) : 0;
    $neg_pct = $rated > 0 ? round(($negative / $rated) * 100, 1) : 0;
    $nsr = round($sat_pct - $neg_pct, 1);
    
    // Rating classification (ARTA Standards)
    if ($sat_pct >= 95) {
        $badge_cls = 'pill-emerald';
        $rating_text = 'Excellent';
    } elseif ($sat_pct >= 90) {
        $badge_cls = 'pill-blue';
        $rating_text = 'Very Satisfactory';
    } elseif ($sat_pct >= 80) {
        $badge_cls = 'pill-amber';
        $rating_text = 'Satisfactory';
    } elseif ($sat_pct >= 60) {
        $badge_cls = 'pill-orange';
        $rating_text = 'Fair';
    } else {
        $badge_cls = 'pill-rose';
        $rating_text = 'Needs Improvement';
    }

    $sqd_stats[$key] = [
        'key' => $key,
        'label' => $label,
        'short' => $sqd_short_labels[$key] ?? $label,
        'arta_name' => $sqd_arta_names[$key] ?? $label,
        '5' => $c5,
        '4' => $c4,
        '3' => $c3,
        '2' => $c2,
        '1' => $c1,
        'na' => $cna,
        'rated' => $rated,
        'mean' => $mean,
        'sat_pct' => $sat_pct,
        'nsr' => $nsr,
        'badge' => $badge_cls,
        'text' => $rating_text,
    ];

    if ($key !== 'sqd0') {
        $total_sqd_rated += $rated;
        $total_sqd_pos += $positive;
        $total_sqd_neg += $negative;
    }
}

// Overall SQD metrics across SQD1-8
$overall_sqd_sat_pct = $total_sqd_rated > 0 ? round(($total_sqd_pos / $total_sqd_rated) * 100, 1) : 0;
$overall_sqd_neg_pct = $total_sqd_rated > 0 ? round(($total_sqd_neg / $total_sqd_rated) * 100, 1) : 0;
$overall_sqd_nsr = round($overall_sqd_sat_pct - $overall_sqd_neg_pct, 1);
if ($overall_sqd_sat_pct >= 95) {
    $overall_sqd_text = 'Outstanding';
} elseif ($overall_sqd_sat_pct >= 90) {
    $overall_sqd_text = 'Very Satisfactory';
} elseif ($overall_sqd_sat_pct >= 80) {
    $overall_sqd_text = 'Satisfactory';
} else {
    $overall_sqd_text = 'Needs Attention';
}

// Top Dimension and Focus Area (excluding SQD0)
$dims_only = array_filter($sqd_stats, function($k) { return $k !== 'sqd0'; }, ARRAY_FILTER_USE_KEY);
uasort($dims_only, function($a, $b) {
    if ($a['sat_pct'] === $b['sat_pct']) {
        return $b['mean'] <=> $a['mean'];
    }
    return $b['sat_pct'] <=> $a['sat_pct'];
});
$top_dimension = !empty($dims_only) ? reset($dims_only) : ['short' => 'N/A', 'sat_pct' => 0, 'mean' => 0];
$bottom_dimension = !empty($dims_only) ? end($dims_only) : ['short' => 'N/A', 'sat_pct' => 0, 'mean' => 0];

// Prepare Radar Data (SQD1-SQD8)
$radar_labels = [];
$radar_means = [];
$radar_sat_pcts = [];
foreach (['sqd1','sqd2','sqd3','sqd4','sqd5','sqd6','sqd7','sqd8'] as $rk) {
    if (isset($sqd_stats[$rk])) {
        $radar_labels[] = $sqd_arta_names[$rk];
        $radar_means[] = $sqd_stats[$rk]['mean'];
        $radar_sat_pcts[] = $sqd_stats[$rk]['sat_pct'];
    }
}
$js_sqd_radar_labels = json_encode($radar_labels);
$js_sqd_radar_means  = json_encode($radar_means);
$js_sqd_radar_sat    = json_encode($radar_sat_pcts);

// Prepare Stacked Horizontal Bar Data (SQD0 to SQD8)
$stacked_labels = [];
$stacked_counts = ['5' => [], '4' => [], '3' => [], '2' => [], '1' => []];
$stacked_pcts   = ['5' => [], '4' => [], '3' => [], '2' => [], '1' => []];

foreach ($sqd_labels as $k => $lbl) {
    $stacked_labels[] = $sqd_short_labels[$k];
    $st = $sqd_stats[$k];
    $tot = $st['rated'] > 0 ? $st['rated'] : 1;
    foreach (['5','4','3','2','1'] as $rating_val) {
        $stacked_counts[$rating_val][] = $st[$rating_val];
        $stacked_pcts[$rating_val][]   = round(($st[$rating_val] / $tot) * 100, 1);
    }
}

$js_sqd_stacked_labels = json_encode($stacked_labels);
$js_sqd_stacked_counts = json_encode($stacked_counts);
$js_sqd_stacked_pcts   = json_encode($stacked_pcts);

// Chart Data Conversion
$js_type_labels   = json_encode(array_keys($client_type_tally));
$js_type_data     = json_encode(array_values($client_type_tally));
$js_gender_labels = json_encode(array_keys($gender_tally));
$js_gender_data   = json_encode(array_values($gender_tally));
$js_age_labels    = json_encode(array_keys($age_tally));
$js_age_data      = json_encode(array_values($age_tally));
$js_prov_labels   = json_encode(array_keys($province_tally));
$js_prov_data     = json_encode(array_values($province_tally));
$js_muni_labels   = json_encode(array_keys($municipality_tally));
$js_muni_data     = json_encode(array_values($municipality_tally));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="pao.ico">
    <title>CSM Admin Dashboard — Isabela</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

    <!-- Phosphor Icons (Threads & Facebook Meta Design System) -->
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
            
            --primary: #047857;
            --primary-light: #ebf5ee;
            --primary-hover: #064e3b;
            
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

        /* ==========================================
           GREEN-FOCUSED PALETTE: remap Bootstrap accent utilities
        =========================================== */
        :root, [data-bs-theme="light"] {
            --bs-primary: #047857;
            --bs-primary-rgb: 4, 120, 87;
            --bs-link-color: #065f46;
            --bs-link-hover-color: #022c22;
            --bs-body-bg: #f8fafc;
            --bs-border-color: #e2e8f0;
        }
        [data-bs-theme="dark"] {
            --bs-primary: #059669;
            --bs-primary-rgb: 5, 150, 105;
            --bs-link-color: #10b981;
            --bs-link-hover-color: #34d399;
            --bs-body-bg: #07110c;
            --bs-border-color: #1b2f24;
        }

        .text-primary { color: #047857 !important; }
        .text-success { color: #047857 !important; }
        .text-warning { color: #65a30d !important; }
        .text-info    { color: #0d9488 !important; }
        main .text-danger { color: #0f766e !important; }
        .text-secondary { color: var(--text-muted) !important; }
        .bg-light { background-color: var(--bg-body) !important; }

        [data-bs-theme="dark"] .text-primary { color: #10b981 !important; }
        [data-bs-theme="dark"] .text-success { color: #10b981 !important; }
        [data-bs-theme="dark"] .text-warning { color: #a3e635 !important; }
        [data-bs-theme="dark"] .text-info    { color: #2dd4bf !important; }
        [data-bs-theme="dark"] main .text-danger { color: #5eead4 !important; }
        [data-bs-theme="dark"] .bg-light { background-color: #12231a !important; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-body);
            overflow-x: hidden;
            letter-spacing: -0.01em;
            -webkit-font-smoothing: antialiased;
        }

        /* SIDEBAR (THREADS / FACEBOOK STYLE) */
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

        /* ICON STYLING: NO BACKGROUND COLOR ON ICONS */
        #sidebar .nav-link i,
        i.ph, i.ph-bold, i.ph-fill {
            background: transparent;
            background-color: transparent;
            box-shadow: none !important;
        }

        #sidebar .nav-link i {
            font-size: 1.35rem;
            line-height: 1;
            color: var(--text-subtle);
            transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.15s ease;
        }

        /* SIDEBAR ROW HOVER BACKGROUND (THREADS / FACEBOOK STYLE) */
        #sidebar .nav-link:hover {
            color: var(--text-heading);
            background-color: #ebf5ee !important;
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

        /* KPI STAT CARDS & ROW: GUARANTEED 1 ROW ACROSS */
        #overviewStatsRow {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 16px;
        }
        #overviewStatsRow > div,
        .stat-kpi-col {
            flex: 1 1 0 !important;
            min-width: 0 !important;
            max-width: 25% !important;
        }

        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-icon {
            width: auto;
            height: auto;
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            line-height: 1;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* FOUR SECTION ICONS: SIGNATURE GRADIENT EFFECT (YELLOW TO GREEN, DOWN TO UP) */
        .stat-icon i {
            background: linear-gradient(to top, #facc15 0%, #84cc16 50%, #047857 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
            display: inline-block;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s ease;
        }

        [data-bs-theme="dark"] .stat-icon i {
            background: linear-gradient(to top, #fde047 0%, #a3e635 50%, #10b981 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.15);
        }

        .stat-card:hover .stat-icon i {
            filter: drop-shadow(0 3px 8px rgba(4, 120, 87, 0.35));
        }

        .stat-val {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: var(--text-subtle);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        .pill-emerald { background-color: #ebf5ee; color: #047857; border: 1px solid #c5dccf; }
        .pill-amber   { background-color: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }
        .pill-blue    { background-color: #ebf5ee; color: #065f46; border: 1px solid #c5dccf; }
        .pill-slate   { background-color: #ebf5ee; color: #2f4a3b; border: 1px solid #dcebe1; }
        .pill-rose    { background-color: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        /* MINIMAL GREETINGS SECTION CARD */
        .card-greetings {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px 26px;
            transition: all 0.2s ease;
            min-height: 380px;
        }

        .greetings-icon-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .greetings-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: -0.01em;
            line-height: 1.25;
        }

        .greetings-subtitle {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .greetings-badge-date {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-body);
            border: 1px solid var(--border-subtle);
            padding: 2px 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            line-height: 1;
        }

        .greetings-badge-live {
            font-size: 0.70rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            line-height: 1;
            background: #ebf5ee;
            color: #047857;
            border: 1px solid #c5dccf;
        }

        .pulse-indicator {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #047857;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(4, 120, 87, 0.7);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(4, 120, 87, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(4, 120, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(4, 120, 87, 0); }
        }

        .greetings-badge-filter {
            font-size: 0.72rem;
            font-weight: 600;
            color: #047857;
            background: rgba(4, 120, 87, 0.08);
            border: 1px solid rgba(4, 120, 87, 0.2);
            padding: 2px 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            line-height: 1;
        }

        .greetings-stat-pill {
            font-size: 0.78rem;
            color: var(--text-heading);
            background: var(--bg-body);
            border: 1px solid var(--border-subtle);
            padding: 5px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            line-height: 1;
        }

        .btn-greetings-action {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            color: var(--text-heading);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: none !important;
            transition: all 0.15s ease;
            line-height: 1;
        }

        .btn-greetings-action:hover {
            background: var(--bg-body);
            border-color: #c5dccf;
            color: var(--primary);
        }

        /* GREEN GREETINGS CARD (Rich forest green gradient, zero background shadow) */
        #greetingsCard {
            background: linear-gradient(135deg, #022c22 0%, #064e3b 50%, #047857 100%) !important;
            border-color: transparent !important;
            color: #ffffff !important;
            box-shadow: none !important;
            min-height: 290px;
            padding: 26px 28px;
        }
        [data-bs-theme="dark"] #greetingsCard {
            background: linear-gradient(135deg, #011812 0%, #022c22 55%, #064e3b 100%) !important;
            border-color: rgba(52, 211, 153, 0.2) !important;
            box-shadow: none !important;
        }

        #greetingsCard .greetings-title,
        #greetingsCard .greetings-title .text-success { color: #ffffff !important; }
        #greetingsCard .greetings-subtitle { color: rgba(255, 255, 255, 0.88) !important; font-size: 0.84rem; }

        #greetingsCard .greetings-icon-wrap i { color: #fef08a !important; font-size: 1.6rem; }
        #greetingsCard .border-top { border-color: rgba(255, 255, 255, 0.2) !important; }

        #greetingsCard .greetings-badge-date,
        #greetingsCard .greetings-badge-filter,
        #greetingsCard .greetings-badge-live,
        #greetingsCard .greetings-stat-pill {
            background: rgba(255, 255, 255, 0.14) !important;
            border-color: rgba(255, 255, 255, 0.24) !important;
            color: #ffffff !important;
        }
        #greetingsCard .greetings-stat-pill strong { color: #ffffff !important; }
        #greetingsCard .greetings-stat-pill i { color: #ffffff !important; }
        #greetingsCard .pulse-indicator { background-color: #ffffff !important; }

        #greetingsCard .btn-greetings-action {
            background: #ffffff !important;
            border-color: #ffffff !important;
            color: #064e3b !important;
            font-weight: 700;
        }
        #greetingsCard .btn-greetings-action i { color: #064e3b !important; }
        #greetingsCard .btn-greetings-action:hover {
            background: #ecfdf5 !important;
            border-color: #ecfdf5 !important;
            color: #022c22 !important;
        }

        /* DIAGRAM CENTER ART (URI NG KLIYENTE & KASARIAN) */
        .gender-center-overlay,
        .type-center-overlay {
            position: absolute;
            top: 42%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gender-center-art-img,
        .type-center-art-img {
            width: 72px;
            height: 72px;
            object-fit: contain;
            filter: none !important;
            box-shadow: none !important;
            background: transparent !important;
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .card-minimal:hover .gender-center-art-img,
        .card-minimal:hover .type-center-art-img {
            transform: scale(1.08);
            filter: none !important;
            box-shadow: none !important;
        }

        /* LUNGSOD / BAYAN MUNICIPALITIES CONTROLS */
        .muni-filter-btn {
            border: 1px solid rgba(16, 185, 129, 0.35);
            background: transparent;
            color: #4b6354;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 6px;
            transition: all 0.15s ease;
            cursor: pointer;
            line-height: 1.2;
        }
        .muni-filter-btn:hover {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
        }
        .muni-filter-btn.active {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: #059669;
            color: #ffffff !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.25);
        }
        [data-bs-theme="dark"] .muni-filter-btn {
            border-color: rgba(52, 211, 153, 0.3);
            color: #a7f3d0;
        }
        [data-bs-theme="dark"] .muni-filter-btn:hover {
            background: rgba(16, 185, 129, 0.2);
            color: #ffffff;
        }
        [data-bs-theme="dark"] .muni-filter-btn.active {
            background: linear-gradient(135deg, #059669, #047857);
            color: #ffffff !important;
        }

        .muni-search-input {
            font-size: 0.72rem;
            padding: 3px 8px 3px 24px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            background-color: var(--card-bg, #ffffff);
            color: var(--text-primary, #1e293b);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .muni-search-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }
        [data-bs-theme="dark"] .muni-search-input {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
        }
        .muni-search-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.75rem;
            color: #64748b;
            pointer-events: none;
        }

        /* GREETINGS SUN ART (TOP-LEFT BADGE DISPLAYED ON RIGHT SIDE) */
        .greetings-sun-art {
            position: absolute;
            right: 0;
            bottom: -5px;
            height: 94%;
            max-height: 285px;
            pointer-events: none;
            user-select: none;
            z-index: 1;
            opacity: 0.88;
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
        }

        .greetings-sun-img {
            height: 100%;
            width: auto;
            object-fit: contain;
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            filter: none !important;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #greetingsCard:hover .greetings-sun-img {
            transform: scale(1.03) rotate(-1deg);
            filter: none !important;
            box-shadow: none !important;
        }

        @media (max-width: 991px) {
            .greetings-sun-art {
                opacity: 0.18;
                right: -25px;
                bottom: -15px;
                height: 70%;
            }
        }

        /* ==========================================================================
           SEAMLESS GRADIENT CARD (GRADIENT FADE TO CANVAS / BOTTOM BLEED)
           Leverages vertical contrast falloff to softly dissolve into the page canvas:
           1. Top surface has crisp contrast & subtle top-edge highlight.
           2. Multi-stop gradient seamlessly blends into the canvas background at the bottom.
           3. Fading border gently dissolves to 0% opacity towards the bottom (no hard cutoff).
           4. Suppressed bottom shadow so the bottom boundary bleeds directly into the canvas.
           ========================================================================== */
        /* ==========================================================================
           SEAMLESS GRADIENT CARDS (GRADIENT FADE TO CANVAS / BOTTOM BLEED / BLUR)
           Applied to:
           - Calendar Section Card (.card-calendar / #calendarCard)
           - Overview KPI Stat Cards (.stat-card)
           - Overview Section Cards (#sec-dashboard .card-minimal)
           
           Leverages vertical contrast falloff to softly dissolve into the page canvas:
           1. Top surface has crisp contrast & subtle top-edge highlight.
           2. Multi-stop gradient seamlessly blends into the canvas background at the bottom.
           3. Fading border gently dissolves to 0% opacity towards the bottom (no hard cutoff).
           4. Suppressed bottom shadow so the bottom boundary bleeds directly into the canvas.
           ========================================================================== */
        .card-calendar,
        .stat-card,
        #sec-dashboard .card-minimal {
            position: relative;
            background: linear-gradient(180deg, 
                #ffffff 0%, 
                #ffffff 28%, 
                rgba(255, 255, 255, 0.85) 55%, 
                rgba(248, 250, 252, 0.90) 80%, 
                #f8fafc 100%) !important;
            border: 1px solid rgba(226, 232, 240, 0.9) !important;
            border-bottom: 1px solid transparent !important;
            border-radius: var(--radius-card);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
            overflow: hidden;
        }

        .card-calendar {
            padding: 24px 26px;
            min-height: 380px;
        }

        /* Border fade-out trick: 1px border that dissolves towards 0% at the bottom */
        .card-calendar::before,
        .stat-card::before,
        #sec-dashboard .card-minimal::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(180deg, 
                rgba(226, 232, 240, 0.95) 0%, 
                rgba(226, 232, 240, 0.65) 45%, 
                rgba(226, 232, 240, 0.15) 80%, 
                transparent 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            z-index: 1;
        }

        /* Ensure all interactive card content sits cleanly above gradient masks */
        .card-calendar > *,
        .stat-card > *,
        #sec-dashboard .card-minimal > * {
            position: relative;
            z-index: 2;
        }

        /* Tables inside overview cards: remove bottom border on last row so it bleeds seamlessly */
        #sec-dashboard .card-minimal .table tr:last-child td,
        #sec-dashboard .card-minimal .table tr:last-child th {
            border-bottom: none !important;
        }

        /* Soft gradient divider for calendar footer so it dissolves naturally */
        .card-calendar .calendar-footer {
            border-top: none !important;
            position: relative;
            padding-top: 10px;
        }
        .card-calendar .calendar-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 5%;
            right: 5%;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(226, 232, 240, 0.8) 25%, 
                rgba(226, 232, 240, 0.8) 75%, 
                transparent 100%);
        }

        /* DARK MODE: Seamless Fade to Dark Canvas (#07110c) */
        [data-bs-theme="dark"] .card-calendar,
        [data-bs-theme="dark"] .stat-card,
        [data-bs-theme="dark"] #sec-dashboard .card-minimal {
            background: linear-gradient(180deg, 
                #11231a 0%, 
                #0e1e16 30%, 
                rgba(13, 26, 19, 0.82) 60%, 
                rgba(7, 17, 12, 0.92) 84%, 
                #07110c 100%) !important;
            border: 1px solid rgba(27, 47, 36, 0.85) !important;
            border-bottom: 1px solid transparent !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.06) !important;
        }

        [data-bs-theme="dark"] .card-calendar::before,
        [data-bs-theme="dark"] .stat-card::before,
        [data-bs-theme="dark"] #sec-dashboard .card-minimal::before {
            background: linear-gradient(180deg, 
                rgba(35, 62, 48, 0.95) 0%, 
                rgba(27, 47, 36, 0.6) 45%, 
                rgba(27, 47, 36, 0.15) 80%, 
                transparent 100%);
        }

        [data-bs-theme="dark"] .card-calendar .calendar-footer::before {
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(27, 47, 36, 0.75) 25%, 
                rgba(27, 47, 36, 0.75) 75%, 
                transparent 100%);
        }

        /* Subtle bottom border dissolve for greetingsCard in dark mode */
        [data-bs-theme="dark"] #greetingsCard {
            border-bottom: 1px solid transparent !important;
        }
        [data-bs-theme="dark"] #greetingsCard::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(180deg, 
                rgba(52, 211, 153, 0.35) 0%, 
                rgba(52, 211, 153, 0.15) 45%, 
                rgba(52, 211, 153, 0.05) 80%, 
                transparent 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            z-index: 1;
        }

        .calendar-month-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: -0.01em;
        }

        .cal-nav-btn {
            background: transparent;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.82rem;
            cursor: pointer;
            box-shadow: none !important;
            transition: all 0.15s ease;
            padding: 0;
        }

        .cal-nav-btn:hover {
            background: var(--bg-body);
            color: var(--text-heading);
            border-color: #c5dccf;
        }

        .cal-time-badge {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-body);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 4px 9px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            line-height: 1;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
            padding: 4px 0;
        }

        .calendar-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: 1fr;
            gap: 6px;
            text-align: center;
        }

        .cal-cell {
            min-height: 44px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 8px;
            color: var(--text-heading);
            position: relative;
            cursor: default;
            user-select: none;
            transition: all 0.15s ease;
        }

        .cal-cell.other-month {
            color: var(--text-subtle);
            opacity: 0.35;
            font-weight: 400;
        }

        .cal-cell.today {
            background-color: var(--primary);
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(4, 120, 87, 0.35);
        }

        .cal-cell:hover:not(.today):not(.other-month) {
            background-color: rgba(4, 120, 87, 0.08);
            color: var(--primary);
        }
        [data-bs-theme="dark"] .cal-cell:hover:not(.today):not(.other-month) {
            background-color: rgba(16, 185, 129, 0.12);
            color: #34d399;
        }

        .cal-cell .cal-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #10b981;
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
        }

        .cal-cell.today .cal-dot {
            background-color: #ffffff;
        }

        .cal-legend-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .cal-legend-today { background-color: var(--primary); }
        .cal-legend-active { background-color: #10b981; }

        /* DARK MODE SUPPORT FOR GREETINGS */
        [data-bs-theme="dark"] .card-greetings {
            background: var(--bg-surface);
            border-color: var(--border-subtle);
        }

        [data-bs-theme="dark"] .greetings-badge-date,
        [data-bs-theme="dark"] .greetings-stat-pill,
        [data-bs-theme="dark"] .cal-nav-btn,
        [data-bs-theme="dark"] .cal-time-badge {
            background: #12231a;
            border-color: var(--border-subtle);
            color: #8fa89a;
        }

        [data-bs-theme="dark"] .greetings-stat-pill strong {
            color: #ebf5ee;
        }

        [data-bs-theme="dark"] .greetings-badge-live {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.3);
        }

        [data-bs-theme="dark"] .greetings-badge-filter {
            background: rgba(52, 211, 153, 0.15);
            color: #6ee7b7;
            border-color: rgba(52, 211, 153, 0.3);
        }

        [data-bs-theme="dark"] .btn-greetings-action {
            background: #12231a;
            border-color: var(--border-subtle);
            color: #c5dccf;
        }

        [data-bs-theme="dark"] .btn-greetings-action:hover,
        [data-bs-theme="dark"] .cal-nav-btn:hover {
            background: #16291f;
            border-color: #4a6657;
            color: #ffffff;
        }

        [data-bs-theme="dark"] .cal-cell:hover:not(.today):not(.other-month) {
            background-color: #16291f;
        }

        [data-bs-theme="dark"] .cal-cell.today {
            background-color: #059669;
            color: #ffffff !important;
        }

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
        }
        .btn-modern-outline:hover {
            background-color: var(--border-light);
            color: var(--text-heading);
            border-color: #c5dccf;
        }

        /* TABLES */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            width: 100% !important;
        }

        table.dataTable thead th {
            background-color: #f4faf6 !important;
            color: var(--text-muted) !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 12px 14px !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            border-top: none !important;
        }

        table.dataTable tbody td {
            padding: 13px 14px !important;
            border-bottom: 1px solid var(--border-light) !important;
            font-size: 0.86rem;
            color: var(--text-body);
            vertical-align: middle;
        }

        table.dataTable tbody tr:hover td {
            background-color: #f4faf6;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: white !important;
            border-color: var(--primary) !important;
            border-radius: 8px;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.85rem;
            outline: none;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        /* CHARTS */
        .chart-container-minimal {
            position: relative;
            height: 230px;
            width: 100%;
        }

        /* SUGGESTIONS AUTO SLIDER */
        .suggestions-slider-wrapper {
            position: relative;
            height: 230px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .suggestion-slide-card,
        .sqd-kpi-inner {
            background-color: #f4faf6;
            border: 1px solid #ebf5ee;
            border-radius: 14px;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
        }

        .suggestion-slide-card {
            height: 195px;
        }

        .sqd-kpi-inner {
            min-height: 175px;
            height: 100%;
        }

        .suggestion-slide-card:hover,
        .sqd-kpi-inner:hover {
            border-color: #dcebe1;
            background-color: #f4faf6;
        }

        .sqd-kpi-inner:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        [data-bs-theme="dark"] .sqd-kpi-inner,
        [data-theme="dark"] .sqd-kpi-inner,
        html.dark .sqd-kpi-inner {
            background-color: #12231a !important;
            border-color: var(--border-subtle) !important;
        }

        [data-bs-theme="dark"] .sqd-kpi-inner:hover,
        [data-theme="dark"] .sqd-kpi-inner:hover,
        html.dark .sqd-kpi-inner:hover {
            border-color: #2f4a3b !important;
            background-color: #172b21 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .sqd-kpi-val {
            font-size: 1.85rem;
            line-height: 1;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        .sqd-kpi-title {
            font-size: 1.05rem;
            line-height: 1.3;
            font-weight: 700;
            color: var(--text-heading);
        }

        .sqd-kpi-desc {
            font-size: 0.78rem;
            line-height: 1.45;
            color: var(--text-muted);
        }

        .sqd-kpi-footer {
            border-top: 1px solid #ebf2ed;
            padding-top: 8px;
        }

        [data-bs-theme="dark"] .sqd-kpi-val,
        [data-theme="dark"] .sqd-kpi-val,
        html.dark .sqd-kpi-val,
        [data-bs-theme="dark"] .sqd-kpi-title,
        [data-theme="dark"] .sqd-kpi-title,
        html.dark .sqd-kpi-title {
            color: var(--text-heading) !important;
        }

        [data-bs-theme="dark"] .sqd-kpi-footer,
        [data-theme="dark"] .sqd-kpi-footer,
        html.dark .sqd-kpi-footer {
            border-top-color: #1a3324 !important;
        }

        .suggestion-quote-text {
            font-size: 0.90rem;
            line-height: 1.55;
            font-weight: 500;
            color: var(--text-heading);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 6px 0;
        }

        .carousel-btn-clean {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.9rem;
            padding: 3px 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .carousel-btn-clean:hover {
            color: var(--text-heading);
            background-color: #ebf5ee;
        }

        .carousel-indicators.carousel-dots-clean {
            position: static;
            margin: 8px 0 0 0;
            padding: 0;
            justify-content: center;
            gap: 6px;
        }

        .carousel-indicators.carousel-dots-clean [data-bs-target] {
            box-sizing: content-box;
            flex: 0 1 auto;
            width: 6px;
            height: 6px;
            padding: 0;
            margin: 0;
            text-indent: -999px;
            cursor: pointer;
            background-color: #c5dccf;
            background-clip: padding-box;
            border: none;
            border-radius: 999px;
            opacity: 0.6;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-indicators.carousel-dots-clean .active {
            width: 20px;
            background-color: var(--primary);
            opacity: 1;
        }

        /* ==========================================
           OVERVIEW MOTION: MODERN TRANSFORM TRANSITIONS
           - Staggered rise + scale entrance (replays on tab return)
           - Spring-like hover lift
           - fill-mode "backwards" so hover transforms still work after entrance
        =========================================== */
        .ov-card {
            transition:
                transform 0.45s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.35s ease,
                border-color 0.3s ease,
                background-color 0.3s ease;
            transform-origin: 50% 100%;
            backface-visibility: hidden;
        }

        .ov-card.ov-animate {
            animation: ovRise 0.7s cubic-bezier(0.22, 1, 0.36, 1) backwards;
            animation-delay: calc(var(--ov-i, 0) * 70ms);
        }

        @keyframes ovRise {
            0%   { opacity: 0; transform: translate3d(0, 28px, 0) scale(0.96); filter: blur(4px); }
            60%  { filter: blur(0); }
            100% { opacity: 1; transform: translate3d(0, 0, 0) scale(1); filter: blur(0); }
        }

        .ov-card:hover {
            transform: translate3d(0, -5px, 0);
        }

        .ov-card:active {
            transform: translate3d(0, -2px, 0) scale(0.995);
            transition-duration: 0.12s;
        }

        .stat-card.ov-card:hover,
        .card-minimal.ov-card:hover,
        .card-calendar.ov-card:hover {
            box-shadow: 0 10px 24px -6px rgba(0, 0, 0, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
        }

        [data-bs-theme="dark"] .stat-card.ov-card:hover,
        [data-bs-theme="dark"] .card-minimal.ov-card:hover,
        [data-bs-theme="dark"] .card-calendar.ov-card:hover {
            box-shadow: 0 10px 24px -6px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
        }

        /* Inner element choreography */
        .ov-card .stat-val {
            display: inline-block;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-origin: left center;
        }
        .ov-card:hover .stat-val {
            transform: scale(1.06);
        }
        .stat-card.ov-card:hover .stat-icon {
            transform: scale(1.15) rotate(-6deg);
        }

        .ov-card .cal-cell {
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.15s ease;
        }
        .ov-card .cal-cell:not(.other-month):hover {
            transform: scale(1.18);
        }

        .ov-card .btn-greetings-action,
        .ov-card .greetings-stat-pill {
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .ov-card .btn-greetings-action:hover,
        .ov-card .greetings-stat-pill:hover {
            transform: translate3d(0, -2px, 0);
        }

        /* Calendar month change: grid swaps with a soft slide */
        .calendar-days-grid.cal-swap-next { animation: calSlideNext 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        .calendar-days-grid.cal-swap-prev { animation: calSlidePrev 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        @keyframes calSlideNext {
            from { opacity: 0; transform: translate3d(18px, 0, 0); }
            to   { opacity: 1; transform: translate3d(0, 0, 0); }
        }
        @keyframes calSlidePrev {
            from { opacity: 0; transform: translate3d(-18px, 0, 0); }
            to   { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .ov-card.ov-animate,
            .calendar-days-grid.cal-swap-next,
            .calendar-days-grid.cal-swap-prev { animation: none; }
            .ov-card, .ov-card:hover, .ov-card:active,
            .ov-card:hover .stat-val { transform: none; transition: none; }
        }

        /* TAB SECTIONS */
        .content-section {
            display: none;
            animation: modernFadeIn 0.25s ease-out forwards;
        }
        .content-section.active {
            display: block;
        }
        @keyframes modernFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* CHATBOT (META AI MINIMALIST FLOATING ICON) */
        .chat-fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #0b2418;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 8px 24px -4px rgba(11, 36, 24, 0.35);
            cursor: pointer;
            z-index: 1050;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .chat-fab:hover {
            transform: scale(1.1);
            background: #16291f;
        }

        .chat-modal-minimal {
            position: fixed;
            bottom: 84px;
            right: 24px;
            width: 360px;
            height: 480px;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            box-shadow: 0 20px 40px -8px rgba(0, 0, 0, 0.12);
            display: none;
            flex-direction: column;
            z-index: 1050;
            overflow: hidden;
        }

        .chat-modal-header {
            padding: 16px 20px;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-body-minimal {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background: #f7fcf9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-bubble {
            max-width: 82%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 0.85rem;
            line-height: 1.45;
        }
        .chat-bubble.bot {
            align-self: flex-start;
            background: #ffffff;
            color: var(--text-body);
            border: 1px solid var(--border-light);
            border-bottom-left-radius: 4px;
        }
        .chat-bubble.user {
            align-self: flex-end;
            background: #0b2418;
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }

        .chat-footer-minimal {
            padding: 12px 16px;
            background: var(--bg-surface);
            border-top: 1px solid var(--border-light);
            display: flex;
            gap: 8px;
        }
        .chat-input-minimal {
            flex: 1;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 0.85rem;
            outline: none;
        }
        .chat-input-minimal:focus {
            border-color: #0b2418;
        }
        .chat-send-btn {
            background: #0b2418;
            color: white;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
        }

        /* CUSTOM SCROLLBAR */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c5dccf; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #8fa89a; }
    </style>
</head>
<body>

<!-- SIDEBAR (THREADS / FACEBOOK STYLE WITH CLEAN ICONS) -->
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

    <!-- Navigation List (Threads / Facebook Style Active Switching) -->
    <p class="sidebar-section-title">Analytics & Insights</p>
    <a class="nav-link active" data-target="sec-dashboard">
        <i class="ph-fill ph-squares-four"></i> Overview
    </a>
    <a class="nav-link" data-target="sec-sqd">
        <i class="ph-bold ph-chart-bar"></i> SQD Breakdown
    </a>
    <a class="nav-link" data-target="sec-demo-summary">
        <i class="ph-bold ph-chart-pie-slice"></i> Demographics
    </a>

    <p class="sidebar-section-title mt-3">Survey Records</p>
    <a class="nav-link" data-target="sec-master-list">
        <i class="ph-bold ph-table"></i> Master List & CC
    </a>
    <a class="nav-link" data-target="sec-demo-records">
        <i class="ph-bold ph-users-three"></i> Client Records
    </a>
    <a class="nav-link" data-target="sec-suggestions">
        <i class="ph-bold ph-heart"></i> Feedback & Suggestions
    </a>

    <p class="sidebar-section-title mt-3">Actions & Tools</p>
    <a class="nav-link" data-target="sec-export">
        <i class="ph-bold ph-file-arrow-down"></i> Export & Reports
    </a>
    <a href="index.php" target="_blank" class="nav-link">
        <i class="ph-bold ph-arrow-square-out"></i> Open Survey Form
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
            <button id="sidebarToggle" class="btn btn-sm btn-light border p-2 rounded-3 text-muted">
                <i class="ph-bold ph-list fs-5"></i>
            </button>
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">Client Satisfaction Measurement</h5>
                <span class="text-muted small">Office of the Provincial Assessor — Province of Isabela</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Solo Month Filter Choices -->
            <form method="GET" action="dashboard.php" class="d-flex align-items-center gap-1.5 bg-light p-1 px-2.5 rounded-3 border">
                <i class="ph-bold ph-calendar-blank text-muted small ms-1"></i>
                <select name="month" id="monthFilter" onchange="this.form.submit()" class="form-select form-select-sm border-0 bg-transparent fw-semibold text-dark pe-4" style="font-size: 0.82rem; cursor: pointer; box-shadow: none; width: auto;" title="Select Month">
                    <option value="">Lahat ng Buwan (All Months)</option>
                    <?php foreach ($available_months as $ym => $m_label): ?>
                        <option value="<?php echo $ym; ?>" <?php echo ($selected_month === $ym) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($selected_month)): ?>
                    <a href="dashboard.php" class="btn btn-sm btn-light py-1 px-2 text-muted" title="Clear Filter"><i class="ph-bold ph-x"></i></a>
                <?php endif; ?>
            </form>

            <!-- Theme Toggle Button -->
            <button type="button" class="theme-toggle-btn" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
                <i class="ph-bold ph-moon fs-5 theme-icon-moon"></i>
                <i class="ph-bold ph-sun fs-5 theme-icon-sun d-none"></i>
            </button>

            <a href="index.php" target="_blank" class="btn btn-sm btn-modern-outline d-none d-sm-inline-flex align-items-center gap-1">
                <i class="ph-bold ph-arrow-up-right"></i> Form
            </a>
            <a href="logout.php" class="btn btn-sm btn-light border text-danger" title="Logout">
                <i class="ph-bold ph-sign-out fs-6"></i>
            </a>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="container-fluid px-4 py-4">

        <!-- ==========================================
             OVERVIEW HEADER ROW (GREETINGS ON LEFT, EXPANDED CALENDAR ON RIGHT)
        =========================================== -->
        <div class="row g-3 mb-4 align-items-stretch" id="overviewHeaderRow">
            <!-- LEFT: GREETINGS SECTION CARD (EXPANDED & GREEN) -->
            <div class="col-xl-8 col-lg-7">
                <div class="card-greetings h-100 d-flex flex-column justify-content-between position-relative overflow-hidden" id="greetingsCard">
                    <!-- Top-Left Sun Art Badge displayed on the Right Side -->
                    <div class="greetings-sun-art" aria-hidden="true">
                        <img src="greetings_sun_topleft.png" alt="" class="greetings-sun-img">
                    </div>

                    <div class="position-relative" style="z-index: 2;">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="greetings-icon-wrap">
                                    <i class="ph-bold <?php echo $greeting_icon; ?> fs-3"></i>
                                </div>
                                <h4 class="greetings-title mb-0">
                                    <?php echo $greeting_text; ?>, <span class="text-success"><?php echo $admin_display_name; ?></span>
                                </h4>
                                <span class="greetings-badge-date">
                                    <?php echo date('l, F j, Y'); ?>
                                </span>
                                <?php if (!empty($selected_month) && isset($available_months[$selected_month])): ?>
                                <span class="greetings-badge-filter">
                                    <i class="ph-bold ph-funnel me-1"></i><?php echo htmlspecialchars($available_months[$selected_month]); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="greetings-badge-live">
                                <span class="pulse-indicator"></span> Live CSM Portal
                            </span>
                        </div>
                        <p class="greetings-subtitle mb-0" style="max-width: 75%;">
                            Client Satisfaction Measurement & Citizen Feedback · Provincial Assessor's Office
                        </p>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-3 mt-3 border-top position-relative" style="z-index: 2;">
                        <!-- Quick Stats Pills -->
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="greetings-stat-pill" title="Mga sagot ngayong araw">
                                <i class="ph-bold ph-calendar-check"></i>
                                <span><strong><?php echo number_format($today_submissions); ?></strong> today</span>
                            </span>
                            <span class="greetings-stat-pill" title="Overall Satisfaction Rate">
                                <i class="ph-bold ph-seal-check"></i>
                                <span><strong><?php echo $satisfaction_pct; ?>%</strong> satisfaction</span>
                            </span>
                            <span class="greetings-stat-pill" title="Kabuuang tugon (Total Records)">
                                <i class="ph-bold ph-users-three"></i>
                                <span><strong><?php echo number_format($total_clients); ?></strong> total</span>
                            </span>
                        </div>

                        <!-- Minimal Action Links -->
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="admin_bulk.php" class="btn-greetings-action" title="Bulk Auto-Encoder">
                                <i class="ph-bold ph-plus-circle"></i> Bulk Encode
                            </a>
                            <a href="print_report.php" target="_blank" class="btn-greetings-action" title="Printable CSM Report">
                                <i class="ph-bold ph-printer"></i> Print
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: EXPANDED CALENDAR SECTION CARD -->
            <div class="col-xl-4 col-lg-5">
                <div class="card-calendar h-100 d-flex flex-column justify-content-between" id="calendarCard">
                    <!-- Calendar Header -->
                    <div class="calendar-header d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ph-bold ph-calendar-blank text-success fs-5"></i>
                            <span class="calendar-month-title fw-bold" id="calMonthYear"></span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="cal-time-badge" id="calLiveClock" title="Philippine Standard Time">
                                <i class="ph-bold ph-clock text-primary"></i> --:--
                            </span>
                            <button type="button" class="cal-nav-btn" id="calPrevBtn" title="Previous Month" aria-label="Previous Month">
                                <i class="ph-bold ph-caret-left"></i>
                            </button>
                            <button type="button" class="cal-nav-btn" id="calTodayBtn" title="Jump to Today" aria-label="Current Month">
                                <i class="ph-bold ph-calendar-check"></i>
                            </button>
                            <button type="button" class="cal-nav-btn" id="calNextBtn" title="Next Month" aria-label="Next Month">
                                <i class="ph-bold ph-caret-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Weekday Headers -->
                    <div class="calendar-weekdays mb-1">
                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                    </div>

                    <!-- Days Grid -->
                    <div class="calendar-days-grid" id="calDaysGrid"></div>

                    <!-- Footer / Legend -->
                    <div class="calendar-footer pt-2 mt-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="cal-legend-dot cal-legend-today"></span> Today
                            </span>
                            <span class="d-inline-flex align-items-center gap-1 ms-1">
                                <span class="cal-legend-dot cal-legend-active"></span> Responses
                            </span>
                        </div>
                        <span class="cal-today-text text-muted small fw-semibold" id="calTodayLabel"><?php echo date('D, M j'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             KPI STATS BAR (4 CARDS IN 1 ROW ACROSS)
        =========================================== -->
        <div class="row g-3 mb-4" id="overviewStatsRow">
            <!-- 1. Feedback Suggestions (Moved to Left) -->
            <div class="col-xl-3 col-lg-3 col-md-3 col-3 stat-kpi-col">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="stat-label">Feedback Suggestions</span>
                        <div class="stat-icon text-danger">
                            <i class="ph-bold ph-chat-teardrop-dots"></i>
                        </div>
                    </div>
                    <div class="stat-val"><?php echo number_format($written_suggestions_count); ?></div>
                    <div class="stat-sub mt-2">
                        <a href="#" class="text-decoration-none text-danger small fw-semibold" onclick="$('[data-target=\'sec-suggestions\']').trigger('click'); return false;">
                            Review comments <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Total Submissions -->
            <div class="col-xl-3 col-lg-3 col-md-3 col-3 stat-kpi-col">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="stat-label">Total Submissions</span>
                        <div class="stat-icon text-success">
                            <i class="ph-bold ph-users-four"></i>
                        </div>
                    </div>
                    <div class="stat-val"><?php echo number_format($total_clients); ?></div>
                    <div class="stat-sub mt-2">
                        <i class="ph-bold ph-clock-counter-clockwise text-success me-1"></i>
                        <?php 
                        if (!empty($selected_month) && isset($available_months[$selected_month])) {
                            echo "Filtered by " . htmlspecialchars($available_months[$selected_month]);
                        } elseif (!empty($start_date) || !empty($end_date)) {
                            echo "Filtered date range";
                        } else {
                            echo "All-time responses";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- 3. Average Score -->
            <div class="col-xl-3 col-lg-3 col-md-3 col-3 stat-kpi-col">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="stat-label">Average Score</span>
                        <div class="stat-icon text-warning">
                            <i class="ph-fill ph-star"></i>
                        </div>
                    </div>
                    <div class="stat-val">
                        <?php echo number_format($average_rating, 2); ?>
                        <span class="text-muted fw-normal fs-6">/ 5.0</span>
                    </div>
                    <div class="stat-sub mt-2">
                        <span class="small fw-semibold text-warning">SQD0 Overall Mean</span>
                    </div>
                </div>
            </div>

            <!-- 4. Satisfaction Rate -->
            <div class="col-xl-3 col-lg-3 col-md-3 col-3 stat-kpi-col">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="stat-label">Satisfaction Rate</span>
                        <div class="stat-icon text-primary">
                            <i class="ph-fill ph-seal-check"></i>
                        </div>
                    </div>
                    <div class="stat-val"><?php echo $satisfaction_pct; ?>%</div>
                    <div class="stat-sub mt-2">
                        <span class="small fw-semibold text-primary">Rated 4 or 5 stars</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 1: DASHBOARD OVERVIEW
        =========================================== -->
        <div id="sec-dashboard" class="content-section active">
            <!-- 3 Top Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card-minimal h-100">
                        <div class="card-header-clean">
                            <h6 class="card-title-clean">Uri ng Kliyente</h6>
                            <span class="small fw-semibold text-muted d-flex align-items-center"><i class="ph-bold ph-user me-1"></i> Client Type</span>
                        </div>
                        <div class="chart-container-minimal position-relative" id="typeChartContainer">
                            <canvas id="chartType"></canvas>
                            <div class="type-center-overlay" aria-hidden="true">
                                <img src="client_type_center_art.png" alt="" class="type-center-art-img">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card-minimal h-100">
                        <div class="card-header-clean">
                            <h6 class="card-title-clean">Kasarian (Gender)</h6>
                            <span class="small fw-semibold text-muted d-flex align-items-center"><i class="ph-bold ph-gender-intersex me-1"></i> Demographics</span>
                        </div>
                        <div class="chart-container-minimal position-relative" id="genderChartContainer">
                            <canvas id="chartGender"></canvas>
                            <div class="gender-center-overlay" aria-hidden="true">
                                <img src="kasarian_center_art.png" alt="" class="gender-center-art-img">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="card-minimal h-100">
                        <div class="card-header-clean">
                            <h6 class="card-title-clean">Edad (Age Group)</h6>
                            <span class="small fw-semibold text-muted d-flex align-items-center"><i class="ph-bold ph-users me-1"></i> Distribution</span>
                        </div>
                        <div class="chart-container-minimal">
                            <canvas id="chartAge"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggestions & Geographic Distribution -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <?php 
                            $filtered_suggestions = [];
                            foreach ($responses as $row) {
                                $sug_val = $row['suggestions'] ?? '';
                                if (is_valid_suggestion($sug_val)) {
                                    $filtered_suggestions[] = $row;
                                }
                            }
                        ?>
                        <div class="card-header-clean mb-2">
                            <div>
                                <h6 class="card-title-clean">
                                    <i class="ph-bold ph-chat-teardrop-text me-2 text-primary"></i>Mga Mungkahi (Suggestions)
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Auto-moving client feedback & notes</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="pill-badge pill-emerald"><?php echo count($filtered_suggestions); ?> feedback</span>
                                <?php if (count($filtered_suggestions) > 1): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <button class="carousel-btn-clean" type="button" data-bs-target="#suggestionsCarousel" data-bs-slide="prev" title="Previous suggestion">
                                        <i class="ph-bold ph-caret-left"></i>
                                    </button>
                                    <button class="carousel-btn-clean" type="button" data-bs-target="#suggestionsCarousel" data-bs-slide="next" title="Next suggestion">
                                        <i class="ph-bold ph-caret-right"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                                <a href="#" onclick="$('[data-target=\'sec-suggestions\']').trigger('click'); return false;" class="text-decoration-none small text-muted d-none d-sm-inline-flex align-items-center" title="View all suggestions table">
                                    <i class="ph-bold ph-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>

                        <?php if (empty($filtered_suggestions)): ?>
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted py-4" style="height: 230px;">
                                <i class="ph-bold ph-chat-circle-dots text-muted mb-2" style="font-size: 2rem; opacity: 0.35;"></i>
                                <div class="small fw-semibold text-secondary">Walang Mungkahi (No Suggestions)</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Responses with 'None' or empty are excluded</div>
                            </div>
                        <?php else: ?>
                            <div id="suggestionsCarousel" class="carousel slide carousel-fade suggestions-slider-wrapper" data-bs-ride="carousel" data-bs-interval="4000" data-bs-pause="hover">
                                <div class="carousel-inner h-100">
                                    <?php foreach ($filtered_suggestions as $i => $item): 
                                        $sug_text = trim($item['suggestions']);
                                        $ctrl = $item['control_number'] ?? 'N/A';
                                        $date = $item['survey_date'] ?? '';
                                        $rating = $item['sqd0'] ?? null;
                                        $email = trim($item['email'] ?? '');
                                        $ctype = $item['client_type'] ?? '';
                                    ?>
                                    <div class="carousel-item h-100 <?php echo $i === 0 ? 'active' : ''; ?>">
                                        <div class="suggestion-slide-card">
                                            <!-- Top Meta Row -->
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <span class="pill-badge pill-emerald" style="font-size: 0.72rem; padding: 2px 8px;">
                                                        <?php echo htmlspecialchars($ctrl); ?>
                                                    </span>
                                                    <?php if(!empty($ctype)): ?>
                                                    <span class="pill-badge pill-slate" style="font-size: 0.7rem; padding: 2px 7px;">
                                                        <?php echo htmlspecialchars($ctype); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if($rating !== null && $rating !== ''): ?>
                                                    <span class="small fw-bold text-amber d-flex align-items-center" style="font-size: 0.76rem;">
                                                        <i class="ph-fill ph-star me-1 text-amber"></i> <?php echo htmlspecialchars($rating); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if(!empty($date)): ?>
                                                    <span class="text-muted small" style="font-size: 0.73rem;">
                                                        <i class="ph-bold ph-calendar-blank me-1"></i><?php echo htmlspecialchars($date); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Main Quote Content -->
                                            <div class="suggestion-quote-text">
                                                <i class="ph-bold ph-quotes text-primary me-1" style="font-size: 1.1rem; opacity: 0.45;"></i>
                                                “<?php echo htmlspecialchars($sug_text); ?>”
                                            </div>

                                            <!-- Bottom Details Row -->
                                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-light">
                                                <div>
                                                    <?php if(!empty($email)): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="text-decoration-none text-muted small d-flex align-items-center" style="font-size: 0.73rem;">
                                                        <i class="ph-bold ph-envelope me-1 text-primary"></i><?php echo htmlspecialchars($email); ?>
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="text-muted small d-flex align-items-center" style="font-size: 0.72rem;">
                                                        <i class="ph-bold ph-seal-check text-success me-1"></i>Verified respondent
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">
                                                    <?php echo ($i + 1) . ' of ' . count($filtered_suggestions); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Minimal Dots Indicators (Bottom) -->
                                <?php if (count($filtered_suggestions) > 1): ?>
                                <div class="carousel-indicators carousel-dots-clean">
                                    <?php foreach ($filtered_suggestions as $i => $item): ?>
                                    <button type="button" data-bs-target="#suggestionsCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-current="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="card-title-clean mb-0">
                                    <i class="ph-bold ph-buildings me-2 text-success"></i>Lungsod/Bayan (Municipalities)
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Respondent distribution across Isabela locations</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Filter View Toggle Buttons -->
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" id="btnMuniAll" class="muni-filter-btn active">All (<?php echo count($municipality_tally); ?>)</button>
                                    <button type="button" id="btnMuniTop10" class="muni-filter-btn">Top 10</button>
                                </div>
                                <!-- Quick Search Input -->
                                <div class="position-relative" style="width: 130px;">
                                    <i class="ph-bold ph-magnifying-glass muni-search-icon"></i>
                                    <input type="text" id="muniQuickSearch" class="muni-search-input w-100" placeholder="Search town...">
                                </div>
                                <span id="muniBadgeCount" class="pill-badge pill-emerald"><?php echo count($municipality_tally); ?> towns</span>
                                <a href="#" onclick="$('[data-target=\'sec-demographics\']').trigger('click'); return false;" class="text-decoration-none small text-muted d-none d-sm-inline-flex align-items-center" title="View all municipalities breakdown">
                                    <i class="ph-bold ph-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                        <div id="muniScrollContainer" class="flex-grow-1" style="min-height: 250px; height: 250px; max-height: 250px; overflow-y: auto; overflow-x: hidden; position: relative; padding-right: 4px;">
                            <div id="muniCanvasWrapper" style="position: relative; width: 100%;">
                                <canvas id="chartMuni"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Data Tables -->
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card-minimal h-100">
                        <div class="card-header-clean">
                            <h6 class="card-title-clean"><i class="ph-bold ph-briefcase me-2 text-success"></i>Services Availed Total</h6>
                            <span class="small text-muted"><?php echo count($services_tally); ?> types</span>
                        </div>
                        <div class="table-responsive">
                            <table id="servicesTable" class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Transaction Type</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services_tally as $service_name => $count): ?>
                                    <tr>
                                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($service_name); ?></td>
                                        <td class="text-end"><span class="pill-badge pill-slate"><?php echo number_format($count); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-minimal h-100">
                        <div class="card-header-clean">
                            <h6 class="card-title-clean"><i class="ph-bold ph-calendar-check me-2 text-primary"></i>Submissions by Date</h6>
                            <span class="small text-muted">Recent Days</span>
                        </div>
                        <div class="table-responsive">
                            <table id="tallyDate" class="table align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Total Submissions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($date_tally as $date_val => $count): ?>
                                    <tr>
                                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($date_val); ?></td>
                                        <td class="text-end"><span class="pill-badge pill-emerald"><?php echo number_format($count); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
        <!-- ==========================================
             SECTION 2: SQD BREAKDOWN & VISUAL ANALYTICS
        =========================================== -->
        <div id="sec-sqd" class="content-section">
            <!-- Top KPI Cards Row (Referenced from Feedback Suggestions UI) -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2">
                            <div>
                                <h6 class="card-title-clean">
                                    <i class="ph-bold ph-shield-check me-2 text-success"></i>Overall SQD
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">ARTA Quality Benchmark</span>
                            </div>
                            <span class="pill-badge pill-emerald"><?php echo $overall_sqd_text; ?></span>
                        </div>
                        <div class="sqd-kpi-inner flex-grow-1">
                            <!-- Top Meta Row -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="pill-badge pill-emerald" style="font-size: 0.72rem; padding: 2px 8px;">
                                        <i class="ph-bold ph-check-circle me-1"></i>SQD1–8
                                    </span>
                                    <span class="pill-badge pill-slate" style="font-size: 0.7rem; padding: 2px 7px;">
                                        8 Standards
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small" style="font-size: 0.73rem;">
                                        <i class="ph-bold ph-target me-1 text-success"></i>ARTA &ge;80%
                                    </span>
                                </div>
                            </div>

                            <!-- Main Highlight Content -->
                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="sqd-kpi-val"><?php echo $overall_sqd_sat_pct; ?>%</span>
                                    <span class="text-muted small fw-semibold">satisfied</span>
                                </div>
                                <div class="sqd-kpi-desc mt-1">
                                    Ratio of Strongly Agree &amp; Agree responses across all 8 ARTA quality standards.
                                </div>
                            </div>

                            <!-- Bottom Details Row -->
                            <div class="d-flex align-items-center justify-content-between sqd-kpi-footer">
                                <div>
                                    <span class="text-muted small d-flex align-items-center" style="font-size: 0.72rem;">
                                        <i class="ph-bold ph-seal-check text-success me-1"></i>Verified Benchmark
                                    </span>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">
                                    <?php echo $total_sqd_pos; ?> of <?php echo $total_sqd_rated; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2">
                            <div>
                                <h6 class="card-title-clean">
                                    <i class="ph-bold ph-chart-line-up me-2 text-primary"></i>Net Satisfaction
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Official ARTA Net Index</span>
                            </div>
                            <span class="pill-badge pill-blue">NSR Index</span>
                        </div>
                        <div class="sqd-kpi-inner flex-grow-1">
                            <!-- Top Meta Row -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="pill-badge pill-blue" style="font-size: 0.72rem; padding: 2px 8px;">
                                        <i class="ph-bold ph-scales me-1"></i>%Sat - %Dissat
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-bold text-success d-flex align-items-center" style="font-size: 0.75rem;">
                                        <i class="ph-bold ph-trend-up me-1"></i>+<?php echo $overall_sqd_nsr; ?>%
                                    </span>
                                </div>
                            </div>

                            <!-- Main Highlight Content -->
                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-2">
                                    <span class="sqd-kpi-val"><?php echo ($overall_sqd_nsr >= 0 ? '+' : '') . $overall_sqd_nsr; ?>%</span>
                                    <span class="text-muted small fw-semibold">net rating</span>
                                </div>
                                <div class="sqd-kpi-desc mt-1">
                                    Net satisfaction rate computed by deducting negative from positive ratings.
                                </div>
                            </div>

                            <!-- Bottom Details Row -->
                            <div class="d-flex align-items-center justify-content-between sqd-kpi-footer">
                                <div>
                                    <span class="text-muted small d-flex align-items-center" style="font-size: 0.72rem;">
                                        <i class="ph-bold ph-seal-check text-primary me-1"></i>High Approval
                                    </span>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">
                                    Target: &ge; +70%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2">
                            <div>
                                <h6 class="card-title-clean">
                                    <i class="ph-bold ph-medal me-2 text-warning"></i>Top Performer
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Highest Approval Standard</span>
                            </div>
                            <span class="pill-badge pill-amber">Rank 1</span>
                        </div>
                        <div class="sqd-kpi-inner flex-grow-1">
                            <!-- Top Meta Row -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="pill-badge pill-amber" style="font-size: 0.72rem; padding: 2px 8px;">
                                        <?php echo strtoupper($top_dimension['key']); ?>
                                    </span>
                                    <span class="pill-badge pill-emerald" style="font-size: 0.7rem; padding: 2px 7px;">
                                        <?php echo $top_dimension['sat_pct']; ?>% sat
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-bold text-amber d-flex align-items-center" style="font-size: 0.76rem;">
                                        <i class="ph-fill ph-star me-1 text-amber"></i><?php echo number_format($top_dimension['mean'], 2); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Main Highlight Content -->
                            <div class="my-2">
                                <div class="sqd-kpi-title text-truncate" title="<?php echo htmlspecialchars($top_dimension['label']); ?>">
                                    <?php echo htmlspecialchars($top_dimension['arta_name']); ?>
                                </div>
                                <div class="sqd-kpi-desc mt-1">
                                    Leading dimension across all respondents with <?php echo $top_dimension['sat_pct']; ?>% approval.
                                </div>
                            </div>

                            <!-- Bottom Details Row -->
                            <div class="d-flex align-items-center justify-content-between sqd-kpi-footer">
                                <div>
                                    <span class="text-muted small d-flex align-items-center" style="font-size: 0.72rem;">
                                        <i class="ph-bold ph-trophy text-warning me-1"></i>Top Dimension
                                    </span>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">
                                    <?php echo $top_dimension['text']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2">
                            <div>
                                <h6 class="card-title-clean">
                                    <i class="ph-bold ph-lightbulb me-2 text-info"></i>Focus Area
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Refinement Opportunity</span>
                            </div>
                            <span class="pill-badge pill-slate">Improvement</span>
                        </div>
                        <div class="sqd-kpi-inner flex-grow-1">
                            <!-- Top Meta Row -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="pill-badge pill-slate" style="font-size: 0.72rem; padding: 2px 8px;">
                                        <?php echo strtoupper($bottom_dimension['key']); ?>
                                    </span>
                                    <span class="pill-badge pill-blue" style="font-size: 0.7rem; padding: 2px 7px;">
                                        <?php echo $bottom_dimension['sat_pct']; ?>% sat
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-bold text-muted d-flex align-items-center" style="font-size: 0.76rem;">
                                        <i class="ph-fill ph-star me-1 text-muted"></i><?php echo number_format($bottom_dimension['mean'], 2); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Main Highlight Content -->
                            <div class="my-2">
                                <div class="sqd-kpi-title text-truncate" title="<?php echo htmlspecialchars($bottom_dimension['label']); ?>">
                                    <?php echo htmlspecialchars($bottom_dimension['arta_name']); ?>
                                </div>
                                <div class="sqd-kpi-desc mt-1">
                                    Target for proactive service delivery and procedural improvements.
                                </div>
                            </div>

                            <!-- Bottom Details Row -->
                            <div class="d-flex align-items-center justify-content-between sqd-kpi-footer">
                                <div>
                                    <span class="text-muted small d-flex align-items-center" style="font-size: 0.72rem;">
                                        <i class="ph-bold ph-arrow-circle-up text-info me-1"></i>Growth Target
                                    </span>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">
                                    <?php echo $bottom_dimension['text']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visual Charts: Stacked Horizontal Distribution & Radar Profile -->
            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="card-title-clean mb-0">
                                    <i class="ph-bold ph-chart-horizontal me-2 text-success"></i>Rating Distribution by Dimension
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Proportion of Strongly Agree (5) down to Strongly Disagree (1)</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" id="btnSqdStackedPct" class="muni-filter-btn active">Percent (%)</button>
                                    <button type="button" id="btnSqdStackedCount" class="muni-filter-btn">Count (#)</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow-1" style="min-height: 380px; position: relative;">
                            <canvas id="chartSqdStacked"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card-minimal h-100 d-flex flex-column">
                        <div class="card-header-clean mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="card-title-clean mb-0">
                                    <i class="ph-bold ph-polygon me-2 text-success"></i>ARTA Dimensions Profile
                                </h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Performance comparison across 8 service standards</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" id="btnSqdRadarScore" class="muni-filter-btn active">Score (1–5)</button>
                                    <button type="button" id="btnSqdRadarSat" class="muni-filter-btn">Sat %</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center" style="min-height: 380px; position: relative;">
                            <canvas id="chartSqdRadar"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Table Card -->
            <div class="card-minimal">
                <div class="card-header-clean flex-wrap gap-2">
                    <div>
                        <h5 class="card-title-clean mb-0">
                            <i class="ph-bold ph-table me-2 text-success"></i>Service Quality Dimensions (SQD) Breakdown
                        </h5>
                        <p class="text-muted small mb-0">Complete tally, weighted mean score, satisfaction rate, and Net Satisfaction Rating (NSR)</p>
                    </div>
                    <span class="pill-badge pill-emerald">Scale 1–5 · ARTA Standard</span>
                </div>
                <div class="table-responsive">
                    <table id="sqdTable" class="table align-middle text-center w-100">
                        <thead>
                            <tr>
                                <th class="text-start">Dimension</th>
                                <th>5 (Strongly Agree)</th>
                                <th>4 (Agree)</th>
                                <th>3 (Neutral)</th>
                                <th>2 (Disagree)</th>
                                <th>1 (Strongly Disagree)</th>
                                <th>N/A</th>
                                <th>Total Rated</th>
                                <th>Mean Score</th>
                                <th>Satisfaction %</th>
                                <th>NSR (%)</th>
                                <th>ARTA Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sqd_stats as $key => $st): 
                                $is_sqd0 = ($key === 'sqd0');
                                $row_class = $is_sqd0 ? 'table-light fw-bold' : '';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="text-start <?php echo $is_sqd0 ? 'fw-bold text-success' : 'fw-semibold text-dark'; ?>">
                                    <?php if ($is_sqd0): ?>
                                        <i class="ph-bold ph-star text-warning me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($st['label']); ?>
                                </td>
                                <td><span class="pill-badge pill-emerald"><?php echo $st['5']; ?></span></td>
                                <td><span class="pill-badge pill-blue"><?php echo $st['4']; ?></span></td>
                                <td><span class="pill-badge pill-amber"><?php echo $st['3']; ?></span></td>
                                <td><span class="pill-badge pill-rose"><?php echo $st['2']; ?></span></td>
                                <td><span class="pill-badge pill-rose fw-bold"><?php echo $st['1']; ?></span></td>
                                <td><span class="pill-badge pill-slate"><?php echo $st['na']; ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo $st['rated']; ?></td>
                                <td class="fw-bold text-dark">&starf; <?php echo number_format($st['mean'], 2); ?></td>
                                <td><span class="pill-badge <?php echo $st['badge']; ?>"><?php echo $st['sat_pct']; ?>%</span></td>
                                <td class="fw-semibold <?php echo $st['nsr'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($st['nsr'] >= 0 ? '+' : '') . $st['nsr']; ?>%
                                </td>
                                <td><span class="pill-badge <?php echo $st['badge']; ?>"><?php echo $st['text']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top border-2">
                            <tr class="fw-bold text-dark">
                                <td class="text-start">Overall SQD Average (SQD1–8)</td>
                                <td><span class="pill-badge pill-emerald"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), '5')); ?></span></td>
                                <td><span class="pill-badge pill-blue"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), '4')); ?></span></td>
                                <td><span class="pill-badge pill-amber"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), '3')); ?></span></td>
                                <td><span class="pill-badge pill-rose"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), '2')); ?></span></td>
                                <td><span class="pill-badge pill-rose fw-bold"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), '1')); ?></span></td>
                                <td><span class="pill-badge pill-slate"><?php echo array_sum(array_column(array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY), 'na')); ?></span></td>
                                <td><?php echo $total_sqd_rated; ?></td>
                                <td>&starf; <?php echo $total_sqd_rated > 0 ? number_format(array_sum(array_map(fn($st) => $st['mean'] * $st['rated'], array_filter($sqd_stats, fn($k) => $k !== 'sqd0', ARRAY_FILTER_USE_KEY))) / $total_sqd_rated, 2) : '0.00'; ?></td>
                                <td><span class="pill-badge pill-emerald"><?php echo $overall_sqd_sat_pct; ?>%</span></td>
                                <td class="<?php echo $overall_sqd_nsr >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo ($overall_sqd_nsr >= 0 ? '+' : '') . $overall_sqd_nsr; ?>%</td>
                                <td><span class="pill-badge pill-emerald"><?php echo $overall_sqd_text; ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 3: DEMOGRAPHICS ANALYTICS
        =========================================== -->
        <div id="sec-demo-summary" class="content-section">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card-minimal h-100">
                        <h6 class="card-title-clean mb-3"><i class="ph-bold ph-user me-2 text-success"></i>Uri ng Kliyente</h6>
                        <table id="tallyType" class="table table-borderless table-sm w-100">
                            <thead class="d-none"><tr><th>Type</th><th>Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($client_type_tally as $name => $count): ?>
                                <tr>
                                    <td class="text-muted ps-0"><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end fw-bold text-dark pe-0"><span class="pill-badge pill-emerald"><?php echo $count; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-minimal h-100">
                        <h6 class="card-title-clean mb-3"><i class="ph-bold ph-gender-intersex me-2 text-primary"></i>Kasarian (Gender)</h6>
                        <table id="tallyGender" class="table table-borderless table-sm w-100">
                            <thead class="d-none"><tr><th>Gender</th><th>Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($gender_tally as $name => $count): 
                                    $is_fem = stripos($name, 'female') !== false || stripos($name, 'babae') !== false;
                                    $badge_class = $is_fem ? 'pill-amber' : 'pill-emerald';
                                ?>
                                <tr>
                                    <td class="text-muted ps-0"><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end fw-bold text-dark pe-0"><span class="pill-badge <?php echo $badge_class; ?>"><?php echo $count; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-minimal h-100">
                        <h6 class="card-title-clean mb-3"><i class="ph-bold ph-users me-2 text-amber"></i>Edad (Age Group)</h6>
                        <table id="tallyAge" class="table table-borderless table-sm w-100">
                            <thead class="d-none"><tr><th>Age</th><th>Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($age_tally as $name => $count): ?>
                                <tr>
                                    <td class="text-muted ps-0"><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end fw-bold text-dark pe-0"><span class="pill-badge pill-slate"><?php echo $count; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card-minimal h-100">
                        <h6 class="card-title-clean mb-3"><i class="ph-bold ph-map-pin me-2 text-amber"></i>Lalawigan (Province Breakdown)</h6>
                        <table id="tallyProv" class="table table-sm align-middle w-100">
                            <thead><tr><th>Province</th><th class="text-end">Respondents</th></tr></thead>
                            <tbody>
                                <?php foreach ($province_tally as $name => $count): ?>
                                <tr>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end"><span class="pill-badge pill-slate"><?php echo $count; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-minimal h-100">
                        <h6 class="card-title-clean mb-3"><i class="ph-bold ph-buildings me-2 text-success"></i>Lungsod/Bayan (Municipality Breakdown)</h6>
                        <table id="tallyMuni" class="table table-sm align-middle w-100">
                            <thead><tr><th>Municipality</th><th class="text-end">Respondents</th></tr></thead>
                            <tbody>
                                <?php foreach ($municipality_tally as $name => $count): ?>
                                <tr>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end"><span class="pill-badge pill-emerald"><?php echo $count; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 4: CLIENT DEMOGRAPHIC RECORDS
        =========================================== -->
        <div id="sec-demo-records" class="content-section">
            <div class="card-minimal">
                <div class="card-header-clean">
                    <div>
                        <h5 class="card-title-clean"><i class="ph-bold ph-users-three me-2 text-success"></i>Client Demographic Records</h5>
                        <p class="text-muted small mb-0">Raw records of respondents and location details</p>
                    </div>
                    <span class="small text-muted"><?php echo $total_clients; ?> records</span>
                </div>
                <div class="table-responsive">
                    <table id="demoTable" class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Date</th>
                                <th>Client Type</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Province</th>
                                <th>Municipality</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($responses as $row): ?>
                            <tr>
                                <td data-sort="<?php echo htmlspecialchars($row['control_number'] ?? ''); ?>">
                                    <span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['control_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['survey_date'] ?? 'N/A'); ?></td>
                                <td><span class="pill-badge pill-slate"><?php echo htmlspecialchars($row['client_type'] ?? 'N/A'); ?></span></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($row['age_group'] ?? 'N/A'); ?></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($row['province'] ?? 'N/A'); ?></td>
                                <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['municipality'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 5: MASTER LIST & CITIZEN'S CHARTER
        =========================================== -->
        <div id="sec-master-list" class="content-section">
            <div class="card-minimal mb-4">
                <div class="card-header-clean">
                    <div>
                        <h5 class="card-title-clean"><i class="ph-bold ph-shield-check me-2 text-primary"></i>Citizen's Charter (CC) Awareness</h5>
                        <p class="text-muted small mb-0">Evaluations for CC Awareness (CC1), Visibility (CC2), and Helpfulness (CC3)</p>
                    </div>
                    <span class="small fw-semibold text-primary">ARTA Standard</span>
                </div>
                <div class="table-responsive">
                    <table id="ccTable" class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Date</th>
                                <th>Client Type</th>
                                <th>CC1 (Knowledge)</th>
                                <th>CC2 (Visibility)</th>
                                <th>CC3 (Helpfulness)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($responses as $row): 
                                $cc1_val = $row['cc1'] ?? 'N/A';
                                $cc1_text = $cc1_val;
                                if ($cc1_val == '1') $cc1_text = "Alam ang CC at nakita ito";
                                elseif ($cc1_val == '2') $cc1_text = "Alam ang CC pero hindi nakita";
                                elseif ($cc1_val == '3') $cc1_text = "Nalaman ang CC nang makita";
                                elseif ($cc1_val == '4') $cc1_text = "Hindi alam ang CC";
                            ?>
                            <tr>
                                <td data-sort="<?php echo htmlspecialchars($row['control_number'] ?? ''); ?>">
                                    <span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['control_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['survey_date'] ?? 'N/A'); ?></td>
                                <td><span class="pill-badge pill-slate"><?php echo htmlspecialchars($row['client_type'] ?? 'N/A'); ?></span></td>
                                <td class="small text-secondary"><?php echo htmlspecialchars($cc1_text); ?></td>
                                <td class="small text-secondary"><?php echo htmlspecialchars($row['cc2'] ?? 'N/A'); ?></td>
                                <td><span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['cc3'] ?? 'N/A'); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-minimal">
                <div class="card-header-clean">
                    <div>
                        <h5 class="card-title-clean"><i class="ph-bold ph-star me-2 text-warning"></i>Master Satisfaction Records</h5>
                        <p class="text-muted small mb-0">Overall satisfaction ratings and transaction details</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="csmTable" class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Date</th>
                                <th>Client Type</th>
                                <th>Transaction</th>
                                <th>CC3 Helpfulness</th>
                                <th class="text-center">Overall Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($responses as $row): ?>
                            <tr>
                                <td data-sort="<?php echo htmlspecialchars($row['control_number'] ?? ''); ?>">
                                    <span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['control_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['survey_date'] ?? 'N/A'); ?></td>
                                <td><span class="pill-badge pill-slate"><?php echo htmlspecialchars($row['client_type'] ?? 'N/A'); ?></span></td>
                                <td class="text-dark small fw-medium"><?php echo htmlspecialchars($row['transaction_type'] ?? 'N/A'); ?></td>
                                <td><span class="pill-badge pill-slate"><?php echo htmlspecialchars($row['cc3'] ?? 'N/A'); ?></span></td>
                                <td class="text-center">
                                    <?php 
                                        $rating = $row['sqd0'] ?? 'N/A';
                                        $badge_cls = ($rating == '5') ? 'pill-emerald' : (($rating == '4') ? 'pill-blue' : (($rating == '3') ? 'pill-amber' : 'pill-rose'));
                                    ?>
                                    <span class="pill-badge <?php echo $badge_cls; ?> fw-bold px-3">
                                        ★ <?php echo htmlspecialchars($rating); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 6: EXPORT & REPORTS
        =========================================== -->
        <div id="sec-export" class="content-section">
            <div class="card-minimal">
                <div class="card-header-clean flex-wrap gap-2">
                    <div>
                        <h5 class="card-title-clean"><i class="ph-bold ph-printer me-2 text-primary"></i>Export & Generate Reports</h5>
                        <p class="text-muted small mb-0">Export filtered records directly to Excel, CSV, or formatted Print report</p>
                    </div>
                    <a href="print_report.php?month=<?php echo htmlspecialchars(!empty($selected_month) ? $selected_month : date('Y-m')); ?>" target="_blank" class="btn-modern-primary text-decoration-none">
                        <i class="ph-bold ph-printer"></i> Print Official CSM Report
                    </a>
                </div>
                <div class="table-responsive">
                    <table id="exportTable" class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th>Control No.</th>
                                <th>Date</th>
                                <th>Client Type</th>
                                <th>Gender</th>
                                <th>Province</th>
                                <th>Municipality</th>
                                <th>Transaction Type</th>
                                <th>Rating</th>
                                <th>Suggestions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($responses as $row): ?>
                            <tr>
                                <td data-sort="<?php echo htmlspecialchars($row['control_number'] ?? ''); ?>" class="fw-bold text-dark"><?php echo htmlspecialchars($row['control_number'] ?? 'N/A'); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['survey_date'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['client_type'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['province'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['municipality'] ?? 'N/A'); ?></td>
                                <td><small class="text-secondary"><?php echo htmlspecialchars($row['transaction_type'] ?? 'N/A'); ?></small></td>
                                <td><span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['sqd0'] ?? 'N/A'); ?></span></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['suggestions'] ?? 'None'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             SECTION 7: CLIENT SUGGESTIONS
        =========================================== -->
        <div id="sec-suggestions" class="content-section">
            <div class="card-minimal">
                <div class="card-header-clean">
                    <div>
                        <h5 class="card-title-clean"><i class="ph-bold ph-heart me-2 text-danger"></i>Client Suggestions & Written Feedback</h5>
                        <p class="text-muted small mb-0">Authentic suggestions and comments submitted by clients</p>
                    </div>
                    <span class="small fw-semibold text-success"><?php echo $written_suggestions_count; ?> Comments</span>
                </div>
                <div class="table-responsive">
                    <table id="suggestionsTable" class="table align-middle w-100">
                        <thead>
                            <tr>
                                <th style="width: 14%;">Control No.</th>
                                <th style="width: 12%;">Date</th>
                                <th style="width: 52%;">Client Suggestion / Comment</th>
                                <th style="width: 22%;">Contact Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                foreach($responses as $row): 
                                    $sug = trim($row['suggestions'] ?? '');
                                    if (is_valid_suggestion($sug)):
                            ?>
                            <tr>
                                <td data-sort="<?php echo htmlspecialchars($row['control_number'] ?? ''); ?>">
                                    <span class="pill-badge pill-emerald"><?php echo htmlspecialchars($row['control_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['survey_date'] ?? 'N/A'); ?></td>
                                <td class="text-dark">
                                    <div class="p-2 rounded-3 bg-light border-0" style="font-size: 0.88rem;">
                                        “<?php echo htmlspecialchars($sug); ?>”
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($row['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-decoration-none small fw-semibold text-primary">
                                            <i class="ph-bold ph-envelope me-1"></i><?php echo htmlspecialchars($row['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Not provided</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                    endif;
                                endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ==========================================
     MINIMAL AI ASSISTANT CHATBOT (META AI SPARKLE)
=========================================== -->
<button class="chat-fab" id="chatToggle" title="Open Meta-style Assistant">
    <i class="ph-fill ph-sparkle"></i>
</button>

<div class="chat-modal-minimal" id="chatWindow">
    <div class="chat-modal-header">
        <div class="d-flex align-items-center gap-2">
            <div class="text-dark fs-5">
                <i class="ph-fill ph-sparkle"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.88rem;">CSM Assistant</h6>
                <span class="text-muted" style="font-size: 0.7rem;">Automated Insights</span>
            </div>
        </div>
        <button id="chatClose" class="btn btn-sm btn-light border-0 p-1 text-muted" style="border-radius: 8px;">
            <i class="ph-bold ph-x"></i>
        </button>
    </div>
    <div class="chat-body-minimal" id="chatBody">
        <div class="chat-bubble bot">
            Hello! I am your local system assistant. You can ask me about <strong>total records</strong>, <strong>average satisfaction</strong>, <strong>SQD meanings</strong>, or <strong>export features</strong>!
        </div>
    </div>
    <div class="chat-footer-minimal">
        <input type="text" id="chatInput" class="chat-input-minimal" placeholder="Ask a question...">
        <button id="chatSend" class="chat-send-btn"><i class="ph-bold ph-paper-plane-tilt"></i></button>
    </div>
</div>

<!-- ==========================================
     SCRIPTS & LIBRARIES 
=========================================== -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(document).ready(function() {
        // --- 1. SIDEBAR & NAVIGATION (THREADS / FACEBOOK DYNAMIC ACTIVE ICONS) ---
        $('#sidebarToggle').on('click', function(e) {
            e.preventDefault();
            $('#sidebar').toggleClass('collapsed');
            $('#main-content').toggleClass('expanded');
            setTimeout(function() { 
                try { $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); } catch(err){}
            }, 300);
        });

        // --- 0. OVERVIEW CARD MOTION (STAGGERED RISE-IN + HOVER LIFT) ---
        function playOverviewMotion() {
            var $cards = $('#greetingsCard, #calendarCard, .stat-card, #sec-dashboard .card-minimal');
            $cards.addClass('ov-card').removeClass('ov-animate').each(function(i) {
                this.style.setProperty('--ov-i', Math.min(i, 12));
            });
            void document.body.offsetWidth; // force reflow so the animation restarts
            $cards.addClass('ov-animate');
        }
        playOverviewMotion();

        $('.nav-link').on('click', function(e) {
            var target = $(this).data('target');
            if (!target) return; // Allow normal link navigation for non-tab links
            e.preventDefault();
            
            // Revert all sidebar icons to outline (ph-bold) like Threads/Instagram
            $('.nav-link').removeClass('active');
            $('.nav-link').each(function() {
                var icon = $(this).find('i');
                var iconClass = icon.attr('class');
                if (iconClass) {
                    icon.attr('class', iconClass.replace('ph-fill', 'ph-bold'));
                }
            });

            // Set clicked tab to active with filled icon (ph-fill)
            $(this).addClass('active');
            var activeIcon = $(this).find('i');
            var activeClass = activeIcon.attr('class');
            if (activeClass) {
                activeIcon.attr('class', activeClass.replace('ph-bold', 'ph-fill'));
            }

            $('.content-section').removeClass('active'); 
            $('#' + target).addClass('active');

            // Show overviewHeaderRow (greetings + calendar) only on the Overview tab
            if (target === 'sec-dashboard') {
                $('#overviewHeaderRow').slideDown(180);
                playOverviewMotion();
            } else {
                $('#overviewHeaderRow').slideUp(180);
            }

            try { $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); } catch(err){}

            if (target === 'sec-sqd' && window.csmCharts) {
                setTimeout(function() {
                    if (window.csmCharts.chartSqdStacked) window.csmCharts.chartSqdStacked.resize();
                    if (window.csmCharts.chartSqdRadar) window.csmCharts.chartSqdRadar.resize();
                }, 60);
            }
        });

        // --- 1.1 SUGGESTIONS AUTO-MOVING CAROUSEL SLIDER ---
        try {
            var sugCarousel = document.getElementById('suggestionsCarousel');
            if (sugCarousel && typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
                var carouselInstance = new bootstrap.Carousel(sugCarousel, {
                    interval: 4000,
                    ride: 'carousel',
                    pause: 'hover',
                    wrap: true
                });
                carouselInstance.cycle();
            }
        } catch(e) {}

        // --- 1.2 MINIMAL INTERACTIVE CALENDAR WIDGET ---
        try {
            (function() {
                var activeDatesMap = <?php echo $js_active_dates ?: '{}'; ?>;
                var today = new Date();
                var currentCalDate = new Date(today.getFullYear(), today.getMonth(), 1);

                var monthNames = ["January", "February", "March", "April", "May", "June", 
                                  "July", "August", "September", "October", "November", "December"];

                function pad2(n) { return (n < 10 ? '0' : '') + n; }

                function renderCalendar() {
                    var year = currentCalDate.getFullYear();
                    var month = currentCalDate.getMonth();

                    $('#calMonthYear').text(monthNames[month] + ' ' + year);

                    var firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
                    var lastDay = new Date(year, month + 1, 0).getDate();
                    var prevLastDay = new Date(year, month, 0).getDate();

                    var html = '';

                    // Previous month overflow days
                    for (var i = firstDayIndex; i > 0; i--) {
                        var prevDayNum = prevLastDay - i + 1;
                        html += '<div class="cal-cell other-month">' + prevDayNum + '</div>';
                    }

                    // Current month days
                    for (var d = 1; d <= lastDay; d++) {
                        var dateStr = year + '-' + pad2(month + 1) + '-' + pad2(d);
                        var isToday = (d === today.getDate() && month === today.getMonth() && year === today.getFullYear());
                        var hasData = !!activeDatesMap[dateStr];
                        var titleAttr = (isToday ? 'Today' : '') + (hasData ? ((isToday ? ' · ' : '') + activeDatesMap[dateStr] + ' responses') : '');

                        var classes = 'cal-cell' + (isToday ? ' today' : '') + (hasData ? ' has-data' : '');
                        html += '<div class="' + classes + '" title="' + titleAttr + '">';
                        html += '<span>' + d + '</span>';
                        if (hasData && !isToday) {
                            html += '<span class="cal-dot"></span>';
                        }
                        html += '</div>';
                    }

                    // Next month overflow days to keep clean 35 or 42 grid cells
                    var totalCells = firstDayIndex + lastDay;
                    var nextDays = (totalCells <= 35) ? (35 - totalCells) : (42 - totalCells);
                    for (var n = 1; n <= nextDays; n++) {
                        html += '<div class="cal-cell other-month">' + n + '</div>';
                    }

                    $('#calDaysGrid').html(html);
                }

                // Live clock in Calendar Header
                function updateClock() {
                    var now = new Date();
                    var hours = now.getHours();
                    var minutes = now.getMinutes();
                    var ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    var strTime = hours + ':' + pad2(minutes) + ' ' + ampm;
                    $('#calLiveClock').html('<i class="ph-bold ph-clock text-primary"></i> ' + strTime);
                }
                setInterval(updateClock, 1000);
                updateClock();

                function swapCalendar(dir) {
                    var $grid = $('#calDaysGrid');
                    $grid.removeClass('cal-swap-next cal-swap-prev');
                    renderCalendar();
                    void $grid[0].offsetWidth; // restart animation
                    if (dir) $grid.addClass(dir > 0 ? 'cal-swap-next' : 'cal-swap-prev');
                }

                $('#calPrevBtn').on('click', function(e) {
                    e.preventDefault();
                    currentCalDate.setMonth(currentCalDate.getMonth() - 1);
                    swapCalendar(-1);
                });

                $('#calNextBtn').on('click', function(e) {
                    e.preventDefault();
                    currentCalDate.setMonth(currentCalDate.getMonth() + 1);
                    swapCalendar(1);
                });

                $('#calTodayBtn').on('click', function(e) {
                    e.preventDefault();
                    currentCalDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    swapCalendar(0);
                });

                renderCalendar();
            })();
        } catch(e) {
            console.error('Calendar init error:', e);
        }

        // --- 2. DATATABLES INITIALIZATION ---
        try {
            $('#servicesTable').DataTable({ 
                "order": [[ 1, "desc" ]], 
                "paging": false, 
                "info": false, 
                "searching": false 
            });

            $('#tallyDate').DataTable({ 
                "order": [[ 0, "desc" ]], 
                "pageLength": 5, 
                "lengthChange": false,
                "info": false
            });

            $('#sqdTable').DataTable({ 
                "paging": false, 
                "info": false, 
                "searching": false,
                "ordering": true,
                "order": []
            });

            $('#tallyType, #tallyGender, #tallyAge').DataTable({ 
                "paging": false, 
                "info": false, 
                "searching": false 
            });
            
            $('#tallyProv').DataTable({ 
                "order": [[ 1, "desc" ]], 
                "pageLength": 5, 
                "info": false, 
                "searching": false,
                "lengthChange": false 
            });

            $('#tallyMuni').DataTable({ 
                "order": [[ 1, "desc" ]], 
                "pageLength": 10, 
                "info": true, 
                "searching": true,
                "lengthChange": false,
                "language": {
                    "search": "Search town:",
                    "searchPlaceholder": "Filter municipality...",
                    "info": "Showing _START_ to _END_ of _TOTAL_ towns"
                }
            });
            
            $('#demoTable, #ccTable, #csmTable, #suggestionsTable').DataTable({ 
                "order": [[ 0, "desc" ]], 
                "pageLength": 10,
                "language": { "search": "Search records:" }
            });

            $('#exportTable').DataTable({
                "order": [[ 0, "desc" ]], 
                "pageLength": 25,
                "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-end'B>>" +
                       "<'row'<'col-sm-12'tr>>" +
                       "<'row mt-3 align-items-center'<'col-sm-12 col-md-5 text-muted small'i><'col-sm-12 col-md-7'p>>",
                "buttons": [
                    { extend: 'excel', className: 'btn btn-modern-primary btn-sm', text: '<i class="ph-bold ph-file-xls me-1"></i> Excel' },
                    { extend: 'csv', className: 'btn btn-modern-outline btn-sm', text: '<i class="ph-bold ph-file-csv me-1"></i> CSV' },
                    { extend: 'print', className: 'btn btn-modern-outline btn-sm', text: '<i class="ph-bold ph-printer me-1"></i> Print', title: 'CSM Master Report' }
                ],
                "language": { "search": "Search within records:" }
            });
        } catch(e) {}

        // --- 3. CHART.JS CONFIGURATION (GREEN-FOCUSED PALETTE) ---
        try {
            var colorGreen   = '#059669'; // Emerald
            var colorBlue    = '#34d399'; // Light green (was blue)
            var colorYellow  = '#84cc16'; // Lime (was yellow)
            var colorRed     = '#166534'; // Forest (was red)
            var colorEmerald = colorGreen;
            var colorAmber   = colorYellow;
            var modernPalette = [colorGreen, colorBlue, colorYellow, colorRed, '#10b981', '#14b8a6'];

            window.csmCharts = window.csmCharts || {};
            var isCurrentDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            var currentGridColor = isCurrentDark ? 'rgba(255, 255, 255, 0.08)' : '#ebf5ee';
            var currentTextColor = isCurrentDark ? '#8fa89a' : '#5f7a6b';
            var currentPolarBorder = isCurrentDark ? '#0d1a13' : '#ffffff';

            Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, -apple-system, sans-serif";
            Chart.defaults.color = currentTextColor;

            if(document.getElementById('chartType')) {
                var rawTypeLabels = <?php echo $js_type_labels ?: '[]'; ?>;

                // Palette harmonized directly with Edad (Yellow, Lime, Emerald Green)
                var typePalette = [
                    // 1. Emerald Green (Citizen / Mamamayan - exact Edad top green)
                    { start: '#047857', mid: '#059669', end: '#10b981', hStart: '#059669', hMid: '#10b981', hEnd: '#34d399', solid: '#047857', border: '#065f46' },
                    // 2. Sunny Yellow (Business / Negosyo - exact Edad bottom yellow)
                    { start: '#eab308', mid: '#facc15', end: '#fef08a', hStart: '#facc15', hMid: '#fde047', hEnd: '#fef9c3', solid: '#facc15', border: '#ca8a04' },
                    // 3. Vibrant Lime (Government / Gobyerno - exact Edad midpoint lime)
                    { start: '#65a30d', mid: '#84cc16', end: '#bef264', hStart: '#84cc16', hMid: '#a3e635', hEnd: '#d9f99d', solid: '#84cc16', border: '#4d7c0f' },
                    // 4. Mint Emerald (fallback)
                    { start: '#065f46', mid: '#10b981', end: '#6ee7b7', hStart: '#10b981', hMid: '#34d399', hEnd: '#a7f3d0', solid: '#10b981', border: '#047857' },
                    // 5. Warm Gold (fallback)
                    { start: '#ca8a04', mid: '#eab308', end: '#fde047', hStart: '#eab308', hMid: '#facc15', hEnd: '#fef08a', solid: '#eab308', border: '#a16207' },
                    // 6. Spring Lime (fallback)
                    { start: '#4d7c0f', mid: '#84cc16', end: '#d9f99d', hStart: '#65a30d', hMid: '#a3e635', hEnd: '#ecfccb', solid: '#84cc16', border: '#365314' }
                ];

                function getTypePal(idx, label) {
                    var l = String(label || '').toLowerCase();
                    if (l.indexOf('citizen') !== -1 || l.indexOf('mamamayan') !== -1) {
                        return typePalette[0]; // Emerald Green
                    }
                    if (l.indexOf('business') !== -1 || l.indexOf('negosyo') !== -1) {
                        return typePalette[1]; // Sunny Yellow
                    }
                    if (l.indexOf('government') !== -1 || l.indexOf('gobyerno') !== -1) {
                        return typePalette[2]; // Vibrant Lime
                    }
                    return typePalette[idx % typePalette.length];
                }

                window.csmCharts.chartType = new Chart(document.getElementById('chartType'), { 
                    type: 'doughnut', 
                    data: { 
                        labels: rawTypeLabels, 
                        datasets: [{ 
                            data: <?php echo $js_type_data ?: '[]'; ?>, 
                            backgroundColor: function(context) {
                                var chart = context.chart;
                                var ctx = chart.ctx;
                                var chartArea = chart.chartArea;
                                var idx = context.dataIndex;
                                var pal = getTypePal(idx, rawTypeLabels[idx]);

                                if (!chartArea) {
                                    return pal.solid;
                                }

                                var grad = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                grad.addColorStop(0, pal.start);
                                grad.addColorStop(0.5, pal.mid);
                                grad.addColorStop(1, pal.end);
                                return grad;
                            },
                            hoverBackgroundColor: function(context) {
                                var chart = context.chart;
                                var ctx = chart.ctx;
                                var chartArea = chart.chartArea;
                                var idx = context.dataIndex;
                                var pal = getTypePal(idx, rawTypeLabels[idx]);

                                if (!chartArea) {
                                    return pal.mid;
                                }

                                var grad = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                grad.addColorStop(0, pal.hStart);
                                grad.addColorStop(0.5, pal.hMid);
                                grad.addColorStop(1, pal.hEnd);
                                return grad;
                            },
                            borderWidth: 0,
                            spacing: 3,
                            borderRadius: 4
                        }] 
                    }, 
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        cutout: '68%',
                        animation: {
                            onComplete: updateTypeCenterPosition
                        },
                        onResize: updateTypeCenterPosition,
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    boxWidth: 10, 
                                    padding: 12, 
                                    font: { size: 11, weight: '600' },
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    generateLabels: function(chart) {
                                        var data = chart.data;
                                        if (data.labels && data.labels.length && data.datasets && data.datasets.length) {
                                            return data.labels.map(function(label, i) {
                                                var pal = getTypePal(i, label);
                                                return {
                                                    text: label,
                                                    fillStyle: pal.solid,
                                                    strokeStyle: pal.border,
                                                    lineWidth: 1,
                                                    hidden: !chart.getDataVisibility(i),
                                                    index: i,
                                                    pointStyle: 'circle'
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                } 
                            }
                        }
                    }
                });
            }

            function updateTypeCenterPosition() {
                var ch = window.csmCharts && window.csmCharts.chartType;
                var ov = document.querySelector('.type-center-overlay');
                if (ch && ov && ch.chartArea) {
                    var cx = (ch.chartArea.left + ch.chartArea.right) / 2;
                    var cy = (ch.chartArea.top + ch.chartArea.bottom) / 2;
                    ov.style.left = cx + 'px';
                    ov.style.top = cy + 'px';
                }
            }

            function updateGenderCenterPosition() {
                var ch = window.csmCharts && window.csmCharts.chartGender;
                var ov = document.querySelector('.gender-center-overlay');
                if (ch && ov && ch.chartArea) {
                    var cx = (ch.chartArea.left + ch.chartArea.right) / 2;
                    var cy = (ch.chartArea.top + ch.chartArea.bottom) / 2;
                    ov.style.left = cx + 'px';
                    ov.style.top = cy + 'px';
                }
            }

            if(document.getElementById('chartGender')) {
                var rawGenderLabels = <?php echo $js_gender_labels ?: '[]'; ?>;

                window.csmCharts.chartGender = new Chart(document.getElementById('chartGender'), { 
                    type: 'doughnut', 
                    data: { 
                        labels: rawGenderLabels, 
                        datasets: [{ 
                            data: <?php echo $js_gender_data ?: '[]'; ?>, 
                            backgroundColor: function(context) {
                                var chart = context.chart;
                                var ctx = chart.ctx;
                                var chartArea = chart.chartArea;
                                var idx = context.dataIndex;
                                var label = (rawGenderLabels[idx] || '').toLowerCase();
                                var isFemale = label.indexOf('female') !== -1 || label.indexOf('babae') !== -1;

                                if (!chartArea) {
                                    return isFemale ? '#facc15' : '#047857';
                                }

                                // Smooth vertical down-to-up linear gradient calibrated directly to Edad (Age Group)
                                var grad = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                if (isFemale) {
                                    // Female: Radiant sunny yellow gradient (from warm gold #eab308 to pure sunny yellow #facc15 to luminous #fef08a)
                                    grad.addColorStop(0, '#eab308');
                                    grad.addColorStop(0.5, '#facc15');
                                    grad.addColorStop(1, '#fef08a');
                                } else {
                                    // Male: Crisp emerald green gradient (from exact Edad emerald #047857 to rich emerald #059669 to luminous #10b981)
                                    grad.addColorStop(0, '#047857');
                                    grad.addColorStop(0.5, '#059669');
                                    grad.addColorStop(1, '#10b981');
                                }
                                return grad;
                            },
                            hoverBackgroundColor: function(context) {
                                var chart = context.chart;
                                var ctx = chart.ctx;
                                var chartArea = chart.chartArea;
                                var idx = context.dataIndex;
                                var label = (rawGenderLabels[idx] || '').toLowerCase();
                                var isFemale = label.indexOf('female') !== -1 || label.indexOf('babae') !== -1;

                                if (!chartArea) {
                                    return isFemale ? '#fde047' : '#059669';
                                }

                                var grad = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                if (isFemale) {
                                    grad.addColorStop(0, '#facc15');
                                    grad.addColorStop(0.5, '#fde047');
                                    grad.addColorStop(1, '#fef9c3');
                                } else {
                                    grad.addColorStop(0, '#059669');
                                    grad.addColorStop(0.5, '#10b981');
                                    grad.addColorStop(1, '#34d399');
                                }
                                return grad;
                            },
                            borderWidth: 0,
                            spacing: 3,
                            borderRadius: 4
                        }] 
                    }, 
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        cutout: '68%',
                        animation: {
                            onComplete: updateGenderCenterPosition
                        },
                        onResize: updateGenderCenterPosition,
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    boxWidth: 10, 
                                    padding: 12, 
                                    font: { size: 11, weight: '600' },
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    generateLabels: function(chart) {
                                        var data = chart.data;
                                        if (data.labels && data.labels.length && data.datasets && data.datasets.length) {
                                            return data.labels.map(function(label, i) {
                                                var l = String(label).toLowerCase();
                                                var isFem = l.indexOf('female') !== -1 || l.indexOf('babae') !== -1;
                                                return {
                                                    text: label,
                                                    fillStyle: isFem ? '#facc15' : '#047857',
                                                    strokeStyle: isFem ? '#ca8a04' : '#065f46',
                                                    lineWidth: 1,
                                                    hidden: !chart.getDataVisibility(i),
                                                    index: i,
                                                    pointStyle: 'circle'
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                } 
                            }
                        }
                    }
                });
            }

            if(document.getElementById('chartAge')) {
                window.csmCharts.chartAge = new Chart(document.getElementById('chartAge'), { 
                    type: 'bar', 
                    data: { 
                        labels: <?php echo $js_age_labels ?: '[]'; ?>, 
                        datasets: [{ 
                            data: <?php echo $js_age_data ?: '[]'; ?>, 
                            backgroundColor: function(context) {
                                const chart = context.chart;
                                const { ctx, chartArea } = chart;
                                if (!chartArea) return '#facc15';
                                const el = context.element;
                                const yBottom = (el && typeof el.base === 'number') ? el.base : chartArea.bottom;
                                const yTop = (el && typeof el.y === 'number' && el.y < yBottom) ? el.y : (chartArea.top < yBottom ? chartArea.top : yBottom - 1);
                                const gradient = ctx.createLinearGradient(0, yBottom, 0, yTop);
                                gradient.addColorStop(0, '#facc15');   // Yellow at the bottom (down)
                                gradient.addColorStop(0.5, '#84cc16'); // Vibrant lime transition
                                gradient.addColorStop(1, '#047857');   // Emerald green at the top (up)
                                return gradient;
                            },
                            hoverBackgroundColor: function(context) {
                                const chart = context.chart;
                                const { ctx, chartArea } = chart;
                                if (!chartArea) return '#fde047';
                                const el = context.element;
                                const yBottom = (el && typeof el.base === 'number') ? el.base : chartArea.bottom;
                                const yTop = (el && typeof el.y === 'number' && el.y < yBottom) ? el.y : (chartArea.top < yBottom ? chartArea.top : yBottom - 1);
                                const gradient = ctx.createLinearGradient(0, yBottom, 0, yTop);
                                gradient.addColorStop(0, '#fde047');
                                gradient.addColorStop(0.5, '#a3e635');
                                gradient.addColorStop(1, '#059669');
                                return gradient;
                            },
                            borderRadius: 6 
                        }] 
                    }, 
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { grid: { color: currentGridColor }, beginAtZero: true }
                        }
                    }
                });
            }

            if(document.getElementById('chartProv')) {
                window.csmCharts.chartProv = new Chart(document.getElementById('chartProv'), { 
                    type: 'bar', 
                    data: { 
                        labels: <?php echo $js_prov_labels ?: '[]'; ?>, 
                        datasets: [{ 
                            data: <?php echo $js_prov_data ?: '[]'; ?>, 
                            backgroundColor: '#14b8a6', 
                            borderRadius: 6 
                        }] 
                    }, 
                    options: { 
                        indexAxis: 'y', 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: currentGridColor }, beginAtZero: true },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            if(document.getElementById('chartMuni')) {
                var rawMuniLabels = <?php echo $js_muni_labels ?: '[]'; ?>;
                var rawMuniData   = <?php echo $js_muni_data ?: '[]'; ?>;
                var muniCurrentMode = 'all'; // 'all' or 'top10'

                function getMuniGradient(context, isHover) {
                    var chart = context.chart;
                    var ctx = chart.ctx;
                    var chartArea = chart.chartArea;
                    if (!chartArea) return isHover ? '#a3e635' : '#84cc16';
                    var el = context.element;
                    var xLeft = (el && typeof el.base === 'number') ? el.base : chartArea.left;
                    var xRight = (el && typeof el.x === 'number' && el.x > xLeft) ? el.x : (chartArea.right > xLeft ? chartArea.right : xLeft + 10);
                    var grad = ctx.createLinearGradient(xLeft, 0, xRight, 0);
                    if (isHover) {
                        grad.addColorStop(0, '#fde047');
                        grad.addColorStop(0.5, '#a3e635');
                        grad.addColorStop(1, '#059669');
                    } else {
                        grad.addColorStop(0, '#facc15');   // Yellow (start)
                        grad.addColorStop(0.5, '#84cc16'); // Lime transition
                        grad.addColorStop(1, '#047857');   // Emerald green (tip)
                    }
                    return grad;
                }

                function adjustMuniHeight(count) {
                    var wrapper = document.getElementById('muniCanvasWrapper');
                    if (!wrapper) return;
                    if (count <= 10) {
                        wrapper.style.height = Math.max(220, count * 24 + 20) + 'px';
                    } else {
                        wrapper.style.height = (count * 25 + 30) + 'px';
                    }
                }

                adjustMuniHeight(rawMuniLabels.length);

                window.csmCharts.chartMuni = new Chart(document.getElementById('chartMuni'), { 
                    type: 'bar', 
                    data: { 
                        labels: rawMuniLabels, 
                        datasets: [{ 
                            data: rawMuniData, 
                            backgroundColor: function(context) { return getMuniGradient(context, false); },
                            hoverBackgroundColor: function(context) { return getMuniGradient(context, true); },
                            borderRadius: 6,
                            maxBarThickness: 16
                        }] 
                    }, 
                    options: { 
                        indexAxis: 'y',
                        responsive: true, 
                        maintainAspectRatio: false, 
                        animation: { duration: 300 },
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' ' + context.label + ': ' + (context.raw || 0) + ' respondents';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: currentGridColor },
                                beginAtZero: true,
                                ticks: { 
                                    precision: 0,
                                    color: currentTextColor
                                }
                            },
                            y: {
                                grid: { display: false },
                                ticks: {
                                    font: { size: 11, weight: '600' },
                                    autoSkip: false,
                                    color: currentTextColor
                                }
                            }
                        }
                    }
                });

                function applyMuniFilter() {
                    var q = ($('#muniQuickSearch').val() || '').trim().toLowerCase();
                    var filteredLabels = [];
                    var filteredData = [];

                    var baseLabels = (muniCurrentMode === 'top10') ? rawMuniLabels.slice(0, 10) : rawMuniLabels;
                    var baseData   = (muniCurrentMode === 'top10') ? rawMuniData.slice(0, 10) : rawMuniData;

                    if (q === '') {
                        filteredLabels = baseLabels;
                        filteredData = baseData;
                        var badgeText = (muniCurrentMode === 'top10') ? 'Top 10 towns' : (rawMuniLabels.length + ' towns');
                        $('#muniBadgeCount').text(badgeText);
                    } else {
                        for (var i = 0; i < rawMuniLabels.length; i++) {
                            if (rawMuniLabels[i].toLowerCase().indexOf(q) !== -1) {
                                filteredLabels.push(rawMuniLabels[i]);
                                filteredData.push(rawMuniData[i]);
                            }
                        }
                        $('#muniBadgeCount').text(filteredLabels.length + ' of ' + rawMuniLabels.length + ' towns');
                    }

                    adjustMuniHeight(filteredLabels.length);
                    window.csmCharts.chartMuni.data.labels = filteredLabels;
                    window.csmCharts.chartMuni.data.datasets[0].data = filteredData;
                    window.csmCharts.chartMuni.update();
                    window.csmCharts.chartMuni.resize();
                }

                $('#btnMuniAll').on('click', function() {
                    muniCurrentMode = 'all';
                    $('#btnMuniAll').addClass('active');
                    $('#btnMuniTop10').removeClass('active');
                    applyMuniFilter();
                });

                $('#btnMuniTop10').on('click', function() {
                    muniCurrentMode = 'top10';
                    $('#btnMuniTop10').addClass('active');
                    $('#btnMuniAll').removeClass('active');
                    applyMuniFilter();
                });

                $('#muniQuickSearch').on('input', function() {
                    applyMuniFilter();
                });
            }

            // --- 3.7 SQD STACKED RATING DISTRIBUTION CHART ---
            if (document.getElementById('chartSqdStacked')) {
                var sqdStackedLabels = <?php echo $js_sqd_stacked_labels ?: '[]'; ?>;
                var sqdStackedCounts = <?php echo $js_sqd_stacked_counts ?: '{}'; ?>;
                var sqdStackedPcts   = <?php echo $js_sqd_stacked_pcts ?: '{}'; ?>;
                var sqdStackedMode   = 'pct'; // 'pct' or 'count'

                function getSqdStackedDatasets(mode) {
                    var dataSrc = (mode === 'pct') ? sqdStackedPcts : sqdStackedCounts;
                    return [
                        {
                            label: '5 - Lubos na Sumasang-ayon (Strongly Agree)',
                            data: dataSrc['5'] || [],
                            backgroundColor: '#047857',
                            hoverBackgroundColor: '#059669',
                            borderRadius: mode === 'pct' ? 0 : 4,
                            barPercentage: 0.75
                        },
                        {
                            label: '4 - Sumasang-ayon (Agree)',
                            data: dataSrc['4'] || [],
                            backgroundColor: '#84cc16',
                            hoverBackgroundColor: '#a3e635',
                            borderRadius: mode === 'pct' ? 0 : 4,
                            barPercentage: 0.75
                        },
                        {
                            label: '3 - Neutral',
                            data: dataSrc['3'] || [],
                            backgroundColor: '#facc15',
                            hoverBackgroundColor: '#fde047',
                            borderRadius: mode === 'pct' ? 0 : 4,
                            barPercentage: 0.75
                        },
                        {
                            label: '2 - Hindi Sumasang-ayon (Disagree)',
                            data: dataSrc['2'] || [],
                            backgroundColor: '#fb923c',
                            hoverBackgroundColor: '#fdba74',
                            borderRadius: mode === 'pct' ? 0 : 4,
                            barPercentage: 0.75
                        },
                        {
                            label: '1 - Lubos na Hindi Sumasang-ayon (Strongly Disagree)',
                            data: dataSrc['1'] || [],
                            backgroundColor: '#ef4444',
                            hoverBackgroundColor: '#f87171',
                            borderRadius: mode === 'pct' ? 0 : 4,
                            barPercentage: 0.75
                        }
                    ];
                }

                window.csmCharts.chartSqdStacked = new Chart(document.getElementById('chartSqdStacked'), {
                    type: 'bar',
                    data: {
                        labels: sqdStackedLabels,
                        datasets: getSqdStackedDatasets('pct')
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 300 },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 10,
                                    boxHeight: 10,
                                    padding: 12,
                                    font: { size: 10.5, weight: '600' },
                                    color: currentTextColor
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var idx = ctx.dataIndex;
                                        var dsKey = ['5','4','3','2','1'][ctx.datasetIndex];
                                        var countVal = (sqdStackedCounts[dsKey] && sqdStackedCounts[dsKey][idx]) || 0;
                                        var pctVal = (sqdStackedPcts[dsKey] && sqdStackedPcts[dsKey][idx]) || 0;
                                        return ' ' + ctx.dataset.label.split(' - ')[0] + ': ' + countVal + ' responses (' + pctVal + '%)';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                max: 100,
                                grid: { color: currentGridColor },
                                ticks: {
                                    color: currentTextColor,
                                    callback: function(v) { return v + '%'; }
                                }
                            },
                            y: {
                                stacked: true,
                                grid: { display: false },
                                ticks: {
                                    font: { size: 11, weight: '600' },
                                    color: currentTextColor
                                }
                            }
                        }
                    }
                });

                $('#btnSqdStackedPct').on('click', function() {
                    sqdStackedMode = 'pct';
                    $('#btnSqdStackedPct').addClass('active');
                    $('#btnSqdStackedCount').removeClass('active');
                    var ch = window.csmCharts.chartSqdStacked;
                    ch.data.datasets = getSqdStackedDatasets('pct');
                    ch.options.scales.x.max = 100;
                    ch.options.scales.x.ticks.callback = function(v) { return v + '%'; };
                    ch.update();
                });

                $('#btnSqdStackedCount').on('click', function() {
                    sqdStackedMode = 'count';
                    $('#btnSqdStackedCount').addClass('active');
                    $('#btnSqdStackedPct').removeClass('active');
                    var ch = window.csmCharts.chartSqdStacked;
                    ch.data.datasets = getSqdStackedDatasets('count');
                    delete ch.options.scales.x.max;
                    ch.options.scales.x.ticks.callback = function(v) { return v; };
                    ch.update();
                });
            }

            // --- 3.8 ARTA SQD RADAR PERFORMANCE PROFILE CHART ---
            if (document.getElementById('chartSqdRadar')) {
                var sqdRadarLabels = <?php echo $js_sqd_radar_labels ?: '[]'; ?>;
                var sqdRadarMeans  = <?php echo $js_sqd_radar_means ?: '[]'; ?>;
                var sqdRadarSat    = <?php echo $js_sqd_radar_sat ?: '[]'; ?>;
                var sqdRadarMode   = 'score'; // 'score' or 'sat'

                window.csmCharts.chartSqdRadar = new Chart(document.getElementById('chartSqdRadar'), {
                    type: 'radar',
                    data: {
                        labels: sqdRadarLabels,
                        datasets: [{
                            label: 'Mean Rating Score',
                            data: sqdRadarMeans,
                            backgroundColor: 'rgba(16, 185, 129, 0.22)',
                            borderColor: '#059669',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#facc15',
                            pointBorderColor: '#047857',
                            pointBorderWidth: 2,
                            pointHoverBackgroundColor: '#ffffff',
                            pointHoverBorderColor: '#059669',
                            pointRadius: 4.5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 300 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        if (sqdRadarMode === 'score') {
                                            return ' Mean: ' + Number(ctx.raw).toFixed(2) + ' / 5.00';
                                        } else {
                                            return ' Satisfaction: ' + ctx.raw + '%';
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            r: {
                                min: 0,
                                max: 5,
                                ticks: {
                                    stepSize: 1,
                                    backdropColor: 'transparent',
                                    color: currentTextColor,
                                    font: { size: 10 }
                                },
                                grid: {
                                    color: isCurrentDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(220, 235, 225, 0.7)'
                                },
                                angleLines: {
                                    color: isCurrentDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(220, 235, 225, 0.7)'
                                },
                                pointLabels: {
                                    font: { size: 11, weight: '600' },
                                    color: currentTextColor
                                }
                            }
                        }
                    }
                });

                $('#btnSqdRadarScore').on('click', function() {
                    sqdRadarMode = 'score';
                    $('#btnSqdRadarScore').addClass('active');
                    $('#btnSqdRadarSat').removeClass('active');
                    var ch = window.csmCharts.chartSqdRadar;
                    ch.data.datasets[0].label = 'Mean Rating Score';
                    ch.data.datasets[0].data = sqdRadarMeans;
                    ch.options.scales.r.min = 0;
                    ch.options.scales.r.max = 5;
                    ch.options.scales.r.ticks.stepSize = 1;
                    ch.update();
                });

                $('#btnSqdRadarSat').on('click', function() {
                    sqdRadarMode = 'sat';
                    $('#btnSqdRadarSat').addClass('active');
                    $('#btnSqdRadarScore').removeClass('active');
                    var ch = window.csmCharts.chartSqdRadar;
                    ch.data.datasets[0].label = 'Satisfaction Rate (%)';
                    ch.data.datasets[0].data = sqdRadarSat;
                    ch.options.scales.r.min = 0;
                    ch.options.scales.r.max = 100;
                    ch.options.scales.r.ticks.stepSize = 20;
                    ch.update();
                });
            }

            // Live Chart theme updater
            window.addEventListener('csmThemeChanged', function(e) {
                var isDark = e.detail && e.detail.theme === 'dark';
                var gridCol = isDark ? 'rgba(255, 255, 255, 0.08)' : '#ebf5ee';
                var textCol = isDark ? '#8fa89a' : '#5f7a6b';
                var polarBorder = isDark ? '#0d1a13' : '#ffffff';

                Chart.defaults.color = textCol;

                if (window.csmCharts) {
                    Object.values(window.csmCharts).forEach(function(ch) {
                        if (!ch) return;
                        if (ch.options && ch.options.scales) {
                            if (ch.options.scales.x && ch.options.scales.x.grid && ch.options.scales.x.grid.display !== false) {
                                ch.options.scales.x.grid.color = gridCol;
                            }
                            if (ch.options.scales.y && ch.options.scales.y.grid && ch.options.scales.y.grid.display !== false) {
                                ch.options.scales.y.grid.color = gridCol;
                            }
                            if (ch.options.scales.x && ch.options.scales.x.ticks) {
                                ch.options.scales.x.ticks.color = textCol;
                            }
                            if (ch.options.scales.y && ch.options.scales.y.ticks) {
                                ch.options.scales.y.ticks.color = textCol;
                            }
                            if (ch.options.scales.r) {
                                if (ch.options.scales.r.grid) ch.options.scales.r.grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(220, 235, 225, 0.7)';
                                if (ch.options.scales.r.angleLines) ch.options.scales.r.angleLines.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(220, 235, 225, 0.7)';
                                if (ch.options.scales.r.ticks) ch.options.scales.r.ticks.color = textCol;
                                if (ch.options.scales.r.pointLabels) ch.options.scales.r.pointLabels.color = textCol;
                            }
                        }
                        if (ch.config && ch.config.type === 'polarArea' && ch.data.datasets && ch.data.datasets[0]) {
                            ch.data.datasets[0].borderColor = polarBorder;
                        }
                        ch.update();
                    });
                    updateTypeCenterPosition();
                    updateGenderCenterPosition();
                }
            });
        } catch(e) {
            console.error('Chart.js initialization error:', e);
        }

        // --- 4. CHATBOT INTERACTIONS ---
        try {
            $('#chatToggle, #chatClose').on('click', function(e) {
                e.preventDefault();
                $('#chatWindow').fadeToggle(180);
            });

            function appendMessage(sender, text) {
                var msgClass = (sender === 'user') ? 'user' : 'bot';
                $('#chatBody').append('<div class="chat-bubble ' + msgClass + '">' + text + '</div>');
                $('#chatBody').scrollTop($('#chatBody')[0].scrollHeight);
            }

            function sendChatMessage() {
                var text = $('#chatInput').val().trim();
                if (text === '') return;
                
                appendMessage('user', text);
                $('#chatInput').val('');
                
                var typingId = 'typing-' + Date.now();
                $('#chatBody').append('<div class="chat-bubble bot text-muted" id="' + typingId + '"><em>Thinking...</em></div>');
                $('#chatBody').scrollTop($('#chatBody')[0].scrollHeight);

                $.ajax({
                    url: 'dashboard.php',
                    method: 'POST',
                    data: { chat_message: text },
                    success: function(response) {
                        $('#' + typingId).remove();
                        appendMessage('bot', response);
                    },
                    error: function() {
                        $('#' + typingId).remove();
                        appendMessage('bot', "Connection error.");
                    }
                });
            }

            $('#chatSend').on('click', function(e) {
                e.preventDefault();
                sendChatMessage();
            });

            $('#chatInput').on('keypress', function(e) {
                if(e.which === 13) {
                    e.preventDefault();
                    sendChatMessage();
                }
            });
        } catch(e) {}
    });
</script>
<script src="darkmode.js"></script>
</body>
</html>
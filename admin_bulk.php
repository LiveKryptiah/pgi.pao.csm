<?php
// ==========================================
// DEBUG MODE: ON
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MUST BE AT THE VERY TOP
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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

$isabelaLocations = [
    "Alicia", "Angadanan", "Aurora", "Benito Soliven", "Burgos", "Cabagan", "Cabatuan", "Cauayan City", "Cordon", "Delfin Albano", 
    "Dinapigue", "Divilacan", "Echague", "Gamu", "Ilagan City", "Jones", "Luna", "Maconacon", "Mallig", "Naguilian", "Palanan", 
    "Quezon", "Quirino", "Ramon", "Reina Mercedes", "Roxas", "San Agustin", "San Guillermo", "San Isidro", "San Manuel", 
    "San Mariano", "San Mateo", "San Pablo", "Santa Maria", "Santiago City", "Santo Tomas", "Tumauini", "Outside Isabela"
];
sort($isabelaLocations);

$success_msg = "";
$error_msg = "";

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
            if ($wipe_scope === 'all') {
                $stmt = $pdo->prepare("DELETE FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = :month");
                $stmt->execute([':month' => $wipe_month]);
                $deleted_count = $stmt->rowCount();
                if ($deleted_count > 0) {
                    $success_msg = "Success! Permanently deleted all " . number_format($deleted_count) . " records for " . $month_label . ".";
                } else {
                    $error_msg = "No records found for " . $month_label . " to delete.";
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM responses WHERE DATE_FORMAT(survey_date, '%Y-%m') = :month AND (control_number LIKE '%B' OR control_number LIKE 'BLK-%')");
                $stmt->execute([':month' => $wipe_month]);
                $deleted_count = $stmt->rowCount();
                if ($deleted_count > 0) {
                    $success_msg = "Success! Permanently deleted " . number_format($deleted_count) . " bulk records for " . $month_label . ".";
                } else {
                    $error_msg = "No bulk-generated records found for " . $month_label . " to delete.";
                }
            }
        } catch (Exception $e) {
            $error_msg = "Database Deletion Error: " . $e->getMessage();
        }
    }
}

// Retrieve distinct months with record tallies for the Wipe Month selector
$month_tallies = [];
try {
    $mt_stmt = $pdo->query("SELECT DATE_FORMAT(survey_date, '%Y-%m') as ym, DATE_FORMAT(survey_date, '%M %Y') as m_label, COUNT(*) as total_cnt, SUM(CASE WHEN control_number LIKE '%B' OR control_number LIKE 'BLK-%' THEN 1 ELSE 0 END) as bulk_cnt FROM responses WHERE survey_date IS NOT NULL AND CAST(survey_date AS VARCHAR) NOT IN ('', '0000-00-00') GROUP BY ym, m_label ORDER BY ym DESC");
    $month_tallies = $mt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 3. HANDLE BULK SUBMISSION (Single JSON payload bypasses PHP max_input_vars limit entirely)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['bulk_json']) || isset($_POST['survey_date']))) {
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
        $success_msg = "Successfully generated and saved " . $count . " records to the database!";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
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

        /* EXCEL-LIKE SPREADSHEET GRID */
        .grid-container {
            overflow-x: auto;
            max-height: 560px;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            background: #ffffff;
        }

        .table-grid {
            width: max-content;
            min-width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-grid thead th {
            background: #f8fafc;
            color: var(--text-muted);
            position: sticky;
            top: 0;
            z-index: 2;
            border-bottom: 1px solid var(--border-subtle);
            border-right: 1px solid var(--border-light);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            padding: 11px 8px;
            text-align: center;
        }

        .table-grid thead th:last-child {
            border-right: none;
        }

        .table-grid tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid var(--border-light);
            border-right: 1px solid var(--border-light);
            vertical-align: middle;
            background: #ffffff;
        }

        .table-grid tbody tr:hover td {
            background-color: #fbfcfe;
        }

        .table-grid thead th.th-sqd0 {
            background-color: #ecfdf5 !important;
            color: #065f46 !important;
            border-bottom: 1px solid #d1fae5;
        }

        .table-grid tbody td.td-sqd0 {
            background-color: #f0fdf4 !important;
        }

        .table-grid select, 
        .table-grid input {
            border: 1px solid transparent;
            background: #f8fafc;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-heading);
            padding: 5px 8px;
            border-radius: 6px;
            width: 100%;
            transition: all 0.15s ease;
            outline: none;
        }

        .table-grid select:focus, 
        .table-grid input:focus {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }

        .table-grid input[readonly] {
            background: transparent;
            color: var(--text-muted);
            cursor: not-allowed;
            border: none;
        }

        .col-action { width: 44px; text-align: center; }
        .col-date   { width: 140px; }
        .col-client { width: 130px; }
        .col-gender { width: 105px; }
        .col-age    { width: 100px; }
        .col-reg    { width: 105px; }
        .col-prov   { width: 95px; }
        .col-muni   { width: 160px; }
        .col-trans  { width: 220px; }
        .col-score  { width: 68px; text-align: center; }
        .col-sqd0   { width: 85px; text-align: center; }

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
            <button id="sidebarToggle" class="btn btn-sm btn-light border p-2 rounded-3 text-muted">
                <i class="ph-bold ph-list fs-5"></i>
            </button>
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">Bulk Data Encoder & Generator</h5>
                <span class="text-muted small">Batch generate synthetic citizen assessments or encode offline records</span>
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
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="background-color: #ecfdf5; color: #065f46; border-radius: 12px;">
                <i class="ph-bold ph-check-circle fs-5 text-success"></i>
                <div class="flex-grow-1 fw-semibold"><?php echo htmlspecialchars($success_msg); ?></div>
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

        <!-- SMART AUTO-GENERATOR PANEL -->
        <div class="card-minimal mb-4">
            <div class="card-header-clean flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="text-primary fs-4 d-flex align-items-center">
                        <i class="ph-bold ph-sparkle"></i>
                    </div>
                    <div>
                        <h6 class="card-title-clean">Smart Date Randomizer & Generator</h6>
                        <span class="text-muted" style="font-size: 0.76rem;">Picks random Mon–Thu business days excluding official PH holidays. Suffixes record IDs with 'B'.</span>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <button type="button" class="btn-modern-outline btn-sm py-1.5 px-3" onclick="clearAllRows()">
                        <i class="ph-bold ph-eraser"></i> Clear Grid
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 fw-semibold py-1.5 px-3 d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#wipeMonthModal" title="Wipe bulk or all records for a specific month">
                        <i class="ph-bold ph-calendar-x"></i> Wipe by Month
                    </button>

                    <form method="POST" action="" onsubmit="return confirm('WARNING: This will permanently delete ALL fake/bulk records (control numbers starting with BLK- or ending with B) across ALL months from your database. Real citizen data will not be affected. Continue?');" style="display:inline;">
                        <button type="submit" name="delete_bulk_data" value="1" class="btn btn-sm btn-outline-secondary text-danger rounded-3 fw-semibold py-1.5 px-3 d-inline-flex align-items-center gap-1.5" title="Wipe all bulk records across all months">
                            <i class="ph-bold ph-trash"></i> Wipe All Bulk
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="row g-3 align-items-end pt-2">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">Target Month</label>
                    <input type="month" id="genMonth" class="form-control form-control-sm fw-semibold" value="<?= date('Y-m') ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">Row Count</label>
                    <input type="number" id="genCount" class="form-control form-control-sm fw-semibold" value="10" min="1" max="500">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">Client Type</label>
                    <select id="genClient" class="form-select form-select-sm fw-semibold">
                        <option value="Random">🎲 Randomize (Weighted)</option>
                        <option value="Citizen">Citizen Only</option>
                        <option value="Business">Business Only</option>
                        <option value="Government">Government Only</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-bold mb-1">Municipality / City</label>
                    <select id="genMuni" class="form-select form-select-sm fw-semibold">
                        <option value="Random">🎲 Randomize (All Isabela)</option>
                        <?php foreach ($isabelaLocations as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12">
                    <label class="form-label text-muted small fw-bold mb-1">Transaction Type</label>
                    <div class="d-flex gap-2">
                        <select id="genTransaction" class="form-select form-select-sm fw-semibold flex-grow-1">
                            <option value="Random">🎲 Randomize (All 13 Types Evenly)</option>
                            <option value="RandomWeighted">🎲 Randomize (Weighted - Cert/Trans/Sub)</option>
                            <optgroup label="Select Specific Transaction">
                            <?php foreach ($transactionTypes as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <button type="button" class="btn-modern-primary py-1.5 px-3 flex-shrink-0" onclick="generateRandomRows()" title="Generate rows into table">
                            <i class="ph-bold ph-lightning text-warning"></i> Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ENCODING GRID CARD -->
        <div class="card-minimal">
            <div class="card-header-clean flex-wrap gap-2">
                <div>
                    <h5 class="card-title-clean fs-6">Data Encoding Grid</h5>
                    <small class="text-muted">Review generated data or manually stage rows before saving to the database.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="rowCountBadge" class="pill-badge pill-slate">0 Records Staged</span>
                    <button type="button" class="btn-modern-outline btn-sm py-1.5 px-3" onclick="addNewRow()">
                        <i class="ph-bold ph-plus"></i> Add Blank Row
                    </button>
                </div>
            </div>

            <form method="POST" action="" id="bulkForm">
                <input type="hidden" name="bulk_json" id="bulkJson" value="">
                <div class="grid-container mb-4">
                    <table class="table-grid">
                        <thead>
                            <tr>
                                <th class="col-action"></th>
                                <th class="col-date">Date</th>
                                <th class="col-client">Client Type</th>
                                <th class="col-gender">Gender</th>
                                <th class="col-age">Age</th>
                                <th class="col-reg">Region</th>
                                <th class="col-prov">Province</th>
                                <th class="col-muni">Municipality</th>
                                <th class="col-trans">Transaction</th>
                                <th class="col-score" title="Citizen's Charter Knowledge">CC1</th>
                                <th class="col-score" title="Citizen's Charter Visibility">CC2</th>
                                <th class="col-score" title="Citizen's Charter Helpfulness">CC3</th>
                                <th class="col-sqd0 th-sqd0" title="Overall Satisfaction (SQD0)">Overall</th>
                                <th class="col-score" title="Responsiveness">SQD1</th>
                                <th class="col-score" title="Reliability">SQD2</th>
                                <th class="col-score" title="Access & Facilities">SQD3</th>
                                <th class="col-score" title="Communication">SQD4</th>
                                <th class="col-score" title="Costs">SQD5</th>
                                <th class="col-score" title="Integrity">SQD6</th>
                                <th class="col-score" title="Professionalism">SQD7</th>
                                <th class="col-score" title="Rules & Explain">SQD8</th>
                            </tr>
                        </thead>
                        <tbody id="bulkBody">
                            <!-- Staged rows dynamically injected here -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-3 border-top flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="ph-bold ph-info text-primary fs-5"></i>
                        <span>Generated rows will receive unique electronic control IDs (<code class="text-primary fw-bold">E{MM}-{seq}B</code>) and update dashboard metrics in real time.</span>
                    </div>
                    <button type="submit" class="btn-modern-primary btn-lg px-4 py-2.5 shadow-sm fw-bold">
                        <i class="ph-bold ph-cloud-arrow-up fs-5"></i> Save All Records to Database
                    </button>
                </div>
            </form>
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
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="wipeMonthModalLabel">Wipe Data by Month</h5>
                        <p class="text-muted small mb-0">Permanently delete staged or bulk records for a target month</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" onsubmit="return confirmWipeMonth();">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold small">Select Month with Existing Records</label>
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
                        <label class="form-label text-muted small fw-bold">Or pick custom month (YYYY-MM)</label>
                        <input type="month" id="modalWipeMonthInput" class="form-control form-control-sm fw-semibold" value="<?= !empty($month_tallies[0]['ym']) ? htmlspecialchars($month_tallies[0]['ym']) : date('Y-m') ?>" onchange="syncWipeMonthSelect(this.value)">
                    </div>

                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label text-dark fw-bold small mb-2">Deletion Scope</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="wipe_scope" id="scopeBulkOnly" value="bulk_only" checked>
                            <label class="form-check-label text-dark small fw-semibold" for="scopeBulkOnly">
                                <span class="d-block text-dark fw-bold">Bulk-Generated Records Only (Recommended)</span>
                                <span class="text-muted" style="font-size: 0.8rem;">Deletes only records ending with 'B' or starting with 'BLK-'. Real citizen responses will not be touched.</span>
                            </label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="wipe_scope" id="scopeAll" value="all">
                            <label class="form-check-label text-danger small fw-semibold" for="scopeAll">
                                <span class="d-block text-danger fw-bold">All Records for this Month</span>
                                <span class="text-muted" style="font-size: 0.8rem;">Wipes all responses for this month, including real citizen and manual submissions.</span>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2.5 px-3 small d-flex align-items-center gap-2 mb-0" style="border-radius: 10px;">
                        <i class="ph-bold ph-warning text-warning fs-5 flex-shrink-0"></i>
                        <span>This action is immediate and cannot be undone. Please ensure you selected the intended month and scope.</span>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-sm btn-light border fw-semibold px-3 py-2 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="wipe_month_data" value="1" class="btn btn-sm btn-danger fw-bold px-4 py-2 rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm">
                        <i class="ph-bold ph-trash"></i> Confirm & Wipe Month
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LIBRARIES -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let rowCount = 0;

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

    function getSelectOptions(optionsArray, selectedValue) {
        return optionsArray.map(opt => `<option value="${opt}" ${opt === selectedValue ? 'selected' : ''}>${opt}</option>`).join('');
    }

    // DATE RANDOMIZER LOGIC (Excludes Holidays, Fri, Sat, Sun)
    function getRandomValidDate(year, monthIndex) {
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
        if (validDates.length === 0) return `${year}-${String(monthIndex+1).padStart(2,'0')}-01`; 
        
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

    function addNewRow() {
        const today = new Date().toISOString().split('T')[0];
        injectRowHtml(today, "Citizen", "Male", "20-29", "Ilagan City", "Transfer", "3", "3", "3", "5", "5", "5", "5", "5", "5", "5", "5", "5");
    }

    function generateRandomRows() {
        const count = parseInt(document.getElementById('genCount').value) || 10;
        const monthVal = document.getElementById('genMonth').value; // e.g. "2026-10"
        const clientMode = document.getElementById('genClient').value;
        const transMode = document.getElementById('genTransaction').value;
        const muniMode = document.getElementById('genMuni').value;

        if (!monthVal) {
            alert('Please select a Target Month first.');
            return;
        }

        const year = parseInt(monthVal.split('-')[0]);
        const monthIndex = parseInt(monthVal.split('-')[1]) - 1; // JS months are 0-indexed

        const genders = ["Male", "Female"]; 
        const ages = ["15-19", "20-29", "30-39", "40-49", "50-59", "60+"];
        const ccScores = ["3", "4"];
        const sqdScores = ["4", "5"];

        for(let i = 0; i < count; i++) {
            const rDate = getRandomValidDate(year, monthIndex);
            const rClient = clientMode === "Random" ? getWeightedClient() : clientMode;
            
            // Transaction mode resolution
            let rTrans;
            if (transMode === "Random") {
                // Distribute evenly across ALL 13 transaction types
                rTrans = transactionTypes[Math.floor(Math.random() * transactionTypes.length)];
            } else if (transMode === "RandomWeighted") {
                rTrans = getWeightedTransaction();
            } else {
                // User explicitly selected one of the 13 specific transaction types
                rTrans = transMode;
            }

            const rMuni = muniMode === "Random" ? isabelaLocations[Math.floor(Math.random() * isabelaLocations.length)] : muniMode;
            const rGender = genders[Math.floor(Math.random() * genders.length)];
            const rAge = ages[Math.floor(Math.random() * ages.length)];
            const getRandSqd = () => sqdScores[Math.floor(Math.random() * sqdScores.length)];
            const getRandCc = () => ccScores[Math.floor(Math.random() * ccScores.length)];

            injectRowHtml(
                rDate, rClient, rGender, rAge, rMuni, rTrans, 
                getRandCc(), getRandCc(), getRandCc(), 
                getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd(), getRandSqd()
            );
        }
    }

    function injectRowHtml(date, client, gender, age, muni, trans, cc1, cc2, cc3, s0, s1, s2, s3, s4, s5, s6, s7, s8) {
        rowCount++;
        updateCounter();
        
        const sqdChoices = ["5", "4", "3", "N/A"];
        const ccChoices = ["1", "2", "3", "4"];
        const genderChoices = ["Male", "Female"];
        const ageChoices = ["15-19", "20-29", "30-39", "40-49", "50-59", "60+"];
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="col-action">
                <button type="button" class="btn btn-sm btn-link text-danger p-0" title="Delete Row" onclick="removeRow(this)">
                    <i class="ph-bold ph-trash fs-6"></i>
                </button>
            </td>
            <td class="col-date"><input type="date" class="row-in in-date" value="${date}" required></td>
            
            <td class="col-client"><select class="row-in in-client">${getSelectOptions(clientChoices, client)}</select></td>
            <td class="col-gender"><select class="row-in in-gender">${getSelectOptions(genderChoices, gender)}</select></td>
            <td class="col-age"><select class="row-in in-age">${getSelectOptions(ageChoices, age)}</select></td>
            <td class="col-reg"><input type="text" class="row-in in-reg" value="Region II" readonly></td>
            <td class="col-prov"><input type="text" class="row-in in-prov" value="Isabela" readonly></td>
            
            <td class="col-muni"><select class="row-in in-muni">${getSelectOptions(isabelaLocations, muni)}</select></td>
            <td class="col-trans"><select class="row-in in-trans">${getSelectOptions(transactionTypes, trans)}</select></td>
            
            <td class="col-score"><select class="row-in in-cc1">${getSelectOptions(ccChoices, cc1)}</select></td>
            <td class="col-score"><select class="row-in in-cc2">${getSelectOptions(ccChoices, cc2)}</select></td>
            <td class="col-score"><select class="row-in in-cc3">${getSelectOptions(ccChoices, cc3)}</select></td>
            
            <td class="col-sqd0 td-sqd0"><select class="row-in in-sqd0 fw-bold text-success">${getSelectOptions(sqdChoices, s0)}</select></td>
            <td class="col-score"><select class="row-in in-sqd1">${getSelectOptions(sqdChoices, s1)}</select></td>
            <td class="col-score"><select class="row-in in-sqd2">${getSelectOptions(sqdChoices, s2)}</select></td>
            <td class="col-score"><select class="row-in in-sqd3">${getSelectOptions(sqdChoices, s3)}</select></td>
            <td class="col-score"><select class="row-in in-sqd4">${getSelectOptions(sqdChoices, s4)}</select></td>
            <td class="col-score"><select class="row-in in-sqd5">${getSelectOptions(sqdChoices, s5)}</select></td>
            <td class="col-score"><select class="row-in in-sqd6">${getSelectOptions(sqdChoices, s6)}</select></td>
            <td class="col-score"><select class="row-in in-sqd7">${getSelectOptions(sqdChoices, s7)}</select></td>
            <td class="col-score"><select class="row-in in-sqd8">${getSelectOptions(sqdChoices, s8)}</select></td>
        `;
        document.getElementById('bulkBody').appendChild(tr);
    }

    function removeRow(btn) {
        btn.closest('tr').remove();
        rowCount--;
        updateCounter();
    }

    function clearAllRows() {
        document.getElementById('bulkBody').innerHTML = '';
        rowCount = 0;
        updateCounter();
    }

    function updateCounter() {
        const badge = document.getElementById('rowCountBadge');
        const text = rowCount === 1 ? "1 Record Staged" : `${rowCount} Records Staged`;
        badge.innerText = text;
        if (rowCount > 0) {
            badge.className = "pill-badge pill-emerald";
        } else {
            badge.className = "pill-badge pill-slate";
        }
    }

    // FORM SUBMISSION: BUNDLE ALL STAGED ROWS INTO SINGLE JSON PAYLOAD
    $('#bulkForm').on('submit', function(e) {
        const rows = $('#bulkBody tr');
        if (rows.length === 0) {
            e.preventDefault();
            alert('No staged records found. Please generate or add rows first.');
            return false;
        }

        const records = [];
        rows.each(function() {
            const tr = $(this);
            records.push({
                survey_date:      tr.find('.in-date').val() || '',
                client_type:      tr.find('.in-client').val() || 'Citizen',
                gender:           tr.find('.in-gender').val() || 'Male',
                age_group:        tr.find('.in-age').val() || '20-29',
                region:           tr.find('.in-reg').val() || 'Region II',
                province:         tr.find('.in-prov').val() || 'Isabela',
                municipality:     tr.find('.in-muni').val() || 'Ilagan City',
                transaction_type: tr.find('.in-trans').val() || 'Certification/CTC/CPC',
                cc1:              tr.find('.in-cc1').val() || '3',
                cc2:              tr.find('.in-cc2').val() || '3',
                cc3:              tr.find('.in-cc3').val() || '3',
                sqd0:             tr.find('.in-sqd0').val() || '5',
                sqd1:             tr.find('.in-sqd1').val() || '5',
                sqd2:             tr.find('.in-sqd2').val() || '5',
                sqd3:             tr.find('.in-sqd3').val() || '5',
                sqd4:             tr.find('.in-sqd4').val() || '5',
                sqd5:             tr.find('.in-sqd5').val() || '5',
                sqd6:             tr.find('.in-sqd6').val() || '5',
                sqd7:             tr.find('.in-sqd7').val() || '5',
                sqd8:             tr.find('.in-sqd8').val() || '5'
            });
        });

        $('#bulkJson').val(JSON.stringify(records));
        
        // Remove name attributes from any table inputs just in case to ensure 
        // the browser sends ONLY the single bulk_json variable
        $('#bulkBody').find('input, select').removeAttr('name');

        const btn = $(this).find('button[type="submit"]');
        btn.css('pointer-events', 'none').css('opacity', '0.75').html('<i class="ph-bold ph-spinner-gap me-2"></i> Saving Records...');
        return true;
    });

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
</script>
<script src="darkmode.js"></script>
</body>
</html>
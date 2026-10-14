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

// 4. RETRIEVE MONTH TALLIES (Executed after all POST operations so tallies immediately reflect new records)
$month_tallies = [];
try {
    $mt_stmt = $pdo->query("SELECT DATE_FORMAT(survey_date, '%Y-%m') as ym, DATE_FORMAT(survey_date, '%M %Y') as m_label, COUNT(*) as total_cnt, SUM(CASE WHEN control_number LIKE '%B' OR control_number LIKE 'BLK-%' THEN 1 ELSE 0 END) as bulk_cnt FROM responses WHERE survey_date IS NOT NULL AND survey_date != '0000-00-00' GROUP BY ym, m_label ORDER BY ym DESC");
    $month_tallies = $mt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
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
                        <h6 class="card-title-clean">Smart Assessment Generator</h6>
                        <span class="text-muted" style="font-size: 0.76rem;">Directly generates and commits synthetic survey records to the database. Excludes PH holidays & weekends.</span>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
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
            
            <form id="generatorForm" method="POST" action="admin_bulk.php">
                <input type="hidden" name="bulk_json" id="bulkJsonInput" value="">
                
                <div class="row g-3 align-items-end pt-2">
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label text-muted small fw-bold mb-1">Target Month</label>
                        <input type="month" id="genMonth" class="form-control form-control-sm fw-semibold" value="<?= date('Y-m') ?>" required>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label text-muted small fw-bold mb-1">Record Count</label>
                        <input type="number" id="genCount" class="form-control form-control-sm fw-semibold" value="10" min="1" max="500" required>
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
                        <select id="genTransaction" class="form-select form-select-sm fw-semibold">
                            <option value="Random">🎲 Randomize (All 13 Types Evenly)</option>
                            <option value="RandomWeighted">🎲 Randomize (Weighted - Cert/Trans/Sub)</option>
                            <optgroup label="Select Specific Transaction">
                            <?php foreach ($transactionTypes as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-3 mt-3 border-top flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="ph-bold ph-info text-primary fs-5"></i>
                        <span>Generates realistic data with control numbers (<code class="text-primary fw-bold">E{MM}-{seq}B</code>) and saves directly to database in real time.</span>
                    </div>
                    <button type="submit" id="generateBtn" class="btn-modern-primary py-2 px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="ph-bold ph-lightning text-warning fs-5"></i>
                        <span id="generateBtnText">Generate & Save to Database</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- DATABASE RECORDS SUMMARY BY MONTH CARD -->
        <div class="card-minimal">
            <div class="card-header-clean flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="text-primary fs-4 d-flex align-items-center">
                        <i class="ph-bold ph-database"></i>
                    </div>
                    <div>
                        <h6 class="card-title-clean">Database Records by Month</h6>
                        <span class="text-muted" style="font-size: 0.76rem;">Current distribution of bulk-generated records versus real citizen responses.</span>
                    </div>
                </div>
                
                <?php 
                    $total_all_records = 0;
                    $total_bulk_records = 0;
                    foreach ($month_tallies as $mt) {
                        $total_all_records += (int)$mt['total_cnt'];
                        $total_bulk_records += (int)$mt['bulk_cnt'];
                    }
                ?>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="pill-badge pill-slate">Total: <?= number_format($total_all_records) ?> Records</span>
                    <span class="pill-badge pill-blue">Bulk: <?= number_format($total_bulk_records) ?></span>
                    <span class="pill-badge pill-emerald">Real: <?= number_format(max(0, $total_all_records - $total_bulk_records)) ?></span>
                </div>
            </div>

            <?php if (!empty($month_tallies)): ?>
                <div class="summary-table-container">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Target Month</th>
                                <th class="text-center">Bulk Generated</th>
                                <th class="text-center">Real Citizen</th>
                                <th class="text-center">Total Records</th>
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
                                            <?= number_format($bulkCount) ?> records
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="pill-badge pill-emerald fw-bold">
                                            <?= number_format($realCount) ?> records
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="pill-badge pill-slate fw-bold">
                                            <?= number_format($totalCount) ?> total
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 py-1 px-2.5 d-inline-flex align-items-center gap-1.5 fw-semibold" onclick="openWipeForMonth('<?= htmlspecialchars($mt['ym']) ?>')" title="Wipe records for <?= htmlspecialchars($mt['m_label']) ?>">
                                            <i class="ph-bold ph-calendar-x"></i> Wipe Month
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="text-muted fs-1 mb-2">
                        <i class="ph-bold ph-tray"></i>
                    </div>
                    <h6 class="fw-bold text-dark">No Survey Records in Database</h6>
                    <p class="text-muted small mb-0">Use the Smart Assessment Generator above to generate test or bulk records.</p>
                </div>
            <?php endif; ?>
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

    // DIRECT GENERATOR & DATABASE SUBMISSION
    $('#generatorForm').on('submit', function(e) {
        e.preventDefault();

        const count = parseInt($('#genCount').val()) || 10;
        const monthVal = $('#genMonth').val(); // e.g. "2026-10"
        const clientMode = $('#genClient').val();
        const transMode = $('#genTransaction').val();
        const muniMode = $('#genMuni').val();

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
        const sqdScores = ["4", "5"];

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
            const getRandSqd = () => sqdScores[Math.floor(Math.random() * sqdScores.length)];
            const getRandCc = () => ccScores[Math.floor(Math.random() * ccScores.length)];

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
                sqd0:             getRandSqd(),
                sqd1:             getRandSqd(),
                sqd2:             getRandSqd(),
                sqd3:             getRandSqd(),
                sqd4:             getRandSqd(),
                sqd5:             getRandSqd(),
                sqd6:             getRandSqd(),
                sqd7:             getRandSqd(),
                sqd8:             getRandSqd()
            });
        }

        $('#bulkJsonInput').val(JSON.stringify(records));

        const btn = $('#generateBtn');
        btn.prop('disabled', true).css('opacity', '0.8');
        $('#generateBtnText').text('Generating & Saving ' + count + ' Records...');
        btn.find('i').removeClass('ph-lightning text-warning').addClass('ph-spinner-gap spin');

        HTMLFormElement.prototype.submit.call(this);
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
</script>
<script src="darkmode.js"></script>
</body>
</html>
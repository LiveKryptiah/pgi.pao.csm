<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="pao.ico">
    <title>Pagsusukat ng Kasiyahan ng Kliyente (Client Satisfaction Measurement - CSM) — Provincial Assessor of Isabela</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

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
            
            /* 4 FOCUS COLORS */
            --primary: #059669;
            --c-green: #059669;
            --c-green-light: #ecfdf5;
            --c-green-border: #d1fae5;
            
            --c-blue: #2563eb;
            --c-blue-light: #eff6ff;
            --c-blue-border: #dbeafe;
            
            --c-yellow: #f59e0b;
            --c-yellow-light: #fffbeb;
            --c-yellow-border: #fef3c7;
            
            --c-red: #dc2626;
            --c-red-light: #fef2f2;
            --c-red-border: #fee2e2;
            
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            
            --radius-card: 16px;
            --radius-btn: 12px;
            --shadow-card: 0 1px 3px 0 rgb(0 0 0 / 0.04), 0 1px 2px -1px rgb(0 0 0 / 0.02);
            --shadow-hover: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        .survey-wrapper {
            max-width: 860px;
            margin: 0 auto;
            padding: 24px 16px 48px 16px;
        }

        /* HEADER BRAND CARD (WITH 4-COLOR FOCUS GRADIENT STRIPE) */
        .brand-header-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 26px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .header-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.06));
            transition: transform 0.2s ease;
        }
        .header-logo:hover {
            transform: scale(1.05);
        }

        @media (max-width: 576px) {
            .header-logo {
                width: 50px;
                height: 50px;
            }
        }

        .header-agency-title {
            font-size: clamp(0.95rem, 2.5vw, 1.35rem);
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.01em;
            line-height: 1.25;
            margin-bottom: 3px;
        }

        .header-office-title {
            font-size: clamp(0.85rem, 2vw, 1.08rem);
            font-weight: 700;
            color: #047857;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        /* PILLS & BADGES (4 FOCUS COLORS) */
        .pill-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }
        .pill-emerald { background-color: var(--c-green-light); color: #065f46; border: 1px solid var(--c-green-border); }
        .pill-blue    { background-color: var(--c-blue-light); color: #1e40af; border: 1px solid var(--c-blue-border); }
        .pill-amber   { background-color: var(--c-yellow-light); color: #92400e; border: 1px solid var(--c-yellow-border); }
        .pill-rose    { background-color: var(--c-red-light); color: #991b1b; border: 1px solid var(--c-red-border); }
        .pill-slate   { background-color: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }

        /* FORM SECTION CARDS WITH 4-COLOR ACCENT BORDERS */
        .csm-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px 28px;
            margin-bottom: 24px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .csm-card:hover {
            box-shadow: var(--shadow-hover);
        }
        @media (max-width: 576px) {
            .csm-card {
                padding: 18px 16px;
            }
        }

        .csm-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-light);
            gap: 12px;
        }

        .csm-card-title {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .csm-card-subtitle {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 3px;
            margin-bottom: 0;
        }

        /* FORM CONTROLS */
        .form-label-modern {
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-body);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }

        .form-control-modern,
        .form-select-modern {
            background-color: #ffffff;
            border: 1.5px solid var(--border-subtle);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
            color: var(--text-heading);
            transition: all 0.15s ease;
            width: 100%;
        }

        .form-control-modern:focus,
        .form-select-modern:focus {
            border-color: var(--c-green);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
            outline: none;
            background-color: #ffffff;
        }

        .form-select-modern option[hidden] {
            display: none !important;
        }

        /* QUESTION CARDS INSIDE SECTIONS */
        .question-box {
            background-color: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 16px;
            transition: border-color 0.15s ease;
        }
        .question-box:hover {
            border-color: var(--border-subtle);
        }

        .question-number-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }
        .question-number-pill.pill-green  { background-color: var(--c-green-light); color: var(--c-green); }
        .question-number-pill.pill-blue   { background-color: var(--c-blue-light); color: var(--c-blue); }
        .question-number-pill.pill-yellow { background-color: var(--c-yellow-light); color: #d97706; }
        .question-number-pill.pill-red    { background-color: var(--c-red-light); color: var(--c-red); }

        /* INTERACTIVE LIKERT RATING GRID (4 FOCUS COLORS) */
        .likert-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        @media (max-width: 768px) {
            .likert-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
        }

        .likert-tile {
            position: relative;
            cursor: pointer;
            display: block;
            margin: 0;
            user-select: none;
        }

        .likert-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .likert-content {
            background-color: #ffffff;
            border: 1.5px solid var(--border-subtle);
            border-radius: 12px;
            padding: 8px 4px;
            text-align: center;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 62px;
        }

        .likert-emoji {
            font-size: 1.65rem;
            line-height: 1;
            margin-bottom: 2px;
            transition: transform 0.15s ease;
            display: inline-block;
        }

        .likert-num {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.1;
        }

        .likert-tile:hover .likert-content {
            transform: translateY(-2px);
        }

        .likert-tile:hover .likert-emoji {
            transform: scale(1.15);
        }

        /* 1: RED (Lubos na hindi sumasang-ayon / Strongly Disagree) */
        .likert-tile.val-1:hover .likert-content { border-color: #fca5a5; background-color: #fef2f2; }
        .likert-tile.val-1 .likert-input:checked + .likert-content {
            border-color: var(--c-red);
            background-color: var(--c-red-light);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
            transform: translateY(-2px);
        }
        .likert-tile.val-1 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-1 .likert-input:checked + .likert-content .likert-label { color: #b91c1c; font-weight: 700; }
        .likert-tile.val-1 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* 2: YELLOW (Hindi sumasang-ayon / Disagree) */
        .likert-tile.val-2:hover .likert-content { border-color: #fde68a; background-color: #fffbeb; }
        .likert-tile.val-2 .likert-input:checked + .likert-content {
            border-color: var(--c-yellow);
            background-color: var(--c-yellow-light);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.20);
            transform: translateY(-2px);
        }
        .likert-tile.val-2 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-2 .likert-input:checked + .likert-content .likert-label { color: #b45309; font-weight: 700; }
        .likert-tile.val-2 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* 3: BLUE (Walang kinikilingan / Neutral) */
        .likert-tile.val-3:hover .likert-content { border-color: #bfdbfe; background-color: #eff6ff; }
        .likert-tile.val-3 .likert-input:checked + .likert-content {
            border-color: var(--c-blue);
            background-color: var(--c-blue-light);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
            transform: translateY(-2px);
        }
        .likert-tile.val-3 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-3 .likert-input:checked + .likert-content .likert-label { color: #1d4ed8; font-weight: 700; }
        .likert-tile.val-3 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* 4: LIGHT GREEN (Sumasang-ayon / Agree) */
        .likert-tile.val-4:hover .likert-content { border-color: #a7f3d0; background-color: #f0fdf4; }
        .likert-tile.val-4 .likert-input:checked + .likert-content {
            border-color: #10b981;
            background-color: #f0fdf4;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
            transform: translateY(-2px);
        }
        .likert-tile.val-4 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-4 .likert-input:checked + .likert-content .likert-label { color: #047857; font-weight: 700; }
        .likert-tile.val-4 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* 5: DEEP GREEN (Lubos na sumasang-ayon / Strongly Agree) */
        .likert-tile.val-5:hover .likert-content { border-color: #6ee7b7; background-color: #ecfdf5; }
        .likert-tile.val-5 .likert-input:checked + .likert-content {
            border-color: var(--c-green);
            background-color: var(--c-green-light);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.22);
            transform: translateY(-2px);
        }
        .likert-tile.val-5 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-5 .likert-input:checked + .likert-content .likert-label { color: #065f46; font-weight: 700; }
        .likert-tile.val-5 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* 6: SLATE (Hindi Naaangkop / Not Applicable) */
        .likert-tile.val-6:hover .likert-content { border-color: #cbd5e1; background-color: #f8fafc; }
        .likert-tile.val-6 .likert-input:checked + .likert-content {
            border-color: #64748b;
            background-color: #f1f5f9;
            box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.18);
            transform: translateY(-2px);
        }
        .likert-tile.val-6 .likert-input:checked + .likert-content .likert-num,
        .likert-tile.val-6 .likert-input:checked + .likert-content .likert-label { color: #334155; font-weight: 700; }
        .likert-tile.val-6 .likert-input:checked + .likert-content .likert-emoji { transform: scale(1.25); }

        /* RATING SCALE LEGEND BAR */
        .rating-legend-bar {
            background-color: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.78rem;
        }

        /* SECTION 3 (SQD): REMOVE GREY BACKGROUNDS & STROKE LINES */
        .section-sqd .rating-legend-bar,
        .section-sqd .question-box {
            background-color: transparent !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding-left: 0;
            padding-right: 0;
        }
        .section-sqd .rating-legend-bar {
            padding-top: 0;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .section-sqd .question-box {
            padding-top: 10px;
            padding-bottom: 14px;
            margin-bottom: 12px;
        }
        .section-sqd .question-box:hover {
            border-color: transparent !important;
        }

        /* SUBMIT BUTTON */
        .btn-submit-csm {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-btn);
            padding: 16px 28px;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            width: 100%;
            cursor: pointer;
            box-shadow: none;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit-csm:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            transform: translateY(-2px);
            box-shadow: none;
            color: #ffffff;
        }

        .btn-submit-csm:active {
            transform: translateY(0);
        }

        /* ADMIN SHORTCUT LINK */
        .btn-admin-link {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
        }
        .btn-admin-link:hover {
            color: var(--text-heading);
            background-color: var(--border-light);
            border-color: #cbd5e1;
        }

        /* FOOTER */
        .survey-footer {
            text-align: center;
            padding: 24px 0 12px 0;
            color: var(--text-muted);
            font-size: 0.76rem;
            line-height: 1.5;
        }
        /* TOP FLOATING CONTROLS BAR (LANG SWITCHER + THEME) */
        .top-controls-bar {
            position: fixed;
            top: 14px;
            right: 18px;
            z-index: 1050;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-subtle);
            padding: 4px 6px;
            border-radius: 999px;
            box-shadow: none;
            transition: all 0.2s ease;
        }

        [data-bs-theme="dark"] .top-controls-bar {
            background: rgba(17, 24, 39, 0.92);
            border-color: var(--border-subtle);
            box-shadow: none;
        }

        .lang-switcher-pills {
            display: inline-flex;
            align-items: center;
            background: rgba(148, 163, 184, 0.12);
            border-radius: 999px;
            padding: 3px;
            border: 1px solid var(--border-subtle);
            box-shadow: none;
        }

        .lang-btn {
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            padding: 5px 13px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            line-height: 1;
            user-select: none;
            box-shadow: none;
        }

        .lang-btn:hover {
            color: var(--text-heading);
            background: rgba(0, 0, 0, 0.05);
            box-shadow: none;
        }

        .lang-btn.active {
            background-color: #059669 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .lang-switcher-pills {
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: none;
        }

        [data-bs-theme="dark"] .lang-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            box-shadow: none;
        }

        [data-bs-theme="dark"] .lang-btn.active {
            background-color: #10b981 !important;
            color: #042f2e !important;
            font-weight: 800;
            box-shadow: none !important;
        }

        .lang-flag {
            font-size: 0.95rem;
            line-height: 1;
        }

        @media (max-width: 576px) {
            .top-controls-bar {
                position: sticky;
                top: 8px;
                right: auto;
                left: auto;
                margin: 0 auto 12px auto;
                width: fit-content;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<!-- TOP FLOATING CONTROLS BAR (LANGUAGE SWITCHER + THEME) -->
<div class="top-controls-bar">
    <div class="lang-switcher-pills" role="group" aria-label="Select Language">
        <button type="button" class="lang-btn active" data-lang="fil" onclick="switchLanguage('fil')" title="Wikang Tagalog">
            <span class="lang-flag">🇵🇭</span> <span>Tagalog</span>
        </button>
        <button type="button" class="lang-btn" data-lang="en" onclick="switchLanguage('en')" title="English">
            <span class="lang-flag">🇺🇸</span> <span>English</span>
        </button>
        <button type="button" class="lang-btn" data-lang="ilo" onclick="switchLanguage('ilo')" title="Pagsasao nga Ilokano">
            <span class="lang-flag">🌾</span> <span>Ilokano</span>
        </button>
    </div>

    <!-- Theme Toggle Button -->
    <button type="button" class="theme-toggle-btn border-0 py-1 px-2" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
        <i class="ph-bold ph-moon fs-5 theme-icon-moon"></i>
        <i class="ph-bold ph-sun fs-5 theme-icon-sun d-none"></i>
    </button>
</div>

<div class="survey-wrapper">

    <!-- ==========================================
         TOP BRAND HEADER CARD
    =========================================== -->
    <header class="brand-header-card">
        <div class="d-flex align-items-center justify-content-between text-center gap-2 gap-sm-3">
            <img src="image_4c6d1c.png" alt="Province of Isabela Seal" class="header-logo flex-shrink-0">
            <div class="flex-grow-1 px-1">
                <div class="header-agency-title" id="agencyTitle">PAMAHALAANG PANLALAWIGAN NG ISABELA</div>
                <div class="header-office-title" id="officeTitle">TANGGAPAN NG PANLALAWIGANG TAGAPAGTASA</div>
                <p class="text-muted small mb-0" id="csmSub">Pagsusukat ng Kasiyahan ng Kliyente (CSM)</p>
            </div>
            <img src="image_4c6ce6.jpg" alt="Provincial Assessor Logo" class="header-logo flex-shrink-0">
        </div>
    </header>

    <!-- ==========================================
         SURVEY FORM
    =========================================== -->
    <form action="submit.php" method="POST" id="csmForm">

        <!-- ==========================================
             SECTION 1: DEMOGRAPHIC INFORMATION (GREEN)
        =========================================== -->
        <section class="csm-card">
            <div class="csm-card-header">
                <div>
                    <h5 class="csm-card-title">
                        <i class="ph-bold ph-identification-card text-success me-2"></i><span id="sec1Title">1. Impormasyon ng Kliyente</span>
                    </h5>
                    <p class="csm-card-subtitle" id="sec1Sub">Punan ang mga batayang impormasyon ng transaksiyon</p>
                </div>
                <span class="pill-badge pill-emerald" id="sec1Badge">Bahagi 1</span>
            </div>

            <div class="row g-3">
                <!-- Date -->
                <div class="col-md-6">
                    <label class="form-label-modern" for="survey_date">
                        <i class="ph-bold ph-calendar-blank me-1.5 text-success"></i><span id="labelDate">Petsa</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <input type="date" class="form-control-modern" name="survey_date" id="survey_date" required>
                </div>

                <!-- Client Type -->
                <div class="col-md-6">
                    <label class="form-label-modern" for="client_type">
                        <i class="ph-bold ph-user me-1.5 text-success"></i><span id="labelClientType">Uri ng Kliyente</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select name="client_type" id="client_type" class="form-select-modern" required>
                        <option value="" disabled selected hidden id="optClientPlaceholder">Pumili ng Uri ng Kliyente...</option>
                        <option value="Mamamayan" id="optClientCitizen">Mamamayan</option>
                        <option value="Negosyo" id="optClientBusiness">Negosyo</option>
                        <option value="Gobyerno" id="optClientGov">Gobyerno</option>
                    </select>
                </div>

                <!-- Gender -->
                <div class="col-md-6">
                    <label class="form-label-modern" for="gender">
                        <i class="ph-bold ph-gender-intersex me-1.5 text-success"></i><span id="labelGender">Kasarian</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select name="gender" id="gender" class="form-select-modern" required>
                        <option value="" disabled selected hidden id="optGenderPlaceholder">Pumili ng Kasarian...</option>
                        <option value="Babae" id="optGenderFemale">Babae</option>
                        <option value="Lalake" id="optGenderMale">Lalake</option>
                    </select>
                </div>

                <!-- Age Group -->
                <div class="col-md-6">
                    <label class="form-label-modern" for="age_group">
                        <i class="ph-bold ph-users me-1.5 text-success"></i><span id="labelAge">Edad</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select name="age_group" id="age_group" class="form-select-modern" required>
                        <option value="" disabled selected hidden id="optAgePlaceholder">Pumili ng Edad...</option>
                        <option value="19 and below" id="optAge19">19 pababa</option>
                        <option value="20-34" id="optAge20">20-34 taong gulang</option>
                        <option value="35-49" id="optAge35">35-49 taong gulang</option>
                        <option value="50-64" id="optAge50">50-64 taong gulang</option>
                        <option value="65 and above" id="optAge65">65 at pataas</option>
                    </select>
                </div>

                <!-- Region -->
                <div class="col-md-6" id="div-region">
                    <label class="form-label-modern" for="region">
                        <i class="ph-bold ph-compass me-1.5 text-success"></i><span id="labelRegion">Rehiyon</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select class="form-select-modern" name="region" id="region" required onchange="handleLocationLogic()">
                        <option value="Region II" selected>Region II (Cagayan Valley)</option>
                        <option value="NCR">NCR (National Capital Region)</option>
                        <option value="Region I">Region I (Ilocos Region)</option>
                        <option value="Region III">Region III (Central Luzon)</option>
                        <option value="Region IV">Region IV-A (CALABARZON)</option>
                        <option value="MIMAROPA Region">MIMAROPA Region</option>
                        <option value="Region V">Region V (Bicol Region)</option>
                        <option value="Region VI">Region VI (Western Visayas)</option>
                        <option value="Region VII">Region VII (Central Visayas)</option>
                        <option value="Region VIII">Region VIII (Eastern Visayas)</option>
                        <option value="Negros Island Region">Negros Island Region (NIR)</option>
                        <option value="Region IX">Region IX (Zamboanga Peninsula)</option>
                        <option value="Region X">Region X (Northern Mindanao)</option>
                        <option value="Region XII">Region XII (SOCCSKSARGEN)</option>
                        <option value="Region XIII">Region XIII (Caraga)</option>
                        <option value="Bangsamoro">Bangsamoro Autonomous Region (BARMM)</option>
                    </select>
                </div>

                <!-- Province -->
                <div class="col-md-6" id="div-province">
                    <label class="form-label-modern" for="province">
                        <i class="ph-bold ph-map-pin me-1.5 text-success"></i><span id="labelProvince">Lalawigan</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select class="form-select-modern" name="province" id="province" required onchange="handleLocationLogic()">
                        <option value="Isabela" selected id="optProvIsabela">Isabela</option>
                        <option value="Outside Isabela" id="optProvOutside">Ibang Lalawigan</option>
                    </select>
                </div>

                <!-- Municipality -->
                <div class="col-md-6" id="div-municipality">
                    <label class="form-label-modern" for="municipality">
                        <i class="ph-bold ph-buildings me-1.5 text-success"></i><span id="labelMuni">Lungsod/Bayan</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select class="form-select-modern" name="municipality" id="municipality" required>
                        <option value="" disabled selected hidden id="optMuniPlaceholder">Pumili ng Lungsod/Bayan...</option>
                    </select>
                </div>

                <!-- Transaction Type -->
                <div class="col-md-6" id="div-transaction">
                    <label class="form-label-modern" for="transaction_type">
                        <i class="ph-bold ph-briefcase me-1.5 text-success"></i><span id="labelTrans">Uri ng Transaksiyon</span> <span class="text-danger ms-1">*</span>
                    </label>
                    <select name="transaction_type" id="transaction_type" class="form-select-modern" required>
                        <option value="" disabled selected hidden id="optTransPlaceholder">Pumili ng Uri ng Transaksiyon...</option>
                        <option value="Transfer">Transfer</option>
                        <option value="Subdivision/Consolidation">Subdivision / Consolidation</option>
                        <option value="Reclassification (Agri to Urban)">Reclassification (Agri to Urban)</option>
                        <option value="Reclassification (Urban to Urban)">Reclassification (Urban to Urban)</option>
                        <option value="Reassessment">Reassessment (DP/PC/DT)</option>
                        <option value="Discovery/New Declaration">Discovery / New Declaration</option>
                        <option value="Certification/CTC/CPC">Certification / CTC / CPC</option>
                        <option value="Verification/Back Tracking">Verification / Back Tracking</option>
                        <option value="Annotation/Cancellation">Annotation / Cancellation of Annotation</option>
                        <option value="Ocular Inspection">Ocular Inspection</option>
                        <option value="Cancellation of TD">Cancellation of TD</option>
                        <option value="TMCR/Section Maps">TMCR / Section Map/s</option>
                        <option value="Posting">Posting</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- ==========================================
             SECTION 2: CITIZEN'S CHARTER (BLUE)
        =========================================== -->
        <section class="csm-card">
            <div class="csm-card-header">
                <div>
                    <h5 class="csm-card-title">
                        <i class="ph-bold ph-book-open-text text-primary me-2"></i><span id="sec2Title">2. Citizen's Charter (CC)</span>
                    </h5>
                    <p class="csm-card-subtitle" id="sec2Sub">Kaalaman at karanasan sa Citizen's Charter</p>
                </div>
                <span class="pill-badge pill-blue" id="sec2Badge">Bahagi 2</span>
            </div>

            <!-- CC1 -->
            <div class="question-box">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="question-number-pill pill-blue">CC1</span>
                    <label class="form-label-modern mb-0" for="cc1">
                        <span id="labelCC1">Alin sa mga sumusunod ang naglalarawan sa iyong kaalaman sa CC?</span> <span class="text-danger ms-1">*</span>
                    </label>
                </div>
                <select name="cc1" id="cc1" class="form-select-modern" required>
                    <option value="" disabled selected hidden id="optCC1Placeholder">Pumili ng sagot...</option>
                    <option value="1" id="optCC1_1">1. Alam ko ang CC at nakita ko ito sa napuntahang opisina.</option>
                    <option value="2" id="optCC1_2">2. Alam ko ang CC pero hindi ko ito nakita sa napuntahang opisina.</option>
                    <option value="3" id="optCC1_3">3. Nalaman ko ang CC nang makita ko ito sa napuntahang opisina.</option>
                    <option value="4" id="optCC1_4">4. Hindi ko alam kung ano ang CC at wala akong nakita sa napuntahang opisina.</option>
                </select>
            </div>

            <!-- CC2 -->
            <div class="question-box">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="question-number-pill pill-blue">CC2</span>
                    <label class="form-label-modern mb-0" for="cc2">
                        <span id="labelCC2">Kung alam ang CC, masasabi mo ba na ang CC ng napuntahang opisina ay:</span> <span class="text-danger ms-1">*</span>
                    </label>
                </div>
                <select name="cc2" id="cc2" class="form-select-modern" required>
                    <option value="" disabled selected hidden id="optCC2Placeholder">Pumili ng sagot...</option>
                    <option value="Madaling Makita" id="optCC2_1">Madaling Makita</option>
                    <option value="Medyo Madaling Makita" id="optCC2_2">Medyo Madaling Makita</option>
                    <option value="Mahirap Makita" id="optCC2_3">Mahirap Makita</option>
                    <option value="Hindi makita" id="optCC2_4">Hindi Makita</option>
                    <option value="N/A" id="optCC2_NA">Hindi Naaangkop</option>
                </select>
            </div>

            <!-- CC3 -->
            <div class="question-box mb-0">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="question-number-pill pill-blue">CC3</span>
                    <label class="form-label-modern mb-0" for="cc3">
                        <span id="labelCC3">Kung alam ang CC, gaano nakakatulong ang CC sa transaksiyon mo?</span> <span class="text-danger ms-1">*</span>
                    </label>
                </div>
                <select name="cc3" id="cc3" class="form-select-modern" required>
                    <option value="" disabled selected hidden id="optCC3Placeholder">Pumili ng sagot...</option>
                    <option value="Sobrang Nakakatulong" id="optCC3_1">Sobrang Nakakatulong</option>
                    <option value="Nakakatulong naman" id="optCC3_2">Nakakatulong naman</option>
                    <option value="Hindi nakatulong" id="optCC3_3">Hindi Nakatulong</option>
                    <option value="N/A" id="optCC3_NA">Hindi Naaangkop</option>
                </select>
            </div>
        </section>

        <!-- ==========================================
             SECTION 3: SERVICE QUALITY DIMENSIONS (YELLOW)
        =========================================== -->
        <section class="csm-card section-sqd">
            <div class="csm-card-header">
                <div>
                    <h5 class="csm-card-title">
                        <i class="ph-bold ph-star text-warning me-2"></i><span id="sec3Title">3. Sukat ng Kalidad ng Serbisyo (SQD)</span>
                    </h5>
                    <p class="csm-card-subtitle" id="sec3Sub">Piliin ang pinaka-angkop na marka para sa bawat aspeto</p>
                </div>
                <span class="pill-badge pill-amber" id="sec3Badge">Bahagi 3</span>
            </div>

            <!-- Rating Scale Guide -->
            <div class="rating-legend-bar mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span class="fw-bold text-dark d-flex align-items-center gap-1" id="ratingGuideTitle">
                        <i class="ph-bold ph-info text-warning"></i> Gabay sa Pagmamarka:
                    </span>
                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                        <span><span class="text-danger fw-bold">☹️ 1</span> <span class="text-muted" id="legend1">Lubos na di sumasang-ayon</span></span>
                        <span><span class="text-warning fw-bold">🙁 2</span> <span class="text-muted" id="legend2">Di sumasang-ayon</span></span>
                        <span><span class="text-primary fw-bold">😐 3</span> <span class="text-muted" id="legend3">Neutral</span></span>
                        <span><span class="text-success fw-bold">🙂 4</span> <span class="text-muted" id="legend4">Sumasang-ayon</span></span>
                        <span><span class="text-success fw-bold">😄 5</span> <span class="text-muted" id="legend5">Lubos na sumasang-ayon</span></span>
                        <span><span class="text-secondary fw-bold">🚫 N/A</span> <span class="text-muted" id="legendNA">Hindi Naaangkop</span></span>
                    </div>
                </div>
            </div>

            <?php
            $sqd_questions = [
                "sqd0" => [
                    "code" => "SQD0",
                    "text" => "SQD0. Nasiyahan ako sa serbisyo na aking natanggap."
                ],
                "sqd1" => [
                    "code" => "SQD1",
                    "text" => "SQD1. Makatwiran ang oras na aking ginugol para sa pagproseso ng aking transaksyon."
                ],
                "sqd2" => [
                    "code" => "SQD2",
                    "text" => "SQD2. Ang opisina ay sumusunod sa mga kinakailangang dokumento at mga hakbang batay sa impormasyong ibinigay."
                ],
                "sqd3" => [
                    "code" => "SQD3",
                    "text" => "SQD3. Ang mga hakbang sa pagproseso, kasama na ang pagbayad ay madali at simple lamang."
                ],
                "sqd4" => [
                    "code" => "SQD4",
                    "text" => "SQD4. Mabilis at madali akong nakahanap ng impormasyon tungkol sa aking transaksiyon mula sa opisina o website."
                ],
                "sqd5" => [
                    "code" => "SQD5",
                    "text" => "SQD5. Nagbayad ako ng makatwirang halaga para sa aking transaksiyon."
                ],
                "sqd6" => [
                    "code" => "SQD6",
                    "text" => "SQD6. Pakiramdam ko ay patas ang opisina sa lahat, o 'walang palakasan', sa aking transaksiyon."
                ],
                "sqd7" => [
                    "code" => "SQD7",
                    "text" => "SQD7. Magalang akong trinato ng mga tauhan, at sila ay handang tumulong sa akin."
                ],
                "sqd8" => [
                    "code" => "SQD8",
                    "text" => "SQD8. Nakuha ko ang kinakailangan ko mula sa tanggapan, o kung tinanggihan man, ito ay sapat na ipinaliwanag."
                ]
            ];

            // 4 Focus Colors mapping: 1 (Red), 2 (Yellow), 3 (Blue), 4 (Light Green), 5 (Deep Green), 6 (Slate)
            $options = [
                "1" => ["emoji" => "☹️", "num" => "1", "label" => "Lubos na di sumasang-ayon", "val" => "1", "safe_id" => "1", "cls" => "val-1"],
                "2" => ["emoji" => "🙁", "num" => "2", "label" => "Hindi sumasang-ayon", "val" => "2", "safe_id" => "2", "cls" => "val-2"],
                "3" => ["emoji" => "😐", "num" => "3", "label" => "Walang kinikilingan", "val" => "3", "safe_id" => "3", "cls" => "val-3"],
                "4" => ["emoji" => "🙂", "num" => "4", "label" => "Sumasang-ayon", "val" => "4", "safe_id" => "4", "cls" => "val-4"],
                "5" => ["emoji" => "😄", "num" => "5", "label" => "Lubos na sumasang-ayon", "val" => "5", "safe_id" => "5", "cls" => "val-5"],
                "6" => ["emoji" => "🚫", "num" => "N/A", "label" => "Hindi Naaangkop", "val" => "6", "safe_id" => "NA", "cls" => "val-6"]
            ];

            foreach ($sqd_questions as $id => $q) {
                echo "<div class='question-box'>";
                echo "  <div class='d-flex align-items-start gap-2 mb-2'>";
                echo "      <span class='question-number-pill pill-yellow flex-shrink-0'>{$q['code']}</span>";
                echo "      <div class='text-dark fw-bold sqd-question-text' id='{$id}_text' style='font-size: 0.88rem; line-height: 1.45;'>{$q['text']} <span class='text-danger'>*</span></div>";
                echo "  </div>";

                echo "  <div class='likert-grid'>";
                foreach ($options as $data) {
                    $val = $data['val'];
                    $safe_id = $id . "_" . $data['safe_id'];
                    $cls = $data['cls'];
                    echo "
                    <label class='likert-tile {$cls}' for='$safe_id'>
                        <input class='likert-input' type='radio' name='$id' id='$safe_id' value='$val' required>
                        <div class='likert-content'>
                            <span class='likert-emoji'>{$data['emoji']}</span>
                            <span class='likert-num'>{$data['num']}</span>
                        </div>
                    </label>";
                }
                echo "  </div>";
                echo "</div>";
            }
            ?>
        </section>

        <!-- ==========================================
             SECTION 4: SUGGESTIONS & FEEDBACK (RED)
        =========================================== -->
        <section class="csm-card">
            <div class="csm-card-header">
                <div>
                    <h5 class="csm-card-title">
                        <i class="ph-bold ph-chat-teardrop-dots text-danger me-2"></i><span id="sec4Title">4. Mga Suhestiyon at Komento</span>
                    </h5>
                    <p class="csm-card-subtitle" id="sec4Sub">Ibahagi ang iyong mga puna upang mapabuti pa ang aming serbisyo</p>
                </div>
                <span class="pill-badge pill-rose" id="sec4Badge">Bahagi 4</span>
            </div>

            <!-- Suggestions -->
            <div class="mb-3">
                <label class="form-label-modern" for="suggestions">
                    <i class="ph-bold ph-pencil-simple-line me-1.5 text-danger"></i><span id="labelSuggestions">Mga suhestiyon kung paano pa mapapabuti ang aming serbisyo:</span>
                </label>
                <textarea name="suggestions" id="suggestions" class="form-control-modern" rows="3" placeholder="Isulat dito ang iyong komento, obserbasyon, o mungkahi upang higit pang mapaganda ang aming serbisyo..."></textarea>
            </div>

            <!-- Email -->
            <div class="mb-2">
                <label class="form-label-modern" for="email">
                    <i class="ph-bold ph-envelope me-1.5 text-danger"></i><span id="labelEmail">Email Address:</span>
                </label>
                <input type="email" name="email" id="email" class="form-control-modern" placeholder="pangalan@email.com">
                <div class="form-text text-muted" style="font-size: 0.75rem; margin-top: 5px;" id="emailNote">
                    <i class="ph-bold ph-info me-1"></i>Kung nais mong makatanggap ng tugon mula sa amin ukol sa iyong puna.
                </div>
            </div>
        </section>

        <!-- Submit Button -->
        <div class="mb-4">
            <button type="submit" class="btn-submit-csm" id="btnSubmit">
                <i class="ph-bold ph-paper-plane-tilt fs-5"></i>
                <span id="btnSubmitText">Ipasa ang Pagsusuri</span>
            </button>
        </div>

    </form>

    <!-- Footer -->
    <footer class="survey-footer">
        <div class="text-dark fw-semibold mb-1" id="footerGov">
            Republika ng Pilipinas • Lalawigan ng Isabela • Tanggapan ng Panlalawigang Tagapagtasa
        </div>
        <div id="footerAct">
            Alinsunod sa Batas Republika Blg. 11032 ukol sa Ease of Doing Business
        </div>
        <div class="mt-2 text-muted" style="font-size: 0.72rem;" id="footerRights">
            &copy; <?php echo date('Y'); ?> Pamahalaang Panlalawigan ng Isabela. Lahat ng Karapatan ay Nakalaan.
        </div>
        <div class="mt-3 d-flex align-items-center justify-content-center gap-2 flex-wrap">
            <div class="lang-switcher-pills" role="group" aria-label="Select Language">
                <button type="button" class="lang-btn active" data-lang="fil" onclick="switchLanguage('fil')" title="Wikang Tagalog">
                    <span class="lang-flag">🇵🇭</span> <span>Tagalog</span>
                </button>
                <button type="button" class="lang-btn" data-lang="en" onclick="switchLanguage('en')" title="English">
                    <span class="lang-flag">🇺🇸</span> <span>English</span>
                </button>
                <button type="button" class="lang-btn" data-lang="ilo" onclick="switchLanguage('ilo')" title="Pagsasao nga Ilokano">
                    <span class="lang-flag">🌾</span> <span>Ilokano</span>
                </button>
            </div>

            <button type="button" class="theme-toggle-btn btn-sm py-1 px-3" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
                <i class="ph-bold ph-moon theme-icon-moon"></i>
                <i class="ph-bold ph-sun theme-icon-sun d-none"></i>
                <span class="theme-label small">Dark Mode</span>
            </button>

            <a href="login.php" class="btn-admin-link">
                <i class="ph-bold ph-lock-key"></i> Admin Login
            </a>
        </div>
    </footer>

</div>

<!-- ==========================================
     SMART LOCATION & FORM LOGIC SCRIPT
=========================================== -->
<script>
    // 1. Set today's date automatically
    const dateInput = document.getElementById('survey_date');
    if (dateInput && !dateInput.value) {
        dateInput.valueAsDate = new Date();
    }

    // 2. All 37 Municipalities & Cities of Isabela (Alphabetical)
    const isabelaLocations = [
        "Alicia", "Angadanan", "Aurora", "Benito Soliven", "Burgos", "Cabagan", "Cabatuan", "Cauayan City", "Cordon", "Delfin Albano", 
        "Dinapigue", "Divilacan", "Echague", "Gamu", "Ilagan City", "Jones", "Luna", "Maconacon", "Mallig", "Naguilian", "Palanan", 
        "Quezon", "Quirino", "Ramon", "Reina Mercedes", "Roxas", "San Agustin", "San Guillermo", "San Isidro", "San Manuel", 
        "San Mariano", "San Mateo", "San Pablo", "Santa Maria", "Santiago City", "Santo Tomas", "Tumauini"
    ].sort();

    const regionSelect   = document.getElementById('region');
    const provinceSelect = document.getElementById('province');
    const muniSelect     = document.getElementById('municipality');
    
    const divRegion       = document.getElementById('div-region');
    const divProvince     = document.getElementById('div-province');
    const divMunicipality = document.getElementById('div-municipality');
    const divTransaction  = document.getElementById('div-transaction');

    // Populate the Municipality dropdown
    isabelaLocations.forEach(muni => {
        let opt = document.createElement('option');
        opt.value = muni;
        opt.textContent = muni;
        muniSelect.appendChild(opt);
    });

    // Smart Location Funnel Logic
    function handleLocationLogic() {
        const region = regionSelect.value;
        const province = provinceSelect.value;

        // SCENARIO 1: Non-Region II respondent
        if (region !== 'Region II') {
            divProvince.style.display = 'none';
            divMunicipality.style.display = 'none';
            
            provinceSelect.required = false;
            muniSelect.required = false;
            
            provinceSelect.value = '';
            muniSelect.value = '';

            divRegion.className = 'col-md-6';
            if (divTransaction) divTransaction.className = 'col-md-6';
        } 
        // SCENARIO 2: Region II respondent
        else {
            divRegion.className = 'col-md-6';
            divProvince.style.display = 'block';
            provinceSelect.required = true;

            // SCENARIO 3: Inside Isabela Province
            if (province === 'Isabela') {
                divMunicipality.style.display = 'block';
                muniSelect.required = true;
                divProvince.className = 'col-md-6';
                divMunicipality.className = 'col-md-6';
                if (divTransaction) divTransaction.className = 'col-md-6';
            } 
            // SCENARIO 4: Outside Isabela Province (Other Region II province)
            else {
                divMunicipality.style.display = 'none';
                muniSelect.required = false;
                muniSelect.value = ''; 
                divProvince.className = 'col-md-6';
                if (divTransaction) divTransaction.className = 'col-12';
            }
        }
    }

    // Run once on load to establish state
    handleLocationLogic();
</script>
<script src="darkmode.js"></script>
<script src="i18n.js"></script>
</body>
</html>
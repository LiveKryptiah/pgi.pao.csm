<?php
session_start();
require 'db.php';

// If already logged in, redirect directly to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Hardcoded fallback for easy testing if you haven't set up the database table yet
    // In a real scenario, rely ONLY on the database check below.
    if ($username === 'admin' && $password === 'password123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'Administrator';
        header("Location: dashboard.php");
        exit;
    }

    // Database Authentication Check
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = !empty($user['username']) ? ucfirst($user['username']) : 'Administrator';
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="pao.ico">
    <title>Admin Login - CSM</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Phosphor Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css">
    
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
        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-surface, #ffffff);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .login-split-page {
            display: flex;
            min-height: 100vh;
            width: 100vw;
            margin: 0;
            padding: 0;
        }

        /* LEFT: FULL SCREEN BACKGROUND PANEL */
        .login-split-left {
            flex: 1 1 52%;
            width: 52%;
            min-height: 100vh;
            background: linear-gradient(145deg, #022c22 0%, #064e3b 50%, #047857 100%);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px 72px;
        }

        [data-bs-theme="dark"] .login-split-left {
            background: linear-gradient(145deg, #011812 0%, #022c22 55%, #064e3b 100%);
        }

        /* Decorative ambient glow and geometric elements */
        .login-left-bg-art {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .login-left-bg-art::before {
            content: '';
            position: absolute;
            width: 520px;
            height: 520px;
            top: -120px;
            left: -120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(52, 211, 153, 0.18) 0%, transparent 70%);
        }

        .login-left-bg-art::after {
            content: '';
            position: absolute;
            width: 620px;
            height: 620px;
            bottom: -160px;
            right: -160px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
        }

        .login-left-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            min-height: calc(100vh - 120px);
        }

        .brand-logo-large {
            width: 68px;
            height: 68px;
            object-fit: contain;
            border-radius: 16px;
            background: #ffffff;
            padding: 7px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25);
        }

        .brand-badge-pill {
            font-size: 0.70rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #047857;
            background: #ecfdf5;
            padding: 3px 10px;
            border-radius: 999px;
            display: inline-block;
            line-height: 1.2;
        }

        .brand-org-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 4px;
        }

        .login-system-tag {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #6ee7b7;
            background: rgba(110, 231, 183, 0.14);
            border: 1px solid rgba(110, 231, 183, 0.25);
            padding: 5px 12px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
        }

        .brand-hero-title {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: #ffffff;
            margin: 0 0 16px;
        }

        .text-accent-emerald {
            color: #6ee7b7;
            background: linear-gradient(135deg, #a7f3d0 0%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-hero-desc {
            font-size: 1.02rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.86);
            max-width: 520px;
            margin-bottom: 32px;
        }

        .brand-feature-grid {
            display: grid;
            gap: 16px;
            max-width: 480px;
        }

        .brand-feature-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            backdrop-filter: blur(8px);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .brand-feature-card:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(4px);
        }

        .brand-feature-card i {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #6ee7b7;
            flex-shrink: 0;
        }

        .brand-feature-card strong {
            display: block;
            font-size: 0.90rem;
            font-weight: 700;
            color: #ffffff;
        }

        .brand-feature-card span {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.75);
        }

        .login-left-footer {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.72);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 24px;
        }

        /* RIGHT: LOGIN FORM PANEL - MINIMAL MODERN */
        .login-split-right {
            flex: 1 1 48%;
            width: 48%;
            min-height: 100vh;
            background: var(--bg-surface, #ffffff);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px 32px;
            position: relative;
            overflow: hidden;
        }

        [data-bs-theme="dark"] .login-split-right {
            background: #07110c;
        }

        /* GREETINGS SUN ART (FROM DASHBOARD GREETING SECTION) */
        .login-sun-art,
        .login-split-right .greetings-sun-art {
            position: absolute;
            right: 0;
            bottom: -5px;
            height: 52%;
            max-height: 380px;
            pointer-events: none;
            user-select: none;
            z-index: 1;
            opacity: 0.88;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
        }

        .login-sun-img,
        .login-split-right .greetings-sun-img {
            height: 100%;
            width: auto;
            object-fit: contain;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            filter: drop-shadow(0 4px 20px rgba(245, 158, 11, 0.12));
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .login-split-right:hover .login-sun-img,
        .login-split-right:hover .greetings-sun-img {
            transform: scale(1.02) rotate(-0.5deg);
        }

        [data-bs-theme="dark"] .login-sun-art,
        [data-bs-theme="dark"] .login-split-right .greetings-sun-art {
            opacity: 0.45;
            filter: drop-shadow(0 0 25px rgba(245, 158, 11, 0.22));
        }

        .login-form-container {
            width: 100%;
            max-width: 360px;
            position: relative;
            z-index: 2;
            animation: formFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) backwards;
        }

        @keyframes formFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            margin-bottom: 28px;
        }

        .login-heading {
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--text-heading, #0f172a);
            margin: 0 0 6px;
            line-height: 1.25;
        }

        [data-bs-theme="dark"] .login-heading {
            color: #f1f5f9;
        }

        .login-subheading {
            font-size: 0.86rem;
            color: var(--text-muted, #64748b);
            margin: 0;
            line-height: 1.45;
        }

        .field-group {
            margin-bottom: 18px;
        }

        .field-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-heading, #1e293b);
            margin-bottom: 6px;
            display: block;
        }

        [data-bs-theme="dark"] .field-label {
            color: #cbd5e1;
        }

        .field-wrap {
            position: relative;
        }

        .field-wrap > .field-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.05rem;
            color: var(--text-subtle, #94a3b8);
            pointer-events: none;
            transition: color 0.15s ease;
        }

        .field-wrap .form-control {
            height: 42px;
            padding-left: 38px;
            padding-right: 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 450;
            background-color: var(--bg-body, #ffffff);
            border: 1px solid var(--border-subtle, #e2e8f0);
            color: var(--text-heading, #0f172a);
            box-shadow: none !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }

        [data-bs-theme="dark"] .field-wrap .form-control {
            background-color: #0d1a13;
            border-color: #1b2f24;
            color: #f1f5f9;
        }

        .field-wrap .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.84rem;
        }

        [data-bs-theme="dark"] .field-wrap .form-control::placeholder {
            color: #5f7a6b;
        }

        .field-wrap .form-control:focus {
            background-color: var(--bg-surface, #ffffff);
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12) !important;
        }

        [data-bs-theme="dark"] .field-wrap .form-control:focus {
            background-color: #0f1e16;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
        }

        .field-wrap:focus-within > .field-icon {
            color: #059669;
        }

        [data-bs-theme="dark"] .field-wrap:focus-within > .field-icon {
            color: #10b981;
        }

        .pw-toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--text-subtle, #94a3b8);
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            cursor: pointer;
            box-shadow: none !important;
            transition: color 0.15s ease, background-color 0.15s ease;
        }

        .pw-toggle:hover {
            color: #059669;
            background: rgba(5, 150, 105, 0.08);
        }

        [data-bs-theme="dark"] .pw-toggle:hover {
            color: #10b981;
            background: rgba(16, 185, 129, 0.12);
        }

        #password { padding-right: 42px; }

        .btn-login {
            height: 42px;
            border: none;
            border-radius: 8px;
            background: #047857;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            transition: background-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-login:hover {
            background: #065f46;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25) !important;
            color: #ffffff;
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }

        .btn-login:disabled {
            opacity: 0.75;
            transform: none;
            cursor: wait;
        }

        .login-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 20px;
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            animation: loginShake 0.3s ease;
        }

        [data-bs-theme="dark"] .login-alert {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.25);
        }

        @keyframes loginShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .back-link {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted, #64748b);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: #047857;
        }

        [data-bs-theme="dark"] .back-link:hover {
            color: #10b981;
        }

        @media (max-width: 991.98px) {
            .login-split-page {
                flex-direction: column;
            }
            .login-split-left {
                flex: none;
                width: 100%;
                min-height: auto;
                padding: 44px 28px 36px;
            }
            .login-left-content {
                min-height: auto;
            }
            .brand-hero-title {
                font-size: 1.85rem;
            }
            .brand-hero-desc {
                font-size: 0.90rem;
                margin-bottom: 20px;
            }
            .brand-feature-grid {
                display: none;
            }
            .login-left-footer {
                display: none;
            }
            .login-split-right {
                flex: none;
                width: 100%;
                min-height: auto;
                padding: 40px 24px 60px;
            }
            .login-sun-art,
            .login-split-right .greetings-sun-art {
                opacity: 0.22;
                right: -25px;
                bottom: -15px;
                height: 38%;
                max-height: 220px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-form-container, .login-alert { animation: none; }
            .btn-login, .btn-login:hover { transition: none; transform: none; }
        }
    </style>
</head>
<body>

<div class="login-split-page">
    <!-- LEFT: FULL SCREEN BACKGROUND & BRANDING -->
    <aside class="login-split-left">
        <div class="login-left-bg-art" aria-hidden="true"></div>
        <div class="login-left-content">
            <!-- Header Brand -->
            <div class="login-left-header">
                <div class="d-flex align-items-center gap-3">
                    <img src="image_4c6ce6.jpg" alt="Provincial Assessor Logo" class="brand-logo-large">
                    <div>
                        <span class="brand-badge-pill">OFFICIAL SYSTEM</span>
                        <div class="brand-org-name">Office of the Provincial Assessor</div>
                    </div>
                </div>
            </div>

            <!-- Hero Main Text -->
            <div class="login-left-body my-auto py-4">
                <span class="login-system-tag"><i class="ph-bold ph-shield-check"></i> Isabela CSM Portal</span>
                <h1 class="brand-hero-title">
                    Client Satisfaction<br>
                    <span class="text-accent-emerald">Measurement</span>
                </h1>
                <p class="brand-hero-desc">
                    Comprehensive citizen feedback monitoring, service quality dimension analytics, and public service assessment for the Province of Isabela.
                </p>

                <div class="brand-feature-grid">
                    <div class="brand-feature-card">
                        <i class="ph-bold ph-chart-line-up"></i>
                        <div>
                            <strong>Real-Time Analytics</strong>
                            <span>Live trend scoring & satisfaction indicators</span>
                        </div>
                    </div>
                    <div class="brand-feature-card">
                        <i class="ph-bold ph-chat-teardrop-text"></i>
                        <div>
                            <strong>Citizen Feedback</strong>
                            <span>Automated suggestion tracking & insights</span>
                        </div>
                    </div>
                    <div class="brand-feature-card">
                        <i class="ph-bold ph-printer"></i>
                        <div>
                            <strong>Official Reports</strong>
                            <span>Print-ready CSM matrices & bulk data encoding</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-left-footer">
                <div>&copy; <?php echo date('Y'); ?> Provincial Government of Isabela. All rights reserved.</div>
                <div class="text-white-50 mt-1" style="font-size: 0.72rem;">Authorized Personnel & System Administrator Access Only</div>
            </div>
        </div>
    </aside>

    <!-- RIGHT: LOGIN FORM -->
    <main class="login-split-right">
        <!-- FLOATING THEME TOGGLE BUTTON -->
        <button type="button" class="theme-toggle-btn theme-toggle-floating" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
            <i class="ph-bold ph-moon fs-5 theme-icon-moon"></i>
            <i class="ph-bold ph-sun fs-5 theme-icon-sun d-none"></i>
            <span class="theme-label small d-none d-sm-inline fw-semibold">Dark</span>
        </button>

        <!-- GREETINGS SUN ART (FROM DASHBOARD GREETING SECTION) -->
        <div class="greetings-sun-art login-sun-art" aria-hidden="true">
            <img src="greetings_sun_topleft.png" alt="Greeting Sun" class="greetings-sun-img login-sun-img">
        </div>

        <div class="login-form-container">
            <!-- Mobile Brand Header (shown only on small screens) -->
            <div class="d-lg-none text-center mb-4">
                <img src="image_4c6ce6.jpg" alt="Logo" style="width: 42px; height: 42px; object-fit: contain; margin-bottom: 8px;">
                <h6 class="fw-bold mb-0">CSM Admin Portal</h6>
                <span class="text-muted" style="font-size: 0.78rem;">Office of the Provincial Assessor</span>
            </div>

            <div class="login-header">
                <h2 class="login-heading">Sign In</h2>
                <p class="login-subheading">Enter your administrator credentials to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="login-alert" role="alert">
                    <i class="ph-bold ph-warning-circle fs-5 flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm" novalidate>
                <div class="field-group">
                    <label class="field-label" for="username">Username</label>
                    <div class="field-wrap">
                        <i class="ph-bold ph-user field-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter username"
                               value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="field-group mb-4">
                    <label class="field-label" for="password">Password</label>
                    <div class="field-wrap">
                        <i class="ph-bold ph-lock-simple field-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password"
                               autocomplete="current-password" required>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" title="Show / hide password">
                            <i class="ph-bold ph-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn" class="btn btn-login w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <span>Sign In</span>
                    <i class="ph-bold ph-arrow-right"></i>
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="index.php" class="back-link">
                    <i class="ph-bold ph-arrow-left"></i> <span>Back to Citizen Survey</span>
                </a>
            </div>
        </div>
    </main>
</div>

<script>
    (function () {
        var pw = document.getElementById('password');
        var toggle = document.getElementById('pwToggle');
        toggle.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.innerHTML = '<i class="ph-bold ' + (show ? 'ph-eye-slash' : 'ph-eye') + '"></i>';
            pw.focus();
        });

        document.getElementById('loginForm').addEventListener('submit', function () {
            var btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <span>Signing in...</span>';
        });
    })();
</script>

<script src="darkmode.js"></script>
</body>
</html>
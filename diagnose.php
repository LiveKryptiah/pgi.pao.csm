<?php
/**
 * CSM System Diagnostics & Office Network Hub
 * Displays office network connections (Connection 1 & Connection 2),
 * co-worker troubleshooting guides, and system health checks.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function get_csm_diagnostic_ips() {
    $ips = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @exec('ipconfig', $output);
        if (is_array($output)) {
            foreach ($output as $line) {
                if (preg_match('/IPv4 Address[ .:]+([0-9.]+)/i', $line, $m)) {
                    $ip = trim($m[1]);
                    if ($ip !== '127.0.0.1' && !in_array($ip, $ips)) {
                        $ips[] = $ip;
                    }
                }
            }
        }
    }
    if (empty($ips)) {
        $ips = ['192.168.1.225', '192.168.4.210'];
    }
    return $ips;
}

$diag_ips = get_csm_diagnostic_ips();
$primary_ip = $diag_ips[0] ?? '192.168.1.225';
$secondary_ip = $diag_ips[1] ?? '192.168.4.210';
$diag_port = $_SERVER['SERVER_PORT'] ?? '8000';
$diag_port_str = (!in_array($diag_port, ['80', '443'])) ? ':' . $diag_port : '';

$url_c1 = "http://{$primary_ip}{$diag_port_str}/index.php";
$url_c2 = "http://{$secondary_ip}{$diag_port_str}/index.php";
$url_admin = "http://{$primary_ip}{$diag_port_str}/login.php";
$url_local = "http://localhost{$diag_port_str}/index.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSM System Diagnostics &amp; Office Network Hub</title>
    <link rel="icon" type="image/png" href="pao.png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 24px;
        }
        .diag-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            padding: 24px;
            margin-bottom: 24px;
        }
        .code-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: monospace;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 960px;">
    <!-- HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <img src="pao.png" alt="PAO Logo" style="width: 44px; height: 44px;">
            <div>
                <h4 class="fw-bold mb-0 text-dark">CSM Diagnostics &amp; Office Network Hub</h4>
                <p class="text-muted small mb-0">Provincial Assessor's Office &bull; Province of Isabela</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary fw-semibold rounded-3 px-3">
                <i class="ph-bold ph-squares-four"></i> Dashboard
            </a>
            <a href="index.php" target="_blank" class="btn btn-sm btn-success fw-semibold rounded-3 px-3 text-white">
                <i class="ph-bold ph-arrow-square-out"></i> Open Survey Form
            </a>
        </div>
    </div>

    <!-- SECTION 1: OFFICE LAN CONNECTIONS -->
    <div class="diag-card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph-bold ph-broadcast fs-3 text-success"></i>
                <h5 class="fw-bold mb-0 text-dark">Office LAN Links for Co-Workers</h5>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-3 fw-bold">
                Port <?= htmlspecialchars($diag_port) ?> Active
            </span>
        </div>

        <p class="text-muted small mb-3">
            Your host computer has two active network connections in the office. Co-workers can use either of the links below from their desktop browsers:
        </p>

        <div class="row g-3 mb-3">
            <!-- CONNECTION 1 -->
            <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-light h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                            Connection 1 (Primary)
                        </span>
                        <code class="text-muted small"><?= htmlspecialchars($primary_ip) ?></code>
                    </div>
                    <div class="code-box text-dark mb-3 text-truncate" title="<?= htmlspecialchars($url_c1) ?>">
                        <?= htmlspecialchars($url_c1) ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-primary fw-semibold rounded-3 flex-grow-1" onclick="copyDiagText('<?= htmlspecialchars($url_c1) ?>', this)">
                            <i class="ph-bold ph-copy"></i> Copy Link
                        </button>
                        <a href="<?= htmlspecialchars($url_c1) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3">
                            <i class="ph-bold ph-arrow-square-out"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- CONNECTION 2 (FASTEST FIX) -->
            <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-success-subtle border-success-subtle h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-success text-white fw-bold">
                            <i class="ph-bold ph-lightning me-1"></i> Connection 2 (Fastest Fix!)
                        </span>
                        <code class="text-success-emphasis small"><?= htmlspecialchars($secondary_ip) ?></code>
                    </div>
                    <div class="code-box text-dark mb-3 text-truncate" title="<?= htmlspecialchars($url_c2) ?>">
                        <?= htmlspecialchars($url_c2) ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-success text-white fw-semibold rounded-3 flex-grow-1" onclick="copyDiagText('<?= htmlspecialchars($url_c2) ?>', this)">
                            <i class="ph-bold ph-copy"></i> Copy 2nd IP Link
                        </button>
                        <a href="<?= htmlspecialchars($url_c2) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-3">
                            <i class="ph-bold ph-arrow-square-out"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 pt-2 text-muted small flex-wrap">
            <div><i class="ph-bold ph-laptop text-primary"></i> <strong>Localhost:</strong> <code><?= htmlspecialchars($url_local) ?></code></div>
            <div><i class="ph-bold ph-shield-check text-warning"></i> <strong>Admin Portal:</strong> <code><?= htmlspecialchars($url_admin) ?></code></div>
        </div>
    </div>

    <!-- SECTION 2: CO-WORKER TROUBLESHOOTING SOLUTIONS -->
    <div class="diag-card">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph-bold ph-wrench fs-3 text-primary"></i>
                <h5 class="fw-bold mb-0 text-dark">Co-Worker Connection Troubleshooting Guide</h5>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded-3" onclick="copyDiagInstructions(this)">
                <i class="ph-bold ph-chat-circle-dots"></i> Copy Instructions for Co-worker
            </button>
        </div>

        <!-- SOLUTION 1 -->
        <div class="p-3 border rounded-3 bg-light mb-3">
            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success">Solution 1</span>
                <span>Have Them Try Your 2nd IP Address (Fastest Fix!)</span>
            </h6>
            <p class="small text-secondary mb-2">
                Earlier diagnostics showed your computer actually has two office network connections:
            </p>
            <ul class="small text-secondary mb-2 ps-3">
                <li><strong>Connection 1:</strong> <code><?= htmlspecialchars($primary_ip) ?></code></li>
                <li><strong>Connection 2:</strong> <code><?= htmlspecialchars($secondary_ip) ?></code></li>
            </ul>
            <p class="small text-secondary mb-2">
                Some computers in your office might be on the second network. Have the co-worker who cannot connect try opening this in Chrome/Edge instead:
            </p>
            <div class="code-box text-dark mb-2">
                <?= htmlspecialchars($url_c2) ?>
            </div>
            <p class="small text-success fw-semibold mb-0">
                <i class="ph-bold ph-bookmark-simple"></i> If this opens immediately for them, bookmark this link for their desk!
            </p>
        </div>

        <!-- SOLUTION 2 -->
        <div class="p-3 border rounded-3 bg-light mb-3">
            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-primary">Solution 2</span>
                <span>Switch to the Same Wi-Fi Network</span>
            </h6>
            <p class="small text-secondary mb-2">
                Often, some co-workers are connected to a "Guest Wi-Fi" or a different router/extender in the office:
            </p>
            <ol class="small text-secondary mb-0 ps-3">
                <li class="mb-1">On the co-worker's PC, click the <strong>Wi-Fi icon</strong> at the bottom-right of their taskbar.</li>
                <li class="mb-1">Compare the network name with one of the co-workers where it is working.</li>
                <li class="mb-1">Make sure they are <strong>NOT</strong> connected to:
                    <ul class="ps-3 mt-1">
                        <li>A phone mobile hotspot</li>
                        <li>A "Guest" Wi-Fi network (Guest networks block computers from seeing each other)</li>
                        <li>A different office Wi-Fi router</li>
                    </ul>
                </li>
                <li>Disconnect and connect to the <strong>exact same Wi-Fi network</strong> as the working computers.</li>
            </ol>
        </div>

        <!-- SOLUTION 3 -->
        <div class="p-3 border rounded-3 bg-light mb-3">
            <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-secondary">Solution 3</span>
                <span>Change Their Network Profile from "Public" to "Private"</span>
            </h6>
            <p class="small text-secondary mb-2">
                If Windows treats the office network as a "Public" network on their PC, Windows will block them from connecting to local computers:
            </p>
            <ol class="small text-secondary mb-0 ps-3">
                <li class="mb-1">On the co-worker's PC, open Settings (<kbd>Win</kbd> + <kbd>I</kbd>).</li>
                <li class="mb-1">Go to <strong>Network &amp; Internet</strong> &rarr; click on their active Wi-Fi (or Ethernet).</li>
                <li class="mb-1">Under <strong>Network profile type</strong>, change it from <em>Public</em> to <strong>Private</strong>.</li>
                <li class="mb-1">Select <strong>Private network</strong> (this allows the computer to communicate with other computers on the office LAN).</li>
                <li>Refresh <code><?= htmlspecialchars($url_c1) ?></code> in their browser.</li>
            </ol>
        </div>

        <!-- SUMMARY CHECKLIST -->
        <div class="p-3 border rounded-3 bg-warning-subtle border-warning-subtle">
            <h6 class="fw-bold text-dark mb-2 small text-uppercase" style="letter-spacing: 0.05em;">
                <i class="ph-bold ph-check-square text-warning me-1"></i> Summary Checklist for the Co-worker Who Cannot Connect:
            </h6>
            <ul class="small text-secondary mb-0 ps-3">
                <li>Try <code><?= htmlspecialchars($url_c2) ?></code> first.</li>
                <li>If that doesn't work, ensure they are on the same Wi-Fi name (not a Guest network or phone hotspot).</li>
                <li>Ensure their network is set to <strong>Private</strong> in Windows Settings.</li>
            </ul>
        </div>
    </div>

    <!-- SECTION 3: SYSTEM HEALTH TESTS -->
    <div class="diag-card">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ph-bold ph-heartbeat fs-3 text-danger"></i>
            <h5 class="fw-bold mb-0 text-dark">System &amp; Database Health Tests</h5>
        </div>

        <!-- File Tests -->
        <h6 class="fw-bold small text-muted text-uppercase mb-2">1. Core Files Check:</h6>
        <div class="mb-3">
            <?php
            $files = ['db.php', 'index.php', 'submit.php', 'dashboard.php', 'login.php', 'admin_bulk.php', 'print_report.php'];
            foreach($files as $file) {
                if(file_exists($file)) {
                    echo "<div class='text-success small fw-semibold mb-1'><i class='ph-bold ph-check-circle me-1'></i> $file is present.</div>";
                } else {
                    echo "<div class='text-danger small fw-semibold mb-1'><i class='ph-bold ph-x-circle me-1'></i> $file is MISSING!</div>";
                }
            }
            ?>
        </div>

        <!-- Database Test -->
        <h6 class="fw-bold small text-muted text-uppercase mb-2">2. Database Connection Test:</h6>
        <div class="mb-3">
            <?php
            try {
                require_once 'db.php';
                if (isset($pdo) && $pdo) {
                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $count = $pdo->query("SELECT count(*) FROM responses")->fetchColumn();
                    echo "<div class='text-success small fw-semibold'><i class='ph-bold ph-check-circle me-1'></i> Database Connected via '$driver'! Total responses recorded: " . number_format($count) . "</div>";
                } else {
                    echo "<div class='text-danger small fw-semibold'><i class='ph-bold ph-x-circle me-1'></i> Database connection object (\$pdo) not initialized.</div>";
                }
            } catch (Throwable $e) {
                echo "<div class='text-danger small fw-semibold'><i class='ph-bold ph-x-circle me-1'></i> Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
        </div>

        <!-- Modules -->
        <h6 class="fw-bold small text-muted text-uppercase mb-2">3. PHP Modules:</h6>
        <div class="small text-secondary">
            <strong>pdo_pgsql:</strong> <?= extension_loaded('pdo_pgsql') ? 'YES' : 'NO' ?> &bull;
            <strong>pdo_mysql:</strong> <?= extension_loaded('pdo_mysql') ? 'YES' : 'NO' ?> &bull;
            <strong>mbstring:</strong> <?= extension_loaded('mbstring') ? 'YES' : 'NO' ?> &bull;
            <strong>PHP Version:</strong> <?= phpversion() ?>
        </div>
    </div>
</div>

<script>
function copyDiagText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check"></i> Copied!`;
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

function copyDiagInstructions(btn) {
    const text = `Solution 1: Have Them Try Your 2nd IP Address (Fastest Fix!)\n\n` +
        `Earlier diagnostics showed your computer actually has two office network connections:\n\n` +
        `Connection 1: <?= $primary_ip ?>\n` +
        `Connection 2: <?= $secondary_ip ?>\n\n` +
        `Some computers in your office might be on the second network. Have the co-worker who cannot connect try opening this in Chrome/Edge instead:\n\n` +
        `<?= $url_c2 ?>\n\n` +
        `If this opens immediately for them, bookmark this link for their desk!\n\n` +
        `Solution 2: Switch to the Same Wi-Fi Network\n\n` +
        `Often, some co-workers are connected to a "Guest Wi-Fi" or a different router/extender in the office:\n` +
        `1. On the co-worker's PC, click the Wi-Fi icon at the bottom-right of their taskbar.\n` +
        `2. Compare the network name with one of the co-workers where it is working.\n` +
        `3. Make sure they are NOT connected to:\n` +
        `   - A phone mobile hotspot\n` +
        `   - A "Guest" Wi-Fi network\n` +
        `   - A different office Wi-Fi router\n` +
        `4. Connect to the exact same Wi-Fi network as the working computers.\n\n` +
        `Solution 3: Change Their Network Profile from "Public" to "Private"\n\n` +
        `If Windows treats the office network as a "Public" network on their PC, Windows will block them from connecting to local computers:\n` +
        `1. On the co-worker's PC, open Settings (Win + I).\n` +
        `2. Go to Network & Internet -> click active Wi-Fi or Ethernet.\n` +
        `3. Under Network profile type, change it from Public to Private.\n` +
        `4. Refresh <?= $url_c1 ?> in their browser.\n\n` +
        `Summary Checklist for the Co-worker Who Cannot Connect:\n` +
        `- Try <?= $url_c2 ?> first.\n` +
        `- Ensure connected to the same Wi-Fi name (not Guest network or phone hotspot).\n` +
        `- Ensure network is set to Private in Windows Settings.`;

    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check text-success"></i> Instructions Copied!`;
        setTimeout(() => { btn.innerHTML = orig; }, 2500);
        alert('Copied troubleshooting instructions to clipboard! You can paste it into Viber, Messenger, or Teams.');
    });
}
</script>

</body>
</html>

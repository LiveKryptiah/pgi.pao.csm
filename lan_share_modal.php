<?php
/**
 * LAN & Office Network Sharing Modal
 * Provides office IP connections (Connection 1 & Connection 2) and troubleshooting
 * solutions for co-workers connecting to the CSM survey kiosk and admin portal.
 */

if (!function_exists('get_csm_lan_ips')) {
    function get_csm_lan_ips() {
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
}

$csm_lan_ips = get_csm_lan_ips();
$csm_ip_primary = $csm_lan_ips[0] ?? '192.168.1.225';
$csm_ip_secondary = $csm_lan_ips[1] ?? '192.168.4.210';
$csm_port = $_SERVER['SERVER_PORT'] ?? '8000';
$csm_port_str = (!in_array($csm_port, ['80', '443'])) ? ':' . $csm_port : '';

$url_conn1_survey = "http://{$csm_ip_primary}{$csm_port_str}/index.php";
$url_conn2_survey = "http://{$csm_ip_secondary}{$csm_port_str}/index.php";
$url_conn1_admin  = "http://{$csm_ip_primary}{$csm_port_str}/login.php";
$url_conn2_admin  = "http://{$csm_ip_secondary}{$csm_port_str}/login.php";
?>

<!-- OFFICE NETWORK & CO-WORKER ACCESS MODAL -->
<div class="modal fade" id="lanShareModal" tabindex="-1" aria-labelledby="lanShareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card, 16px);">
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-success-subtle text-success" style="width: 40px; height: 40px;">
                        <i class="ph-bold ph-broadcast fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="lanShareModalLabel">Office Network &amp; Co-worker Access</h5>
                        <p class="text-muted small mb-0">Connect office computers, desks, and client kiosks to this CSM system.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- IP CONNECTION CARDS -->
                <div class="row g-3 mb-4">
                    <!-- CONNECTION 1 (PRIMARY) -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100 position-relative">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-2 py-1 rounded-2" style="font-size: 0.72rem;">
                                    Connection 1 (Primary)
                                </span>
                                <span class="text-muted small fw-semibold">Port <?= htmlspecialchars($csm_port) ?></span>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?= htmlspecialchars($csm_ip_primary) ?>
                            </div>
                            <div class="text-muted small mb-3 text-truncate" title="<?= htmlspecialchars($url_conn1_survey) ?>">
                                <?= htmlspecialchars($url_conn1_survey) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-primary fw-semibold rounded-3 py-1.5 px-3 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="copyLanUrl('<?= htmlspecialchars($url_conn1_survey) ?>', this)">
                                    <i class="ph-bold ph-copy"></i> <span>Copy Survey Link</span>
                                </button>
                                <a href="<?= htmlspecialchars($url_conn1_survey) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3 py-1.5 px-2.5" title="Open Form">
                                    <i class="ph-bold ph-arrow-square-out fs-6"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- CONNECTION 2 (SECONDARY - FASTEST FIX) -->
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-success-subtle border-success-subtle h-100 position-relative">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success text-white fw-bold px-2 py-1 rounded-2" style="font-size: 0.72rem;">
                                    <i class="ph-bold ph-lightning me-1"></i> Connection 2 (Fastest Fix!)
                                </span>
                                <span class="text-success-emphasis small fw-bold">2nd Subnet</span>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?= htmlspecialchars($csm_ip_secondary) ?>
                            </div>
                            <div class="text-muted small mb-3 text-truncate" title="<?= htmlspecialchars($url_conn2_survey) ?>">
                                <?= htmlspecialchars($url_conn2_survey) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-success fw-semibold rounded-3 py-1.5 px-3 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1.5 text-white" onclick="copyLanUrl('<?= htmlspecialchars($url_conn2_survey) ?>', this)">
                                    <i class="ph-bold ph-copy"></i> <span>Copy 2nd IP Link</span>
                                </button>
                                <a href="<?= htmlspecialchars($url_conn2_survey) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-3 py-1.5 px-2.5" title="Open Form">
                                    <i class="ph-bold ph-arrow-square-out fs-6"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CO-WORKER TROUBLESHOOTING GUIDE -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-wrench text-primary"></i>
                            <span>Co-worker Connection Troubleshooting</span>
                        </h6>
                        <span class="text-muted small">If a desk cannot open the survey portal</span>
                    </div>

                    <div class="accordion" id="csmLanTroubleshootAccordion">
                        <!-- SOLUTION 1 -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-xs">
                            <h2 class="accordion-header" id="headingSol1">
                                <button class="accordion-button py-2.5 px-3 fw-bold text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSol1" aria-expanded="true" aria-controls="collapseSol1">
                                    <span class="badge bg-success me-2">Solution 1</span> Have Them Try Your 2nd IP Address (Fastest Fix!)
                                </button>
                            </h2>
                            <div id="collapseSol1" class="accordion-collapse collapse show" aria-labelledby="headingSol1" data-bs-parent="#csmLanTroubleshootAccordion">
                                <div class="accordion-body small text-secondary py-3">
                                    <p class="mb-2">Your computer has two office network connections:</p>
                                    <ul class="mb-2 ps-3">
                                        <li><strong>Connection 1:</strong> <code><?= htmlspecialchars($csm_ip_primary) ?></code></li>
                                        <li><strong>Connection 2:</strong> <code><?= htmlspecialchars($csm_ip_secondary) ?></code></li>
                                    </ul>
                                    <p class="mb-2">Some computers in the office are routed through the second network adapter. Have the co-worker who cannot connect open this link in Chrome or Edge:</p>
                                    <div class="p-2 bg-light border rounded-2 d-flex align-items-center justify-content-between mb-2">
                                        <code class="text-dark fw-bold"><?= htmlspecialchars($url_conn2_survey) ?></code>
                                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 rounded" onclick="copyLanUrl('<?= htmlspecialchars($url_conn2_survey) ?>', this)">
                                            <i class="ph-bold ph-copy"></i> Copy
                                        </button>
                                    </div>
                                    <div class="text-success fw-semibold">
                                        <i class="ph-bold ph-bookmark-simple"></i> If this opens immediately for them, bookmark this link for their desk!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SOLUTION 2 -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-xs">
                            <h2 class="accordion-header" id="headingSol2">
                                <button class="accordion-button collapsed py-2.5 px-3 fw-bold text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSol2" aria-expanded="false" aria-controls="collapseSol2">
                                    <span class="badge bg-primary me-2">Solution 2</span> Switch to the Same Wi-Fi Network
                                </button>
                            </h2>
                            <div id="collapseSol2" class="accordion-collapse collapse" aria-labelledby="headingSol2" data-bs-parent="#csmLanTroubleshootAccordion">
                                <div class="accordion-body small text-secondary py-3">
                                    <p class="mb-2">Often, some co-workers are connected to a "Guest Wi-Fi" or a different router/extender in the office:</p>
                                    <ol class="mb-2 ps-3">
                                        <li>On the co-worker's PC, click the <strong>Wi-Fi icon</strong> at the bottom-right of their taskbar.</li>
                                        <li>Compare the network name with one of the co-workers where it is working.</li>
                                        <li>Make sure they are <strong>NOT</strong> connected to:
                                            <ul class="ps-3 mt-1">
                                                <li>A phone mobile hotspot</li>
                                                <li>A "Guest" Wi-Fi network (Guest networks block computers from seeing each other)</li>
                                                <li>A different office Wi-Fi router</li>
                                            </ul>
                                        </li>
                                        <li>Disconnect and connect to the <strong>exact same Wi-Fi network</strong> as the working computers.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- SOLUTION 3 -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-xs">
                            <h2 class="accordion-header" id="headingSol3">
                                <button class="accordion-button collapsed py-2.5 px-3 fw-bold text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSol3" aria-expanded="false" aria-controls="collapseSol3">
                                    <span class="badge bg-secondary me-2">Solution 3</span> Change Network Profile from "Public" to "Private"
                                </button>
                            </h2>
                            <div id="collapseSol3" class="accordion-collapse collapse" aria-labelledby="headingSol3" data-bs-parent="#csmLanTroubleshootAccordion">
                                <div class="accordion-body small text-secondary py-3">
                                    <p class="mb-2">If Windows treats the office network as a "Public" network on their PC, Windows will block them from connecting to local computers:</p>
                                    <ol class="mb-2 ps-3">
                                        <li>On the co-worker's PC, open Settings (<kbd>Win</kbd> + <kbd>I</kbd>).</li>
                                        <li>Go to <strong>Network &amp; Internet</strong> &rarr; click on their active Wi-Fi (or Ethernet).</li>
                                        <li>Under <strong>Network profile type</strong>, change it from <em>Public</em> to <strong>Private</strong>.</li>
                                        <li>Select <strong>Private network</strong> (this allows the computer to communicate with other computers on the office LAN).</li>
                                        <li>Refresh <code><?= htmlspecialchars($url_conn1_survey) ?></code> in their browser.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY CHECKLIST BOX & 1-CLICK CHAT MESSAGE COPY -->
                <div class="p-3 bg-light border rounded-3">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.05em;">
                            <i class="ph-bold ph-check-square-offset text-success me-1"></i> Summary Checklist for the Co-worker:
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded-3 py-1 px-2.5 d-inline-flex align-items-center gap-1" onclick="copyCoworkerHelpMessage(this)">
                            <i class="ph-bold ph-chat-circle-dots"></i> <span>Copy Message for Co-worker</span>
                        </button>
                    </div>
                    <ul class="mb-0 small text-muted ps-3">
                        <li>Try <code><?= htmlspecialchars($url_conn2_survey) ?></code> first.</li>
                        <li>If that doesn't work, ensure they are on the same Wi-Fi name (not a Guest network or phone hotspot).</li>
                        <li>Ensure their network is set to <strong>Private</strong> in Windows Settings.</li>
                    </ul>
                </div>
            </div>

            <div class="modal-footer border-top py-2.5 px-4 bg-light d-flex align-items-center justify-content-between">
                <a href="diagnose.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3 py-1.5 px-3 fw-semibold d-inline-flex align-items-center gap-1.5">
                    <i class="ph-bold ph-pulse"></i> <span>Open Full Diagnostics</span>
                </a>
                <button type="button" class="btn btn-sm btn-primary rounded-3 py-1.5 px-3 fw-semibold" data-bs-dismiss="modal">
                    Got It
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function copyLanUrl(url, btn) {
    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(url);
    }

    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check text-success"></i> <span class="text-success">Copied!</span>`;
        setTimeout(() => { btn.innerHTML = orig; }, 2200);
    }

    if (typeof showCopyToast === 'function') {
        showCopyToast('Link Copied', url);
    }
}

function copyCoworkerHelpMessage(btn) {
    const text = `Hi! Here are the steps to open the CSM Survey Form:\n\n` +
        `1. Try this link first:\n` +
        `   <?= $url_conn2_survey ?>\n\n` +
        `2. If that doesn't load, try the primary link:\n` +
        `   <?= $url_conn1_survey ?>\n\n` +
        `3. Make sure you are on the same office Wi-Fi (NOT a phone hotspot or Guest Wi-Fi).\n` +
        `4. In Windows Settings (Win + I) -> Network & Internet, set your network profile to Private.`;

    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(text);
    }

    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check text-success"></i> <span class="text-success">Message Copied!</span>`;
        setTimeout(() => { btn.innerHTML = orig; }, 2200);
    }

    if (typeof showCopyToast === 'function') {
        showCopyToast('Message Copied', 'Paste this directly in Viber, Messenger, or Teams.');
    } else {
        alert('Checklist copied to clipboard! Paste it into Viber, Messenger, or Teams.');
    }
}
</script>

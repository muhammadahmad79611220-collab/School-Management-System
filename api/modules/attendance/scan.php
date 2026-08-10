<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('add', 'attendance')) {
    require_role('admin');
}
$settings = get_settings();
$brandName = !empty($settings['school_name']) ? $settings['school_name'] : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scan Attendance – <?php echo e($brandName); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        .scanner-wrap { display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: flex-start; }
        @media (max-width: 900px) { .scanner-wrap { grid-template-columns: 1fr; } }

        .cam-card { background: #11182b; border-radius: 16px; padding: 14px; box-shadow: 0 6px 22px rgba(0,0,0,0.18); position: relative; }
        .cam-card video, .cam-card canvas { width: 100%; border-radius: 10px; display: block; }
        .cam-card canvas { display: none; }
        .cam-frame { position: relative; }
        .cam-frame::after {
            content: ''; position: absolute; inset: 14%; border: 3px solid rgba(46,204,113,0.85); border-radius: 14px;
            box-shadow: 0 0 0 2000px rgba(0,0,0,0.25); pointer-events: none;
        }
        .cam-status { text-align: center; color: #cfd6ea; font-size: 12.5px; margin-top: 10px; }
        .status-toggle { display: flex; gap: 8px; margin-top: 12px; }
        .status-toggle button { flex: 1; padding: 8px; border-radius: 8px; border: 2px solid #2a3554; background: #182238; color: #9aa6c4; font-weight: 700; cursor: pointer; }
        .status-toggle button.active.present { background: #1a6b3a; border-color: #1a6b3a; color: #fff; }
        .status-toggle button.active.late { background: #b8860b; border-color: #b8860b; color: #fff; }

        .feed-card { background: #fff; border-radius: 16px; padding: 18px 20px; box-shadow: 0 2px 10px rgba(30,60,114,0.06); min-height: 420px; }
        .feed-empty { text-align: center; color: #aaa; padding: 60px 20px; }
        .scan-row { display: flex; align-items: center; gap: 14px; padding: 12px 8px; border-radius: 10px; margin-bottom: 8px; animation: slideIn .25s ease; }
        .scan-row.ok { background: #f0fbf3; }
        .scan-row.dup { background: #fff8e6; }
        .scan-row.err { background: #fdf0f0; }
        .scan-row .avatar { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; background: #e8edf8; flex-shrink: 0; display:flex;align-items:center;justify-content:center;font-size:20px; }
        .scan-row .info { flex: 1; min-width: 0; }
        .scan-row .info .name { font-weight: 700; color: #1e3c72; }
        .scan-row .info .meta { font-size: 12px; color: #888; }
        .scan-row .badge-status { font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; flex-shrink: 0; }
        .badge-status.present { background: #d4f4dd; color: #1a6b3a; }
        .badge-status.late { background: #fdebc9; color: #8a6500; }
        .badge-status.error { background: #fbdcdc; color: #a33; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .beep-flash { position: fixed; inset: 0; background: rgba(46,204,113,0.18); pointer-events: none; opacity: 0; transition: opacity .15s; z-index: 999; }
        .beep-flash.show { opacity: 1; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header" style="margin-bottom:16px;">
        <div class="card-title">📷 Scan Attendance (QR Card Scanner)</div>
        <a href="mark.php" class="btn btn-secondary">📋 Manual Attendance</a>
    </div>

    <div class="tip-box" style="background:#f8f9ff;border:1px solid #e8eaff;border-radius:10px;padding:10px 14px;font-size:12.5px;color:#555;margin-bottom:16px;">
        ✨ Point a student's printed ID card QR code at the camera, fairly close (10–15cm) and well lit. Attendance for <strong>today</strong> is marked the instant it's recognized — duplicate scans are detected automatically.
    </div>

    <div class="scanner-wrap">
        <div>
            <div class="cam-card">
                <div class="cam-frame">
                    <video id="video" playsinline muted></video>
                </div>
                <canvas id="canvas"></canvas>
                <div class="cam-status" id="camStatus">Starting camera…</div>
            </div>
            <div class="status-toggle">
                <button type="button" class="active present" id="btnPresent" onclick="setMode('Present')">✅ Present</button>
                <button type="button" class="late" id="btnLate" onclick="setMode('Late')">🕒 Late</button>
            </div>

            <div class="card" style="margin-top:16px;padding:14px 16px;">
                <label style="font-size:12px;color:#888;display:block;margin-bottom:6px;">Camera not working? Type the "Card Code" printed on the back of the ID card:</label>
                <form id="manualForm" style="display:flex;gap:8px;">
                    <input type="text" id="manualToken" placeholder="Paste / type card code" autocomplete="off" style="flex:1;">
                    <button type="submit" class="btn btn-sm">Mark</button>
                </form>
            </div>
        </div>

        <div class="feed-card">
            <div class="card-title" style="margin-bottom:12px;">🪪 Live Scan Feed</div>
            <div id="feed">
                <div class="feed-empty">No scans yet — point a card at the camera to begin.</div>
            </div>
        </div>
    </div>
</div>
<div class="beep-flash" id="beepFlash"></div>

<script>
const csrfToken = <?php echo json_encode(csrf_token()); ?>;
const endpoint = '<?php echo BASE_URL; ?>modules/attendance/scan_mark.php';
let currentMode = 'Present';
let lastToken = null;
let lastScanAt = 0;
let scanning = true;

function setMode(mode) {
    currentMode = mode;
    document.getElementById('btnPresent').classList.toggle('active', mode === 'Present');
    document.getElementById('btnLate').classList.toggle('active', mode === 'Late');
}

function flash() {
    const el = document.getElementById('beepFlash');
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 150);
}

function addFeedRow(type, html) {
    const feed = document.getElementById('feed');
    const empty = feed.querySelector('.feed-empty');
    if (empty) empty.remove();
    const row = document.createElement('div');
    row.className = 'scan-row ' + type;
    row.innerHTML = html;
    feed.prepend(row);
    while (feed.children.length > 25) feed.removeChild(feed.lastChild);
}

async function submitToken(token) {
    const now = Date.now();
    if (token === lastToken && (now - lastScanAt) < 4000) return; // ignore rapid duplicate reads of the same card
    lastToken = token;
    lastScanAt = now;

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'token=' + encodeURIComponent(token) + '&status=' + encodeURIComponent(currentMode) + '&csrf_token=' + encodeURIComponent(csrfToken)
        });
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            addFeedRow('err', `<div class="avatar">⚠️</div><div class="info"><div class="name">Server error (HTTP ${res.status})</div><div class="meta">${raw.slice(0, 140).replace(/</g,'&lt;')}</div></div><span class="badge-status error">Error</span>`);
            return;
        }
        flash();

        if (!data.ok) {
            addFeedRow('err', `<div class="avatar">⚠️</div><div class="info"><div class="name">Not recognized</div><div class="meta">${data.message}</div></div><span class="badge-status error">Error</span>`);
            return;
        }
        const s = data.student;
        const avatar = s.picture ? `<img class="avatar" src="${s.picture}">` : `<div class="avatar">👤</div>`;
        const statusClass = s.status === 'Late' ? 'late' : 'present';
        const rowType = data.duplicate ? 'dup' : 'ok';
        addFeedRow(rowType, `${avatar}<div class="info"><div class="name">${s.name}</div><div class="meta">Roll ${s.roll_no} · ${s.class}${data.duplicate ? ' · already scanned today' : ''}</div></div><span class="badge-status ${statusClass}">${s.status}</span>`);
    } catch (e) {
        addFeedRow('err', `<div class="avatar">⚠️</div><div class="info"><div class="name">Could not reach server</div><div class="meta">${(e && e.message) || 'Unknown error'}</div></div><span class="badge-status error">Error</span>`);
    }
}

document.getElementById('manualForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const input = document.getElementById('manualToken');
    const val = input.value.trim();
    if (!val) return;
    lastToken = null; // allow immediate re-scan regardless of last camera read
    submitToken(val);
    input.value = '';
    input.focus();
});

async function startCamera() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const statusEl = document.getElementById('camStatus');

    if (!window.jsQR) {
        statusEl.textContent = '⚠️ Scanner library failed to load (check your internet connection). You can still use the manual code box below.';
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.textContent = '⚠️ Camera is not supported in this browser. Use the manual code box below, or try Chrome/Edge.';
        return;
    }

    let stream;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
        });
    } catch (err) {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true }); // fallback to any camera
        } catch (err2) {
            statusEl.textContent = '⚠️ Camera access denied or unavailable. Allow camera permission and reload, or use the manual code box below.';
            return;
        }
    }

    video.srcObject = stream;
    try { await video.play(); } catch (e) { /* some browsers auto-play on metadata load */ }
    statusEl.textContent = 'Camera active — show a card to scan';

    function tick() {
        if (!scanning) { requestAnimationFrame(tick); return; }
        if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            try {
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
                if (code && code.data) {
                    submitToken(code.data.trim());
                }
            } catch (e) { /* ignore transient decode errors */ }
        }
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

startCamera();
</script>
</body>
</html>

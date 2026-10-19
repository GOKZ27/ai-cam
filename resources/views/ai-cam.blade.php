<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Cam — Asisten Penglihatan</title>
    <style>
        :root {
            color-scheme: dark;
            --bg:#05070f; --bg-soft:#0a1020;
            --panel:rgba(20,30,49,.72); --panel-2:rgba(31,46,71,.55); --panel-3:rgba(15,24,40,.75);
            --line:rgba(122,152,192,.22); --line-strong:rgba(122,152,192,.42);
            --text:#eef4ff; --muted:#9fb0c9;
            --accent:#5eead4; --accent-2:#7c9dff; --accent-ink:#032a23;
            --danger:#ff9bb0; --danger-bg:rgba(120,25,45,.32); --warn:#ffd479; --focus:#8fd0ff;
            --radius:20px; --shadow:0 22px 55px -28px rgba(0,0,0,.95);
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body {
            margin:0; min-height:100vh; color:var(--text); line-height:1.55;
            font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
            background:
                radial-gradient(1100px 620px at 10% -12%, rgba(94,234,212,.15), transparent 60%),
                radial-gradient(1000px 600px at 102% -6%, rgba(124,157,255,.17), transparent 55%),
                radial-gradient(900px 720px at 50% 118%, rgba(124,157,255,.09), transparent 62%),
                var(--bg);
            background-attachment:fixed;
        }
        .appbar {
            position:sticky; top:0; z-index:5; display:flex; align-items:center; gap:14px; flex-wrap:wrap;
            padding:12px 18px; border-bottom:1px solid var(--line);
            background:linear-gradient(180deg, rgba(7,11,20,.94), rgba(7,11,20,.55));
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
        }
        .brand { display:flex; align-items:center; gap:12px; min-width:0; }
        .brand-mark {
            width:44px; height:44px; border-radius:14px; display:grid; place-items:center; flex:none;
            background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#04231a;
            box-shadow:0 10px 26px -10px rgba(94,234,212,.75);
        }
        .brand-title { margin:0; font-size:1.05rem; font-weight:800; letter-spacing:.2px; }
        .brand-sub { margin:0; font-size:.76rem; color:var(--muted); letter-spacing:.03em; }
        .appbar .spacer { flex:1; }
        .pill {
            display:inline-flex; align-items:center; gap:8px; font-size:.78rem; font-weight:700;
            padding:7px 13px; border-radius:999px; background:var(--panel-2);
            border:1px solid var(--line); color:var(--muted); white-space:nowrap;
        }
        .wrap { max-width:1180px; margin:0 auto; padding:28px 18px 64px; }
        .hero { margin:6px 0 10px; }
        h1 { margin:0 0 10px; font-size:clamp(1.7rem,4.4vw,2.55rem); line-height:1.14; letter-spacing:-.6px; }
        h1 .accent { background:linear-gradient(120deg,var(--accent),var(--accent-2)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        h2 { font-size:1.12rem; margin:0; }
        h3 { font-size:.78rem; margin:22px 0 10px; color:var(--muted); text-transform:uppercase; letter-spacing:.09em; font-weight:800; }
        .lead { color:var(--muted); margin:0; max-width:74ch; font-size:1.02rem; }
        .muted { color:var(--muted); }
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; border:0; }
        .layout { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr); gap:18px; margin-top:18px; }
        .panel {
            background:var(--panel); border:1px solid var(--line); border-radius:var(--radius);
            padding:18px; min-width:0; box-shadow:var(--shadow);
            backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
        }
        .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .stage {
            position:relative; width:100%; background:#02040a; border-radius:16px; overflow:hidden;
            aspect-ratio:4/3; border:1px solid var(--line-strong);
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.02), 0 20px 40px -30px #000;
        }
        video, canvas { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; }
        canvas { pointer-events:none; }
        .badge {
            position:absolute; top:12px; left:12px; z-index:2; display:inline-flex; align-items:center; gap:8px;
            font-size:.82rem; font-weight:700; padding:7px 13px; border-radius:999px;
            background:rgba(18,28,46,.86); color:#dce7f7; border:1px solid var(--line-strong);
            backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);
        }
        .badge::before { content:""; width:9px; height:9px; border-radius:50%; background:#8aa0bf; flex:none; }
        .badge[data-state="on"] { color:#a7f3d4; border-color:rgba(94,234,212,.6); }
        .badge[data-state="on"]::before { background:var(--accent); animation:pulse 1.9s ease-out infinite; }
        .badge[data-state="paused"] { color:#ffe6a6; border-color:rgba(255,212,121,.5); }
        .badge[data-state="paused"]::before { background:var(--warn); }
        @keyframes pulse { 0% { box-shadow:0 0 0 0 rgba(94,234,212,.6); } 70% { box-shadow:0 0 0 10px rgba(94,234,212,0); } 100% { box-shadow:0 0 0 0 rgba(94,234,212,0); } }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        button {
            border:0; border-radius:14px; min-height:52px; padding:13px 18px; font-size:1rem; font-weight:800;
            font-family:inherit; cursor:pointer; background:linear-gradient(135deg,var(--accent),#49d3c5);
            color:var(--accent-ink); transition:filter .15s ease, transform .06s ease, box-shadow .15s ease;
            box-shadow:0 12px 28px -14px rgba(94,234,212,.85);
        }
        button.secondary { background:var(--panel-2); color:var(--text); border:1px solid var(--line-strong); box-shadow:none; }
        button.secondary[aria-pressed="true"] { border-color:rgba(94,234,212,.55); color:#bdf7e9; }
        button.danger { background:var(--danger-bg); color:#ffc9d2; border:1px solid rgba(255,120,150,.45); box-shadow:none; }
        button:hover:not(:disabled) { filter:brightness(1.08); transform:translateY(-1px); }
        button:active:not(:disabled) { transform:translateY(0); }
        button:disabled { opacity:.45; cursor:not-allowed; box-shadow:none; }
        :focus-visible { outline:3px solid var(--focus); outline-offset:3px; border-radius:14px; }
        .status { min-height:26px; margin:16px 0 0; color:var(--text); font-size:.98rem; }
        .error { margin:12px 0 0; padding:12px 14px; border-radius:12px; background:var(--danger-bg); color:var(--danger); border:1px solid rgba(255,120,150,.42); font-weight:600; }
        .settings { margin-top:16px; display:flex; flex-wrap:wrap; align-items:center; gap:12px; background:var(--panel-2); border:1px solid var(--line); border-radius:14px; padding:12px 14px; }
        .settings label { font-weight:700; font-size:.9rem; color:var(--muted); }
        select, input[type="number"], input[type="text"] { font-family:inherit; font-size:.95rem; color:var(--text); background:#0b1424; border:1px solid var(--line-strong); border-radius:11px; padding:10px 12px; min-height:48px; }
        .indicators { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .indicator {
            position:relative; background:var(--panel-3); border:1px solid var(--line); border-radius:13px;
            padding:11px 12px 11px 17px; font-size:.85rem; font-weight:700; line-height:1.35; color:var(--text);
        }
        .indicator::before { content:""; position:absolute; left:0; top:12px; bottom:12px; width:3px; border-radius:3px; background:#5b6b85; }
        .indicator[data-state="ok"]::before { background:var(--accent); }
        .indicator[data-state="busy"]::before { background:var(--warn); }
        .indicator[data-state="error"]::before { background:var(--danger); }
        .stats { display:flex; flex-wrap:wrap; gap:8px; }
        .stat { background:var(--panel-2); border:1px solid var(--line); border-radius:999px; padding:7px 13px; font-size:.83rem; font-weight:700; }
        .objects { list-style:none; padding:0; margin:12px 0 0; display:flex; flex-direction:column; gap:8px; }
        .objects li { display:flex; align-items:center; justify-content:space-between; gap:12px; background:var(--panel-3); border:1px solid var(--line); border-radius:12px; padding:11px 14px; }
        .obj-name { font-weight:700; }
        .obj-conf {
            color:var(--accent); font-variant-numeric:tabular-nums; font-weight:800; font-size:.82rem;
            background:rgba(94,234,212,.1); border:1px solid rgba(94,234,212,.35); padding:2px 9px; border-radius:999px;
        }
        .desc-card { background:var(--panel-3); border:1px solid var(--line); border-radius:14px; padding:15px 16px; }
        .result { white-space:pre-wrap; margin:0; font-size:1.06rem; line-height:1.7; }
        .meta { font-size:.8rem; color:var(--muted); margin:10px 0 0; }
        footer.foot {
            margin-top:26px; font-size:.88rem; color:var(--muted); line-height:1.6;
            border:1px solid rgba(255,212,121,.32); background:rgba(120,95,15,.16);
            border-radius:16px; padding:15px 17px;
        }
        footer.foot strong { color:var(--warn); }
        .hint { font-size:.85rem; color:var(--muted); margin:12px 0 0; }
        .notice { margin:14px 0 0; padding:12px 14px; border-radius:12px; background:rgba(120,95,15,.26); color:#ffe6a6; border:1px solid rgba(255,212,121,.4); font-size:.9rem; }
        .teach { margin-top:16px; background:rgba(124,157,255,.1); border:1px solid rgba(124,157,255,.42); border-radius:14px; padding:14px; }
        .teach-title { margin:0; font-weight:800; font-size:.96rem; }
        .teach-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; align-items:center; }
        .teach-row input[type="text"] { flex:1 1 220px; min-width:0; }
        .teach-classes { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .teach-chip { display:inline-flex; align-items:center; gap:6px; background:var(--panel-2); border:1px solid var(--line); border-radius:999px; padding:5px 12px; font-size:.82rem; font-weight:700; }
        .teach-chip b { color:var(--accent); }
        .stage canvas.teaching { pointer-events:auto; cursor:crosshair; touch-action:none; }
        .diag-panel { margin-top:18px; }
        .diag-summary { font-weight:800; margin:8px 0 0; color:var(--muted); }
        .diag-summary[data-state="busy"] { color:var(--warn); }
        .diag-summary[data-state="ok"] { color:var(--accent); }
        .diag-summary[data-state="warn"] { color:var(--warn); }
        .diag-summary[data-state="error"] { color:var(--danger); }
        .diag-list { list-style:none; padding:0; margin:14px 0 0; display:grid; gap:8px; }
        .diag-item { position:relative; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:4px 12px; background:var(--panel-3); border:1px solid var(--line); border-radius:12px; padding:11px 14px 11px 17px; }
        .diag-item::before { content:""; position:absolute; left:0; top:11px; bottom:11px; width:3px; border-radius:3px; background:#5b6b85; }
        .diag-item[data-state="ok"]::before { background:var(--accent); }
        .diag-item[data-state="warn"]::before { background:var(--warn); }
        .diag-item[data-state="error"]::before { background:var(--danger); }
        .diag-item[data-state="busy"]::before { background:var(--warn); animation:pulse 1.4s ease-out infinite; }
        .diag-name { font-weight:800; }
        .diag-lat { font-variant-numeric:tabular-nums; font-weight:800; font-size:.82rem; color:var(--muted); white-space:nowrap; }
        .diag-item[data-state="ok"] .diag-lat { color:var(--accent); }
        .diag-item[data-state="warn"] .diag-lat { color:var(--warn); }
        .diag-item[data-state="error"] .diag-lat { color:var(--danger); }
        .diag-note { grid-column:1 / -1; font-size:.82rem; color:var(--muted); line-height:1.5; }
        .diag-legend { display:flex; flex-wrap:wrap; gap:14px; margin:12px 0 0; font-size:.82rem; color:var(--muted); }
        .diag-legend span { display:inline-flex; align-items:center; gap:6px; }
        .diag-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
        @media(max-width:860px) { .layout { grid-template-columns:1fr; } .indicators { grid-template-columns:1fr; } }
        @media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; scroll-behavior:auto !important; } }
    </style>
</head>
<body>
<header class="appbar">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 7.5V6.2A2.2 2.2 0 0 1 6.2 4h2.1l1-1.6A1 1 0 0 1 10.1 2h3.8a1 1 0 0 1 .9.4l1 1.6h2.1A2.2 2.2 0 0 1 20 6.2v11.6A2.2 2.2 0 0 1 17.8 20H6.2A2.2 2.2 0 0 1 4 17.8Z" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.5"/>
            </svg>
        </span>
        <div>
            <p class="brand-title">AI Cam</p>
            <p class="brand-sub">Asisten Penglihatan</p>
        </div>
    </div>
    <span class="spacer"></span>
    <span class="pill">Kamera → Deteksi → Suara</span>
</header>

<main class="wrap">
    <header class="hero">
        <h1>Cukup arahkan kamera, <span class="accent">semuanya dibacakan otomatis.</span></h1>
        <p class="lead">Sistem mengamati lingkungan, mengenali benda, dan mengucapkannya dalam bahasa Indonesia. Tidak perlu menekan tombol untuk setiap benda.</p>
        <p id="privacyNotice" class="notice" hidden>Saat Gemini aktif, gambar kamera dikirim ke layanan cloud Google untuk dianalisis. Hindari mengarahkan kamera ke dokumen pribadi atau area privat bila tidak ingin diproses di cloud.</p>
    </header>

    <div class="layout">
        <section class="panel" aria-labelledby="cam-heading">
            <div class="panel-head">
                <h2 id="cam-heading">Pratinjau kamera</h2>
                <span class="pill" aria-hidden="true">Langsung</span>
            </div>
            <div class="stage">
                <video id="video" autoplay playsinline muted aria-label="Pratinjau langsung dari kamera"></video>
                <canvas id="overlay" aria-hidden="true"></canvas>
                <div id="stateBadge" class="badge" data-state="off">Kamera belum aktif</div>
            </div>

            <div class="actions" role="group" aria-label="Kontrol asisten">
                <button id="start" type="button">Mulai Realtime</button>
                <button id="pause" type="button" class="secondary" disabled aria-pressed="false">Jeda</button>
                <button id="sound" type="button" class="secondary" aria-pressed="true">Suara: Aktif</button>
                <button id="analyze" type="button" class="secondary" disabled>Analisis Sekarang</button>
                <button id="teach" type="button" class="secondary" disabled aria-pressed="false">Ajar Objek</button>
                <button id="stop" type="button" class="danger" disabled>Berhenti</button>
            </div>

            <div class="settings">
                <label for="cameraSelect">Sumber kamera</label>
                <select id="cameraSelect" aria-describedby="cameraHint">
                    <option value="">Kamera otomatis</option>
                </select>
                <label for="visionInterval">Jeda analisis vision</label>
                <select id="visionInterval">
                    <option value="5000">5 detik</option>
                    <option value="10000" selected>10 detik</option>
                    <option value="15000">15 detik</option>
                    <option value="30000">30 detik</option>
                    <option value="60000">60 detik</option>
                </select>
                <span id="cameraHint" class="muted" style="font-size:.84rem">Pilih <strong>Iriun Webcam</strong> bila memakai kamera HP sebagai sumber gambar.</span>
            </div>

            <div id="teachPanel" class="teach" hidden>
                <p class="teach-title">Ajar objek baru</p>
                <p class="hint" style="margin-top:4px">Gambar di pratinjau <strong>dibekukan</strong>. Tarik kotak pada objek, ketik namanya, lalu Simpan. Contoh ini terkumpul sebagai data latih; objek akan dikenali setelah model dilatih ulang (lihat DOKUMENTASI/Ajar Objek).</p>
                <div class="teach-row">
                    <input id="teachLabel" type="text" maxlength="40" placeholder="Nama objek, mis. &quot;botol hijau&quot;" aria-label="Nama objek">
                    <button id="teachSave" type="button" disabled>Simpan contoh</button>
                    <button id="teachCancel" type="button" class="secondary">Batal</button>
                </div>
                <p id="teachStatus" class="meta" role="status" aria-live="polite"></p>
                <div id="teachClasses" class="teach-classes"></div>
            </div>

            <p id="status" class="status" role="status" aria-live="polite">Tekan “Mulai Realtime” untuk mengaktifkan kamera dan analisis otomatis.</p>
            <p id="error" class="error" role="alert" hidden></p>
            <p class="hint">Semua kontrol dapat digunakan dengan keyboard (Tab lalu Enter/Space). Jika browser tidak mendukung suara, teks tetap ditampilkan di layar.</p>
        </section>

        <aside class="panel" aria-labelledby="results-heading">
            <div class="panel-head">
                <h2 id="results-heading">Status &amp; Hasil</h2>
            </div>

            <div class="indicators">
                <div id="statCam" class="indicator" data-state="off">Kamera: Belum aktif</div>
                <div id="statYolo" class="indicator">YOLO: Menunggu</div>
                <div id="statVision" class="indicator">Vision AI: Menunggu</div>
                <div id="statSound" class="indicator" data-state="ok">Suara: Aktif</div>
            </div>

            <h3>Objek terlihat (YOLO)</h3>
            <div class="stats">
                <span class="stat" id="statCount">0 benda</span>
                <span class="stat" id="statModel">Model: -</span>
                <span class="stat" id="statProvider">Penyedia: -</span>
            </div>
            <ul id="objectList" class="objects">
                <li class="muted">Belum ada benda yang terdeteksi.</li>
            </ul>
            <p class="hint">Angka persen adalah keyakinan deteksi YOLO, bukan tingkat kepastian deskripsi vision AI.</p>

            <h3>Deskripsi lingkungan (Vision AI)</h3>
            <div class="desc-card">
                <p id="description" class="result muted" aria-live="polite">Menunggu analisis otomatis pertama…</p>
            </div>
            <p id="visionMeta" class="meta">Belum ada analisis dijalankan.</p>
        </aside>
    </div>

    <section class="panel diag-panel" aria-labelledby="diag-heading">
        <div class="panel-head">
            <h2 id="diag-heading">Diagnostik Sistem</h2>
            <button id="diagRun" type="button" class="secondary">Jalankan Uji</button>
        </div>
        <p class="hint">Uji ini mengukur kecepatan tiap bagian agar terlihat mana yang belum maksimal. Uji vision memakai 1 permintaan kuota (Gemini); bila kamera belum aktif, gambar uji sintetis dipakai.</p>
        <p id="diagSummary" class="diag-summary" role="status" aria-live="polite">Belum diuji. Tekan “Jalankan Uji”.</p>
        <ul id="diagList" class="diag-list">
            <li id="diagServer" class="diag-item"><span class="diag-name">Server &amp; layanan AI</span><span class="diag-lat">—</span><span class="diag-note">Belum diuji.</span></li>
            <li id="diagCamera" class="diag-item"><span class="diag-name">Kamera</span><span class="diag-lat">—</span><span class="diag-note">Belum diuji.</span></li>
            <li id="diagYolo" class="diag-item"><span class="diag-name">Deteksi YOLO</span><span class="diag-lat">—</span><span class="diag-note">Belum diuji.</span></li>
            <li id="diagVision" class="diag-item"><span class="diag-name">Vision AI (Gemini/Ollama)</span><span class="diag-lat">—</span><span class="diag-note">Belum diuji.</span></li>
            <li id="diagSound" class="diag-item"><span class="diag-name">Suara (TTS)</span><span class="diag-lat">—</span><span class="diag-note">Belum diuji.</span></li>
        </ul>
        <div class="diag-legend">
            <span><i class="diag-dot" style="background:var(--accent)"></i>Optimal</span>
            <span><i class="diag-dot" style="background:var(--warn)"></i>Bisa lebih baik</span>
            <span><i class="diag-dot" style="background:var(--danger)"></i>Bermasalah</span>
        </div>
    </section>

    <footer class="foot">
        <strong>Peringatan penting:</strong> aplikasi ini adalah alat bantu, bukan pengganti tongkat, anjing pemandu, atau alat navigasi khusus. Sistem tidak mengukur jarak dan tidak menjamin jalur aman. Deteksi berasal dari model yang dapat keliru, terutama untuk benda kecil, gambar gelap, atau objek jauh. Gunakan selalu bersama alat bantu mobilitas yang biasa Anda pakai.
    </footer>
</main>
<script>
(() => {
    'use strict';

    const byId = (id) => document.getElementById(id);
    const video = byId('video');
    const canvas = byId('overlay');
    const ctx = canvas.getContext('2d');
    const statusEl = byId('status');
    const errorEl = byId('error');
    const objectList = byId('objectList');
    const descriptionEl = byId('description');
    const visionMeta = byId('visionMeta');
    const privacyNotice = byId('privacyNotice');
    const statProvider = byId('statProvider');
    const statCount = byId('statCount');
    const statModel = byId('statModel');
    const statCam = byId('statCam');
    const statYolo = byId('statYolo');
    const statVision = byId('statVision');
    const statSound = byId('statSound');
    const stateBadge = byId('stateBadge');
    const startBtn = byId('start');
    const pauseBtn = byId('pause');
    const soundBtn = byId('sound');
    const analyzeBtn = byId('analyze');
    const stopBtn = byId('stop');
    const intervalSelect = byId('visionInterval');
    const cameraSelect = byId('cameraSelect');
    const cameraHint = byId('cameraHint');
    const diagRun = byId('diagRun');
    const diagSummary = byId('diagSummary');
    const teachBtn = byId('teach');
    const teachPanel = byId('teachPanel');
    const teachLabelInput = byId('teachLabel');
    const teachSaveBtn = byId('teachSave');
    const teachCancelBtn = byId('teachCancel');
    const teachStatus = byId('teachStatus');
    const teachClasses = byId('teachClasses');

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const DETECT_URL = '/api/ai-cam/detect';
    const DESCRIBE_URL = '/api/ai-cam/describe';
    const HEALTH_URL = '/api/ai-cam/health';
    const ANNOTATE_URL = '/api/ai-cam/annotate';
    const DATASET_URL = '/api/ai-cam/dataset';

    const CONF_MIN = 0.5;
    const DETECT_INTERVAL = 1200;
    const DETECT_INTERVAL_BUSY = 3000;
    const DETECT_MAX_INTERVAL = 10000;
    const DETECT_TIMEOUT = 15000;
    const DESCRIBE_TIMEOUT = 200000;
    const SPEECH_COOLDOWN = 4000;
    const DETECT_SPEECH_COOLDOWN = 6000;
    const VISION_SIMILARITY_THRESHOLD = 0.55;
    const REANNOUNCE_AFTER = 15000;

    const supportsSpeech = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;

    let stream = null;
    let running = false;
    let paused = false;
    let soundOn = true;
    let busy = false;
    let visionBusy = false;
    let visionAttempted = false;
    let visionHealthy = false;
    let visionErrors = 0;
    let timer = null;
    let visionTimer = null;
    let visionIntervalMs = 10000;
    let currentDeviceId = '';
    let replacingStream = false;
    let detectErrors = 0;
    let lastData = null;
    let lastDetectSignature = '';
    let lastSpokenAt = 0;
    let lastAnnouncedAt = 0;
    let lastVisionKey = '';
    let diagRunning = false;
    let teaching = false;
    let teachSource = null;
    let teachBox = null;
    let teachPointer = null;

    const namesID = {
        person:'orang', bicycle:'sepeda', car:'mobil', motorcycle:'sepeda motor',
        airplane:'pesawat', bus:'bus', train:'kereta', truck:'truk', boat:'perahu',
        'traffic light':'lampu lalu lintas', 'fire hydrant':'hidran', 'stop sign':'rambu berhenti',
        'parking meter':'meteran parkir', bench:'bangku', bird:'burung', cat:'kucing', dog:'anjing',
        horse:'kuda', sheep:'domba', cow:'sapi', elephant:'gajah', bear:'beruang', zebra:'zebra',
        giraffe:'jerapah', backpack:'ransel', umbrella:'payung', handbag:'tas tangan',
        tie:'dasi', suitcase:'koper', frisbee:'frisbee', skis:'ski', snowboard:'papan seluncur',
        'sports ball':'bola', kite:'layang-layang', 'baseball bat':'tongkat bisbol',
        'baseball glove':'sarung tangan bisbol', skateboard:'papan skateboard',
        surfboard:'papan selancar', 'tennis racket':'raket tenis', bottle:'botol',
        'wine glass':'gelas berkaki', cup:'cangkir', fork:'garpu', knife:'pisau', spoon:'sendok',
        bowl:'mangkuk', banana:'pisang', apple:'apel', sandwich:'roti lapis', orange:'jeruk',
        broccoli:'brokoli', carrot:'wortel', 'hot dog':'hot dog', pizza:'pizza', donut:'donat',
        cake:'kue', chair:'kursi', couch:'sofa', 'potted plant':'tanaman dalam pot',
        bed:'tempat tidur', 'dining table':'meja makan', toilet:'toilet', tv:'televisi',
        laptop:'laptop', mouse:'mouse komputer', remote:'remote', keyboard:'keyboard',
        'cell phone':'ponsel', microwave:'microwave', oven:'oven', toaster:'pemanggang roti',
        sink:'wastafel', refrigerator:'kulkas', book:'buku', clock:'jam', vase:'vas',
        scissors:'gunting', 'teddy bear':'boneka beruang', 'hair drier':'pengering rambut',
        toothbrush:'sikat gigi'
    };

    function idName(label) { return namesID[label] || String(label).replaceAll('_', ' '); }
    function setStatus(text) { statusEl.textContent = text; }
    function setBadge(text, state) { stateBadge.textContent = text; stateBadge.dataset.state = state; }
    function showError(text) { errorEl.textContent = text; errorEl.hidden = !text; }
    function clearError() { errorEl.textContent = ''; errorEl.hidden = true; }
    function setIndicator(el, label, value, state) {
        el.textContent = label + ': ' + value;
        el.dataset.state = state || '';
    }

    const speechQueue = [];
    let speaking = false;

    function cancelSpeech() {
        speechQueue.length = 0;
        if (supportsSpeech) {
            try { window.speechSynthesis.cancel(); } catch (error) { /* diabaikan */ }
        }
        speaking = false;
    }

    function pumpSpeech() {
        if (speaking || !speechQueue.length || !supportsSpeech) return;
        const text = speechQueue.shift();
        try {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            const voices = (window.speechSynthesis.getVoices && window.speechSynthesis.getVoices()) || [];
            const idVoice = voices.find((voice) => /^id[-_]ID$/i.test(voice.lang));
            if (idVoice) utterance.voice = idVoice;
            speaking = true;
            utterance.onend = () => { speaking = false; pumpSpeech(); };
            utterance.onerror = () => { speaking = false; pumpSpeech(); };
            window.speechSynthesis.speak(utterance);
        } catch (error) {
            speaking = false;
        }
    }

    function speak(text, options) {
        const priority = options && options.priority;
        if (!supportsSpeech || !soundOn || !text) return false;
        if (priority) cancelSpeech();
        speechQueue.push(text);
        pumpSpeech();
        return true;
    }

    function providerName(provider) {
        if (provider === 'gemini') return 'Gemini (cloud)';
        if (provider === 'ollama') return 'Ollama (lokal)';
        return provider || 'Tidak diketahui';
    }

    function updatePrivacyNotice(provider) {
        if (privacyNotice) privacyNotice.hidden = provider !== 'gemini';
    }

    function normalizeKey(text) {
        return String(text).toLowerCase().replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function keyTokens(key) {
        return key.split(' ').filter((word) => word.length > 3);
    }

    function similarity(a, b) {
        const setA = new Set(keyTokens(a));
        const setB = new Set(keyTokens(b));
        if (!setA.size || !setB.size) return 0;
        let intersection = 0;
        for (const word of setA) if (setB.has(word)) intersection += 1;
        return intersection / (setA.size + setB.size - intersection);
    }

    function shortenForSpeech(text) {
        const sentences = String(text).replace(/([.!?])\s+/g, '$1|').split('|').filter((part) => part.trim());
        let output = sentences.slice(0, 2).join(' ').trim();
        if (output.length > 200) output = output.slice(0, 197).trim() + '…';
        return output || String(text).slice(0, 200);
    }

    async function captureBlob(options) {
        const maxSide = (options && options.maxSide) || 800;
        const quality = (options && options.quality) || 0.7;
        if (!video.videoWidth || !video.videoHeight) throw new Error('Kamera sedang disiapkan.');
        const temp = document.createElement('canvas');
        const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
        temp.width = Math.round(video.videoWidth * scale);
        temp.height = Math.round(video.videoHeight * scale);
        temp.getContext('2d').drawImage(video, 0, 0, temp.width, temp.height);
        return new Promise((resolve, reject) => {
            temp.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error('Gagal mengambil gambar kamera.'))),
                'image/jpeg',
                quality
            );
        });
    }

    async function postBlob(url, blob, timeoutMs) {
        const form = new FormData();
        form.append('image', blob, 'camera.jpg');

        const controller = new AbortController();
        const abort = setTimeout(() => controller.abort(), timeoutMs);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: form,
                signal: controller.signal
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(data.detail || data.message || ('Permintaan gagal (' + response.status + ')'));
                error.status = response.status;
                throw error;
            }
            return data;
        } finally {
            clearTimeout(abort);
        }
    }

    async function postImage(url, timeoutMs, captureOptions) {
        const blob = await captureBlob(captureOptions);
        return postBlob(url, blob, timeoutMs);
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, (char) => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]
        ));
    }

    function normalizedBox(box) {
        if (!box) return null;
        return {
            x1: Math.min(box.x1, box.x2), x2: Math.max(box.x1, box.x2),
            y1: Math.min(box.y1, box.y2), y2: Math.max(box.y1, box.y2)
        };
    }

    function canvasToBlob(source, quality) {
        return new Promise((resolve, reject) => {
            source.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error('Gagal membuat gambar.'))),
                'image/jpeg',
                quality
            );
        });
    }

    function teachMetrics() {
        const rect = canvas.getBoundingClientRect();
        const vw = teachSource ? teachSource.width : 1;
        const vh = teachSource ? teachSource.height : 1;
        const scale = Math.min(rect.width / vw, rect.height / vh);
        const dw = vw * scale, dh = vh * scale;
        return { rect, dw, dh, dx: (rect.width - dw) / 2, dy: (rect.height - dh) / 2 };
    }

    function pointToNormalized(event) {
        const { rect, dw, dh, dx, dy } = teachMetrics();
        const x = (event.clientX - rect.left - dx) / dw;
        const y = (event.clientY - rect.top - dy) / dh;
        return { x: Math.min(Math.max(x, 0), 1), y: Math.min(Math.max(y, 0), 1) };
    }

    function drawTeaching() {
        if (!teachSource) return;
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(rect.width * dpr);
        canvas.height = Math.round(rect.height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, rect.width, rect.height);
        const { dw, dh, dx, dy } = teachMetrics();
        ctx.drawImage(teachSource, dx, dy, dw, dh);
        const box = normalizedBox(teachBox);
        if (box && (box.x2 - box.x1) > 0 && (box.y2 - box.y1) > 0) {
            const x = dx + box.x1 * dw, y = dy + box.y1 * dh;
            const w = (box.x2 - box.x1) * dw, h = (box.y2 - box.y1) * dh;
            ctx.fillStyle = 'rgba(124,157,255,.18)';
            ctx.fillRect(x, y, w, h);
            ctx.strokeStyle = '#7c9dff';
            ctx.lineWidth = 2;
            ctx.strokeRect(x, y, w, h);
            const label = teachLabelInput.value.trim() || 'objek';
            ctx.font = 'bold 13px system-ui';
            ctx.fillStyle = '#7c9dff';
            ctx.fillText(label, x + 5, Math.max(14, y - 6));
        }
    }

    function updateTeachSave() {
        const box = normalizedBox(teachBox);
        const hasBox = box && (box.x2 - box.x1) >= 0.01 && (box.y2 - box.y1) >= 0.01;
        teachSaveBtn.disabled = !(hasBox && teachLabelInput.value.trim().length > 0);
    }

    function renderTeachClasses(classes) {
        const entries = Object.entries(classes || {});
        if (!entries.length) {
            teachClasses.innerHTML = '<span class="muted">Belum ada objek yang diajari.</span>';
            return;
        }
        teachClasses.innerHTML = entries
            .sort((a, b) => b[1] - a[1])
            .map(([name, count]) => '<span class="teach-chip">' + escapeHtml(name) + ' <b>' + count + '</b></span>')
            .join('');
    }

    async function refreshDataset() {
        try {
            const response = await fetch(DATASET_URL, { headers: { 'Accept': 'application/json' } });
            const data = await response.json().catch(() => ({}));
            renderTeachClasses(data.classes || {});
            if (typeof data.total === 'number') {
                const objectCount = Object.keys(data.classes || {}).length;
                teachStatus.textContent = data.total > 0
                    ? data.total + ' contoh terkumpul untuk ' + objectCount + ' objek.'
                    : 'Belum ada contoh. Tandai objek untuk mulai mengajari sistem.';
            }
        } catch (error) { /* diabaikan */ }
    }

    function startTeaching() {
        if (!running || !video.videoWidth) {
            setStatus('Nyalakan kamera dulu untuk mengajari objek.');
            return;
        }
        teaching = true;
        clearTimeout(timer);
        clearTimeout(visionTimer);
        cancelSpeech();
        const frozen = document.createElement('canvas');
        frozen.width = video.videoWidth;
        frozen.height = video.videoHeight;
        frozen.getContext('2d').drawImage(video, 0, 0, frozen.width, frozen.height);
        teachSource = frozen;
        teachBox = null;
        teachPointer = null;
        teachLabelInput.value = '';
        teachPanel.hidden = false;
        teachBtn.setAttribute('aria-pressed', 'true');
        canvas.classList.add('teaching');
        drawTeaching();
        updateTeachSave();
        setBadge('Mode ajar', 'paused');
        setIndicator(statYolo, 'YOLO', 'Dijeda (ajar)', '');
        setStatus('Mode ajar: tarik kotak pada objek, ketik namanya, lalu Simpan. Tekan Batal untuk keluar.');
        refreshDataset();
        teachLabelInput.focus();
    }

    function stopTeaching(resume) {
        const wasTeaching = teaching;
        teaching = false;
        teachPointer = null;
        teachBox = null;
        teachSource = null;
        teachPanel.hidden = true;
        teachBtn.setAttribute('aria-pressed', 'false');
        canvas.classList.remove('teaching');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (resume !== false && wasTeaching && running) {
            detectErrors = 0;
            setBadge('Kamera aktif', 'on');
            setIndicator(statYolo, 'YOLO', 'Aktif', 'ok');
            setStatus('Keluar dari mode ajar. Mengamati lingkungan…');
            detectLoop();
            if (!visionBusy) runVisionAnalysis();
        }
    }

    async function saveAnnotation() {
        if (!teaching || !teachSource) return;
        const label = teachLabelInput.value.trim();
        if (!label) { setStatus('Ketik nama objek dulu.'); teachLabelInput.focus(); return; }
        const box = normalizedBox(teachBox);
        if (!box || (box.x2 - box.x1) < 0.01 || (box.y2 - box.y1) < 0.01) {
            setStatus('Gambar kotak pada objek dulu (agak besar).');
            return;
        }
        teachSaveBtn.disabled = true;
        teachSaveBtn.textContent = 'Menyimpan…';
        try {
            const blob = await canvasToBlob(teachSource, 0.9);
            const form = new FormData();
            form.append('image', blob, 'ajar.jpg');
            form.append('label', label);
            form.append('x1', box.x1.toFixed(5));
            form.append('y1', box.y1.toFixed(5));
            form.append('x2', box.x2.toFixed(5));
            form.append('y2', box.y2.toFixed(5));
            const controller = new AbortController();
            const abort = setTimeout(() => controller.abort(), 20000);
            let response;
            try {
                response = await fetch(ANNOTATE_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: form,
                    signal: controller.signal
                });
            } finally {
                clearTimeout(abort);
            }
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.detail || data.message || ('Gagal menyimpan (' + response.status + ')'));
            }
            teachStatus.textContent = 'Tersimpan: ' + label + ' • total ' + data.total + ' contoh.';
            renderTeachClasses(data.classes || {});
            teachBox = null;
            teachPointer = null;
            teachLabelInput.value = '';
            drawTeaching();
            speak('Contoh ' + label + ' tersimpan.');
        } catch (error) {
            teachStatus.textContent = 'Gagal menyimpan: ' + (error.name === 'AbortError' ? 'waktu habis.' : error.message);
        } finally {
            teachSaveBtn.textContent = 'Simpan contoh';
            updateTeachSave();
        }
    }

    function onTeachPointerDown(event) {
        if (!teaching || !teachSource) return;
        event.preventDefault();
        try { canvas.setPointerCapture(event.pointerId); } catch (error) { /* diabaikan */ }
        const point = pointToNormalized(event);
        teachPointer = point;
        teachBox = { x1: point.x, y1: point.y, x2: point.x, y2: point.y };
        drawTeaching();
    }

    function onTeachPointerMove(event) {
        if (!teaching || !teachSource || !teachPointer) return;
        event.preventDefault();
        const point = pointToNormalized(event);
        teachBox = { x1: teachPointer.x, y1: teachPointer.y, x2: point.x, y2: point.y };
        drawTeaching();
        updateTeachSave();
    }

    function onTeachPointerUp(event) {
        if (!teaching || !teachPointer) return;
        try { canvas.releasePointerCapture(event.pointerId); } catch (error) { /* diabaikan */ }
        teachPointer = null;
        updateTeachSave();
    }

    function drawBoxes(data) {
        lastData = data;
        const rect = video.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(rect.width * dpr);
        canvas.height = Math.round(rect.height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const cw = rect.width, ch = rect.height;
        ctx.clearRect(0, 0, cw, ch);
        const vw = data && data.image_width, vh = data && data.image_height;
        if (!vw || !vh) return;
        const scale = Math.min(cw / vw, ch / vh);
        const ox = (cw - vw * scale) / 2, oy = (ch - vh * scale) / 2;
        ctx.lineWidth = 2;
        ctx.font = 'bold 13px system-ui';
        for (const item of (data.boxes || [])) {
            const [x1, y1, x2, y2] = item.box;
            const x = ox + x1 * scale, y = oy + y1 * scale;
            const w = (x2 - x1) * scale, h = (y2 - y1) * scale;
            ctx.strokeStyle = '#63e6b0';
            ctx.strokeRect(x, y, w, h);
            const label = idName(item.label) + ' ' + Math.round(Number(item.confidence) * 100) + '%';
            const tw = ctx.measureText(label).width + 10;
            const ly = Math.max(0, y - 22);
            ctx.fillStyle = '#63e6b0';
            ctx.fillRect(x, ly, tw, 20);
            ctx.fillStyle = '#04231a';
            ctx.fillText(label, x + 5, ly + 14);
        }
    }

    function renderObjects(data) {
        const items = (data.boxes || []).filter((box) => Number(box.confidence) >= CONF_MIN);
        const grouped = new Map();
        for (const box of items) {
            const name = idName(box.label);
            const conf = Number(box.confidence);
            const current = grouped.get(name) || { name, count: 0, maxConf: 0 };
            current.count += 1;
            current.maxConf = Math.max(current.maxConf, conf);
            grouped.set(name, current);
        }
        const list = [...grouped.values()].sort((a, b) => b.maxConf - a.maxConf);
        statCount.textContent = items.length + ' benda';

        objectList.innerHTML = '';
        if (!list.length) {
            const li = document.createElement('li');
            li.className = 'muted';
            li.textContent = 'Belum ada benda yang dikenali dengan cukup yakin.';
            objectList.appendChild(li);
            return;
        }
        for (const object of list) {
            const li = document.createElement('li');
            const name = document.createElement('span');
            name.className = 'obj-name';
            name.textContent = object.count > 1 ? (object.count + ' ' + object.name) : object.name;
            const conf = document.createElement('span');
            conf.className = 'obj-conf';
            conf.textContent = Math.round(object.maxConf * 100) + '%';
            li.appendChild(name);
            li.appendChild(conf);
            objectList.appendChild(li);
        }
    }

    function speakVision(text) {
        const key = normalizeKey(text);
        if (!key) return;
        if (key === lastVisionKey) return;
        const now = Date.now();
        if (lastVisionKey && similarity(key, lastVisionKey) > VISION_SIMILARITY_THRESHOLD) {
            lastVisionKey = key;
            return;
        }
        const spoken = shortenForSpeech(text);
        if (speak(spoken, { priority: true })) {
            lastVisionKey = key;
            lastSpokenAt = now;
        }
    }

    function speakDetections(data) {
        const items = (data.boxes || []).filter((box) => Number(box.confidence) >= CONF_MIN);
        const names = [...new Set(items.map((box) => idName(box.label)))];
        if (!names.length) { lastDetectSignature = ''; return; }
        const signature = names.slice().sort().join('|');
        const now = Date.now();
        const changed = signature !== lastDetectSignature;
        const stale = now - lastAnnouncedAt > REANNOUNCE_AFTER;
        if (!changed && !stale) return;
        if (now - lastSpokenAt < DETECT_SPEECH_COOLDOWN) return;
        const words = names.length === 1
            ? 'Terlihat ' + names[0] + '.'
            : 'Terlihat ' + names.slice(0, 3).join(', ') + '.';
        if (speak(words)) {
            lastDetectSignature = signature;
            lastAnnouncedAt = now;
            lastSpokenAt = now;
        }
    }

    function scheduleDetect() {
        clearTimeout(timer);
        if (!running || paused || teaching) return;
        let delay = busy || visionBusy ? DETECT_INTERVAL_BUSY : DETECT_INTERVAL;
        if (detectErrors) delay = Math.min(DETECT_INTERVAL * Math.pow(2, Math.max(0, detectErrors - 1)), DETECT_MAX_INTERVAL);
        timer = setTimeout(detectLoop, delay);
    }

    async function detectLoop() {
        if (!running || paused || busy || teaching) return;
        busy = true;
        try {
            const data = await postImage(DETECT_URL, DETECT_TIMEOUT, { maxSide: 960, quality: 0.7 });
            if (!running || paused || teaching) return;
            detectErrors = 0;
            drawBoxes(data);
            renderObjects(data);
            speakDetections(data);
            clearError();
            statModel.textContent = 'Model: ' + (data.model || '-');
            setIndicator(statYolo, 'YOLO', 'Aktif (' + (data.total || 0) + ' objek)', 'ok');
        } catch (error) {
            detectErrors += 1;
            setIndicator(statYolo, 'YOLO', 'Gagal, mencoba lagi', 'error');
            if (error.status === 503) {
                setStatus('Layanan deteksi Python tidak aktif (port 8001).');
            } else if (!error.status) {
                setStatus('Tidak dapat menghubungi server Laravel.');
            } else {
                setStatus('Deteksi YOLO bermasalah. Mencoba lagi dengan jeda lebih lama.');
            }
        } finally {
            busy = false;
            scheduleDetect();
        }
    }

    function scheduleVision() {
        clearTimeout(visionTimer);
        if (!running || paused || teaching) return;
        visionTimer = setTimeout(runVisionAnalysis, visionIntervalMs);
    }

    async function runVisionAnalysis() {
        if (!running || paused || visionBusy || teaching) return;
        visionBusy = true;
        const started = performance.now();
        setIndicator(statVision, 'Vision AI', 'Menganalisis…', 'busy');
        try {
            const data = await postImage(DESCRIBE_URL, DESCRIBE_TIMEOUT, { maxSide: 1024, quality: 0.85 });
            if (!running || paused || teaching) return;
            const text = (data.description || '').trim();
            if (!text) throw new Error('Model tidak mengembalikan deskripsi.');
            visionHealthy = true;
            visionErrors = 0;
            descriptionEl.textContent = text;
            descriptionEl.classList.remove('muted');
            speakVision(text);
            const seconds = ((performance.now() - started) / 1000).toFixed(1);
            const providerLabel = providerName(data.provider);
            const modelLabel = data.model ? ' • ' + data.model : '';
            setIndicator(statVision, 'Vision AI', 'Selesai (' + seconds + ' s)', 'ok');
            statProvider.textContent = 'Penyedia: ' + providerLabel + modelLabel;
            updatePrivacyNotice(data.provider);
            const fallbackNote = data.fallback_used ? ' • cadangan: ' + providerLabel : '';
            visionMeta.textContent = 'Analisis terakhir: ' + new Date().toLocaleTimeString('id-ID') + ' • ' + seconds + ' s' + (data.elapsed_ms ? ' (server ' + (data.elapsed_ms / 1000).toFixed(1) + ' s)' : '') + fallbackNote;
        } catch (error) {
            visionErrors += 1;
            if (visionErrors >= 2) visionHealthy = false;
            if (error.name === 'AbortError') {
                setIndicator(statVision, 'Vision AI', 'Timeout, ulangi', 'error');
                setStatus('Analisis vision melewati batas waktu. Sistem akan mencoba lagi.');
            } else if (error.status === 429) {
                setIndicator(statVision, 'Vision AI', 'Kuota habis', 'error');
                setStatus('Kuota Gemini tercapai. Sistem mencoba lagi nanti; Ollama dipakai bila tersedia.');
            } else if (error.status === 503) {
                setIndicator(statVision, 'Vision AI', 'Layanan vision mati', 'error');
                setStatus('Layanan vision tidak aktif. Gemini butuh internet; Ollama harus berjalan di port 11434.');
            } else if (error.status === 504) {
                setIndicator(statVision, 'Vision AI', 'Timeout', 'error');
                setStatus('Analisis vision terlalu lama. Perbesar jeda analisis bila jaringan atau CPU berat.');
            } else if (!error.status) {
                setIndicator(statVision, 'Vision AI', 'Server tidak terjangkau', 'error');
                setStatus('Tidak dapat menghubungi server untuk analisis vision.');
            } else {
                setIndicator(statVision, 'Vision AI', 'Gagal', 'error');
                setStatus('Analisis vision gagal: ' + error.message);
            }
        } finally {
            visionBusy = false;
            visionAttempted = true;
            scheduleVision();
        }
    }

    const DIAG_OK = { server: 1200, yolo: 2000, vision: 8000 };
    const DIAG_WARN = { server: 3000, yolo: 4000, vision: 20000 };

    function setDiag(id, state, note, latencyMs) {
        const row = byId(id);
        if (!row) return;
        row.dataset.state = state;
        row.querySelector('.diag-lat').textContent = (latencyMs == null) ? '—' : (latencyMs / 1000).toFixed(1) + ' s';
        row.querySelector('.diag-note').textContent = note;
    }

    function diagVerdict(ms, kind) {
        if (ms <= DIAG_OK[kind]) return 'ok';
        if (ms <= DIAG_WARN[kind]) return 'warn';
        return 'error';
    }

    async function diagSyntheticBlob() {
        const temp = document.createElement('canvas');
        temp.width = 640;
        temp.height = 480;
        const g = temp.getContext('2d');
        g.fillStyle = '#1a2740'; g.fillRect(0, 0, 640, 480);
        g.fillStyle = '#2f4a70'; g.fillRect(40, 300, 560, 130);
        g.fillStyle = '#5eead4'; g.fillRect(120, 140, 150, 160);
        g.fillStyle = '#ffd479'; g.beginPath(); g.arc(470, 220, 80, 0, Math.PI * 2); g.fill();
        g.fillStyle = '#dce7f7'; g.font = 'bold 26px sans-serif'; g.fillText('AI Cam - uji sistem', 160, 440);
        return new Promise((resolve, reject) => {
            temp.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Gagal membuat gambar uji.'))), 'image/jpeg', 0.85);
        });
    }

    async function runDiagnostics() {
        if (diagRunning) return;
        diagRunning = true;
        diagRun.disabled = true;
        diagSummary.dataset.state = 'busy';
        diagSummary.textContent = 'Menjalankan uji…';
        ['diagServer', 'diagCamera', 'diagYolo', 'diagVision', 'diagSound'].forEach((id) => setDiag(id, 'busy', 'Menguji…', null));

        const problems = [];
        let blob = null;

        try {
            const started = performance.now();
            const response = await fetch(HEALTH_URL, { headers: { 'Accept': 'application/json' } });
            const ms = performance.now() - started;
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const provider = data.vision_provider || 'ollama';
            const modelLabel = data.vision_model ? ' • ' + data.vision_model : '';
            const state = diagVerdict(ms, 'server');
            setDiag('diagServer', state, 'YOLO ' + (data.yolo_model || '-') + ' @ ' + (data.yolo_imgsz || '-') + 'px • Vision ' + providerName(provider) + modelLabel, ms);
            if (state !== 'ok') problems.push({ name: 'Server/layanan AI', state });
        } catch (error) {
            setDiag('diagServer', 'error', 'Tidak terjangkau. Pastikan Laravel (8000) dan FastAPI (8001) berjalan.', null);
            problems.push({ name: 'Server/layanan AI', state: 'error' });
        }

        if (running && video.videoWidth) {
            try {
                const started = performance.now();
                blob = await captureBlob({ maxSide: 1024, quality: 0.85 });
                const ms = performance.now() - started;
                setDiag('diagCamera', 'ok', video.videoWidth + '×' + video.videoHeight + ' • ' + (blob.size / 1024).toFixed(0) + ' KB per frame', ms);
            } catch (error) {
                setDiag('diagCamera', 'error', 'Gagal mengambil gambar: ' + error.message, null);
                problems.push({ name: 'Kamera', state: 'error' });
            }
        } else {
            setDiag('diagCamera', 'warn', 'Kamera belum aktif. Tekan “Mulai Realtime” untuk hasil kecepatan asli.', null);
            problems.push({ name: 'Kamera (belum aktif)', state: 'warn' });
        }

        if (!blob) {
            try { blob = await diagSyntheticBlob(); } catch (error) { /* diabaikan */ }
        }

        if (blob) {
            try {
                const started = performance.now();
                const data = await postBlob(DETECT_URL, blob, DETECT_TIMEOUT);
                const ms = performance.now() - started;
                const state = diagVerdict(ms, 'yolo');
                setDiag('diagYolo', state, (data.total || 0) + ' objek • ' + (data.model || 'model'), ms);
                if (state !== 'ok') problems.push({ name: 'Deteksi YOLO', state });
            } catch (error) {
                setDiag('diagYolo', 'error', 'Gagal: ' + error.message, null);
                problems.push({ name: 'Deteksi YOLO', state: 'error' });
            }
        }

        if (blob) {
            try {
                const started = performance.now();
                const data = await postBlob(DESCRIBE_URL, blob, DESCRIBE_TIMEOUT);
                const ms = performance.now() - started;
                const state = diagVerdict(ms, 'vision');
                const providerLabel = providerName(data.provider) + (data.model ? ' • ' + data.model : '');
                const fallbackNote = data.fallback_used ? ' • cadangan dipakai' : '';
                setDiag('diagVision', state, providerLabel + fallbackNote + ' • ' + (data.description ? 'deskripsi diterima' : 'deskripsi kosong'), ms);
                if (state !== 'ok') problems.push({ name: 'Vision AI', state });
            } catch (error) {
                let note = 'Gagal: ' + error.message;
                if (error.status === 429) note = 'Kuota Gemini habis (mis. 20/hari). Tunggu reset, pakai model lite, atau aktifkan billing.';
                else if (error.status === 503) note = 'Layanan vision tidak aktif di server Python.';
                else if (error.name === 'AbortError') note = 'Timeout: jaringan atau CPU terlalu berat.';
                setDiag('diagVision', 'error', note, null);
                problems.push({ name: 'Vision AI', state: 'error' });
            }
        }

        if (supportsSpeech) {
            let voices = [];
            try { voices = window.speechSynthesis.getVoices() || []; } catch (error) { /* diabaikan */ }
            const hasIndonesian = voices.some((voice) => /^id[-_]ID$/i.test(voice.lang));
            const state = hasIndonesian ? 'ok' : 'warn';
            setDiag('diagSound', state, (voices.length ? voices.length + ' suara tersedia' : 'Daftar suara belum termuat (buka ulang bila kosong)') + (hasIndonesian ? ' • suara Indonesia tersedia' : ' • belum ada suara id-ID'), null);
            if (!hasIndonesian) problems.push({ name: 'Suara (tanpa id-ID)', state: 'warn' });
        } else {
            setDiag('diagSound', 'error', 'Browser tidak mendukung Web Speech API.', null);
            problems.push({ name: 'Suara', state: 'error' });
        }

        const hasError = problems.some((problem) => problem.state === 'error');
        const overall = hasError ? 'error' : (problems.length ? 'warn' : 'ok');
        diagSummary.dataset.state = overall;
        if (!problems.length) {
            diagSummary.textContent = 'Semua bagian optimal. Sistem siap.';
        } else {
            diagSummary.textContent = (hasError ? 'Bermasalah: ' : 'Belum maksimal: ') + problems.map((problem) => problem.name).join(', ') + '.';
        }

        diagRun.disabled = false;
        diagRunning = false;
    }

    function friendlyCameraError(error) {
        const name = error && error.name;
        if (name === 'NotAllowedError' || name === 'SecurityError') {
            return 'Izin kamera ditolak. Aktifkan izin kamera untuk situs ini di pengaturan browser, lalu coba lagi.';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'Kamera tidak ditemukan. Pastikan perangkat memiliki kamera yang terpasang.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
        }
        if (name === 'OverconstrainedError') {
            return 'Kamera tidak mendukung resolusi yang diminta.';
        }
        return 'Kamera gagal dibuka: ' + (error && error.message ? error.message : 'penyebab tidak diketahui') + '.';
    }

    function setCameraHint() {
        const count = cameraSelect.options.length - 1;
        cameraHint.textContent = count > 0
            ? 'Pilih “Iriun Webcam” (kamera HP) bila tersedia di daftar.'
            : 'Nyalakan kamera sekali agar daftar perangkat muncul. Untuk kamera HP, pasang Iriun Webcam di HP dan PC.';
    }

    async function refreshCameraList() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
        let devices = [];
        try {
            devices = await navigator.mediaDevices.enumerateDevices();
        } catch (error) {
            return;
        }
        const previous = currentDeviceId || cameraSelect.value;
        const cams = devices.filter((device) => device.kind === 'videoinput');
        cameraSelect.innerHTML = '<option value="">Kamera otomatis</option>';
        cams.forEach((cam, index) => {
            const option = document.createElement('option');
            option.value = cam.deviceId;
            option.textContent = cam.label || ('Kamera ' + (index + 1));
            cameraSelect.appendChild(option);
        });
        const match = [...cameraSelect.options].find((option) => option.value === previous);
        if (match) cameraSelect.value = previous;
        setCameraHint();
    }

    async function openCamera() {
        const size = { width: { ideal: 1280 }, height: { ideal: 720 } };
        const wanted = currentDeviceId
            ? { deviceId: { exact: currentDeviceId }, ...size }
            : { facingMode: { ideal: 'environment' }, ...size };
        try {
            return await navigator.mediaDevices.getUserMedia({ video: wanted, audio: false });
        } catch (error) {
            if (currentDeviceId) {
                currentDeviceId = '';
                cameraSelect.value = '';
                showError('Kamera yang dipilih tidak tersedia. Beralih ke kamera otomatis.');
                try {
                    const fallback = { video: { facingMode: { ideal: 'environment' }, ...size }, audio: false };
                    return await navigator.mediaDevices.getUserMedia(fallback);
                } catch (secondError) {
                    error = secondError;
                }
            }
            if (error && (error.name === 'OverconstrainedError' || error.name === 'NotFoundError')) {
                return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            }
            throw error;
        }
    }

    async function switchCamera(deviceId) {
        currentDeviceId = deviceId || '';
        try { localStorage.setItem('aicamCameraId', currentDeviceId); } catch (error) {}
        if (!running) {
            setStatus(currentDeviceId ? 'Kamera dipilih. Tekan “Mulai Realtime” untuk memakai kamera ini.' : 'Kamera otomatis dipilih.');
            return;
        }
        replacingStream = true;
        setStatus('Mengganti kamera…');
        try {
            const newStream = await openCamera();
            if (stream) stream.getTracks().forEach((track) => track.stop());
            stream = newStream;
            video.srcObject = stream;
            await video.play();
            watchCameraTracks();
            clearError();
            setStatus('Kamera aktif. Mengamati lingkungan…');
            if (!paused && !busy) detectLoop();
        } catch (error) {
            showError(friendlyCameraError(error));
            setStatus('Gagal mengganti kamera.');
        } finally {
            replacingStream = false;
        }
    }

    function watchCameraTracks() {
        if (!stream) return;
        for (const track of stream.getVideoTracks()) {
            track.addEventListener('ended', () => {
                if (running && !replacingStream) {
                    showError('Kamera terputus atau dihentikan. Tekan “Mulai Realtime” untuk memulai lagi.');
                    stopAssistant();
                }
            });
        }
    }

    async function startAssistant() {
        if (running) return;
        clearError();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Browser tidak mendukung akses kamera. Gunakan browser modern melalui localhost atau HTTPS.');
            return;
        }
        startBtn.disabled = true;
        setStatus('Meminta izin kamera…');
        try {
            stream = await openCamera();
            video.srcObject = stream;
            await video.play();
            running = true;
            paused = false;
            detectErrors = 0;
            visionErrors = 0;
            visionAttempted = false;
            visionHealthy = false;
            lastVisionKey = '';
            lastDetectSignature = '';
            pauseBtn.disabled = false;
            pauseBtn.setAttribute('aria-pressed', 'false');
            pauseBtn.textContent = 'Jeda';
            analyzeBtn.disabled = false;
            teachBtn.disabled = false;
            stopBtn.disabled = false;
            watchCameraTracks();
            refreshCameraList();
            setBadge('Kamera aktif', 'on');
            setIndicator(statCam, 'Kamera', 'Aktif', 'ok');
            setIndicator(statYolo, 'YOLO', 'Mulai…', 'busy');
            setIndicator(statVision, 'Vision AI', 'Menganalisis…', 'busy');
            setStatus('Kamera aktif. Mengamati lingkungan dan membacakan benda secara otomatis…');
            speak('Asisten AI Cam aktif. Mengamati dan mengenali benda secara otomatis.', { priority: true });
            detectLoop();
            runVisionAnalysis();
        } catch (error) {
            running = false;
            startBtn.disabled = false;
            showError(friendlyCameraError(error));
            setStatus('Kamera tidak dapat dinyalakan.');
            setIndicator(statCam, 'Kamera', 'Gagal dibuka', 'error');
            if (stream) { stream.getTracks().forEach((track) => track.stop()); stream = null; }
        }
    }

    function togglePause() {
        if (!running) return;
        paused = !paused;
        pauseBtn.setAttribute('aria-pressed', String(paused));
        pauseBtn.textContent = paused ? 'Lanjut' : 'Jeda';
        if (paused) {
            clearTimeout(timer);
            clearTimeout(visionTimer);
            cancelSpeech();
            setBadge('Dijeda', 'paused');
            setIndicator(statCam, 'Kamera', 'Dijeda', 'busy');
            setIndicator(statVision, 'Vision AI', 'Dijeda', '');
            setStatus('Deteksi dan analisis dijeda. Tekan “Lanjut” untuk melanjutkan.');
            speak('Dijeda.', { priority: true });
        } else {
            detectErrors = 0;
            setBadge('Kamera aktif', 'on');
            setIndicator(statCam, 'Kamera', 'Aktif', 'ok');
            setStatus('Dilanjutkan. Mengamati lingkungan…');
            speak('Dilanjutkan.', { priority: true });
            detectLoop();
            runVisionAnalysis();
        }
    }

    function toggleSound() {
        if (!supportsSpeech) {
            setStatus('Browser ini tidak mendukung suara (Web Speech API). Hasil tetap ditampilkan sebagai teks.');
            return;
        }
        soundOn = !soundOn;
        soundBtn.setAttribute('aria-pressed', String(soundOn));
        soundBtn.textContent = soundOn ? 'Suara: Aktif' : 'Suara: Mati';
        setIndicator(statSound, 'Suara', soundOn ? 'Aktif' : 'Mati', soundOn ? 'ok' : '');
        if (!soundOn) cancelSpeech();
        setStatus(soundOn ? 'Suara diaktifkan.' : 'Suara dinonaktifkan.');
        if (soundOn) speak('Suara diaktifkan.');
    }

    function stopAssistant() {
        running = false;
        paused = false;
        if (teaching) stopTeaching(false);
        teachBtn.disabled = true;
        clearTimeout(timer);
        clearTimeout(visionTimer);
        timer = null;
        visionTimer = null;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        lastData = null;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        cancelSpeech();
        startBtn.disabled = false;
        pauseBtn.disabled = true;
        pauseBtn.setAttribute('aria-pressed', 'false');
        pauseBtn.textContent = 'Jeda';
        analyzeBtn.disabled = true;
        stopBtn.disabled = true;
        detectErrors = 0;
        visionErrors = 0;
        visionAttempted = false;
        visionHealthy = false;
        lastDetectSignature = '';
        setBadge('Kamera belum aktif', 'off');
        setIndicator(statCam, 'Kamera', 'Belum aktif', 'off');
        setIndicator(statYolo, 'YOLO', 'Menunggu', '');
        setIndicator(statVision, 'Vision AI', 'Menunggu', '');
        statCount.textContent = '0 benda';
        statProvider.textContent = 'Penyedia: -';
        objectList.innerHTML = '<li class="muted">Belum ada benda yang terdeteksi.</li>';
        setStatus('Dihentikan. Tekan “Mulai Realtime” untuk memulai lagi.');
    }

    async function checkService() {
        try {
            const response = await fetch(HEALTH_URL, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('unhealthy');
            const data = await response.json();
            statModel.textContent = 'Model: ' + (data.yolo_model || '-')
                + (data.yolo_custom_active ? ' + ' + (data.yolo_custom_model || 'custom') : '');
            const provider = data.vision_provider || 'ollama';
            const modelLabel = data.vision_model ? ' • ' + data.vision_model : '';
            statProvider.textContent = 'Penyedia: ' + providerName(provider) + modelLabel;
            updatePrivacyNotice(provider);
            setIndicator(statYolo, 'YOLO', 'Siap', 'ok');
            setIndicator(statVision, 'Vision AI', providerName(provider) + ' siap', 'ok');
            if (provider === 'ollama' && data.gemini_configured === false) {
                setStatus('Vision memakai Ollama lokal. Isi GEMINI_API_KEY dan GEMINI_MODEL untuk memakai Gemini.');
            }
        } catch (error) {
            setStatus('Catatan: layanan AI Python belum aktif. Jalankan FastAPI di port 8001 sebelum memulai.');
            setIndicator(statYolo, 'YOLO', 'Layanan mati', 'error');
            setIndicator(statVision, 'Vision AI', 'Layanan mati', 'error');
        }
    }

    function applyInterval(value) {
        const parsed = parseInt(value, 10);
        if (!Number.isNaN(parsed) && parsed >= 2000) visionIntervalMs = parsed;
        if (running && !paused && !visionBusy) scheduleVision();
    }

    startBtn.addEventListener('click', startAssistant);
    pauseBtn.addEventListener('click', togglePause);
    soundBtn.addEventListener('click', toggleSound);
    analyzeBtn.addEventListener('click', () => { if (running && !visionBusy) runVisionAnalysis(); });
    stopBtn.addEventListener('click', stopAssistant);
    diagRun.addEventListener('click', runDiagnostics);
    teachBtn.addEventListener('click', () => { if (teaching) { stopTeaching(true); } else { startTeaching(); } });
    teachSaveBtn.addEventListener('click', saveAnnotation);
    teachCancelBtn.addEventListener('click', () => stopTeaching(true));
    teachLabelInput.addEventListener('input', () => { updateTeachSave(); drawTeaching(); });
    teachLabelInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!teachSaveBtn.disabled) saveAnnotation();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            stopTeaching(true);
        }
    });
    canvas.addEventListener('pointerdown', onTeachPointerDown);
    canvas.addEventListener('pointermove', onTeachPointerMove);
    canvas.addEventListener('pointerup', onTeachPointerUp);
    canvas.addEventListener('pointercancel', onTeachPointerUp);
    intervalSelect.addEventListener('change', () => {
        applyInterval(intervalSelect.value);
        try { localStorage.setItem('aicamVisionInterval', intervalSelect.value); } catch (error) {}
    });
    cameraSelect.addEventListener('change', () => { switchCamera(cameraSelect.value); });
    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', refreshCameraList);
    }
    window.addEventListener('beforeunload', stopAssistant);
    window.addEventListener('resize', () => {
        if (teaching) {
            drawTeaching();
        } else if (lastData) {
            drawBoxes(lastData);
        } else if (video.videoWidth) {
            drawBoxes({ image_width: video.videoWidth, image_height: video.videoHeight, boxes: [] });
        }
    });

    if (!supportsSpeech) {
        soundOn = false;
        soundBtn.disabled = true;
        soundBtn.textContent = 'Suara: Tidak didukung';
        setIndicator(statSound, 'Suara', 'Tidak didukung', 'error');
    } else {
        try {
            const saved = localStorage.getItem('aicamVisionInterval');
            if (saved && intervalSelect.querySelector('option[value="' + saved + '"]')) {
                intervalSelect.value = saved;
            }
        } catch (error) {}
    }
    applyInterval(intervalSelect.value);

    try {
        const savedCamera = localStorage.getItem('aicamCameraId');
        if (savedCamera) currentDeviceId = savedCamera;
    } catch (error) {}
    refreshCameraList();
    checkService();
})();
</script>
</body>
</html>

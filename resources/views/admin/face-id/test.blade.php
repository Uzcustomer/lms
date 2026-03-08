@extends('layouts.admin')
@section('title', 'Face ID — Sinov sahifasi')

@section('content')
<div style="max-width:1100px; margin:0 auto; padding:20px 16px; font-family:sans-serif;">

    <!-- Sarlavha -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:700; color:#1e293b; margin:0;">🧪 Face ID — Sinov sahifasi</h1>
            <p style="font-size:12px; color:#64748b; margin:4px 0 0;">Faqat admin uchun · Hech qanday login amalga oshirilmaydi</p>
        </div>
        <a href="{{ route('admin.face-id.settings') }}"
           style="padding:7px 14px; background:#f1f5f9; color:#475569; border-radius:8px; font-size:13px; text-decoration:none; border:1px solid #e2e8f0;">
            ⚙️ Sozlamalar
        </a>
    </div>

    <!-- Asosiy layout: chap kamera, o'ng debug -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        <!-- === CHAP: Kamera va boshqaruv === -->
        <div>

            <!-- Talaba tanlash -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Talaba ID raqami</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="inp-student-id"
                           placeholder="Masalan: 20202005010028"
                           style="flex:1; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none;">
                    <button id="btn-load-student"
                            style="padding:8px 14px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-size:13px; cursor:pointer; white-space:nowrap;">
                        Yuklash
                    </button>
                </div>
                <div id="student-info-box" style="display:none; margin-top:10px; padding:10px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img id="ref-img-preview" src="" alt=""
                             style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #38bdf8;"
                             onerror="this.style.opacity=0.3">
                        <div>
                            <div id="student-name-label" style="font-weight:600; font-size:13px; color:#0c4a6e;"></div>
                            <div id="student-enroll-label" style="font-size:11px; color:#0369a1; margin-top:2px;"></div>
                        </div>
                    </div>
                </div>
                <div id="student-error" style="display:none; margin-top:8px; padding:8px 10px; background:#fef2f2; color:#b91c1c; border-radius:8px; font-size:12px;"></div>
            </div>

            <!-- Kamera oynasi -->
            <div style="background:#000; border-radius:12px; overflow:hidden; position:relative; aspect-ratio:4/3;">
                <video id="video" autoplay playsinline muted
                       style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1); display:block;"></video>
                <canvas id="overlay" style="position:absolute; inset:0; width:100%; height:100%; transform:scaleX(-1); pointer-events:none;"></canvas>

                <!-- Oval ko'rsatkich -->
                <div id="oval" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-55%);
                     width:42%; padding-bottom:53%; border:3px solid rgba(255,255,255,0.5);
                     border-radius:50%; pointer-events:none; transition:border-color 0.3s; box-sizing:border-box;"></div>

                <!-- Kamera yo'q xabari -->
                <div id="no-camera-msg" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:13px;">
                    Kamera yuklanmagan
                </div>
            </div>

            <!-- Tugmalar -->
            <div style="display:flex; gap:8px; margin-top:10px;">
                <button id="btn-start-camera"
                        style="flex:1; padding:9px; background:#10b981; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                    📷 Kamerani yoqish
                </button>
                <button id="btn-capture" disabled
                        style="flex:1; padding:9px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; opacity:0.5;">
                    🔍 Taqqoslash
                </button>
                <button id="btn-stop-camera"
                        style="padding:9px 14px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; cursor:pointer;">
                    ⏹
                </button>
            </div>

            <!-- Liveness boshqaruv -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-top:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">Liveness test</div>
                <div style="display:flex; gap:8px;">
                    <button id="btn-test-blink"
                            style="flex:1; padding:8px; background:#fef9c3; color:#713f12; border:1px solid #fde047; border-radius:8px; font-size:12px; cursor:pointer;">
                        👁 Ko'z yumish testi
                    </button>
                    <button id="btn-test-head"
                            style="flex:1; padding:8px; background:#ede9fe; color:#4c1d95; border:1px solid #c4b5fd; border-radius:8px; font-size:12px; cursor:pointer;">
                        🔄 Bosh burish testi
                    </button>
                    <button id="btn-test-full"
                            style="flex:1; padding:8px; background:#dcfce7; color:#14532d; border:1px solid #86efac; border-radius:8px; font-size:12px; cursor:pointer;">
                        ✅ To'liq test
                    </button>
                </div>
                <div id="liveness-status" style="margin-top:10px; padding:10px; background:#f8fafc; border-radius:8px; font-size:13px; text-align:center; min-height:40px; color:#475569;">
                    Liveness testi boshlanmagan
                </div>
                <div style="margin-top:8px; background:#e2e8f0; border-radius:4px; height:6px;">
                    <div id="liveness-bar" style="height:6px; border-radius:4px; background:#6366f1; width:0%; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- === O'NG: Real-time debug paneli === -->
        <div>

            <!-- Sozlamalar paneli (real vaqtda o'zgartiriladi) -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:12px;">⚙️ Parametrlar (real-vaqt)</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label style="font-size:11px; color:#64748b;">Threshold (distance)</label>
                        <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                            <input type="range" id="cfg-threshold" min="0.25" max="0.70" step="0.01"
                                   value="{{ $settings['threshold'] }}"
                                   style="flex:1; accent-color:#6366f1;">
                            <span id="cfg-threshold-val" style="font-size:12px; font-weight:700; color:#6366f1; min-width:36px;">{{ $settings['threshold'] }}</span>
                        </div>
                        <div id="threshold-pct" style="font-size:11px; color:#94a3b8; margin-top:1px;">
                            ≈ {{ round((1 - $settings['threshold']/0.6) * 100) }}% yaqinlik
                        </div>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Ko'z yumish soni</label>
                        <select id="cfg-blinks" style="width:100%; margin-top:3px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                            @foreach([0,1,2,3] as $n)
                            <option value="{{ $n }}" {{ $settings['blinks_required'] == $n ? 'selected' : '' }}>
                                {{ $n }} marta{{ $n===0?' (o\'ch.)':'' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Bosh burish</label>
                        <label style="display:flex; align-items:center; gap:6px; margin-top:5px; cursor:pointer;">
                            <input type="checkbox" id="cfg-head-turn" {{ $settings['head_turn_required'] ? 'checked' : '' }}
                                   style="width:14px; height:14px; accent-color:#6366f1;">
                            <span style="font-size:12px; color:#475569;">Talab qilinsin</span>
                        </label>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Vaqt chegarasi (s)</label>
                        <input type="number" id="cfg-timeout" value="{{ $settings['liveness_timeout'] }}"
                               min="5" max="120"
                               style="width:100%; margin-top:3px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    </div>
                </div>
                <div style="font-size:11px; color:#94a3b8; margin-top:10px; padding:8px; background:#f8fafc; border-radius:6px;">
                    ⚠️ Bu sahifadagi o'zgarishlar faqat shu seans uchun. Saqlash uchun →
                    <a href="{{ route('admin.face-id.settings') }}" style="color:#6366f1;">Sozlamalar</a>
                </div>
            </div>

            <!-- Real-time metrikalari -->
            <div style="background:#0f172a; border-radius:12px; padding:14px; color:#e2e8f0; font-family:monospace; font-size:12px;">
                <div style="font-size:11px; font-weight:600; color:#94a3b8; margin-bottom:10px; font-family:sans-serif;">📊 Real-time metrikalar</div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                    <!-- Yuz topildi -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">YUZ TOPILDI</div>
                        <div id="m-face" style="font-size:1.4rem; font-weight:700;">—</div>
                    </div>
                    <!-- FPS -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">DETEKSIYA TEZLIGI</div>
                        <div id="m-fps" style="font-size:1.4rem; font-weight:700; color:#38bdf8;">—</div>
                    </div>
                    <!-- EAR -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">EAR (ko'z)</div>
                        <div id="m-ear" style="font-size:1.4rem; font-weight:700; color:#a78bfa;">—</div>
                        <div id="m-ear-status" style="font-size:10px; color:#64748b; margin-top:2px;">—</div>
                    </div>
                    <!-- Yaw -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">YAW (bosh burish)</div>
                        <div id="m-yaw" style="font-size:1.4rem; font-weight:700; color:#fb923c;">—</div>
                        <div id="m-yaw-status" style="font-size:10px; color:#64748b; margin-top:2px;">—</div>
                    </div>
                    <!-- Distance -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">DISTANCE</div>
                        <div id="m-distance" style="font-size:1.4rem; font-weight:700;">—</div>
                        <div id="m-distance-result" style="font-size:10px; margin-top:2px;">—</div>
                    </div>
                    <!-- Confidence -->
                    <div style="background:#1e293b; border-radius:8px; padding:10px;">
                        <div style="font-size:10px; color:#64748b; margin-bottom:4px;">YAQINLIK %</div>
                        <div id="m-conf" style="font-size:1.4rem; font-weight:700;">—</div>
                    </div>
                </div>

                <!-- Blink count -->
                <div style="background:#1e293b; border-radius:8px; padding:10px; margin-bottom:8px;">
                    <div style="font-size:10px; color:#64748b; margin-bottom:6px;">KO'Z YUMISH TARIXI</div>
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <span id="m-blink-count" style="font-size:1.2rem; font-weight:700; color:#4ade80;">0</span>
                        <span style="color:#475569; font-size:11px;">marta yumdi</span>
                        <span id="m-blink-dots" style="color:#4ade80; font-size:16px; letter-spacing:2px;"></span>
                    </div>
                </div>

                <!-- Log oyna -->
                <div style="background:#1e293b; border-radius:8px; padding:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:10px; color:#64748b;">LOG</span>
                        <button onclick="document.getElementById('log-box').innerHTML=''" style="font-size:10px; color:#475569; background:none; border:none; cursor:pointer;">tozalash</button>
                    </div>
                    <div id="log-box" style="height:140px; overflow-y:auto; font-size:11px; line-height:1.7; color:#94a3b8;">
                        <span style="color:#475569;">Kamerani yoqing...</span>
                    </div>
                </div>
            </div>

            <!-- Taqqoslash natijasi -->
            <div id="result-box" style="display:none; background:#fff; border-radius:12px; padding:14px; margin-top:12px; border:2px solid #e2e8f0;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">🔍 Taqqoslash natijasi</div>
                <div id="result-content" style="text-align:center; padding:10px;"></div>
                <!-- Snapshot preview -->
                <div id="snapshot-preview" style="margin-top:10px; display:none;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:4px;">Snapshot:</div>
                    <img id="snapshot-img" style="width:100%; border-radius:8px; border:1px solid #e2e8f0;">
                </div>
            </div>

        </div>
    </div>

    <!-- Statistika pastda -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:16px;">
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;">
            <div style="font-size:1.5rem; font-weight:700; color:#6366f1;">{{ $enrolledCount }}</div>
            <div style="font-size:12px; color:#64748b; margin-top:4px;">Descriptor saqlangan talabalar</div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;">
            <div style="font-size:1.5rem; font-weight:700; color:#0ea5e9;">{{ $totalStudents }}</div>
            <div style="font-size:12px; color:#64748b; margin-top:4px;">HEMIS rasmi bor talabalar</div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;">
            <div style="font-size:1.5rem; font-weight:700; color:#10b981;" id="stat-session-ok">0</div>
            <div style="font-size:12px; color:#64748b; margin-top:4px;">Shu seans muvaffaqiyatli</div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function () {
'use strict';

// ─── Config (sahifadagi slayderlardan o'qiladi) ─────────────────────────────
function getCfg() {
    return {
        threshold:    parseFloat(document.getElementById('cfg-threshold').value),
        blinks:       parseInt(document.getElementById('cfg-blinks').value),
        headTurn:     document.getElementById('cfg-head-turn').checked,
        timeout:      parseInt(document.getElementById('cfg-timeout').value) * 1000,
    };
}

const MODELS_PATH = '/face-models';
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
const CHECK_URL   = '{{ route('student.face-id.check-student') }}';
const PHOTO_URL   = '{{ route('student.face-id.photo', ['id' => ':id']) }}';
const SAVE_URL    = '{{ route('student.face-id.save-descriptor') }}';

// ─── State ──────────────────────────────────────────────────────────────────
let modelsLoaded  = false;
let stream        = null;
let detectLoop    = null;
let studentData   = null;   // {student_id, full_name, photo_url, has_descriptor}
let refDescriptor = null;   // Float32Array
let blinkCount    = 0;
let eyeClosed     = false;
let sessionOk     = 0;

// Liveness state
let liveness = { running: false, phase: 'idle', blinksDone: 0, headDone: false,
                 dir: 'left', startTs: 0, passed: false };

// FPS counter
let lastFrameTs = 0;
let fps = 0;

const $ = id => document.getElementById(id);

// ─── Logger ────────────────────────────────────────────────────────────────
function log(msg, color = '#94a3b8') {
    const box = $('log-box');
    const now  = new Date().toLocaleTimeString('uz');
    box.innerHTML += `<span style="color:${color}">[${now}] ${msg}</span>\n`;
    box.scrollTop  = box.scrollHeight;
}

// ─── Threshold slider ──────────────────────────────────────────────────────
$('cfg-threshold').addEventListener('input', function() {
    const v = parseFloat(this.value);
    $('cfg-threshold-val').textContent = v.toFixed(2);
    $('threshold-pct').textContent = '≈ ' + Math.round((1 - v/0.6)*100) + '% yaqinlik';
});

// ─── Models ────────────────────────────────────────────────────────────────
async function loadModels() {
    log('Modellar yuklanmoqda...', '#38bdf8');
    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_PATH),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_PATH),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_PATH),
    ]);
    modelsLoaded = true;
    log('✅ Modellar yuklandi', '#4ade80');
}

// ─── Camera ────────────────────────────────────────────────────────────────
$('btn-start-camera').addEventListener('click', async () => {
    $('no-camera-msg').style.display = 'none';
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width:{ideal:640}, height:{ideal:480}, facingMode:'user' }
        });
        $('video').srcObject = stream;
        await new Promise(res => $('video').onloadedmetadata = res);
        $('overlay').width  = $('video').videoWidth;
        $('overlay').height = $('video').videoHeight;
        log('📷 Kamera yoqildi', '#4ade80');

        if (!modelsLoaded) await loadModels();
        startContinuousDetection();
        $('btn-capture').disabled = false;
        $('btn-capture').style.opacity = '1';
    } catch(e) {
        log('❌ Kamera xatosi: ' + e.message, '#f87171');
    }
});

$('btn-stop-camera').addEventListener('click', () => {
    stopCamera();
    log('⏹ Kamera to\'xtatildi', '#94a3b8');
});

function stopCamera() {
    if (detectLoop) { clearInterval(detectLoop); detectLoop = null; }
    if (stream) { stream.getTracks().forEach(t=>t.stop()); stream = null; }
    $('btn-capture').disabled = true;
    $('btn-capture').style.opacity = '0.5';
}

// ─── EAR & Yaw ─────────────────────────────────────────────────────────────
const EAR_CLOSED = 0.22;
const EAR_OPEN   = 0.28;
const YAW_THRESH = 0.18;

function computeEAR(lm) {
    const p = lm.positions;
    const ear = (i1,i2,i3,i4,i5,i6) => {
        const A = dist(p[i2], p[i6]);
        const B = dist(p[i3], p[i5]);
        const C = dist(p[i1], p[i4]);
        return (A+B) / (2*C);
    };
    return (ear(36,37,38,39,40,41) + ear(42,43,44,45,46,47)) / 2;
}

function computeYaw(lm) {
    const p = lm.positions;
    const nose = p[30], le = p[39], re = p[42];
    const cx = (le.x+re.x)/2;
    const fw = Math.abs(re.x-le.x);
    return fw > 0 ? (nose.x - cx) / fw : 0;
}

function dist(a, b) {
    return Math.hypot(a.x-b.x, a.y-b.y);
}

// ─── Continuous detection loop ──────────────────────────────────────────────
function startContinuousDetection() {
    if (detectLoop) clearInterval(detectLoop);
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize:224, scoreThreshold:0.4 });
    const ctx   = $('overlay').getContext('2d');

    detectLoop = setInterval(async () => {
        if (!stream || !$('video').videoWidth) return;

        // FPS
        const now = performance.now();
        fps = now - lastFrameTs > 0 ? Math.round(1000/(now-lastFrameTs)) : fps;
        lastFrameTs = now;
        $('m-fps').textContent = fps + ' fps';

        ctx.clearRect(0, 0, $('overlay').width, $('overlay').height);

        let det;
        try {
            det = await faceapi.detectSingleFace($('video'), opts)
                    .withFaceLandmarks().withFaceDescriptor();
        } catch(e) { return; }

        if (!det) {
            $('m-face').textContent = '❌';
            $('m-face').style.color = '#f87171';
            $('oval').style.borderColor = 'rgba(255,255,255,0.3)';
            return;
        }

        $('m-face').textContent = '✅';
        $('m-face').style.color = '#4ade80';
        $('oval').style.borderColor = '#4ade80';

        // Overlay — landmark nuqtalar
        ctx.fillStyle = 'rgba(99,102,241,0.7)';
        det.landmarks.positions.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, 1.5, 0, 2*Math.PI);
            ctx.fill();
        });

        // Metrics
        const ear = computeEAR(det.landmarks);
        const yaw = computeYaw(det.landmarks);

        // EAR
        $('m-ear').textContent = ear.toFixed(3);
        const eyeNowClosed = ear < EAR_CLOSED;
        if (eyeNowClosed) {
            $('m-ear').style.color = '#fb923c';
            $('m-ear-status').textContent = '👁‍🗨 YUMIQ';
        } else if (ear < EAR_OPEN) {
            $('m-ear').style.color = '#fbbf24';
            $('m-ear-status').textContent = '〰 O\'tish';
        } else {
            $('m-ear').style.color = '#a78bfa';
            $('m-ear-status').textContent = '👁 OCHIQ';
        }

        // Global blink counter
        if (eyeNowClosed && !eyeClosed) { eyeClosed = true; }
        if (!eyeNowClosed && eyeClosed) {
            eyeClosed = false;
            blinkCount++;
            $('m-blink-count').textContent = blinkCount;
            $('m-blink-dots').textContent  = '●'.repeat(Math.min(blinkCount, 10));
            log(`👁 Ko'z yumdi #${blinkCount} (EAR peak: ${ear.toFixed(3)})`, '#c4b5fd');
        }

        // Yaw
        $('m-yaw').textContent = yaw.toFixed(3);
        if (yaw < -YAW_THRESH) {
            $('m-yaw').style.color = '#fb923c';
            $('m-yaw-status').textContent = '← Chap';
        } else if (yaw > YAW_THRESH) {
            $('m-yaw').style.color = '#fb923c';
            $('m-yaw-status').textContent = '→ O\'ng';
        } else {
            $('m-yaw').style.color = '#94a3b8';
            $('m-yaw-status').textContent = 'To\'g\'ri';
        }

        // Distance (agar ref descriptor yuklangan bo'lsa)
        if (refDescriptor) {
            const live = det.descriptor;
            let sum = 0;
            for (let i = 0; i < 128; i++) {
                const d = live[i] - refDescriptor[i];
                sum += d*d;
            }
            const dist_ = Math.sqrt(sum);
            const conf  = Math.max(0, Math.min(100, (1 - dist_/0.6)*100));
            const thr   = getCfg().threshold;

            $('m-distance').textContent = dist_.toFixed(3);
            $('m-conf').textContent = conf.toFixed(1) + '%';

            if (dist_ <= thr) {
                $('m-distance').style.color = '#4ade80';
                $('m-distance-result').style.color = '#4ade80';
                $('m-distance-result').textContent = '✅ MOS (' + thr + ' dan kichik)';
            } else {
                $('m-distance').style.color = '#f87171';
                $('m-distance-result').style.color = '#f87171';
                $('m-distance-result').textContent = '❌ MOS EMAS (' + thr + ' dan katta)';
            }
        }

        // Liveness jarayon
        if (liveness.running) processLiveness(det, ear, yaw);

    }, 150);
}

// ─── Liveness jarayon ────────────────────────────────────────────────────────
function processLiveness(det, ear, yaw) {
    const cfg = getCfg();
    const elapsed = Date.now() - liveness.startTs;

    if (elapsed > cfg.timeout) {
        liveness.running = false;
        setLivenessStatus('⏰ Vaqt tugadi! Qayta bosing.', '#f87171', 0);
        log('⏰ Liveness timeout', '#f87171');
        return;
    }

    const totalSteps = (cfg.blinks > 0 ? 1 : 0) + (cfg.headTurn ? 1 : 0);
    let doneSteps = 0;

    // Blink bosqich
    if (cfg.blinks > 0 && liveness.phase === 'blink') {
        const eyeNowClosed = ear < EAR_CLOSED;
        if (eyeNowClosed && !liveness._eyeClosed) liveness._eyeClosed = true;
        if (!eyeNowClosed && liveness._eyeClosed) {
            liveness._eyeClosed = false;
            liveness.blinksDone++;
            log(`👁 Liveness blink ${liveness.blinksDone}/${cfg.blinks}`, '#c4b5fd');
            setLivenessStatus(`Ko'z yumish: ${liveness.blinksDone}/${cfg.blinks}`, '#6366f1', (liveness.blinksDone/cfg.blinks)*50);
        }
        if (liveness.blinksDone >= cfg.blinks) {
            doneSteps++;
            liveness.phase = cfg.headTurn ? 'head' : 'done';
            if (cfg.headTurn) {
                liveness.dir = Math.random() > 0.5 ? 'left' : 'right';
                const label = liveness.dir === 'left' ? '← Chapga' : '→ O\'ngga';
                setLivenessStatus(`Boshingizni ${label} burting`, '#f59e0b', 55);
                log(`🔄 Bosh burish bosqichi: ${liveness.dir}`, '#fb923c');
            }
        } else {
            setLivenessStatus(`Ko'zingizni yumib-oching (${liveness.blinksDone}/${cfg.blinks})`, '#6366f1',
                Math.round(liveness.blinksDone / cfg.blinks * 45));
        }
    }

    // Head turn bosqich
    if (cfg.headTurn && liveness.phase === 'head') {
        const turned = liveness.dir === 'left' ? (yaw < -YAW_THRESH) : (yaw > YAW_THRESH);
        if (turned && !liveness.headDone) {
            liveness.headDone = true;
            log(`✅ Bosh burildi (${liveness.dir}), yaw=${yaw.toFixed(3)}`, '#4ade80');
            setLivenessStatus('Yaxshi! Endi to\'g\'ri qarang', '#10b981', 85);
        }
        if (liveness.headDone && Math.abs(yaw) < 0.08) {
            doneSteps++;
            liveness.phase = 'done';
        }
    }

    // Tugadi
    if (liveness.phase === 'done') {
        liveness.running = false;
        liveness.passed  = true;
        setLivenessStatus('✅ Liveness muvaffaqiyatli o\'tdi!', '#10b981', 100);
        log('✅ LIVENESS PASSED', '#4ade80');
    }
}

function setLivenessStatus(text, color, pct) {
    $('liveness-status').textContent = text;
    $('liveness-status').style.color = color;
    $('liveness-bar').style.width = pct + '%';
    $('liveness-bar').style.background = pct >= 100 ? '#10b981' : '#6366f1';
}

// ─── Talaba yuklash ──────────────────────────────────────────────────────────
$('btn-load-student').addEventListener('click', async () => {
    const id = $('inp-student-id').value.trim();
    if (!id) return;
    $('student-error').style.display = 'none';
    $('student-info-box').style.display = 'none';
    $('btn-load-student').textContent = '...';

    try {
        const resp = await fetch(CHECK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ student_id_number: id }),
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Xato');

        studentData = data;
        $('student-name-label').textContent  = data.full_name;
        $('student-enroll-label').textContent = data.has_descriptor
            ? '✅ Descriptor bazada mavjud (server-side taqqoslash)'
            : '⚠️ Descriptor yo\'q — HEMIS rasmidan olinadi';
        $('ref-img-preview').src = data.photo_url;
        $('student-info-box').style.display  = 'block';

        log(`👤 Talaba yuklandi: ${data.full_name}`, '#38bdf8');

        // Reference descriptor olish
        if (!modelsLoaded) {
            log('Modellar yuklanmoqda...', '#38bdf8');
            await loadModels();
        }

        log('🖼 HEMIS rasmdan descriptor olinmoqda...', '#38bdf8');
        try {
            const img = await faceapi.fetchImage(data.photo_url);
            const det = await faceapi.detectSingleFace(img,
                new faceapi.TinyFaceDetectorOptions({ inputSize:320 }))
                .withFaceLandmarks().withFaceDescriptor();

            if (!det) {
                log('⚠️ HEMIS rasmda yuz aniqlanmadi', '#fbbf24');
                $('student-enroll-label').textContent += ' · HEMIS rasmda yuz topilmadi!';
            } else {
                refDescriptor = det.descriptor;
                log(`✅ Reference descriptor tayyor (128-dim)`, '#4ade80');

                // Serverga saqlash
                fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({
                        student_id: data.student_id,
                        descriptor: Array.from(refDescriptor),
                        source_url: data.photo_url,
                    }),
                }).then(() => log('💾 Descriptor serverga saqlandi', '#4ade80'))
                  .catch(e => log('⚠️ Descriptor saqlanmadi: ' + e.message, '#fbbf24'));
            }
        } catch(e) {
            log('❌ Rasm yuklash xatosi: ' + e.message, '#f87171');
        }
    } catch(e) {
        $('student-error').textContent = e.message;
        $('student-error').style.display = 'block';
        log('❌ Talaba topilmadi: ' + e.message, '#f87171');
    } finally {
        $('btn-load-student').textContent = 'Yuklash';
    }
});

$('inp-student-id').addEventListener('keydown', e => {
    if (e.key === 'Enter') $('btn-load-student').click();
});

// ─── Taqqoslash (manual) ─────────────────────────────────────────────────────
$('btn-capture').addEventListener('click', async () => {
    if (!refDescriptor) {
        log('⚠️ Avval talabani tanlang', '#fbbf24'); return;
    }
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize:320, scoreThreshold:0.5 });
    log('📸 Skanerlanmoqda...', '#38bdf8');

    const det = await faceapi.detectSingleFace($('video'), opts)
        .withFaceLandmarks().withFaceDescriptor();

    if (!det) {
        log('❌ Yuz topilmadi, qayta urinib ko\'ring', '#f87171'); return;
    }

    const live = det.descriptor;
    let sum = 0;
    for (let i=0; i<128; i++) { const d=live[i]-refDescriptor[i]; sum+=d*d; }
    const dist_ = Math.sqrt(sum);
    const conf  = Math.max(0, Math.min(100, (1-dist_/0.6)*100));
    const thr   = getCfg().threshold;
    const pass  = dist_ <= thr;

    if (pass) sessionOk++;
    $('stat-session-ok').textContent = sessionOk;

    // Snapshot
    const sc = document.createElement('canvas');
    sc.width  = Math.min($('video').videoWidth, 320);
    sc.height = Math.round($('video').videoHeight * sc.width / $('video').videoWidth);
    sc.getContext('2d').drawImage($('video'), 0, 0, sc.width, sc.height);
    const snap = sc.toDataURL('image/jpeg', 0.7);

    $('snapshot-img').src     = snap;
    $('snapshot-preview').style.display = 'block';

    const color = pass ? '#10b981' : '#ef4444';
    const icon  = pass ? '✅' : '❌';
    $('result-content').innerHTML = `
        <div style="font-size:2rem; margin-bottom:8px;">${icon}</div>
        <div style="font-size:1rem; font-weight:700; color:${color}; margin-bottom:6px;">
            ${pass ? 'MOS KELDI' : 'MOS KELMADI'}
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; text-align:left;">
            <div style="padding:8px; background:#f8fafc; border-radius:6px; font-size:12px;">
                <div style="color:#64748b; font-size:10px; margin-bottom:2px;">DISTANCE</div>
                <span style="font-weight:700; color:${color}; font-size:1.1rem;">${dist_.toFixed(4)}</span>
                <div style="color:#94a3b8; font-size:10px;">threshold: ${thr}</div>
            </div>
            <div style="padding:8px; background:#f8fafc; border-radius:6px; font-size:12px;">
                <div style="color:#64748b; font-size:10px; margin-bottom:2px;">YAQINLIK</div>
                <span style="font-weight:700; color:${color}; font-size:1.1rem;">${conf.toFixed(1)}%</span>
                <div style="color:#94a3b8; font-size:10px;">chegaradan ${pass ? dist_.toFixed(3)+' kichik' : (dist_-thr).toFixed(3)+' katta'}</div>
            </div>
        </div>
    `;
    $('result-box').style.display = 'block';
    $('result-box').style.borderColor = color;

    log(`${icon} distance=${dist_.toFixed(3)}, conf=${conf.toFixed(1)}%, threshold=${thr}`, pass ? '#4ade80' : '#f87171');
});

// ─── Liveness tugmalar ───────────────────────────────────────────────────────
function startLiveness(phase) {
    const cfg = getCfg();
    liveness = {
        running: true, passed: false,
        phase:   phase,
        blinksDone: 0, _eyeClosed: false,
        headDone: false,
        dir: Math.random() > 0.5 ? 'left' : 'right',
        startTs: Date.now(),
    };
    log(`▶ Liveness boshlandi: ${phase}`, '#38bdf8');
    const dirLabel = liveness.dir === 'left' ? '← Chapga' : '→ O\'ngga';
    if (phase === 'blink') {
        setLivenessStatus(`Ko'zingizni ${cfg.blinks} marta yumib-oching`, '#6366f1', 5);
    } else if (phase === 'head') {
        setLivenessStatus(`Boshingizni ${dirLabel} burting`, '#f59e0b', 5);
    }
}

$('btn-test-blink').addEventListener('click', () => {
    if (!stream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    blinkCount = 0;
    $('m-blink-count').textContent = '0';
    $('m-blink-dots').textContent  = '';
    startLiveness('blink');
});

$('btn-test-head').addEventListener('click', () => {
    if (!stream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    startLiveness('head');
});

$('btn-test-full').addEventListener('click', () => {
    if (!stream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    blinkCount = 0;
    $('m-blink-count').textContent = '0';
    $('m-blink-dots').textContent  = '';
    const cfg = getCfg();
    const firstPhase = cfg.blinks > 0 ? 'blink' : (cfg.headTurn ? 'head' : 'done');
    startLiveness(firstPhase);
});

})();
</script>
@endsection

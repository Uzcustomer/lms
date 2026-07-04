<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test kiosk</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:700px;background:#fff;border:1px solid #dbe4ef;border-radius:20px;box-shadow:0 12px 32px rgba(15,23,42,.08);overflow:hidden}
        .head{padding:28px 30px 22px;background:#344e99;border-bottom:1px solid #2a407d}
        .body{padding:24px}
        .eyebrow{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#cfe0ff}
        .title{margin:10px 0 0;font-size:28px;line-height:1.15;font-weight:700;color:#fff;text-align:center}
        .tab-shell{margin-top:20px;padding:8px;background:#eaf1fb;border:1px solid #d6e2f2;border-radius:18px;display:flex;gap:8px}
        .tab-btn{flex:1;border:none;background:transparent;border-radius:12px;padding:12px 14px;font-size:14px;font-weight:600;color:#344e99;cursor:pointer;transition:.2s ease}
        .tab-btn.active{background:#fff;color:#1d4ed8;box-shadow:0 4px 14px rgba(37,99,235,.12)}
        .panel{display:none;border:1px solid #dbe4ef;border-radius:18px;background:#fff;overflow:hidden}
        .panel.active{display:block}
        .panel-head{padding:18px 20px;background:#fbfdff;border-bottom:1px solid #e2e8f0}
        .panel-title{margin:0;font-size:24px;line-height:1.2;font-weight:700}
        .panel-body{padding:20px}
        .label{display:block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#475569;margin-bottom:8px}
        .input{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:16px 18px;font-size:24px;font-weight:700;box-sizing:border-box;background:#fff}
        .input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.12)}
        .button-grid{margin-top:18px;display:grid;gap:10px}
        .btn{display:inline-flex;align-items:center;justify-content:center;width:100%;border:none;border-radius:14px;padding:14px 18px;background:#344e99;color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:.2s ease}
        .btn:hover{background:#2a407d}
        .btn-secondary{background:#fff;color:#1e40af;border:1px solid #bfdbfe}
        .btn-secondary:hover{background:#eff6ff}
        .error{margin-top:14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        .student-box{margin-top:16px;padding:14px;border-radius:14px;background:#f8fbff;border:1px solid #dbe4ef;display:none;align-items:center;gap:12px}
        .student-photo{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid #bfdbfe;background:#fff}
        .camera-box{margin-top:16px;display:none}
        .camera-frame{position:relative;width:100%;max-width:360px;margin:0 auto}
        .camera-video{width:100%;border-radius:16px;background:#0f172a;transform:scaleX(-1);aspect-ratio:3/4;object-fit:cover}
        .camera-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;border-radius:16px;background:rgba(15,23,42,.55);color:#fff;text-align:center;padding:16px}
        .result{display:none;margin-top:16px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.6}
        .muted-line{margin-top:12px;font-size:13px;color:#64748b;text-align:center}
        @media (max-width: 640px){
            .wrap{padding:14px}
            .head{padding:22px 18px 18px}
            .body{padding:16px}
            .panel-head,.panel-body{padding:16px}
            .title{font-size:24px}
            .panel-title{font-size:22px}
            .input{font-size:21px}
            .tab-shell{flex-direction:column}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div class="eyebrow">Test kiosk</div>
            <h1 class="title">Test sahifasiga kirish</h1>
            <div class="tab-shell">
                <button type="button" class="tab-btn active" data-tab-target="tab-only-id" onclick="kioskActivateTab('tab-only-id')">Talaba ID orqali kirish</button>
                <button type="button" class="tab-btn" data-tab-target="tab-face-verify" onclick="kioskActivateTab('tab-face-verify')">Face ID orqali kirish</button>
            </div>
        </div>
        <div class="body">
            <div class="panel active" id="tab-only-id">
                <div class="panel-head">
                    <h2 class="panel-title">Student ID bilan kirish</h2>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('student.test-kiosk.lookup') }}" id="kiosk-id-form">
                        @csrf
                        <label class="label">Student ID</label>
                        <input
                            type="text"
                            id="student_id_number"
                            name="student_id_number"
                            class="input"
                            value="{{ old('student_id_number') }}"
                            placeholder="Masalan: 368251100277"
                            autofocus
                            autocomplete="off"
                        >
                        @error('student_id_number')
                            <div class="error">{{ $message }}</div>
                        @enderror
                        <div class="button-grid">
                            <button type="submit" class="btn">Davom etish</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel" id="tab-face-verify">
                <div class="panel-head">
                    <h2 class="panel-title">ID + Face Verify</h2>
                </div>
                <div class="panel-body">
                    <label class="label">Student ID</label>
                    <input
                        type="text"
                        id="student_id_number_face"
                        class="input"
                        value="{{ old('student_id_number') }}"
                        placeholder="Masalan: 368251100277"
                        autocomplete="off"
                    >
                    <div class="button-grid">
                        <button type="button" class="btn" id="btn-face-verify">Face ID bilan tasdiqlash</button>
                    </div>

                    <div class="student-box" id="student-box">
                        <img src="" alt="" id="student-photo" class="student-photo" style="display:none;">
                        <div style="flex:1;">
                            <div id="student-name" style="font-size:15px;font-weight:600;color:#0f172a;"></div>
                            <div id="student-id" style="font-size:13px;color:#64748b;margin-top:3px;"></div>
                        </div>
                    </div>

                    <div class="camera-box" id="camera-box">
                        <div class="camera-frame">
                            <video id="camera-video" class="camera-video" autoplay playsinline muted></video>
                            <div class="camera-overlay" id="camera-overlay">
                                <div>
                                    <div id="camera-icon" style="font-size:30px;">📷</div>
                                    <div id="camera-title" style="font-size:14px;font-weight:600;margin-top:8px;">Kamera ochilmoqda...</div>
                                    <div id="camera-sub" style="font-size:12px;opacity:.85;margin-top:4px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="result" id="face-result"></div>
                        <div class="button-grid" style="margin-top:12px;">
                            <button type="button" class="btn" id="btn-face-retry" style="display:none;">Qayta urinish</button>
                            <button type="button" class="btn btn-secondary" id="btn-face-cancel">Bekor qilish</button>
                        </div>
                        <div class="muted-line">Talaba ID saqlanadi, Face ID esa shu talabani tasdiqlash uchun ishlatiladi.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function kioskActivateTab(targetId) {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-tab-target') === targetId);
        });
        document.querySelectorAll('.panel').forEach(function (panel) {
            panel.classList.toggle('active', panel.id === targetId);
        });
        if (targetId !== 'tab-face-verify' && typeof window.__kioskStopFace === 'function') {
            window.__kioskStopFace();
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    (function () {
        const CFG = {
            checkUrl: @json(route('student.test-kiosk.check-student')),
            verifyUrl: @json(route('student.test-kiosk.face-verify')),
            csrfToken: @json(csrf_token()),
            modelsPath: @json(asset('face-models')),
            captureDelayMs: 700,
            minFaceConfidence: 0.6,
        };

        const state = {
            student: null,
            stream: null,
            detectionLoop: null,
            steadyStart: null,
            sent: false,
        };

        const $ = (id) => document.getElementById(id);

        function syncStudentInputs(fromFaceInput) {
            if (fromFaceInput) {
                $('student_id_number').value = $('student_id_number_face').value;
            } else {
                $('student_id_number_face').value = $('student_id_number').value;
            }
        }

        function setOverlay(icon, title, sub) {
            $('camera-icon').textContent = icon;
            $('camera-title').textContent = title;
            $('camera-sub').textContent = sub || '';
            $('camera-overlay').style.display = 'flex';
        }

        function hideOverlay() {
            $('camera-overlay').style.display = 'none';
        }

        function showResult(message, type) {
            const box = $('face-result');
            box.textContent = message;
            box.style.display = 'block';
            if (type === 'success') {
                box.style.background = '#ecfdf5';
                box.style.border = '1px solid #bbf7d0';
                box.style.color = '#15803d';
            } else {
                box.style.background = '#fef2f2';
                box.style.border = '1px solid #fecaca';
                box.style.color = '#dc2626';
            }
        }

        function resetFaceUi() {
            $('student-box').style.display = 'none';
            $('camera-box').style.display = 'none';
            $('face-result').style.display = 'none';
            $('btn-face-retry').style.display = 'none';
            state.student = null;
            state.sent = false;
            state.steadyStart = null;
        }

        function stopCamera() {
            if (state.detectionLoop) {
                clearInterval(state.detectionLoop);
                state.detectionLoop = null;
            }
            if (state.stream) {
                state.stream.getTracks().forEach((track) => track.stop());
                state.stream = null;
            }
        }
        window.__kioskStopFace = function () {
            stopCamera();
            resetFaceUi();
        };

        async function loadModels() {
            await faceapi.nets.tinyFaceDetector.loadFromUri(CFG.modelsPath);
        }

        async function captureSnapshot() {
            const v = $('camera-video');
            const c = document.createElement('canvas');
            c.width = Math.min(v.videoWidth, 480);
            c.height = Math.round(v.videoHeight * c.width / v.videoWidth);
            const ctx = c.getContext('2d');
            ctx.translate(c.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(v, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.85);
        }

        async function fetchStudent(studentIdNumber) {
            const resp = await fetch(CFG.checkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CFG.csrfToken
                },
                body: JSON.stringify({ student_id_number: studentIdNumber })
            });
            const data = await resp.json();
            if (!resp.ok) {
                throw new Error(data.error || 'Talaba topilmadi.');
            }
            return data;
        }

        async function sendVerify(snapshot) {
            const resp = await fetch(CFG.verifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CFG.csrfToken
                },
                body: JSON.stringify({
                    student_id_number: $('student_id_number_face').value.trim(),
                    liveness_passed: true,
                    snapshot: snapshot
                })
            });
            const data = await resp.json();
            return { ok: resp.ok, status: resp.status, data };
        }

        async function startCamera() {
            await loadModels();
            state.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });
            $('camera-video').srcObject = state.stream;
            await new Promise((resolve) => $('camera-video').onloadedmetadata = resolve);
            await $('camera-video').play();
            runDetectionLoop();
        }

        function runDetectionLoop() {
            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: CFG.minFaceConfidence });
            state.sent = false;
            state.steadyStart = null;
            hideOverlay();

            state.detectionLoop = setInterval(async function () {
                if (state.sent) return;
                let det;
                try {
                    det = await faceapi.detectSingleFace($('camera-video'), opts);
                } catch (e) {
                    return;
                }
                if (!det) {
                    state.steadyStart = null;
                    return;
                }
                if (!state.steadyStart) {
                    state.steadyStart = Date.now();
                    return;
                }
                if (Date.now() - state.steadyStart < CFG.captureDelayMs) return;

                state.sent = true;
                clearInterval(state.detectionLoop);
                state.detectionLoop = null;

                setOverlay('🔎', 'Tekshirilmoqda...', 'Iltimos kuting');
                const snap = await captureSnapshot();
                const result = await sendVerify(snap);

                if (result.ok && result.data.success) {
                    showResult('Tasdiqlandi. ' + (result.data.student_name || '') + ' kiosk sahifasi ochilmoqda...', 'success');
                    stopCamera();
                    setTimeout(function () {
                        window.location.href = result.data.redirect;
                    }, 700);
                    return;
                }

                stopCamera();
                setOverlay('✖', 'Mos kelmadi', result.data.error || 'Face ID tekshiruvi o'tmadi.');
                showResult((result.data.error || 'Yuz mos kelmadi.') + (result.data.confidence ? ' (' + result.data.confidence + '%)' : ''), 'error');
                $('btn-face-retry').style.display = 'inline-flex';
            }, 250);
        }

        async function beginFaceFlow() {
            syncStudentInputs(true);
            const studentIdNumber = $('student_id_number_face').value.trim();
            const fieldError = document.querySelector('.error');
            if (fieldError) fieldError.style.display = 'none';
            if (!studentIdNumber) {
                alert('Avval Student ID kiriting.');
                return;
            }

            try {
                $('btn-face-verify').disabled = true;
                $('btn-face-verify').textContent = 'Tekshirilmoqda...';
                const student = await fetchStudent(studentIdNumber);
                state.student = student;
                $('student-name').textContent = student.full_name || '';
                $('student-id').textContent = studentIdNumber;
                if (student.photo_url) {
                    $('student-photo').src = student.photo_url;
                    $('student-photo').style.display = '';
                } else {
                    $('student-photo').style.display = 'none';
                }
                $('student-box').style.display = 'flex';
                $('camera-box').style.display = 'block';
                $('face-result').style.display = 'none';
                $('btn-face-retry').style.display = 'none';
                setOverlay('📷', 'Kamera ochilmoqda...', '');
                await startCamera();
            } catch (error) {
                alert(error.message || 'Face ID oqimini boshlab bo'lmadi.');
            } finally {
                $('btn-face-verify').disabled = false;
                $('btn-face-verify').textContent = 'Face ID bilan tasdiqlash';
            }
        }

        $('student_id_number').addEventListener('input', function () { syncStudentInputs(false); });
        $('student_id_number_face').addEventListener('input', function () { syncStudentInputs(true); });
        $('btn-face-verify').addEventListener('click', beginFaceFlow);
        $('btn-face-cancel').addEventListener('click', function () {
            stopCamera();
            resetFaceUi();
        });
        $('btn-face-retry').addEventListener('click', async function () {
            $('face-result').style.display = 'none';
            $('btn-face-retry').style.display = 'none';
            setOverlay('📷', 'Kamera ochilmoqda...', '');
            await startCamera();
        });

        window.addEventListener('beforeunload', stopCamera);
    })();
</script>
</body>
</html>

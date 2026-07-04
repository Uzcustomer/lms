<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test kiosk</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:620px;background:#fff;border:1px solid #dbe4ef;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);overflow:hidden}
        .head{padding:24px 26px;background:#f8fbff;border-bottom:1px solid #dbe4ef}
        .body{padding:28px 30px}
        .label{display:block;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#475569;margin-bottom:8px}
        .input{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:15px 16px;font-size:24px;font-weight:600;box-sizing:border-box}
        .input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.12)}
        .btn{display:inline-flex;align-items:center;justify-content:center;width:100%;border:none;border-radius:12px;padding:14px 18px;background:#2563eb;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
        .btn-secondary{background:#fff;color:#1e40af;border:1px solid #bfdbfe}
        .error{margin-top:14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
        .hint{margin-top:16px;font-size:14px;line-height:1.7;color:#64748b}
        .student-box{margin-top:16px;padding:14px;border-radius:12px;background:#f8fbff;border:1px solid #dbe4ef;display:none;align-items:center;gap:12px}
        .student-photo{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid #bfdbfe;background:#fff}
        .camera-box{margin-top:16px;display:none}
        .camera-frame{position:relative;width:100%;max-width:360px;margin:0 auto}
        .camera-video{width:100%;border-radius:14px;background:#0f172a;transform:scaleX(-1);aspect-ratio:3/4;object-fit:cover}
        .camera-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(15,23,42,.55);color:#fff;text-align:center;padding:16px}
        .result{display:none;margin-top:16px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.6}
        .muted-line{margin-top:12px;font-size:13px;color:#64748b;text-align:center}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div style="font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">Test kiosk</div>
            <h1 style="margin:10px 0 0;font-size:32px;line-height:1.1;font-weight:600;">Student ID bilan kirish</h1>
            <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#475569;">
                Talaba o'zining Student ID raqamini kiritadi. Agar hozirgi dars uchun test ochiq bo'lsa, shu yerdan testni boshlaydi.
            </p>
        </div>
        <div class="body">
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
                <div style="margin-top:18px;display:grid;gap:10px;">
                    <button type="submit" class="btn">Davom etish</button>
                    <button type="button" class="btn btn-secondary" id="btn-face-verify">Face ID bilan tasdiqlash</button>
                </div>
            </form>

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
                            <div id="camera-icon" style="font-size:32px;">📷</div>
                            <div id="camera-title" style="font-size:14px;font-weight:600;margin-top:8px;">Kamera ochilmoqda...</div>
                            <div id="camera-sub" style="font-size:12px;opacity:.85;margin-top:4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="result" id="face-result"></div>
                <div style="margin-top:12px;display:grid;gap:10px;">
                    <button type="button" class="btn" id="btn-face-retry" style="display:none;">Qayta urinish</button>
                    <button type="button" class="btn btn-secondary" id="btn-face-cancel">Bekor qilish</button>
                </div>
                <div class="muted-line">Talaba ID saqlanadi, Face ID esa shu talabani tasdiqlash uchun ishlatiladi.</div>
            </div>

            <div class="hint">
                Test hali ochilmagan bo'lsa, keyingi oynada kutish holati chiqadi. O'qituvchi testni ochgandan keyin `Testni boshlash` tugmasi paydo bo'ladi.
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    (function () {
        const CFG = {
            checkUrl: @json(route('student.face-id.check-student')),
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
                    student_id_number: $('student_id_number').value.trim(),
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
                setOverlay('❌', 'Mos kelmadi', result.data.error || 'Face ID tekshiruvi o‘tmadi.');
                showResult((result.data.error || 'Yuz mos kelmadi.') + (result.data.confidence ? ' (' + result.data.confidence + '%)' : ''), 'error');
                $('btn-face-retry').style.display = 'inline-flex';
            }, 250);
        }

        async function beginFaceFlow() {
            const studentIdNumber = $('student_id_number').value.trim();
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
                alert(error.message || 'Face ID oqimini boshlab bo‘lmadi.');
            } finally {
                $('btn-face-verify').disabled = false;
                $('btn-face-verify').textContent = 'Face ID bilan tasdiqlash';
            }
        }

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

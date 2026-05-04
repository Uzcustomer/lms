<x-guest-layout>
    {{-- Face ID sahifasida tepadagi logo va sarlavhani yashirish --}}
    <style>
        body > .login-bg > div:first-child > div:first-of-type:has(a[href="/"]) { display: none !important; }
        .login-bg > div > div:first-child > a[href="/"] { display: none !important; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var logoLinks = document.querySelectorAll('a[href="/"]');
            logoLinks.forEach(function(a) {
                if (a.querySelector('img[alt="Logo"]')) {
                    var wrap = a.closest('div[style*="text-align:center"]') || a.parentElement;
                    if (wrap) wrap.style.display = 'none';
                }
            });
        });
    </script>

    <div id="faceid-app" style="max-width:420px;margin:0 auto;">

        <!-- Bosqich 1: Talaba ID kiritish -->
        <div id="step-id-input">
            <div style="text-align:center;margin-bottom:1.2rem;">
                <h2 style="font-size:1.05rem;font-weight:700;color:#1e40af;margin:0;">{{ __('Face ID bilan kirish') }}</h2>
                <p style="font-size:12px;color:#6b7280;margin-top:6px;">{{ __('Talaba ID raqamingizni kiriting') }}</p>
            </div>

            <div id="id-error" style="display:none;background:#fee2e2;color:#b91c1c;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:10px;"></div>

            <input type="text" id="student_id_number" placeholder="{{ __('Talaba ID (masalan: 368241100173)') }}"
                   style="width:100%;padding:0.7rem 0.85rem;border:1px solid #d1d5db;border-radius:0.5rem;font-size:14px;outline:none;box-sizing:border-box;"
                   autocomplete="off" autofocus />

            <button id="btn-check-student" type="button"
                    style="width:100%;margin-top:0.75rem;padding:0.7rem;background:#1d4ed8;color:#fff;font-size:14px;font-weight:700;border:none;border-radius:0.5rem;cursor:pointer;">
                {{ __('Davom etish') }}
            </button>

            <div style="display:flex;align-items:center;margin:1rem 0;">
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
                <span style="padding:0 0.75rem;font-size:12px;color:#9ca3af;">{{ __('yoki') }}</span>
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
            </div>

            <a href="javascript:history.length > 1 ? history.back() : (window.location.href='{{ url('/') }}');"
               style="display:flex;align-items:center;justify-content:center;width:100%;padding:0.65rem;background:linear-gradient(135deg,#16a34a,#22c55e);border-radius:8px;color:#fff;text-decoration:none;font-size:13px;font-weight:700;box-shadow:0 4px 10px rgba(34,197,94,0.3);">
                {{ __('Parol bilan kirish') }}
            </a>
        </div>

        <!-- Bosqich 2: Kamera -->
        <div id="step-camera" style="display:none;">
            <!-- Talaba ma'lumoti -->
            <div id="student-info" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding:8px 10px;background:#f0f9ff;border-radius:10px;border:1px solid #bfdbfe;">
                <img id="student-photo" src="" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;" onerror="this.style.display='none'" />
                <div style="flex:1;">
                    <div id="student-name" style="font-weight:700;font-size:13px;color:#1e3a5f;"></div>
                    <div id="student-idnum" style="font-size:11px;color:#6b7280;"></div>
                </div>
                <button id="btn-back-to-id" type="button" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:12px;text-decoration:underline;">{{ __("O'zgartirish") }}</button>
            </div>

            <div style="position:relative;width:100%;max-width:380px;margin:0 auto;">
                <video id="camera-video" autoplay playsinline muted
                       style="width:100%;border-radius:14px;background:#000;transform:scaleX(-1);aspect-ratio:3/4;object-fit:cover;"></video>
                <div id="status-overlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(15,23,42,0.55);color:#fff;text-align:center;padding:14px;">
                    <div>
                        <div id="status-icon" style="font-size:34px;margin-bottom:6px;">📷</div>
                        <div id="status-title" style="font-size:14px;font-weight:700;">{{ __('Kamera ochilmoqda...') }}</div>
                        <div id="status-sub" style="font-size:12px;opacity:0.85;margin-top:4px;"></div>
                    </div>
                </div>
            </div>

            <div id="result-box" style="display:none;margin-top:14px;padding:12px;border-radius:10px;border:1px solid;font-size:13px;"></div>

            <div style="display:flex;gap:10px;margin-top:14px;">
                <button id="btn-retry" type="button"
                        style="display:none;flex:1;padding:10px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;text-align:center;line-height:1.2;cursor:pointer;font-family:inherit;">{{ __('Qayta urinish') }}</button>
                <a href="javascript:history.length > 1 ? history.back() : (window.location.href='{{ url('/') }}');"
                   style="display:flex;align-items:center;justify-content:center;flex:1;padding:10px;background:linear-gradient(135deg,#16a34a,#22c55e);border:none;border-radius:8px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;line-height:1.2;text-align:center;box-shadow:0 4px 10px rgba(34,197,94,0.3);font-family:inherit;">
                    {{ __('Parol bilan kirish') }}
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
    (function(){
        const CFG = {
            modelsPath: '{{ asset("face-models") }}',
            checkUrl:    '{{ route("student.face-id.check-student") }}',
            verifyUrl:   '{{ route("student.face-id.verify") }}',
            csrfToken:   '{{ csrf_token() }}',
            captureDelayMs: 700,
            minFaceConfidence: 0.6,
        };

        const state = {
            student: null,
            stream: null,
            video: null,
            detectionLoop: null,
            steadyStart: null,
            sent: false,
        };

        const $ = (id) => document.getElementById(id);

        function showStep(id) {
            ['step-id-input', 'step-camera'].forEach(s => $(s).style.display = (s === id ? '' : 'none'));
        }

        function setStatus(icon, title, sub) {
            $('status-icon').textContent = icon;
            $('status-title').textContent = title;
            $('status-sub').textContent = sub || '';
            $('status-overlay').style.display = 'flex';
        }
        function hideStatus() { $('status-overlay').style.display = 'none'; }

        function showResult(html, color) {
            const box = $('result-box');
            box.innerHTML = html;
            box.style.display = 'block';
            box.style.background = color === 'green' ? '#ecfdf5' : (color === 'red' ? '#fef2f2' : '#fef3c7');
            box.style.borderColor = color === 'green' ? '#86efac' : (color === 'red' ? '#fecaca' : '#fcd34d');
            box.style.color = color === 'green' ? '#166534' : (color === 'red' ? '#991b1b' : '#92400e');
        }

        function showIdError(msg) {
            const e = $('id-error');
            e.textContent = msg;
            e.style.display = 'block';
        }
        function clearIdError() { $('id-error').style.display = 'none'; }

        async function checkStudent() {
            const idNum = $('student_id_number').value.trim();
            clearIdError();
            if (!idNum) {
                showIdError("Talaba ID raqamini kiriting");
                return;
            }
            $('btn-check-student').disabled = true;
            $('btn-check-student').textContent = 'Tekshirilmoqda...';
            try {
                const resp = await fetch(CFG.checkUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({ student_id_number: idNum }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    showIdError(data.error || 'Talaba topilmadi');
                    return;
                }
                state.student = data;
                $('student-name').textContent = data.full_name;
                $('student-idnum').textContent = idNum;
                if (data.photo_url) {
                    $('student-photo').src = data.photo_url;
                    $('student-photo').style.display = '';
                }
                showStep('step-camera');
                await startCamera();
            } catch (e) {
                showIdError('Server bilan aloqa yo\'q');
            } finally {
                $('btn-check-student').disabled = false;
                $('btn-check-student').textContent = 'Davom etish';
            }
        }

        async function loadModels() {
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri(CFG.modelsPath);
                return true;
            } catch (e) {
                showResult('Yuz aniqlash modullari yuklanmadi.', 'red');
                return false;
            }
        }

        async function startCamera() {
            const modelsOk = await loadModels();
            if (!modelsOk) return;

            try {
                state.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                });
            } catch (err) {
                setStatus('🚫', 'Kamera ochilmadi', err.name === 'NotAllowedError' ? 'Iltimos kameraga ruxsat bering.' : 'Kamera mavjud emas yoki band.');
                return;
            }
            state.video = $('camera-video');
            state.video.srcObject = state.stream;
            await new Promise((res) => state.video.onloadedmetadata = res);
            await state.video.play();
            startDetectionLoop();
        }

        function stopCamera() {
            if (state.detectionLoop) { clearInterval(state.detectionLoop); state.detectionLoop = null; }
            if (state.stream) {
                state.stream.getTracks().forEach((t) => t.stop());
                state.stream = null;
            }
        }

        async function captureSnapshot() {
            const v = state.video;
            const c = document.createElement('canvas');
            c.width = Math.min(v.videoWidth, 480);
            c.height = Math.round(v.videoHeight * c.width / v.videoWidth);
            const ctx = c.getContext('2d');
            ctx.translate(c.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(v, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.85);
        }

        async function sendVerify(snapshot) {
            try {
                const resp = await fetch(CFG.verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({
                        student_id_number: state.student.student_id_number || $('student_id_number').value.trim(),
                        liveness_passed: true,
                        snapshot: snapshot,
                    }),
                });
                return { ok: resp.ok, data: await resp.json(), status: resp.status };
            } catch (e) {
                return { ok: false, data: { error: 'Server bilan aloqa yo\'q' }, status: 0 };
            }
        }

        function startDetectionLoop() {
            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: CFG.minFaceConfidence });
            setStatus('👀', 'Yuzingizni qidiryapman...', 'Kameraga to\'g\'ri qarang');
            hideStatus();

            state.detectionLoop = setInterval(async () => {
                if (state.sent) return;
                let det;
                try {
                    det = await faceapi.detectSingleFace(state.video, opts);
                } catch (e) { return; }
                if (!det) { state.steadyStart = null; return; }
                if (!state.steadyStart) { state.steadyStart = Date.now(); return; }
                if (Date.now() - state.steadyStart < CFG.captureDelayMs) return;

                state.sent = true;
                clearInterval(state.detectionLoop);
                state.detectionLoop = null;

                $('status-overlay').style.display = 'flex';
                setStatus('🔍', 'Tekshirilmoqda...', 'Iltimos kuting');

                const snap = await captureSnapshot();
                const r = await sendVerify(snap);

                if (r.ok && r.data.success) {
                    setStatus('✅', 'Kirish muvaffaqiyatli', state.student.full_name);
                    showResult('<b>Salom, ' + state.student.full_name + '!</b> Kabinetga kirilmoqda...', 'green');
                    stopCamera();
                    setTimeout(() => { window.location.href = r.data.redirect; }, 900);
                } else {
                    const msg = (r.data.message || r.data.error || 'Yuz mos kelmadi.');
                    setStatus('❌', 'Mos kelmadi', msg);
                    showResult(msg + (r.data.confidence ? ' (' + r.data.confidence + '%)' : ''), 'red');
                    $('btn-retry').style.display = 'block';
                }
            }, 250);
        }

        // Tugmalar
        $('btn-check-student').addEventListener('click', checkStudent);
        $('student_id_number').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') checkStudent();
        });

        $('btn-back-to-id').addEventListener('click', () => {
            stopCamera();
            state.sent = false;
            state.steadyStart = null;
            $('result-box').style.display = 'none';
            $('btn-retry').style.display = 'none';
            showStep('step-id-input');
            $('student_id_number').focus();
        });

        $('btn-retry').addEventListener('click', () => {
            state.sent = false;
            state.steadyStart = null;
            $('result-box').style.display = 'none';
            $('btn-retry').style.display = 'none';
            startDetectionLoop();
        });

        window.addEventListener('beforeunload', stopCamera);
    })();
    </script>
</x-guest-layout>

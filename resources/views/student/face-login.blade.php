<x-guest-layout>
    <div id="faceid-app" style="max-width:420px;margin:0 auto;">

        <!-- Sarlavha -->
        <div style="text-align:center;margin-bottom:1rem;">
            <h2 style="font-size:1.05rem;font-weight:700;color:#1e40af;margin:0;">{{ __('Face ID bilan kirish') }}</h2>
            <p id="hint" style="font-size:12px;color:#6b7280;margin-top:6px;">{{ __('Iltimos, kameraga to\'g\'ri qarang. Sistem sizni avtomatik tanib oladi.') }}</p>
        </div>

        <!-- Kamera oynasi -->
        <div style="position:relative;width:100%;max-width:380px;margin:0 auto;">
            <video id="camera-video" autoplay playsinline muted
                   style="width:100%;border-radius:14px;background:#000;transform:scaleX(-1);aspect-ratio:3/4;object-fit:cover;"></video>
            <canvas id="overlay-canvas" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;border-radius:14px;"></canvas>

            <!-- Spinner / status -->
            <div id="status-overlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(15,23,42,0.55);color:#fff;text-align:center;padding:14px;">
                <div>
                    <div id="status-icon" style="font-size:34px;margin-bottom:6px;">📷</div>
                    <div id="status-title" style="font-size:14px;font-weight:700;">{{ __('Kamera ochilmoqda...') }}</div>
                    <div id="status-sub" style="font-size:12px;opacity:0.85;margin-top:4px;"></div>
                </div>
            </div>
        </div>

        <!-- Xato/natija paneli -->
        <div id="result-box" style="display:none;margin-top:14px;padding:12px;border-radius:10px;border:1px solid;font-size:13px;"></div>

        <!-- Tugmalar -->
        <div style="display:flex;gap:10px;margin-top:14px;">
            <button id="btn-retry" type="button"
                    style="display:none;flex:1;padding:10px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">{{ __('Qayta urinish') }}</button>
            <a href="{{ route('student.login') }}"
               style="flex:1;text-align:center;padding:10px;border:1px solid #d1d5db;border-radius:8px;color:#374151;text-decoration:none;font-size:13px;">
                {{ __('Parol bilan kirish') }}
            </a>
        </div>
    </div>

    <!-- face-api.js — yuz aniqlash uchun (kamera oldida yuz turishini tekshirish) -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
    (function(){
        const CFG = {
            modelsPath: '{{ asset("face-models") }}',
            identifyUrl: '{{ route("student.face-id.identify") }}',
            csrfToken:   '{{ csrf_token() }}',
            // Detection sozlamalari
            captureDelayMs: 600,         // yuz topilgach shu vaqt o'tib snapshot olinadi
            minFaceConfidence: 0.6,
        };

        const state = {
            stream: null,
            video: null,
            canvas: null,
            detectionLoop: null,
            steadyStart: null,
            sent: false,
        };

        const $ = (id) => document.getElementById(id);

        function setStatus(icon, title, sub) {
            $('status-icon').textContent  = icon;
            $('status-title').textContent = title;
            $('status-sub').textContent   = sub || '';
        }

        function hideStatusOverlay() {
            $('status-overlay').style.display = 'none';
        }

        function showResult(html, color) {
            const box = $('result-box');
            box.innerHTML = html;
            box.style.display = 'block';
            box.style.background = color === 'green' ? '#ecfdf5' : (color === 'red' ? '#fef2f2' : '#fef3c7');
            box.style.borderColor = color === 'green' ? '#86efac' : (color === 'red' ? '#fecaca' : '#fcd34d');
            box.style.color = color === 'green' ? '#166534' : (color === 'red' ? '#991b1b' : '#92400e');
        }

        async function loadModels() {
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(CFG.modelsPath),
                ]);
                return true;
            } catch (e) {
                console.error('Model yuklash xato:', e);
                showResult('Yuz aniqlash modullari yuklanmadi. Internetni tekshirib qayta urinib ko\'ring.', 'red');
                return false;
            }
        }

        async function startCamera() {
            try {
                state.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                });
            } catch (err) {
                console.error('Camera error:', err);
                setStatus('🚫', 'Kamera ochilmadi', err.name === 'NotAllowedError'
                    ? 'Iltimos kameraga ruxsat bering.'
                    : 'Kamera mavjud emas yoki band.');
                return false;
            }
            const v = $('camera-video');
            v.srcObject = state.stream;
            await new Promise((res) => v.onloadedmetadata = res);
            await v.play();
            state.video = v;
            return true;
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
            c.width  = Math.min(v.videoWidth, 480);
            c.height = Math.round(v.videoHeight * c.width / v.videoWidth);
            const ctx = c.getContext('2d');
            // Mirror'ni qaytarib olamiz (CSS scaleX(-1) qilingan, lekin server'ga oddiy yo'naltirilgan)
            ctx.translate(c.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(v, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.85);
        }

        async function sendToIdentify(snapshot) {
            try {
                const resp = await fetch(CFG.identifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({ snapshot: snapshot }),
                });
                const data = await resp.json();
                return { ok: resp.ok, data: data, status: resp.status };
            } catch (e) {
                console.error('identify fetch error:', e);
                return { ok: false, data: { error: 'Server bilan aloqa yo\'q' }, status: 0 };
            }
        }

        async function startDetectionLoop() {
            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: CFG.minFaceConfidence });
            setStatus('👀', 'Yuzingizni qidiryapman...', 'Kameraga to\'g\'ri qarang');
            hideStatusOverlay();

            state.detectionLoop = setInterval(async () => {
                if (state.sent) return;
                let det;
                try {
                    det = await faceapi.detectSingleFace(state.video, opts);
                } catch (e) { return; }

                if (!det) {
                    state.steadyStart = null;
                    return;
                }

                if (!state.steadyStart) {
                    state.steadyStart = Date.now();
                    return;
                }

                const elapsed = Date.now() - state.steadyStart;
                if (elapsed >= CFG.captureDelayMs) {
                    state.sent = true;
                    clearInterval(state.detectionLoop);
                    state.detectionLoop = null;

                    $('status-overlay').style.display = 'flex';
                    setStatus('🔍', 'Tekshirilmoqda...', 'Iltimos kuting (1-2 soniya)');

                    const snap = await captureSnapshot();
                    const r = await sendToIdentify(snap);

                    if (r.ok && r.data.success) {
                        const name = r.data.student_name || '';
                        const conf = r.data.confidence ?? null;
                        setStatus('✅', 'Kirish muvaffaqiyatli', name + (conf !== null ? ' (' + conf.toFixed(1) + '%)' : ''));
                        showResult('<b>Salom, ' + name + '!</b> Kabinetga kirilmoqda...', 'green');
                        stopCamera();
                        setTimeout(() => { window.location.href = r.data.redirect; }, 900);
                    } else {
                        const msg = (r.data.message || r.data.error || 'Tanib bo\'lmadi.');
                        setStatus('❌', 'Tanib bo\'lmadi', msg);
                        showResult(msg + (r.data.confidence ? ' (eng yaqin: ' + r.data.confidence + '%)' : ''), 'red');
                        $('btn-retry').style.display = 'block';
                    }
                }
            }, 250);
        }

        $('btn-retry').addEventListener('click', () => {
            state.sent = false;
            state.steadyStart = null;
            $('result-box').style.display = 'none';
            $('btn-retry').style.display = 'none';
            startDetectionLoop();
        });

        // Bosh ish
        (async () => {
            const modelsOk = await loadModels();
            if (!modelsOk) return;
            const camOk = await startCamera();
            if (!camOk) return;
            startDetectionLoop();
        })();

        window.addEventListener('beforeunload', stopCamera);
    })();
    </script>
</x-guest-layout>

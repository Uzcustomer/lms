<x-guest-layout>
    <!-- Tab navigatsiya -->
    <div style="display: flex; margin-bottom: 1.2rem; border-bottom: 2px solid #e5e7eb;">
        <a href="{{ route('student.login') }}"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 3px solid transparent; color: #1e40af; background-color: #dbeafe; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            {{ __('Talaba') }}
        </a>
        <a href="{{ route('teacher.login') }}"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 500; text-decoration: none; border-bottom: 3px solid transparent; color: #1e40af; background-color: #dbeafe; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            {{ __('Xodim') }}
        </a>
    </div>

    <div id="faceid-app">

        <!-- Bosqich 1: ID kiritish -->
        <div id="step-id-input">
            <div style="text-align: center; margin-bottom: 1.2rem;">
                <div style="font-size: 2rem; margin-bottom: 0.4rem;">🪪</div>
                <h2 style="font-size: 1rem; font-weight: 600; color: #1e40af;">{{ __('Face ID bilan kirish') }}</h2>
                <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">{{ __('Talaba ID raqamingizni kiriting') }}</p>
            </div>

            <div id="id-error" style="display:none; background:#fee2e2; color:#b91c1c; padding:8px 12px; border-radius:6px; font-size:13px; margin-bottom:10px;"></div>

            <input type="text" id="student_id_number" placeholder="{{ __('Talaba ID (masalan: 20202005010028)') }}"
                   style="width:100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 14px; outline: none; box-sizing: border-box;"
                   autocomplete="off" autofocus />

            <button id="btn-check-student"
                    style="width:100%; margin-top: 0.75rem; padding: 0.65rem; background-color: #1d4ed8; color: #fff; font-size: 14px; font-weight: 600; border: none; border-radius: 0.5rem; cursor: pointer;">
                {{ __('Davom etish') }}
            </button>

            <div style="display: flex; align-items: center; margin: 1rem 0;">
                <div style="flex: 1; height: 1px; background-color: #e5e7eb;"></div>
                <span style="padding: 0 0.75rem; font-size: 12px; color: #9ca3af;">{{ __('yoki') }}</span>
                <div style="flex: 1; height: 1px; background-color: #e5e7eb;"></div>
            </div>

            <a href="{{ route('student.login') }}"
               style="display: block; text-align: center; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 13px; color: #374151; text-decoration: none;">
                {{ __('Parol bilan kirish') }}
            </a>
        </div>

        <!-- Bosqich 2: Kamera va liveness check -->
        <div id="step-camera" style="display:none;">
            <!-- Talaba ma'lumoti -->
            <div id="student-info" style="display:flex; align-items:center; gap:10px; margin-bottom:12px; padding:8px; background:#f0f9ff; border-radius:8px;">
                <img id="student-photo" src="" alt="" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid #3b82f6;" onerror="this.style.display='none'" />
                <div>
                    <div id="student-name" style="font-weight:600; font-size:13px; color:#1e3a5f;"></div>
                    <div id="student-idnum" style="font-size:11px; color:#6b7280;"></div>
                </div>
                <button id="btn-back-to-id" style="margin-left:auto; background:none; border:none; cursor:pointer; color:#6b7280; font-size:12px; text-decoration:underline;">{{ __('O\'zgartirish') }}</button>
            </div>

            <!-- Kamera oynasi -->
            <div style="position:relative; width:100%; max-width:320px; margin:0 auto;">
                <video id="camera-video" autoplay playsinline muted
                       style="width:100%; border-radius:12px; background:#000; transform: scaleX(-1);"></video>
                <canvas id="overlay-canvas"
                        style="position:absolute; top:0; left:0; width:100%; height:100%; border-radius:12px; transform: scaleX(-1);"></canvas>

                <!-- Yuz oval ko'rsatkich -->
                <div id="face-oval" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-55%);
                     width:160px; height:200px; border:3px solid rgba(255,255,255,0.6);
                     border-radius:50%; pointer-events:none; transition: border-color 0.3s;"></div>
            </div>

            <!-- Status xabarlari -->
            <div id="status-box" style="margin-top:12px; text-align:center; min-height:60px;">
                <div id="status-icon" style="font-size:1.5rem; margin-bottom:4px;">⏳</div>
                <div id="status-text" style="font-size:13px; font-weight:600; color:#374151;"></div>
                <div id="status-sub"  style="font-size:11px; color:#6b7280; margin-top:3px;"></div>
            </div>

            <!-- Progress bar -->
            <div style="margin-top:10px; background:#e5e7eb; border-radius:4px; height:6px;">
                <div id="progress-bar" style="height:6px; border-radius:4px; background:#3b82f6; width:0%; transition: width 0.3s;"></div>
            </div>

            <!-- Tugmalar -->
            <div style="display:flex; gap:8px; margin-top:12px;">
                <button id="btn-retry" style="display:none; flex:1; padding:0.55rem; background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:0.5rem; font-size:13px; cursor:pointer;">
                    {{ __('Qayta urinish') }}
                </button>
                <button id="btn-start-liveness" style="flex:1; padding:0.55rem; background:#1d4ed8; color:#fff; border:none; border-radius:0.5rem; font-size:13px; font-weight:600; cursor:pointer;">
                    {{ __('Boshlash') }}
                </button>
            </div>

            <div style="margin-top:10px; text-align:center;">
                <a href="{{ route('student.login') }}" style="font-size:12px; color:#6b7280; text-decoration:underline;">{{ __('Parol bilan kirish') }}</a>
            </div>
        </div>

        <!-- Bosqich 3: Natija -->
        <div id="step-result" style="display:none; text-align:center; padding:20px 0;">
            <div id="result-icon" style="font-size:3rem; margin-bottom:12px;">✅</div>
            <div id="result-title" style="font-size:1.1rem; font-weight:700; color:#1e3a5f;"></div>
            <div id="result-sub" style="font-size:13px; color:#6b7280; margin-top:8px;"></div>
            <div id="result-confidence" style="font-size:12px; color:#9ca3af; margin-top:4px;"></div>
        </div>
    </div>

    <!-- face-api.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <script>
    (function () {
        'use strict';

        // ─── Config (serverdan) ─────────────────────────────────────────
        const CFG = {
            threshold:        {{ $settings['threshold'] }},
            blinksRequired:   {{ $settings['blinks_required'] }},
            headTurnRequired: {{ $settings['head_turn_required'] ? 'true' : 'false' }},
            livenessTimeout:  {{ $settings['liveness_timeout'] }},
            modelsPath:       '/face-models',
            csrfToken:        '{{ csrf_token() }}',
            checkStudentUrl:  '{{ route('student.face-id.check-student') }}',
            verifyUrl:        '{{ route('student.face-id.verify') }}',
            descriptorUrl:    '{{ route('student.face-id.save-descriptor') }}',
        };

        // ─── State ──────────────────────────────────────────────────────
        let state = {
            studentData:     null,   // {student_id, full_name, photo_url, has_descriptor}
            modelsLoaded:    false,
            stream:          null,
            detectionLoop:   null,
            liveness: {
                phase:          'idle',  // idle | blink | head_turn | capture | done
                blinkCount:     0,
                blinked:        false,   // eye currently closed
                eyeClosedFrames:0,
                headTurned:     false,
                headTurnDir:    null,    // 'left' or 'right' (random)
                startTime:      null,
                passed:         false,
            },
            refDescriptor:   null,   // Float32Array (128) reference from HEMIS photo
            liveDescriptor:  null,   // Float32Array (128) from live capture
        };

        // ─── DOM refs ───────────────────────────────────────────────────
        const $ = id => document.getElementById(id);

        const stepIdInput  = $('step-id-input');
        const stepCamera   = $('step-camera');
        const stepResult   = $('step-result');
        const video        = $('camera-video');
        const overlayCanvas= $('overlay-canvas');
        const faceOval     = $('face-oval');

        // ─── Helpers ────────────────────────────────────────────────────
        function showStep(id) {
            [stepIdInput, stepCamera, stepResult].forEach(el => el.style.display = 'none');
            $(id).style.display = 'block';
        }

        function setStatus(icon, text, sub = '', color = '#374151') {
            $('status-icon').textContent = icon;
            $('status-text').textContent = text;
            $('status-text').style.color  = color;
            $('status-sub').textContent  = sub;
        }

        function setProgress(pct) {
            $('progress-bar').style.width = pct + '%';
            $('progress-bar').style.background = pct >= 100 ? '#22c55e' : '#3b82f6';
        }

        function setOvalColor(color) {
            faceOval.style.borderColor = color;
        }

        function showError(msg) {
            const el = $('id-error');
            el.textContent = msg;
            el.style.display = 'block';
        }

        function hideError() {
            $('id-error').style.display = 'none';
        }

        function resetLiveness() {
            state.liveness = {
                phase: 'idle', blinkCount: 0, blinked: false,
                eyeClosedFrames: 0, headTurned: false,
                headTurnDir: Math.random() > 0.5 ? 'left' : 'right',
                startTime: null, passed: false,
            };
        }

        // Eye Aspect Ratio (EAR) ko'z holatini aniqlash uchun
        function computeEAR(landmarks) {
            // face-api.js 68-point landmark uchun ko'z indekslari
            // Chap ko'z: 36-41, O'ng ko'z: 42-47
            const pts = landmarks.positions;

            function ear(p1, p2, p3, p4, p5, p6) {
                const A = Math.hypot(pts[p2].x - pts[p6].x, pts[p2].y - pts[p6].y);
                const B = Math.hypot(pts[p3].x - pts[p5].x, pts[p3].y - pts[p5].y);
                const C = Math.hypot(pts[p1].x - pts[p4].x, pts[p1].y - pts[p4].y);
                return (A + B) / (2.0 * C);
            }

            const leftEAR  = ear(36, 37, 38, 39, 40, 41);
            const rightEAR = ear(42, 43, 44, 45, 46, 47);
            return (leftEAR + rightEAR) / 2.0;
        }

        // Bosh burilish (yaw) — burun uchi pozitsiyasi asosida
        function computeHeadYaw(landmarks) {
            const pts = landmarks.positions;
            // Burun uchi: 30, Ko'z markazlari: 39 (chap), 42 (o'ng)
            const noseTip  = pts[30];
            const leftEye  = pts[39];
            const rightEye = pts[42];
            const eyeCenter = { x: (leftEye.x + rightEye.x) / 2, y: (leftEye.y + rightEye.y) / 2 };
            const faceWidth = Math.abs(rightEye.x - leftEye.x);
            if (faceWidth < 1) return 0;
            return (noseTip.x - eyeCenter.x) / faceWidth; // musbat → o'ng, manfiy → chap
        }

        // ─── Model yuklash ──────────────────────────────────────────────
        async function loadModels() {
            setStatus('⏳', 'Modellar yuklanmoqda...', 'Bir daqiqa kuting', '#6b7280');
            try {
                const path = CFG.modelsPath;
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(path),
                    faceapi.nets.faceLandmark68Net.loadFromUri(path),
                    faceapi.nets.faceRecognitionNet.loadFromUri(path),
                ]);
                state.modelsLoaded = true;
                setStatus('✅', 'Tayyor', 'Boshlash tugmasini bosing');
            } catch (err) {
                console.error('Model loading error:', err);
                setStatus('❌', 'Modellar yuklanmadi', err.message, '#dc2626');
            }
        }

        // ─── Kamera ─────────────────────────────────────────────────────
        async function startCamera() {
            try {
                state.stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                });
                video.srcObject = state.stream;
                await new Promise(res => video.onloadedmetadata = res);
                overlayCanvas.width  = video.videoWidth;
                overlayCanvas.height = video.videoHeight;
            } catch (err) {
                setStatus('❌', 'Kamera ishlamadi', 'Kameraga ruxsat bering', '#dc2626');
                throw err;
            }
        }

        function stopCamera() {
            if (state.stream) {
                state.stream.getTracks().forEach(t => t.stop());
                state.stream = null;
            }
            if (state.detectionLoop) {
                clearInterval(state.detectionLoop);
                state.detectionLoop = null;
            }
        }

        // ─── Reference descriptor (HEMIS rasmidan) ──────────────────────
        async function loadRefDescriptor() {
            // Agar server-side saqlangan descriptor bo'lsa, u server tarafida ishlatiladi
            // Aks holda — rasmni yuklab, client-side descriptor olamiz
            if (state.studentData.has_descriptor) {
                // Faqat live descriptor yuborish kifoya, server o'zi taqqoslaydi
                state.refDescriptor = null;
                return true;
            }

            // HEMIS rasmidan descriptor olish
            setStatus('🖼️', 'Rasm yuklanmoqda...', 'HEMIS rasmingiz yuklanmoqda');
            try {
                const img = await faceapi.fetchImage(state.studentData.photo_url);
                const detection = await faceapi
                    .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!detection) {
                    setStatus('⚠️', 'Rasmingizda yuz aniqlanmadi', 'Admin bilan bog\'laning', '#b45309');
                    return false;
                }

                state.refDescriptor = detection.descriptor;

                // Descriptor serverga saqlaymiz (keyingi kirish uchun)
                saveDescriptorToServer(Array.from(state.refDescriptor), state.studentData.photo_url);

                return true;
            } catch (err) {
                console.error('Ref descriptor error:', err);
                setStatus('⚠️', 'Rasm yuklanmadi', 'Internet aloqasini tekshiring', '#b45309');
                return false;
            }
        }

        async function saveDescriptorToServer(descriptor, sourceUrl) {
            try {
                await fetch(CFG.descriptorUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({
                        student_id: state.studentData.student_id,
                        descriptor: descriptor,
                        source_url: sourceUrl,
                    }),
                });
            } catch (e) { /* silent */ }
        }

        // ─── Liveness + detection loop ──────────────────────────────────
        async function runDetectionLoop() {
            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
            const ctx   = overlayCanvas.getContext('2d');
            const lv    = state.liveness;

            const EAR_THRESHOLD   = 0.22;   // Ko'z yumildi deyish uchun
            const EAR_OPEN_THRESH = 0.28;   // Ko'z ochildi
            const HEAD_TURN_THRESHOLD = 0.18; // Bosh burish miqdori

            state.detectionLoop = setInterval(async () => {
                if (!video.videoWidth) return;

                ctx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

                let detection;
                try {
                    detection = await faceapi
                        .detectSingleFace(video, opts)
                        .withFaceLandmarks()
                        .withFaceDescriptor();
                } catch (e) { return; }

                if (!detection) {
                    setOvalColor('rgba(255,255,255,0.5)');
                    if (lv.phase !== 'idle') {
                        setStatus('👀', 'Yuzingizni ko\'rsating', 'Kameraga to\'g\'ri qarang', '#b45309');
                    }
                    return;
                }

                // Yuz topildi — oval yashil
                setOvalColor('#22c55e');

                const landmarks = detection.landmarks;
                const ear = computeEAR(landmarks);
                const yaw = computeHeadYaw(landmarks);

                // Liveness bosqichlari
                if (lv.phase === 'blink') {
                    // Timeout tekshiruvi
                    if (Date.now() - lv.startTime > CFG.livenessTimeout * 1000) {
                        clearInterval(state.detectionLoop);
                        state.detectionLoop = null;
                        setStatus('⏰', 'Vaqt tugadi', 'Qayta urinib ko\'ring', '#dc2626');
                        $('btn-retry').style.display = 'block';
                        $('btn-start-liveness').style.display = 'none';
                        return;
                    }

                    const required = CFG.blinksRequired;
                    const progress = Math.min(lv.blinkCount / required, 1);
                    setProgress(progress * (CFG.headTurnRequired ? 50 : 90));

                    if (ear < EAR_THRESHOLD && !lv.blinked) {
                        lv.blinked = true;
                        lv.eyeClosedFrames++;
                    } else if (ear > EAR_OPEN_THRESH && lv.blinked) {
                        lv.blinked = false;
                        if (lv.eyeClosedFrames >= 1) {
                            lv.blinkCount++;
                            lv.eyeClosedFrames = 0;
                            setStatus('👁️', `Ko'z yumish: ${lv.blinkCount}/${required}`, 'Davom eting', '#1d4ed8');
                        }
                    }

                    if (lv.blinkCount >= required) {
                        if (CFG.headTurnRequired) {
                            lv.phase = 'head_turn';
                            const dir = lv.headTurnDir === 'left' ? 'chapga' : 'o\'ngga';
                            setStatus('↩️', `Boshingizni ${dir} burting`, 'So\'ng to\'g\'ri qarang', '#1d4ed8');
                        } else {
                            lv.phase = 'capture';
                            setStatus('📸', 'Endi to\'g\'ri qarang', 'Siz skanlanmoqdasiz...', '#1d4ed8');
                        }
                    } else {
                        setStatus('👁️', `Ko'z yumib-oching (${lv.blinkCount}/${required})`, `EAR: ${ear.toFixed(3)}`, '#1d4ed8');
                    }
                }

                else if (lv.phase === 'head_turn') {
                    if (Date.now() - lv.startTime > CFG.livenessTimeout * 1000) {
                        clearInterval(state.detectionLoop);
                        state.detectionLoop = null;
                        setStatus('⏰', 'Vaqt tugadi', 'Qayta urinib ko\'ring', '#dc2626');
                        $('btn-retry').style.display = 'block';
                        $('btn-start-liveness').style.display = 'none';
                        return;
                    }

                    setProgress(60);
                    const dir = lv.headTurnDir;
                    const turned = dir === 'left' ? (yaw < -HEAD_TURN_THRESHOLD) : (yaw > HEAD_TURN_THRESHOLD);

                    if (turned && !lv.headTurned) {
                        lv.headTurned = true;
                        setStatus('✅', 'Yaxshi! Endi to\'g\'ri qarang', 'Tekshiruv boshlanmoqda...', '#059669');
                    }
                    if (lv.headTurned && Math.abs(yaw) < 0.08) {
                        lv.phase = 'capture';
                        setStatus('📸', 'Harakatsiz turing', 'Skanlanmoqda...', '#1d4ed8');
                        setProgress(80);
                    } else if (!lv.headTurned) {
                        const dirLabel = dir === 'left' ? '← Chapga' : '→ O\'ngga';
                        setStatus('🔄', `Boshingizni ${dir === 'left' ? 'chapga' : 'o\'ngga'} burting`, `Yaw: ${yaw.toFixed(3)}`, '#b45309');
                    }
                }

                else if (lv.phase === 'capture') {
                    lv.passed = true;
                    lv.phase  = 'done';

                    clearInterval(state.detectionLoop);
                    state.detectionLoop = null;

                    setProgress(95);
                    setStatus('🔍', 'Yuz taqqoslanmoqda...', 'Bir soniya', '#6b7280');

                    state.liveDescriptor = detection.descriptor;

                    // Snapshot
                    const snapCanvas = document.createElement('canvas');
                    snapCanvas.width  = Math.min(video.videoWidth, 320);
                    snapCanvas.height = Math.round(video.videoHeight * snapCanvas.width / video.videoWidth);
                    snapCanvas.getContext('2d').drawImage(video, 0, 0, snapCanvas.width, snapCanvas.height);
                    const snapshot = snapCanvas.toDataURL('image/jpeg', 0.6);

                    await performVerification(Array.from(state.liveDescriptor), snapshot);
                }

            }, 150); // 6-7 FPS detection
        }

        // ─── Tekshiruv ──────────────────────────────────────────────────
        async function performVerification(liveDesc, snapshot) {
            let distance   = null;
            let confidence = null;

            // Client-side taqqoslash (faqat saqlangan descriptor yo'q bo'lsa)
            if (state.refDescriptor) {
                const ref = state.refDescriptor;
                let sum = 0;
                for (let i = 0; i < 128; i++) {
                    const d = liveDesc[i] - ref[i];
                    sum += d * d;
                }
                distance   = Math.sqrt(sum);
                confidence = Math.max(0, Math.min(100, (1 - distance / 0.6) * 100));
            }

            try {
                const resp = await fetch(CFG.verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({
                        student_id_number: $('student_id_number').value.trim(),
                        liveness_passed:   true,
                        descriptor:        liveDesc,
                        distance:          distance,
                        confidence:        confidence,
                        snapshot:          snapshot,
                    }),
                });

                const data = await resp.json();

                if (data.success) {
                    setProgress(100);
                    setStatus('✅', 'Muvaffaqiyatli!', 'Tizimga kirilmoqda...', '#059669');
                    setOvalColor('#22c55e');

                    showStep('step-result');
                    $('result-icon').textContent  = '✅';
                    $('result-title').textContent = 'Muvaffaqiyatli tasdiqlandi!';
                    $('result-title').style.color = '#059669';
                    $('result-sub').textContent   = 'Tizimga kirilmoqda...';
                    if (confidence !== null) {
                        $('result-confidence').textContent = `Yaqinlik: ${confidence.toFixed(1)}%`;
                    }

                    stopCamera();
                    setTimeout(() => { window.location.href = data.redirect; }, 1200);
                } else {
                    showFailure(data.message || 'Yuz mos kelmadi.', data.confidence);
                }
            } catch (err) {
                showFailure('Server bilan aloqa yo\'q. Qayta urinib ko\'ring.');
            }
        }

        function showFailure(msg, conf = null) {
            setStatus('❌', 'Muvaffaqiyatsiz', msg, '#dc2626');
            setOvalColor('#ef4444');
            $('btn-retry').style.display       = 'block';
            $('btn-start-liveness').style.display = 'none';
            if (conf !== null) {
                $('status-sub').textContent = `${msg} (${parseFloat(conf).toFixed(1)}%)`;
            }
        }

        // ─── Event listeners ────────────────────────────────────────────

        // Talaba ID tekshiruvi
        $('btn-check-student').addEventListener('click', async () => {
            hideError();
            const idNum = $('student_id_number').value.trim();
            if (!idNum) { showError('ID raqamini kiriting.'); return; }

            $('btn-check-student').disabled    = true;
            $('btn-check-student').textContent = 'Tekshirilmoqda...';

            try {
                const resp = await fetch(CFG.checkStudentUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFG.csrfToken },
                    body: JSON.stringify({ student_id_number: idNum }),
                });
                const data = await resp.json();

                if (!resp.ok) {
                    showError(data.error || 'Xatolik yuz berdi.');
                    return;
                }

                state.studentData = data;

                // Student info ko'rsatish
                $('student-name').textContent  = data.full_name;
                $('student-idnum').textContent = idNum;
                $('student-photo').src         = data.photo_url;

                showStep('step-camera');

                // Modellarni fon rejimida yuklash
                await startCamera();
                if (!state.modelsLoaded) {
                    await loadModels();
                }
                // Reference descriptor yuklash
                if (!state.studentData.has_descriptor) {
                    await loadRefDescriptor();
                } else {
                    setStatus('🎯', 'Tayyor', 'Boshlash tugmasini bosing');
                }

            } catch (err) {
                showError('Server bilan aloqa yo\'q. Sahifani yangilang.');
            } finally {
                $('btn-check-student').disabled    = false;
                $('btn-check-student').textContent = 'Davom etish';
            }
        });

        // Enter tugmasi
        $('student_id_number').addEventListener('keydown', e => {
            if (e.key === 'Enter') $('btn-check-student').click();
        });

        // Ortga qaytish
        $('btn-back-to-id').addEventListener('click', () => {
            stopCamera();
            resetLiveness();
            state.studentData    = null;
            state.refDescriptor  = null;
            state.liveDescriptor = null;
            showStep('step-id-input');
            $('btn-retry').style.display          = 'none';
            $('btn-start-liveness').style.display = 'block';
        });

        // Liveness boshlash
        $('btn-start-liveness').addEventListener('click', () => {
            if (!state.modelsLoaded) {
                setStatus('⏳', 'Modellar hali yuklanmadi...', 'Biroz kuting');
                return;
            }
            resetLiveness();
            state.liveness.phase     = CFG.blinksRequired > 0 ? 'blink' : (CFG.headTurnRequired ? 'head_turn' : 'capture');
            state.liveness.startTime = Date.now();
            $('btn-start-liveness').style.display = 'none';
            $('btn-retry').style.display          = 'none';

            if (state.liveness.phase === 'blink') {
                const dir = state.liveness.headTurnDir === 'left' ? 'chapga' : 'o\'ngga';
                setStatus('👁️', `Ko'zingizni ${CFG.blinksRequired} marta yumib-oching`, 'Jonlilik tekshiruvi', '#1d4ed8');
            } else if (state.liveness.phase === 'head_turn') {
                const dir = state.liveness.headTurnDir === 'left' ? 'chapga' : 'o\'ngga';
                setStatus('🔄', `Boshingizni ${dir} burting`, 'So\'ng to\'g\'ri qarang', '#1d4ed8');
            } else {
                setStatus('📸', 'To\'g\'ri qarang', 'Skanlanmoqda...', '#1d4ed8');
            }

            setProgress(5);
            runDetectionLoop();
        });

        // Qayta urinish
        $('btn-retry').addEventListener('click', () => {
            $('btn-retry').style.display          = 'none';
            $('btn-start-liveness').style.display = 'block';
            resetLiveness();
            setStatus('🎯', 'Tayyor', 'Boshlash tugmasini bosing');
            setOvalColor('rgba(255,255,255,0.6)');
            setProgress(0);
        });

        // ─── Init ────────────────────────────────────────────────────────
        // Modellarni sahifa yuklanganda fon rejimida yuklash boshlash
        // (kamera bosqichiga o'tganda tugallangan bo'lishi uchun)
    })();
    </script>

    <style>
        #camera-video { display: block; }
        #overlay-canvas { pointer-events: none; }
    </style>
</x-guest-layout>

<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Face ID tekshiruvi
    </h2>
</x-slot>

<style>
    .fc-stage { position: relative; display: inline-block; width: 100%; max-width: 480px; }
    .fc-stage video { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 12px; background: #000; transform: scaleX(-1); display: block; }
    .fc-frame { position: absolute; inset: 0; pointer-events: none; }
    .fc-oval { fill: none; stroke: #d1d5db; stroke-width: 2; vector-effect: non-scaling-stroke; transition: stroke .2s; }
    .fc-oval.ok { stroke: #10b981; }
    .fc-oval.warn { stroke: #f59e0b; }
    .fc-arrow { position: absolute; top: 50%; transform: translateY(-50%); font-size: 56px; color: #fbbf24; text-shadow: 0 0 8px rgba(0,0,0,.8); animation: fcPulse 1s infinite; pointer-events: none; font-weight: bold; }
    .fc-arrow.left { left: 12px; }
    .fc-arrow.right { right: 12px; }
    @keyframes fcPulse { 0%,100% { opacity: .5; } 50% { opacity: 1; } }
    .fc-progress { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
    .fc-progress > div { height: 100%; width: 0; background: #10b981; transition: width .25s; }
    .fc-msg { min-height: 24px; }
</style>

<div class="container mx-auto px-4 py-6 max-w-3xl">

    <div class="flex items-center justify-end mb-5">
        <a href="{{ route('admin.registrator.face-check.index') }}"
           class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">← Ro'yxat</a>
    </div>

    {{-- Phase: initial --}}
    <div id="phase-init" class="bg-white rounded-lg border p-6 text-center">
        <p class="text-sm text-gray-600 mb-4">
            Test markazida muammosiz kirishini oldindan tekshirish. Talaba kameraga qarab,
            ekranda chiqadigan ko'rsatmalarga (boshini chapga/o'ngga burish) amal qiladi.
        </p>
        <button id="btn-start"
                class="px-6 py-2 bg-emerald-600 text-white rounded font-semibold hover:bg-emerald-700">
            Tekshirishni boshlash
        </button>
    </div>

    {{-- Phase: liveness in progress --}}
    <div id="phase-liveness" class="bg-white rounded-lg border p-6 hidden">
        <div class="flex justify-center">
            <div class="fc-stage">
                <video id="fc-video" autoplay playsinline muted></video>
                <svg class="fc-frame" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <ellipse class="fc-oval" id="fc-oval" cx="50" cy="40" rx="28" ry="34"></ellipse>
                </svg>
                <div class="fc-arrow left"  id="fc-arr-left"  style="display:none;">◀</div>
                <div class="fc-arrow right" id="fc-arr-right" style="display:none;">▶</div>
            </div>
        </div>
        <div class="fc-msg text-center mt-3 text-base font-medium text-gray-800" id="fc-msg">Kamera ochilmoqda…</div>
        <div class="fc-progress mt-3"><div id="fc-progress"></div></div>
        <div class="text-center mt-4">
            <button id="btn-cancel-liveness" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                Bekor qilish
            </button>
        </div>
    </div>

    {{-- Phase: result PASS --}}
    <div id="phase-result-pass" class="bg-emerald-50 border border-emerald-300 rounded-lg p-6 text-center hidden">
        <div class="text-5xl mb-3">✅</div>
        <div class="text-lg font-bold text-emerald-800">Test markazida muammosiz o'tadi</div>
        <p class="text-sm text-emerald-700 mt-2">Talaba ortiqcha qadamlarsiz test markaziga kirishi mumkin.</p>
        <div class="mt-5">
            <a href="{{ route('admin.registrator.face-check.index') }}"
               class="px-4 py-2 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700">
                Ro'yxatga qaytish
            </a>
        </div>
    </div>

    {{-- Phase: result FAIL → offer recapture --}}
    <div id="phase-result-fail" class="bg-amber-50 border border-amber-300 rounded-lg p-6 text-center hidden">
        <div class="text-5xl mb-3">⚠️</div>
        <div class="text-lg font-bold text-amber-800" id="fail-title">Test markazida muammo bo'lishi mumkin</div>
        <p class="text-sm text-amber-700 mt-2" id="fail-detail">
            Talabaning yuzi mavjud descriptor bilan mos kelmadi. Yangi rasm olish tavsiya etiladi.
        </p>
        <div class="mt-5 flex gap-3 justify-center">
            <button id="btn-recapture"
                    class="px-4 py-2 bg-indigo-600 text-white rounded text-sm font-semibold hover:bg-indigo-700">
                Yangi rasm olish
            </button>
            <button id="btn-retry-precheck"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">
                Qayta urinish
            </button>
        </div>
    </div>

    {{-- Phase: recapture step 1 — student types username --}}
    <div id="phase-recapture-username" class="bg-white rounded-lg border p-6 hidden">
        <h3 class="text-base font-bold text-gray-800 mb-2">1-qadam: O'zingizni tasdiqlang</h3>
        <p class="text-sm text-gray-600 mb-4">
            Talaba o'z <strong>username</strong>ini (HEMIS ID raqamini) yozsin.
            Tizim ismni ko'rsatadi — registrator pasport bilan solishtiradi.
        </p>
        <div class="flex gap-2">
            <input type="text" id="re-username" autocomplete="off"
                   class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm font-mono"
                   placeholder="Username (ID raqam)" />
            <button id="btn-lookup"
                    class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                Tasdiqlash
            </button>
        </div>
        <div id="lookup-error" class="mt-3 text-sm text-red-600 hidden"></div>
    </div>

    {{-- Phase: recapture step 2 — registrator confirms passport --}}
    <div id="phase-recapture-confirm" class="bg-white rounded-lg border p-6 hidden">
        <h3 class="text-base font-bold text-gray-800 mb-2">2-qadam: Pasportni solishtiring</h3>
        <p class="text-sm text-gray-600 mb-3">
            Quyidagi ism pasportdagi ism bilan bir xil bo'lishi shart.
            Solishtirgandan keyin "Rasmga olish" tugmasini bosing.
        </p>
        <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
            <div class="text-xs text-blue-700 mb-1">Talaba kiritgan username uchun ism:</div>
            <div id="re-fullname" class="text-lg font-bold text-blue-900"></div>
        </div>
        <div class="flex gap-2">
            <button id="btn-do-capture"
                    class="px-4 py-2 bg-emerald-600 text-white rounded text-sm font-semibold hover:bg-emerald-700">
                Pasport mos — rasmga olish
            </button>
            <button id="btn-cancel-recapture"
                    class="px-3 py-2 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">
                Bekor qilish
            </button>
        </div>
    </div>

    {{-- Phase: recapture step 3 — webcam + capture --}}
    <div id="phase-recapture-webcam" class="bg-white rounded-lg border p-6 hidden">
        <h3 class="text-base font-bold text-gray-800 mb-3">3-qadam: Yuzni rasmga oling</h3>
        <div class="flex justify-center">
            <div class="fc-stage">
                <video id="fc-video2" autoplay playsinline muted></video>
                <svg class="fc-frame" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <ellipse class="fc-oval ok" cx="50" cy="40" rx="28" ry="34"></ellipse>
                </svg>
            </div>
        </div>
        <div class="fc-msg text-center mt-3 text-sm text-gray-600" id="fc-msg2">Yuzni oval ichiga joylashtiring…</div>
        <div class="text-center mt-3">
            <button id="btn-snap" disabled
                    class="px-4 py-2 bg-emerald-600 text-white rounded text-sm font-semibold hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                Rasmga olish
            </button>
        </div>
        <div id="snap-result" class="mt-4"></div>
    </div>

</div>

<script src="{{ asset('vendor/face-api-moodle.min.js') }}"></script>
<script>
(() => {
    const STUDENT_ID = @json($student->student_id_number);
    const URLS = {
        precheck: @json(route('admin.registrator.face-check.precheck', $student->student_id_number)),
        lookup:   @json(route('admin.registrator.face-check.lookup',   $student->student_id_number)),
        verify:   @json(route('admin.registrator.face-check.verify',   $student->student_id_number)),
    };
    const MODEL_URL = @json(asset('face-models-moodle'));
    const CSRF = @json(csrf_token());

    // ─── Liveness tunables — match Moodle's auth/faceid defaults ─────────
    const CFG = {
        livenessTimeoutMs: 30000,
        yawThreshold:      0.25,
        yawCenter:         0.072,
    };

    // ─── DOM phases ──────────────────────────────────────────────────────
    const phases = ['init', 'liveness', 'result-pass', 'result-fail',
                    'recapture-username', 'recapture-confirm', 'recapture-webcam'];
    function showPhase(name) {
        phases.forEach(p => {
            document.getElementById('phase-' + p).classList.toggle('hidden', p !== name);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────
    function $(id) { return document.getElementById(id); }
    function setMsg(id, text) { const el = $(id); if (el) el.textContent = text; }
    function setProgress(pct) { const el = $('fc-progress'); if (el) el.style.width = Math.max(0, Math.min(100, pct)) + '%'; }
    function setOval(id, kind) {
        const el = $(id); if (!el) return;
        el.setAttribute('class', 'fc-oval' + (kind ? ' ' + kind : ''));
    }

    async function postJson(url, payload) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        const body = await resp.json().catch(() => ({}));
        return { status: resp.status, body };
    }

    // Same EAR/yaw helpers as Moodle's auth/faceid/login.js
    function computeYaw(landmarks) {
        const pts = landmarks.positions;
        const nose = pts[30], le = pts[39], re = pts[42];
        const eyeCx = (le.x + re.x) / 2;
        const faceW = Math.abs(re.x - le.x) || 1;
        return (nose.x - eyeCx) / faceW;
    }

    let modelsLoaded = false;
    async function loadModels() {
        if (modelsLoaded) return;
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        modelsLoaded = true;
    }

    async function openCamera(videoEl) {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false,
        });
        videoEl.srcObject = stream;
        await new Promise(r => { videoEl.onloadedmetadata = r; });
        await videoEl.play();
        for (let waited = 0; waited < 2000 && (!videoEl.videoWidth || !videoEl.videoHeight); waited += 100) {
            await new Promise(r => setTimeout(r, 100));
        }
        return stream;
    }
    function stopCamera(stream) {
        if (stream) stream.getTracks().forEach(t => t.stop());
    }

    // ─── Phase: liveness + Moodle precheck ──────────────────────────────
    let livenessStream = null;
    async function runLiveness() {
        showPhase('liveness');
        const video = $('fc-video');
        setMsg('fc-msg', 'Modellar yuklanmoqda…'); setProgress(5);
        try {
            await loadModels();
            setMsg('fc-msg', 'Kamera ochilmoqda…'); setProgress(10);
            livenessStream = await openCamera(video);
        } catch (e) {
            setMsg('fc-msg', 'Xato: ' + (e.message || 'kamera yoki modellar yuklanmadi'));
            return;
        }

        // Build random head-turn sequence (port of Moodle login.js variants).
        const variant = Math.floor(Math.random() * 4);
        let sequence;
        switch (variant) {
            case 0: sequence = ['headleft', 'headright']; break;
            case 1: sequence = ['headright', 'headleft']; break;
            case 2: sequence = ['headleft']; break;
            default: sequence = ['headright']; break;
        }
        sequence.push('capture');

        const PROMPT = {
            headleft:  'Boshingizni CHAPGA buring, so\'ng markazga qaytaring.',
            headright: 'Boshingizni O\'NGGA buring, so\'ng markazga qaytaring.',
            capture:   'Kameraga to\'g\'ri qarang…',
        };

        const state = { seqIdx: 0, phase: sequence[0], yawMax: 0, yawCentered: false };
        const detectorOpts = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
        const startedAt = Date.now();
        let finalDetection = null;
        let cancelled = false;

        $('btn-cancel-liveness').onclick = () => { cancelled = true; };

        function advance() {
            state.seqIdx++;
            state.phase = sequence[state.seqIdx];
            state.yawMax = 0; state.yawCentered = false;
            const pct = 20 + Math.round(60 * (state.seqIdx / Math.max(1, sequence.length - 1)));
            setProgress(pct);
            setMsg('fc-msg', PROMPT[state.phase] || 'Kameraga qarang…');
        }

        setMsg('fc-msg', PROMPT[state.phase]); setProgress(20);

        while (!cancelled && !finalDetection) {
            if (Date.now() - startedAt > CFG.livenessTimeoutMs) {
                setMsg('fc-msg', 'Vaqt tugadi. Qayta urining.');
                stopCamera(livenessStream);
                return;
            }

            const det = await faceapi.detectSingleFace(video, detectorOpts)
                .withFaceLandmarks().withFaceDescriptor();

            if (!det) {
                setOval('fc-oval', 'warn');
                $('fc-arr-left').style.display = 'none';
                $('fc-arr-right').style.display = 'none';
                setMsg('fc-msg', 'Yuzingizni oval ichida joylashtiring.');
                await new Promise(r => setTimeout(r, 150));
                continue;
            }

            // Alignment
            const box = det.detection.box;
            const vw = video.videoWidth || 1, vh = video.videoHeight || 1;
            const faceRatio = box.width / vw;
            const cxNorm = (box.x + box.width / 2) / vw;
            const cyNorm = (box.y + box.height / 2) / vh;
            const ex = (cxNorm - 0.5) / 0.28;
            const ey = (cyNorm - 0.4) / 0.34;
            const insideOval = (ex * ex + ey * ey) <= 1;
            let alignOk = true;
            if (faceRatio < 0.22) { setOval('fc-oval', 'warn'); setMsg('fc-msg', 'Kameraga yaqinroq keling.'); alignOk = false; }
            else if (faceRatio > 0.70) { setOval('fc-oval', 'warn'); setMsg('fc-msg', 'Kameradan biroz uzoqlashing.'); alignOk = false; }
            else if (!insideOval) { setOval('fc-oval', 'warn'); setMsg('fc-msg', 'Yuzingizni oval ichida joylashtiring.'); alignOk = false; }
            else { setOval('fc-oval', 'ok'); }
            if (!alignOk) {
                $('fc-arr-left').style.display = 'none';
                $('fc-arr-right').style.display = 'none';
                await new Promise(r => setTimeout(r, 120)); continue;
            }

            const yaw = computeYaw(det.landmarks);

            // Direction arrows
            if (state.phase === 'headleft' && !state.yawCentered) {
                $('fc-arr-left').style.display = state.yawMax > CFG.yawThreshold ? 'none' : '';
                $('fc-arr-right').style.display = 'none';
            } else if (state.phase === 'headright' && !state.yawCentered) {
                $('fc-arr-right').style.display = state.yawMax > CFG.yawThreshold ? 'none' : '';
                $('fc-arr-left').style.display = 'none';
            } else {
                $('fc-arr-left').style.display = 'none';
                $('fc-arr-right').style.display = 'none';
            }

            if (state.phase === 'headleft' || state.phase === 'headright') {
                const wantSign = (state.phase === 'headleft') ? 1 : -1;
                const signedYaw = yaw * wantSign;
                if (signedYaw > state.yawMax) state.yawMax = signedYaw;
                if (state.yawMax > CFG.yawThreshold && Math.abs(yaw) < CFG.yawCenter) {
                    state.yawCentered = true;
                }
                if (state.yawCentered) advance();
                else if (state.yawMax > CFG.yawThreshold) setMsg('fc-msg', 'Endi markazga qaytaring.');
                else setMsg('fc-msg', PROMPT[state.phase]);
            } else if (state.phase === 'capture') {
                finalDetection = det;
            }

            if (!finalDetection) await new Promise(r => setTimeout(r, 120));
        }

        if (cancelled) {
            stopCamera(livenessStream);
            showPhase('init');
            return;
        }

        setMsg('fc-msg', 'Test markazi bilan solishtirilmoqda…'); setProgress(95);

        const descriptor = Array.from(finalDetection.descriptor);
        const { body } = await postJson(URLS.precheck, { descriptor });
        setProgress(100);
        stopCamera(livenessStream); livenessStream = null;

        if (body.ok && body.matched) {
            showPhase('result-pass');
        } else {
            $('fail-detail').textContent = body.message
                || 'Talabaning yuzi mavjud descriptor bilan mos kelmadi.';
            showPhase('result-fail');
        }
    }

    // ─── Phase: recapture flow ──────────────────────────────────────────
    let recaptureStream = null;

    $('btn-recapture').onclick = () => {
        $('re-username').value = '';
        $('lookup-error').classList.add('hidden');
        showPhase('recapture-username');
        $('re-username').focus();
    };
    $('btn-retry-precheck').onclick = () => runLiveness();

    $('btn-lookup').onclick = async () => {
        const username = $('re-username').value.trim();
        if (!username) return;
        $('lookup-error').classList.add('hidden');
        const { body } = await postJson(URLS.lookup, { username });
        if (body.ok) {
            $('re-fullname').textContent = body.full_name;
            showPhase('recapture-confirm');
        } else {
            const err = $('lookup-error');
            err.textContent = body.message || 'Username mos kelmadi.';
            err.classList.remove('hidden');
        }
    };
    $('re-username').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); $('btn-lookup').click(); }
    });

    $('btn-cancel-recapture').onclick = () => showPhase('result-fail');

    $('btn-do-capture').onclick = async () => {
        showPhase('recapture-webcam');
        const video = $('fc-video2');
        try {
            await loadModels();
            setMsg('fc-msg2', 'Kamera ochilmoqda…');
            recaptureStream = await openCamera(video);
            setMsg('fc-msg2', 'Yuzni oval ichiga joylashtirib "Rasmga olish" tugmasini bosing.');
            $('btn-snap').disabled = false;
        } catch (e) {
            setMsg('fc-msg2', 'Xato: ' + (e.message || 'kamera ochilmadi'));
        }
    };

    $('btn-snap').onclick = async () => {
        $('btn-snap').disabled = true;
        const video = $('fc-video2');
        const result = $('snap-result');
        result.innerHTML = '<div class="text-sm text-gray-500 text-center">⏳ Tekshirilmoqda…</div>';

        try {
            const det = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                .withFaceLandmarks().withFaceDescriptor();
            if (!det) {
                result.innerHTML = '<div class="text-sm text-red-600 text-center">❌ Yuz aniqlanmadi. Kameraga qarab qayta urining.</div>';
                $('btn-snap').disabled = false;
                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

            const { body } = await postJson(URLS.verify, {
                snapshot:   dataUrl,
                descriptor: Array.from(det.descriptor),
                det_score:  det.detection.score,
                username:   $('re-username').value.trim(),
            });

            if (body.ok) {
                stopCamera(recaptureStream); recaptureStream = null;
                result.innerHTML =
                    '<div class="bg-emerald-50 border border-emerald-300 rounded p-4 text-center">' +
                    '<div class="text-3xl mb-2">✅</div>' +
                    '<div class="text-emerald-800 font-semibold">' + (body.message || 'Tasdiqlandi.') + '</div>' +
                    '<div class="mt-3"><button id="btn-recheck-now" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm">Test markazi bilan qayta tekshirish</button></div>' +
                    '</div>';
                $('btn-recheck-now').onclick = () => {
                    result.innerHTML = '';
                    runLiveness();
                };
            } else {
                let html =
                    '<div class="bg-amber-50 border border-amber-300 rounded p-4 text-center">' +
                    '<div class="text-3xl mb-2">⚠️</div>' +
                    '<div class="text-amber-800">' + (body.message || 'Tasdiqlanmadi. Qayta urining.') + '</div>';

                // Admin-only diagnostic block (server only includes this for
                // super-admins / admins; registrators get an empty diagnostic).
                if (body.diagnostic) {
                    const d = body.diagnostic;
                    const fmtBadge = (label, score, threshold, passed) => {
                        const color = passed ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                              : 'bg-red-100 text-red-800 border-red-300';
                        const icon = passed ? '✓' : '✗';
                        return '<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded border ' + color + ' text-sm font-mono">' +
                               '<span class="font-bold">' + icon + '</span>' +
                               '<span>' + label + ':</span>' +
                               '<span class="font-semibold">' + score.toFixed(2) + '%</span>' +
                               '<span class="text-xs opacity-70">(≥' + threshold.toFixed(0) + '%)</span>' +
                               '</div>';
                    };
                    html +=
                        '<div class="mt-4 pt-3 border-t border-amber-300 text-left">' +
                        '<div class="text-xs uppercase tracking-wider text-amber-700 font-semibold mb-2">🛡️ Admin diagnostika (faqat siz ko\'rasiz)</div>' +
                        '<div class="flex flex-wrap gap-2 justify-center mb-2">' +
                            fmtBadge('HEMIS', d.similarity_hemis, d.threshold_hemis, d.hemis_passed) +
                            fmtBadge('MARK',  d.similarity_mark,  d.threshold_mark,  d.mark_passed) +
                        '</div>' +
                        '<div class="text-sm text-slate-700 bg-white rounded p-2 border border-slate-200">' +
                            '<strong>Sabab:</strong> ' + d.hint +
                        '</div>' +
                        '</div>';
                }

                html += '</div>';
                result.innerHTML = html;
                $('btn-snap').disabled = false;
            }
        } catch (e) {
            result.innerHTML = '<div class="text-sm text-red-600 text-center">❌ ' + e.message + '</div>';
            $('btn-snap').disabled = false;
        }
    };

    // ─── Boot ─────────────────────────────────────────────────────────────
    $('btn-start').onclick = () => runLiveness();

    window.addEventListener('beforeunload', () => {
        stopCamera(livenessStream);
        stopCamera(recaptureStream);
    });
})();
</script>
</x-app-layout>

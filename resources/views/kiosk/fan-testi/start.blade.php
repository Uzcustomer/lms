@extends('kiosk.fan-testi.layout')

@section('title', $test->name)

@section('styles')
        .s-desc {
            margin: 0 0 22px; padding: 14px 17px;
            border-left: 3px solid var(--gold); border-radius: 0 4px 4px 0;
            background: #fdfaf0; color: var(--ink-soft); font-size: 13.5px; line-height: 1.65;
        }
        .s-rules { margin: 0 0 24px; padding: 0; list-style: none; }
        .s-rules li {
            display: flex; gap: 11px; padding: 9px 0;
            border-bottom: 1px dashed var(--line-soft);
            color: var(--ink-soft); font-size: 13.5px;
        }
        .s-rules li:last-child { border-bottom: 0; }
        .s-rules b { flex: none; color: var(--navy); font-weight: 500; }
        .s-num {
            flex: none; display: grid; place-items: center;
            width: 21px; height: 21px; margin-top: 1px; border-radius: 50%;
            background: #eaeff7; color: var(--navy);
            font-size: 11px; font-weight: 700;
        }

        /* ---- Yuz tekshiruvi ---- */
        .f-who {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 14px; padding: 10px 12px;
            border: 1px solid var(--line); border-radius: 6px; background: #f7f9fc;
        }
        .f-who img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--navy); background: #e5ebf3; }
        .f-who b { display: block; color: var(--navy); font-size: 14.5px; }
        .f-who span { color: var(--muted); font-size: 12.5px; }
        .f-who button { margin-left: auto; border: 0; background: none; color: var(--muted); font: inherit; font-size: 12.5px; text-decoration: underline; cursor: pointer; }
        .f-stage { position: relative; max-width: 420px; margin: 0 auto; }
        .f-stage video {
            display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover;
            border-radius: 8px; background: #0f172a; transform: scaleX(-1);
        }
        .f-ring { position: absolute; inset: 0; border: 3px solid transparent; border-radius: 8px; pointer-events: none; transition: border-color .2s; }
        .f-ring.ok { border-color: #16a34a; }
        .f-ring.bad { border-color: #dc2626; }
        .f-prompt { margin: 14px 0 10px; color: var(--navy); font-size: 17px; font-weight: 700; text-align: center; }
        .f-sub { margin: -4px 0 12px; color: var(--muted); font-size: 13px; text-align: center; min-height: 1.4em; }
        .f-steps { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .f-step {
            padding: 5px 12px; border: 1px solid var(--line); border-radius: 999px;
            color: var(--muted); font-size: 12.5px; font-weight: 600; background: #fff;
        }
        .f-step.done { border-color: #86efac; background: #ecfdf5; color: #166534; }
        .f-step.now { border-color: var(--navy); color: var(--navy); }
        .f-actions { display: flex; gap: 10px; margin-top: 16px; }
        .f-actions .k-btn { flex: 1; }
@endsection

@section('content')
    <div class="k-card">
        <div class="k-head">
            <div class="k-eyebrow">Nazorat testi</div>
            <h1>{{ $test->name }}</h1>
            <p>{{ $test->subject?->subject_name }}@if($test->subject?->semester_name) · {{ $test->subject->semester_name }}@endif</p>

            @php
                $activeCount = collect($test->questions ?? [])
                    ->filter(fn ($q) => ($q['is_active'] ?? true) !== false)
                    ->count();
            @endphp
            <div class="k-meta">
                <div class="k-meta-item"><b>{{ $activeCount }}</b><span>Savol</span></div>
                <div class="k-meta-item"><b>{{ $test->duration_minutes }}</b><span>Daqiqa</span></div>
                @if($test->pass_percent)
                    <div class="k-meta-item"><b>{{ $test->pass_percent }}%</b><span>O'tish chegarasi</span></div>
                @endif
            </div>
        </div>

        <div class="k-body">
            @if($errors->any())
                <div class="k-error"><span>&#9888;</span><span>{{ $errors->first() }}</span></div>
            @endif

            @if($test->description)
                <p class="s-desc">{{ $test->description }}</p>
            @endif

            <ul class="s-rules">
                <li><span class="s-num">1</span><span>Test <b>{{ $test->duration_minutes }} daqiqa</b> davom etadi. Vaqt tugashiga bir daqiqa qolganda ogohlantirish beriladi.</span></li>
                <li><span class="s-num">2</span><span>Vaqt tugaganda javoblaringiz <b>avtomatik topshiriladi</b>.</span></li>
                <li><span class="s-num">3</span><span>Har bir talaba bu testni <b>faqat bir marta</b> topshiradi.</span></li>
                <li><span class="s-num">4</span><span>Kirishda <b>yuz tekshiruvi</b> bor: boshingizni o'ngga, chapga burib, so'ng kameraga to'g'ri qarang.</span></li>
            </ul>

            {{-- 1-bosqich: talaba ID --}}
            <div id="stepId">
                <div class="k-error" id="idError" style="display:none"><span>&#9888;</span><span></span></div>
                <label class="k-label" for="student_id_number">Talaba ID raqamingizni kiriting</label>
                <input class="k-input" id="student_id_number"
                       value="{{ old('student_id_number') }}" autocomplete="off" autofocus
                       inputmode="numeric" placeholder="000000000000">

                <div style="margin-top:20px">
                    <button class="k-btn k-btn-lg" type="button" id="btnCheck">Davom etish</button>
                </div>
            </div>

            {{-- 2-bosqich: yuz tekshiruvi (liveness + tasdiqlangan rasm bilan solishtirish) --}}
            <div id="stepFace" style="display:none">
                <div class="f-who">
                    <img id="whoPhoto" src="" alt="">
                    <div><b id="whoName"></b><span id="whoGroup"></span></div>
                    <button type="button" id="btnBack">Boshqa ID</button>
                </div>

                <div class="f-stage">
                    <video id="faceVideo" autoplay playsinline muted></video>
                    <div class="f-ring" id="faceRing"></div>
                </div>

                <div class="f-prompt" id="facePrompt">Kamera ochilmoqda...</div>
                <div class="f-sub" id="faceSub"></div>
                <div class="f-steps">
                    <span class="f-step" data-step="right">1. O'ngga qarang</span>
                    <span class="f-step" data-step="left">2. Chapga qarang</span>
                    <span class="f-step" data-step="center">3. To'g'riga qarang</span>
                </div>

                <div class="k-error" id="faceError" style="display:none;margin-top:14px"><span>&#9888;</span><span></span></div>
                <div class="f-actions">
                    <button class="k-btn" type="button" id="btnRetry" style="display:none">Qayta urinish</button>
                </div>
            </div>

            <p class="k-note">
                ID raqamingizni talaba guvohnomangizdan yoki HEMIS tizimidan topishingiz mumkin.<br>
                Testga kirishda kamera orqali yuzingiz tasdiqlangan rasmingiz bilan solishtiriladi.<br>
                Muammo yuzaga kelsa o'qituvchiga murojaat qiling.
            </p>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function () {
    const CFG = {
        checkUrl: @json(route('kiosk.fan-testi.check', $test)),
        startUrl: @json(route('kiosk.fan-testi.start', $test)),
        models: @json(asset('face-models')),
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        timeoutMs: Math.max(20, {{ (int) ($liveness['timeout_seconds'] ?? 30) }}) * 1000,
        yawTurn: 0.18,     // boshni burish chegarasi (admin Face ID test sahifasi bilan bir xil)
        yawCenter: 0.08,   // to'g'ri qarash
        holdMs: 700,       // surat olishdan oldin to'g'ri qarab turish vaqti
    };

    const $ = (id) => document.getElementById(id);
    const state = { idNumber: '', stream: null, timer: null, busy: false, modelsReady: false };

    function showError(boxId, message) {
        const box = $(boxId);
        box.querySelector('span:last-child').textContent = message;
        box.style.display = message ? '' : 'none';
    }

    function messageFrom(data, fallback) {
        if (data && data.errors) {
            const first = Object.values(data.errors)[0];
            if (first && first.length) return first[0];
        }
        return (data && data.message) || fallback;
    }

    async function postJson(url, body) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
            body: JSON.stringify(body),
        });
        if (resp.status === 419) {
            // Sahifa uzoq ochiq turib sessiya eskirgan — yangilaymiz.
            window.location.reload();
            return { ok: false, data: {} };
        }
        let data = {};
        try { data = await resp.json(); } catch (e) {}
        return { ok: resp.ok, status: resp.status, data };
    }

    // ---- 1-bosqich: ID ----
    async function checkId() {
        const idNumber = $('student_id_number').value.trim();
        showError('idError', '');
        if (!idNumber) { showError('idError', 'Talaba ID raqamini kiriting.'); return; }

        const btn = $('btnCheck');
        btn.disabled = true;
        btn.textContent = 'Tekshirilmoqda...';
        try {
            const r = await postJson(CFG.checkUrl, { student_id_number: idNumber });
            if (!r.ok) { showError('idError', messageFrom(r.data, 'Talaba topilmadi.')); return; }

            state.idNumber = idNumber;
            $('whoName').textContent = r.data.full_name || '';
            $('whoGroup').textContent = (r.data.group_name ? r.data.group_name + ' · ' : '') + idNumber;
            $('whoPhoto').src = r.data.photo_url || '';
            $('stepId').style.display = 'none';
            $('stepFace').style.display = '';
            await startCamera();
        } catch (e) {
            showError('idError', "Server bilan aloqa yo'q. Qayta urinib ko'ring.");
        } finally {
            btn.disabled = false;
            btn.textContent = 'Davom etish';
        }
    }

    // ---- 2-bosqich: kamera va liveness ----
    async function startCamera() {
        setPrompt('Kamera ochilmoqda...', '');
        try {
            if (!state.modelsReady) {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(CFG.models),
                    faceapi.nets.faceLandmark68Net.loadFromUri(CFG.models),
                ]);
                state.modelsReady = true;
            }
        } catch (e) {
            fail('Yuz aniqlash modullari yuklanmadi. Sahifani yangilang.');
            return;
        }
        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
        } catch (e) {
            fail(e && e.name === 'NotAllowedError' ? 'Kameraga ruxsat bering.' : 'Kamera topilmadi yoki band.');
            return;
        }
        const video = $('faceVideo');
        video.srcObject = state.stream;
        await new Promise((resolve) => { video.onloadedmetadata = resolve; });
        await video.play();
        runLiveness();
    }

    function stopCamera() {
        if (state.timer) { clearInterval(state.timer); state.timer = null; }
        if (state.stream) { state.stream.getTracks().forEach((t) => t.stop()); state.stream = null; }
    }

    function setPrompt(title, sub) {
        $('facePrompt').textContent = title;
        $('faceSub').textContent = sub || '';
    }

    function markSteps(done, current) {
        document.querySelectorAll('.f-step').forEach((el) => {
            const step = el.dataset.step;
            el.classList.toggle('done', !!done[step]);
            el.classList.toggle('now', step === current && !done[step]);
        });
    }

    function ring(kind) {
        $('faceRing').className = 'f-ring' + (kind ? ' ' + kind : '');
    }

    function fail(message) {
        stopCamera();
        ring('bad');
        setPrompt("Tekshiruv to'xtadi", '');
        showError('faceError', message);
        $('btnRetry').style.display = '';
    }

    // Burun uchining ko'zlar markaziga nisbatan siljishi. Kadr ko'zgulanmagan:
    // talaba o'ngga qaraganda qiymat manfiy, chapga qaraganda musbat bo'ladi.
    function yawOf(landmarks) {
        const p = landmarks.positions;
        const cx = (p[39].x + p[42].x) / 2;
        const fw = Math.abs(p[42].x - p[39].x);
        return fw > 0 ? (p[30].x - cx) / fw : 0;
    }

    function runLiveness() {
        const video = $('faceVideo');
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });
        const done = { right: false, left: false, center: false };
        const startedAt = Date.now();
        let centerSince = null;

        showError('faceError', '');
        $('btnRetry').style.display = 'none';
        ring('');
        markSteps(done, 'right');
        setPrompt("Boshingizni o'ngga burang", "Yuzingiz kamerada to'liq ko'rinsin");

        state.timer = setInterval(async () => {
            if (state.busy) return;
            if (Date.now() - startedAt > CFG.timeoutMs) {
                fail("Vaqt tugadi. Qayta urinib, ko'rsatmalarni bajaring.");
                return;
            }
            state.busy = true;
            let faces = [];
            try {
                faces = await faceapi.detectAllFaces(video, opts).withFaceLandmarks();
            } catch (e) {
                state.busy = false;
                return;
            }
            state.busy = false;
            if (!state.timer) return;

            if (faces.length === 0) {
                ring('');
                $('faceSub').textContent = "Yuzingiz ko'rinmayapti — kameraga yaqinroq turing";
                centerSince = null;
                return;
            }
            if (faces.length > 1) {
                ring('bad');
                $('faceSub').textContent = "Kamerada faqat bitta odam bo'lishi kerak";
                centerSince = null;
                return;
            }

            ring('ok');
            const yaw = yawOf(faces[0].landmarks);
            if (yaw < -CFG.yawTurn) done.right = true;
            if (yaw > CFG.yawTurn) done.left = true;

            if (!done.right || !done.left) {
                const next = !done.right ? 'right' : 'left';
                markSteps(done, next);
                setPrompt(next === 'right' ? "Boshingizni o'ngga burang" : 'Boshingizni chapga burang', '');
                return;
            }

            markSteps(done, 'center');
            if (Math.abs(yaw) > CFG.yawCenter) {
                centerSince = null;
                setPrompt("Endi kameraga to'g'ri qarang", '');
                return;
            }
            if (!centerSince) {
                centerSince = Date.now();
                setPrompt("Endi kameraga to'g'ri qarang", 'Qimirlamang...');
                return;
            }
            if (Date.now() - centerSince < CFG.holdMs) return;

            done.center = true;
            markSteps(done, null);
            clearInterval(state.timer);
            state.timer = null;
            await verify(capture(video));
        }, 150);
    }

    function capture(video) {
        const canvas = document.createElement('canvas');
        const vw = video.videoWidth || 640;
        const vh = video.videoHeight || 480;
        canvas.width = Math.min(vw, 640);
        canvas.height = Math.round(vh * canvas.width / vw);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.9);
    }

    async function verify(snapshot) {
        setPrompt('Yuzingiz tekshirilmoqda...', 'Iltimos kuting');
        let r;
        try {
            r = await postJson(CFG.startUrl, {
                student_id_number: state.idNumber,
                snapshot: snapshot,
                liveness_passed: true,
            });
        } catch (e) {
            fail("Server bilan aloqa yo'q. Qayta urinib ko'ring.");
            return;
        }
        if (r.ok && r.data.redirect) {
            stopCamera();
            setPrompt('Tasdiqlandi', 'Testga kirilmoqda...');
            window.location.href = r.data.redirect;
            return;
        }
        fail(messageFrom(r.data, 'Yuz tasdiqlanmadi.') + (r.data.confidence ? ' (' + r.data.confidence + '%)' : ''));
    }

    $('btnCheck').addEventListener('click', checkId);
    $('student_id_number').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); checkId(); }
    });
    $('btnRetry').addEventListener('click', () => { showError('faceError', ''); startCamera(); });
    $('btnBack').addEventListener('click', () => {
        stopCamera();
        $('stepFace').style.display = 'none';
        $('stepId').style.display = '';
        $('student_id_number').focus();
    });
    window.addEventListener('beforeunload', stopCamera);
})();
</script>
@endsection

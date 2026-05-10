<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Webcam tekshiruv: {{ $student->full_name }}
    </h2>
</x-slot>

<div class="container mx-auto px-4 py-6 max-w-5xl">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">🆔 {{ $student->full_name }}</h1>
            <p class="text-sm text-gray-500">
                ID: <span class="font-mono">{{ $student->student_id_number }}</span>
                @if($student->group_name) · {{ $student->group_name }}@endif
            </p>
        </div>
        <a href="{{ route('admin.registrator.face-check.index') }}"
           class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">← Ro'yxat</a>
    </div>

    @if(!empty($missing))
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 mb-5 text-sm">
        <strong>⚠️ Tekshiruv mumkin emas:</strong>
        Talabaning quyidagi rasmlari yo'q: <strong>{{ implode(', ', $missing) }}</strong>.
        @if(in_array('Mark approved rasm', $missing, true))
            Avval tutor orqali rasm yuklatish va tasdiqlash kerak.
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Reference photos --}}
        <div class="bg-white rounded-lg border p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Mavjud rasmlar</h3>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <div class="text-gray-500 mb-1">HEMIS</div>
                    @if(!empty($student->image))
                        <img src="{{ str_starts_with($student->image, 'http') ? $student->image : rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz'), '/') . '/' . ltrim($student->image, '/') }}"
                             alt="HEMIS" class="w-full aspect-square object-cover rounded border" referrerpolicy="no-referrer">
                    @else
                        <div class="w-full aspect-square bg-gray-100 rounded border flex items-center justify-center text-gray-400">yo'q</div>
                    @endif
                </div>
                <div>
                    <div class="text-gray-500 mb-1">Mark (approved)</div>
                    @if($approvedPhoto)
                        <img src="{{ asset($approvedPhoto->photo_path) }}" alt="Mark"
                             class="w-full aspect-square object-cover rounded border">
                    @else
                        <div class="w-full aspect-square bg-gray-100 rounded border flex items-center justify-center text-gray-400">yo'q</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Webcam --}}
        <div class="bg-white rounded-lg border p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Webcam</h3>
            <div class="relative">
                <video id="webcam" autoplay playsinline muted
                       class="w-full aspect-square object-cover rounded bg-black"></video>
                <canvas id="canvas" class="hidden"></canvas>
            </div>
            <div id="model-status" class="mt-2 text-xs text-gray-500">⏳ Modellar yuklanmoqda…</div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-5 bg-white rounded-lg border p-4">
        <div class="flex flex-wrap gap-3">
            <button id="btn-verify" disabled
                    class="px-4 py-2 bg-emerald-600 text-white rounded text-sm font-semibold hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                1️⃣ Anti-fraud tekshiruv ({{ $antiFraudPct }}% qattiq)
            </button>
            <button id="btn-precheck" disabled
                    class="px-4 py-2 bg-indigo-600 text-white rounded text-sm font-semibold hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                2️⃣ Moodle precheck (test markazi simulyatsiyasi)
            </button>
        </div>
        <div class="mt-2 text-xs text-gray-500">
            Tartib: avval anti-fraud o'tsa, keyin Moodle descriptor yuborilgach
            "Moodle precheck" tugmasi orqali test markazi kirishini taqlid qiling.
        </div>
    </div>

    {{-- Result panel --}}
    <div id="result" class="mt-4 hidden"></div>

</div>

<script src="{{ asset('vendor/face-api-moodle.min.js') }}"></script>
<script>
(() => {
    const MODEL_URL = "{{ asset('face-models-moodle') }}";
    const STUDENT_ID = @json($student->student_id_number);
    const VERIFY_URL = @json(route('admin.registrator.face-check.verify', $student->student_id_number));
    const PRECHECK_URL = @json(route('admin.registrator.face-check.precheck', $student->student_id_number));
    const CSRF = @json(csrf_token());
    const HAS_REFS = @json(empty($missing));

    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    const status = document.getElementById('model-status');
    const result = document.getElementById('result');
    const btnVerify = document.getElementById('btn-verify');
    const btnPrecheck = document.getElementById('btn-precheck');

    let modelsReady = false;
    let cameraReady = false;

    function setStatus(text, kind = 'info') {
        const cls = { info: 'text-gray-500', ok: 'text-emerald-600', err: 'text-red-600' }[kind] || 'text-gray-500';
        status.className = 'mt-2 text-xs ' + cls;
        status.textContent = text;
    }

    function showResult(html, kind = 'info') {
        const palette = {
            ok: 'bg-emerald-50 border-emerald-200 text-emerald-800',
            warn: 'bg-amber-50 border-amber-200 text-amber-800',
            err: 'bg-red-50 border-red-200 text-red-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800',
        };
        result.className = 'mt-4 border rounded-lg p-4 text-sm ' + (palette[kind] || palette.info);
        result.innerHTML = html;
        result.classList.remove('hidden');
    }

    async function loadModels() {
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);
            modelsReady = true;
            checkReady();
        } catch (e) {
            setStatus('❌ Modellar yuklanmadi: ' + e.message, 'err');
        }
    }

    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
            video.srcObject = stream;
            await video.play();
            cameraReady = true;
            checkReady();
        } catch (e) {
            setStatus('❌ Webcam ochilmadi: ' + e.message, 'err');
        }
    }

    function checkReady() {
        if (modelsReady && cameraReady) {
            setStatus('✅ Tayyor — yuzni kameraga yaqin va yorug\' joyga tushiring', 'ok');
            if (HAS_REFS) {
                btnVerify.disabled = false;
            }
        }
    }

    async function captureFrame() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        return {
            dataUrl: canvas.toDataURL('image/jpeg', 0.9),
        };
    }

    async function extractDescriptor() {
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (!detection) return null;
        return {
            descriptor: Array.from(detection.descriptor),
            detScore: detection.detection.score,
        };
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

    btnVerify.addEventListener('click', async () => {
        btnVerify.disabled = true;
        btnPrecheck.disabled = true;
        showResult('⏳ Yuz aniqlanmoqda va AI 85% qattiq solishtiruv ishga tushirilmoqda…', 'info');
        try {
            const desc = await extractDescriptor();
            if (!desc) {
                showResult('❌ Yuz aniqlanmadi. Yuzni ramkaga to\'liq, yorug\' joyda tushiring.', 'err');
                btnVerify.disabled = false;
                return;
            }
            const frame = await captureFrame();
            const { status, body } = await postJson(VERIFY_URL, {
                snapshot:   frame.dataUrl,
                descriptor: desc.descriptor,
                det_score:  desc.detScore,
            });

            if (body.ok) {
                showResult(
                    `✅ <strong>Tasdiqlandi.</strong><br>` +
                    `HEMIS bilan o'xshashlik: <strong>${body.similarity_hemis}%</strong>, ` +
                    `Mark rasmi bilan: <strong>${body.similarity_mark}%</strong><br>` +
                    `${body.message}`,
                    'ok'
                );
                btnPrecheck.disabled = false;
            } else if (status === 422 && body.reason === 'similarity_too_low') {
                showResult(
                    `⚠️ <strong>${body.message}</strong><br>` +
                    `HEMIS bilan o'xshashlik: <strong>${body.similarity_hemis}%</strong>, ` +
                    `Mark rasmi bilan: <strong>${body.similarity_mark}%</strong> ` +
                    `(talab: ≥ ${body.threshold}%)<br>` +
                    `Hech narsa saqlanmadi — qayta urinib ko'ring.`,
                    'warn'
                );
                btnVerify.disabled = false;
            } else {
                showResult(`❌ ${body.message || 'Xato'}`, 'err');
                btnVerify.disabled = false;
            }
        } catch (e) {
            showResult('❌ Texnik xato: ' + e.message, 'err');
            btnVerify.disabled = false;
        }
    });

    btnPrecheck.addEventListener('click', async () => {
        btnPrecheck.disabled = true;
        showResult('⏳ Moodle bilan bog\'lanmoqda — test markazidagi solishtiruv ishga tushirilmoqda…', 'info');
        try {
            const desc = await extractDescriptor();
            if (!desc) {
                showResult('❌ Yuz aniqlanmadi. Yuzni qayta to\'g\'rilang.', 'err');
                btnPrecheck.disabled = false;
                return;
            }
            const { body } = await postJson(PRECHECK_URL, { descriptor: desc.descriptor });

            if (!body.ok) {
                showResult(`❌ ${body.message || 'Moodle bilan bog\'lanib bo\'lmadi'}`, 'err');
                btnPrecheck.disabled = false;
                return;
            }

            const meta = `(masofa: ${body.distance ?? '–'}, ishonch: ${body.confidence ?? '–'}%, threshold: ${body.threshold ?? '–'})`;

            if (body.matched) {
                showResult(`✅ <strong>${body.message}</strong><br>${meta}`, 'ok');
            } else if (body.status === 'no_descriptors') {
                showResult(
                    `⏳ ${body.message}<br>${meta}<br>` +
                    `Bir necha soniyadan keyin qayta bosing — sinxron job ishga tushgan.`,
                    'warn'
                );
                btnPrecheck.disabled = false;
            } else {
                showResult(`⚠️ <strong>${body.message}</strong><br>${meta}`, 'warn');
                btnPrecheck.disabled = false;
            }
        } catch (e) {
            showResult('❌ Texnik xato: ' + e.message, 'err');
            btnPrecheck.disabled = false;
        }
    });

    loadModels();
    startCamera();
})();
</script>
</x-app-layout>

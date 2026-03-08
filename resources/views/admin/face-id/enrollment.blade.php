@extends('layouts.admin')

@section('title', 'Face ID Enrollment')

@section('content')
<div class="container mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">📷 Face ID Enrollment</h1>
            <p class="text-sm text-gray-500">Talabalar yuzini HEMIS rasm asosida ro'yxatga olish</p>
        </div>
        <a href="{{ route('admin.face-id.settings') }}"
           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">⚙️ Sozlamalar</a>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <!-- Tushuntirish -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5 text-sm text-blue-800">
        <strong>Qanday ishlaydi:</strong>
        <ul class="mt-2 ml-4 list-disc space-y-1 text-blue-700">
            <li>Har bir talabaning HEMIS rasmi yuklanadi va face-api.js orqali 128-o'lchovli descriptor olinadi</li>
            <li>Descriptor serverda saqlanadi — keyingi kirishlarda server o'zi taqqoslaydi (xavfsizroq)</li>
            <li>"Barchani enroll" tugmasi barcha talabalarni (rasmi bor bo'lganlarga) ketma-ket qayta ishlaydi</li>
        </ul>
    </div>

    <!-- Filter -->
    <form method="GET" class="bg-white rounded-lg border p-4 mb-4 flex gap-3 items-end flex-wrap">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Holat</label>
            <select name="filter" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="all"         {{ $filter === 'all'         ? 'selected' : '' }}>Barchasi</option>
                <option value="enrolled"    {{ $filter === 'enrolled'    ? 'selected' : '' }}>✅ Ro'yxatda bor</option>
                <option value="not_enrolled"{{ $filter === 'not_enrolled'? 'selected' : '' }}>⚠️ Ro'yxatda yo'q</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Qidirish</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Ism yoki ID" class="border border-gray-300 rounded px-2 py-1.5 text-sm w-48">
        </div>
        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg">Filter</button>
        <button type="button" id="btn-enroll-all"
                class="px-4 py-1.5 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700">
            🚀 Barchani enroll
        </button>
    </form>

    <!-- Progress bar (batch enrollment uchun) -->
    <div id="batch-progress" style="display:none;" class="bg-white rounded-lg border p-4 mb-4">
        <div class="flex justify-between text-sm mb-2">
            <span id="batch-status">Tayyorlanmoqda...</span>
            <span id="batch-counter">0/0</span>
        </div>
        <div class="bg-gray-200 rounded-full h-3">
            <div id="batch-bar" class="bg-purple-600 h-3 rounded-full transition-all" style="width:0%"></div>
        </div>
    </div>

    <!-- Talabalar jadvali -->
    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rasm</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="enrollment-table">
                @foreach($students as $student)
                <tr id="row-{{ $student->id }}" data-student-id="{{ $student->id }}"
                    data-photo-url="{{ route('student.face-id.photo', $student->id) }}"
                    data-descriptor-url="{{ route('admin.face-id.descriptor.save') }}">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800">{{ $student->full_name }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $student->student_id_number }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @if($student->image)
                        <img src="{{ route('student.face-id.photo', $student->id) }}" alt=""
                             class="w-10 h-10 rounded-full object-cover border"
                             onerror="this.style.opacity=0.3">
                        @else
                        <span class="text-gray-300 text-xs">Rasm yo'q</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span id="status-{{ $student->id }}"
                              class="px-2 py-0.5 rounded-full text-xs font-medium {{ $student->faceDescriptor ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $student->faceDescriptor ? '✅ Ro\'yxatda' : '⚪ Yo\'q' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($student->image)
                        <button class="btn-enroll-one text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                                data-student-id="{{ $student->id }}">
                            📷 Enroll
                        </button>
                        @endif
                        @if($student->faceDescriptor)
                        <form method="POST" action="{{ route('admin.face-id.descriptor.delete', $student->id) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" onclick="return confirm('O\'chirasizmi?')"
                                    class="text-xs px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 ml-1">
                                🗑️
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($students->hasPages())
        <div class="px-4 py-3 border-t">{{ $students->links() }}</div>
        @endif
    </div>
</div>

<!-- Hidden canvas for face processing -->
<canvas id="proc-canvas" style="display:none;"></canvas>
<img id="proc-img" style="display:none; max-width:400px;" crossorigin="anonymous">

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function() {
    let modelsLoaded = false;
    const MODELS_PATH = '/face-models';
    const CSRF = '{{ csrf_token() }}';
    const SAVE_URL = '{{ route('admin.face-id.descriptor.save') }}';

    async function ensureModels() {
        if (modelsLoaded) return;
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_PATH),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_PATH),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_PATH),
        ]);
        modelsLoaded = true;
    }

    async function enrollStudent(studentId, photoUrl, descriptorUrl) {
        const status = document.getElementById('status-' + studentId);
        status.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-600';
        status.textContent = '⏳ Yuklanmoqda...';

        try {
            const img = await faceapi.fetchImage(photoUrl);
            const detection = await faceapi
                .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                status.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700';
                status.textContent = '⚠️ Yuz aniqlanmadi';
                return false;
            }

            const resp = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    student_id: parseInt(studentId),
                    descriptor: Array.from(detection.descriptor),
                    source_url: photoUrl,
                }),
            });

            if (resp.ok) {
                status.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700';
                status.textContent = '✅ Ro\'yxatda';
                return true;
            } else {
                throw new Error('Server xatosi');
            }
        } catch (err) {
            status.className = 'px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700';
            status.textContent = '❌ Xato';
            console.error('Enroll error for student', studentId, err);
            return false;
        }
    }

    // Individual enroll
    document.querySelectorAll('.btn-enroll-one').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const studentId  = row.dataset.studentId;
            const photoUrl   = row.dataset.photoUrl;
            const descriptorUrl = row.dataset.descriptorUrl;

            btn.disabled    = true;
            btn.textContent = '⏳';

            await ensureModels().catch(() => {});
            await enrollStudent(studentId, photoUrl, descriptorUrl);

            btn.disabled    = false;
            btn.textContent = '📷 Enroll';
        });
    });

    // Batch enroll
    document.getElementById('btn-enroll-all').addEventListener('click', async () => {
        const rows = Array.from(document.querySelectorAll('#enrollment-table tr[data-student-id]'));
        if (!rows.length) return;

        const progressDiv = document.getElementById('batch-progress');
        const bar         = document.getElementById('batch-bar');
        const counter     = document.getElementById('batch-counter');
        const batchStatus = document.getElementById('batch-status');

        progressDiv.style.display = 'block';
        batchStatus.textContent   = 'Modellar yuklanmoqda...';

        await ensureModels();
        batchStatus.textContent = 'Enrollment boshlandi...';

        let done = 0;
        for (const row of rows) {
            const studentId = row.dataset.studentId;
            const photoUrl  = row.dataset.photoUrl;
            counter.textContent = `${done + 1}/${rows.length}`;
            batchStatus.textContent = `${row.querySelector('.font-medium')?.textContent?.trim()}...`;

            await enrollStudent(studentId, photoUrl, SAVE_URL);
            done++;
            bar.style.width = Math.round(done / rows.length * 100) + '%';
        }

        batchStatus.textContent = `Tayyor! ${done} ta talaba qayta ishlandi.`;
        bar.style.background = '#22c55e';
    });
})();
</script>
@endsection

@extends('layouts.app')

@section('title', 'Face ID Sozlamalari')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-4xl">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">🪪 Face ID Sozlamalari</h1>
            <p class="text-sm text-gray-500 mt-1">Biometrik autentifikatsiya parametrlari</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.face-id.logs') }}"
               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                📋 Loglar
            </a>
            <a href="{{ route('admin.face-id.enrollment') }}"
               class="px-3 py-2 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                📷 Enrollment
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    <!-- Statistika kartlar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $totalStudents }}</div>
            <div class="text-xs text-gray-500 mt-1">Jami talabalar</div>
        </div>
        <div class="bg-white rounded-lg border p-4 text-center">
            <div class="text-2xl font-bold text-purple-600">{{ $enrolledCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Ro'yxatga olingan</div>
        </div>
        <div class="bg-white rounded-lg border p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $lastDaySuccess }}</div>
            <div class="text-xs text-gray-500 mt-1">Bugun muvaffaqiyatli</div>
        </div>
        <div class="bg-white rounded-lg border p-4 text-center">
            <div class="text-2xl font-bold text-red-600">{{ $lastDayFailed }}</div>
            <div class="text-xs text-gray-500 mt-1">Bugun muvaffaqiyatsiz</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.face-id.settings.update') }}">
        @csrf

        <!-- Global holat -->
        <div class="bg-white rounded-lg border p-5 mb-4">
            <h2 class="font-semibold text-gray-700 mb-4">🌐 Global Holat</h2>
            <label class="flex items-center gap-3 cursor-pointer">
                <div class="relative">
                    <input type="hidden" name="faceid_global_enabled" value="0">
                    <input type="checkbox" name="faceid_global_enabled" value="1" id="global_enabled"
                           class="sr-only peer"
                           {{ $settings['global_enabled'] ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
                </div>
                <div>
                    <div class="font-medium text-gray-800">Face ID login yoqilgan</div>
                    <div class="text-xs text-gray-500">O'chirilsa barcha talabalar parol bilan kiradi</div>
                </div>
            </label>
        </div>

        <!-- Taqqoslash sozlamalari -->
        <div class="bg-white rounded-lg border p-5 mb-4">
            <h2 class="font-semibold text-gray-700 mb-4">🎯 Taqqoslash sozlamalari</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Euclidean distance chegarasi
                    <span class="text-gray-400 font-normal">({{ $settings['threshold'] }})</span>
                </label>
                <input type="range" name="faceid_threshold" id="threshold_slider"
                       min="0.25" max="0.70" step="0.01"
                       value="{{ $settings['threshold'] }}"
                       class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                       oninput="document.getElementById('threshold_val').textContent = parseFloat(this.value).toFixed(2) + ' (' + Math.round((1 - this.value/0.6)*100) + '% yaqinlik)'">
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0.25 (qattiq)</span>
                    <span id="threshold_val">{{ $settings['threshold'] }} ({{ round((1 - $settings['threshold']/0.6) * 100) }}% yaqinlik)</span>
                    <span>0.70 (yumshoq)</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    Tavsiya: 0.35–0.45 (85–92% yaqinlik). Kichik → qat'iyroq, katta → yumshoqroq.
                </p>
            </div>
        </div>

        <!-- Liveness check -->
        <div class="bg-white rounded-lg border p-5 mb-4">
            <h2 class="font-semibold text-gray-700 mb-4">👁️ Jonlilik tekshiruvi (Liveness)</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Ko'z yumish soni (0 = o'chirilgan)
                </label>
                <select name="faceid_blinks_required" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach([0, 1, 2, 3] as $n)
                    <option value="{{ $n }}" {{ $settings['blinks_required'] == $n ? 'selected' : '' }}>
                        {{ $n }} marta{{ $n === 0 ? ' (o\'chirilgan)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                        <input type="hidden" name="faceid_head_turn_required" value="0">
                        <input type="checkbox" name="faceid_head_turn_required" value="1"
                               class="sr-only peer"
                               {{ $settings['head_turn_required'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
                    </div>
                    <div>
                        <div class="font-medium text-sm text-gray-800">Bosh burilishini tekshirish</div>
                        <div class="text-xs text-gray-500">Talabadan chap yoki o'ng tomonga burish talab qilinadi</div>
                    </div>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Liveness vaqt chegarasi (soniya)
                </label>
                <input type="number" name="faceid_liveness_timeout"
                       value="{{ $settings['liveness_timeout'] }}"
                       min="10" max="120"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
            </div>
        </div>

        <!-- Snapshot sozlamalari -->
        <div class="bg-white rounded-lg border p-5 mb-4">
            <h2 class="font-semibold text-gray-700 mb-4">📸 Snapshot (tekshiruv uchun)</h2>

            <div class="mb-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                        <input type="hidden" name="faceid_save_snapshots" value="0">
                        <input type="checkbox" name="faceid_save_snapshots" value="1"
                               class="sr-only peer"
                               {{ $settings['save_snapshots'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
                    </div>
                    <div>
                        <div class="font-medium text-sm text-gray-800">Snapshotni saqlash</div>
                        <div class="text-xs text-gray-500">Har bir urinishda talaba rasmi logga saqlanadi</div>
                    </div>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Maksimal snapshot hajmi (KB)
                </label>
                <input type="number" name="faceid_max_snapshot_kb"
                       value="{{ $settings['max_snapshot_kb'] }}"
                       min="10" max="500"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                Saqlash
            </button>
        </div>
    </form>
</div>
@endsection

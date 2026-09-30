<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>
    @if ($errors->any())
        <div class="mx-auto mt-4 max-w-7xl sm:px-6 lg:px-8">
            <div class="relative px-4 py-3 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
                <strong class="font-bold">Xatolik yuz berdi!</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mx-auto mt-4 max-w-7xl sm:px-6 lg:px-8">
            <div class="relative px-4 py-3 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
                <strong class="font-bold">Xatolik:</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="mx-auto mt-4 max-w-7xl sm:px-6 lg:px-8">
            <div class="relative px-4 py-3 text-green-700 bg-green-100 border border-green-400 rounded" role="alert">
                <strong class="font-bold">Muvaffaqiyatli:</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{ __("Tizimga kirish muvafaqiyatli amalga oshirildi!") }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tyutor rolidagi xodim profiliga har kirganda: guruh starostalarini belgilash eslatmasi (popup) --}}
    @if(function_exists('is_active_tyutor') && is_active_tyutor())
        <div id="tyutorStarostaModal" class="tyutor-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tyutorStarostaTitle">
            <div class="tyutor-modal">
                <span class="tyutor-modal-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.5-4.66"/></svg>
                </span>
                <h3 id="tyutorStarostaTitle">Guruh starostalari</h3>
                <p>Iltimos, guruh starostalarini belgilab chiqing.</p>
                <button type="button" class="tyutor-modal-btn" onclick="loCloseTyutorModal()">Tushunarli</button>
            </div>
        </div>
        <style>
            .tyutor-modal-overlay { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; background:rgba(15,23,42,.55); }
            .tyutor-modal { width:100%; max-width:420px; background:#fff; border-radius:16px; padding:28px 24px; text-align:center; box-shadow:0 20px 50px rgba(15,23,42,.3); animation:tyutorPop .18s ease-out; }
            .tyutor-modal-icon { display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; margin-bottom:14px; border-radius:50%; background:#eff6ff; color:#2563eb; }
            .tyutor-modal-icon svg { width:32px; height:32px; }
            .tyutor-modal h3 { margin:0 0 8px; font-size:19px; font-weight:800; color:#0f172a; }
            .tyutor-modal p { margin:0 0 20px; font-size:14.5px; line-height:1.5; color:#475569; }
            .tyutor-modal-btn { display:inline-flex; align-items:center; justify-content:center; min-width:140px; padding:11px 22px; border:0; border-radius:10px; background:#2563eb; color:#fff; font-size:14px; font-weight:700; cursor:pointer; transition:background .12s; }
            .tyutor-modal-btn:hover { background:#1d4ed8; }
            @keyframes tyutorPop { from { transform:scale(.94); opacity:0; } to { transform:scale(1); opacity:1; } }
        </style>
        <script>
            function loCloseTyutorModal() {
                var m = document.getElementById('tyutorStarostaModal');
                if (m) { m.remove(); }
            }
            // Fon (overlay) bosilса ham yopiladi
            (function () {
                var overlay = document.getElementById('tyutorStarostaModal');
                if (overlay) {
                    overlay.addEventListener('click', function (e) { if (e.target === overlay) { loCloseTyutorModal(); } });
                }
            })();
        </script>
    @endif
</x-app-layout>

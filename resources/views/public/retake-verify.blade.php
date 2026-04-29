<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tasdiqnoma — Tekshirish</title>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">

            @if($found)
                {{-- ✓ Tasdiqlangan --}}
                <div class="bg-emerald-500 px-6 py-8 text-center">
                    <div class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center mb-3">
                        <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-white">Tasdiqlangan ariza</h1>
                    <p class="text-sm text-emerald-100 mt-1">Bu tasdiqnoma haqiqiy</p>
                </div>

                <div class="p-6 space-y-4">

                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Talaba</p>
                        <p class="text-base font-semibold text-gray-900">{{ $student?->full_name }}</p>
                        <p class="text-sm text-gray-600">{{ $student?->department_name }} — {{ $student?->group_name }}-guruh</p>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Fan</p>
                        <p class="text-base font-semibold text-gray-900">{{ $application->subject_name }}</p>
                        <p class="text-sm text-gray-600">{{ $application->semester_name }} — {{ number_format((float) $application->credit, 1) }} kredit</p>
                    </div>

                    @if($group)
                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Qayta o'qish guruhi</p>
                        <p class="text-base font-semibold text-gray-900">{{ $group->name }}</p>
                        <p class="text-sm text-gray-600">
                            {{ $group->start_date->format('d.m.Y') }} → {{ $group->end_date->format('d.m.Y') }}
                        </p>
                        @if($teacher)
                            <p class="text-sm text-gray-600 mt-1">O'qituvchi: <span class="font-medium">{{ $teacher->full_name }}</span></p>
                        @endif
                    </div>
                    @endif

                    <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Tasdiqlangan</p>
                            <p class="text-sm font-medium text-gray-700">
                                {{ $application->academic_dept_reviewed_at?->format('d.m.Y') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold mb-1">Ariza №</p>
                            <p class="text-sm font-medium text-gray-700">
                                {{ str_pad((string) $application->id, 6, '0', STR_PAD_LEFT) }}
                            </p>
                        </div>
                    </div>

                </div>
            @else
                {{-- ✕ Topilmadi --}}
                <div class="bg-red-500 px-6 py-8 text-center">
                    <div class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center mb-3">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-white">Tasdiqnoma topilmadi</h1>
                    <p class="text-sm text-red-100 mt-1">Bunday tasdiqlangan ariza tizimda mavjud emas</p>
                </div>

                <div class="p-6 text-center">
                    <p class="text-sm text-gray-600">
                        Agar ariza yaqinda tasdiqlangan bo'lsa, bir necha daqiqa kutib, sahifani yangilang.
                    </p>
                </div>
            @endif
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            Toshkent davlat tibbiyot universiteti Termiz filiali
        </p>
    </div>
</body>
</html>

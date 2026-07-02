<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Mening hujjat va ma'lumotlarim") }}
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 px-3 py-4">
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="px-5 py-5 border-b border-slate-100 bg-gradient-to-r from-blue-50 via-white to-indigo-50">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ __("Mening hujjat va ma'lumotlarim") }}</h1>
                        <p class="text-sm text-slate-500 mt-1">
                            {{ __("Admin tomonidan yuklangan hujjatlarni shu yerdan ko'rishingiz va yuklab olishingiz mumkin.") }}
                        </p>
                    </div>
                    <div class="inline-flex items-center px-4 py-2 rounded-2xl bg-blue-100 text-blue-700 text-sm font-semibold">
                        {{ $files->count() }} ta fayl
                    </div>
                </div>
            </div>

            @if(session('error'))
                <div class="mx-5 mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($files->isEmpty())
                <div class="px-5 py-12 text-center">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800">{{ __("Hozircha fayl yuklanmagan") }}</h3>
                    <p class="text-sm text-slate-500 mt-2">{{ __("Siz uchun hali student files bo'limiga hujjat yuklanmagan.") }}</p>
                </div>
            @else
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($files as $file)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-base font-semibold text-slate-900 break-words">{{ $file->name }}</div>
                                        <div class="text-xs text-slate-500 mt-1 break-all">{{ $file->original_name }}</div>
                                    </div>
                                    <div class="w-11 h-11 rounded-2xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400">{{ __("Hajmi") }}</div>
                                        <div class="font-medium text-slate-700">{{ number_format(((int) $file->size) / 1024 / 1024, 2) }} MB</div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400">{{ __("Yuklangan sana") }}</div>
                                        <div class="font-medium text-slate-700">{{ optional($file->created_at)->format('d.m.Y H:i') }}</div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <a href="{{ route('student.documents.download', $file) }}"
                                       class="inline-flex items-center justify-center w-full px-4 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition">
                                        {{ __("Yuklab olish") }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-student-app-layout>

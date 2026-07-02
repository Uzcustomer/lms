<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Mening hujjat va ma'lumotlarim") }}
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 px-3 py-4 sm:py-6">
        <div class="rounded-[28px] overflow-hidden border border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.08)]">
            <div class="relative px-5 py-6 sm:px-7 sm:py-7 overflow-hidden bg-[linear-gradient(135deg,#eff6ff_0%,#ffffff_42%,#eef2ff_100%)]">
                <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-indigo-100/70 blur-2xl"></div>
                <div class="absolute -bottom-10 -left-8 w-28 h-28 rounded-full bg-sky-100/70 blur-2xl"></div>

                <div class="relative flex items-start justify-between gap-4 flex-wrap">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/80 border border-indigo-100 text-[11px] font-semibold tracking-[0.12em] uppercase text-indigo-700 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ __("Student files") }}
                        </div>
                        <h1 class="mt-4 text-[26px] sm:text-[32px] leading-tight font-black text-slate-900">
                            {{ __("Mening hujjat va ma'lumotlarim") }}
                        </h1>
                        <p class="text-sm sm:text-base text-slate-600 mt-2 leading-6">
                            {{ __("Admin tomonidan yuklangan hujjatlarni shu yerdan ko'rishingiz va bir tugma orqali yuklab olishingiz mumkin.") }}
                        </p>
                    </div>

                    <div class="min-w-[150px] rounded-[24px] border border-white/80 bg-white/85 backdrop-blur px-4 py-4 shadow-[0_14px_34px_rgba(99,102,241,0.12)]">
                        <div class="text-[11px] uppercase tracking-[0.16em] text-slate-400 font-bold">{{ __("Jami fayl") }}</div>
                        <div class="mt-2 text-3xl font-black text-slate-900">{{ $files->count() }}</div>
                        <div class="mt-1 text-xs font-medium text-indigo-600">{{ __("yuklangan hujjatlar") }}</div>
                    </div>
                </div>
            </div>

            @if(session('error'))
                <div class="mx-5 mt-5 sm:mx-7 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($files->isEmpty())
                <div class="px-5 py-14 sm:px-7 sm:py-16 text-center bg-[radial-gradient(circle_at_top,#f8fbff,transparent_55%)]">
                    <div class="mx-auto w-20 h-20 rounded-[26px] bg-white border border-slate-200 flex items-center justify-center mb-5 shadow-[0_10px_30px_rgba(15,23,42,0.06)]">
                        <svg class="w-9 h-9 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">{{ __("Hozircha fayl yuklanmagan") }}</h3>
                    <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto leading-6">{{ __("Siz uchun hali student files bo'limiga hujjat yuklanmagan. Yangi hujjat yuklansa shu sahifada avtomatik ko'rinadi.") }}</p>
                </div>
            @else
                <div class="p-5 sm:p-7 bg-[linear-gradient(180deg,#ffffff_0%,#f8fbff_100%)]">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @foreach($files as $file)
                            <div class="group relative rounded-[26px] border border-slate-200/80 bg-white p-4 sm:p-5 shadow-[0_14px_40px_rgba(15,23,42,0.06)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_50px_rgba(79,70,229,0.12)]">
                                <div class="absolute inset-x-0 top-0 h-1 rounded-t-[26px] bg-gradient-to-r from-indigo-500 via-violet-500 to-sky-500"></div>

                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-bold uppercase tracking-[0.12em]">
                                            {{ __("Hujjat") }}
                                        </div>
                                        <div class="mt-3 text-lg font-extrabold text-slate-900 break-words leading-6">{{ $file->name }}</div>
                                        <div class="text-xs text-slate-500 mt-2 break-all leading-5">{{ $file->original_name }}</div>
                                    </div>
                                    <div class="w-12 h-12 rounded-2xl bg-[linear-gradient(135deg,#e0e7ff_0%,#dbeafe_100%)] flex items-center justify-center flex-shrink-0 shadow-inner">
                                        <svg class="w-5 h-5 text-indigo-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/80 px-3.5 py-3">
                                        <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400 font-bold">{{ __("Hajmi") }}</div>
                                        <div class="mt-1 font-extrabold text-slate-800 text-base">{{ number_format(((int) $file->size) / 1024 / 1024, 2) }} MB</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/80 px-3.5 py-3">
                                        <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400 font-bold">{{ __("Yuklangan sana") }}</div>
                                        <div class="mt-1 font-extrabold text-slate-800 text-base leading-5">{{ optional($file->created_at)->format('d.m.Y H:i') }}</div>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <a href="{{ route('student.documents.download', $file) }}"
                                       class="inline-flex items-center justify-center gap-2 w-full px-4 py-3.5 rounded-2xl bg-[linear-gradient(135deg,#4f46e5_0%,#6d28d9_100%)] text-white text-sm font-bold transition duration-200 group-hover:shadow-[0_16px_40px_rgba(79,70,229,0.28)]">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12l-4-4m4 4l4-4M4.5 19.5h15" />
                                        </svg>
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

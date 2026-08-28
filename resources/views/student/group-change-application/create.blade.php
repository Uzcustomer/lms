<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Guruhni o'zgartirish uchun ariza</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-3 sm:px-6 lg:px-8 py-5">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-200">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-5.25-5.25M19.5 12l-5.25 5.25M4.5 6.75v10.5"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Xizmat ochiq</p>
                    <h1 class="mt-1 text-xl font-extrabold text-slate-800">Guruhni o'zgartirish uchun ariza</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Sizga guruhni o'zgartirish xizmatidan foydalanish uchun ruxsat berildi. Ariza shakli keyingi bosqichda shu sahifaga qo'shiladi.</p>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-800">Talaba ma'lumotlari</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 px-4 py-3"><span class="block text-[11px] text-slate-400">F.I.Sh.</span><b class="mt-1 block text-sm text-slate-700">{{ $student->full_name }}</b></div>
                <div class="rounded-xl bg-slate-50 px-4 py-3"><span class="block text-[11px] text-slate-400">Amaldagi guruh</span><b class="mt-1 block text-sm text-slate-700">{{ $student->group_name ?: 'Taqsimlanmagan' }}</b></div>
            </div>
        </div>
    </div>
</x-student-app-layout>

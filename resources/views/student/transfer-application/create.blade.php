<x-student-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('student.services') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-blue-300 hover:text-blue-600" aria-label="Xizmatlarga qaytish">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h2 class="font-semibold text-sm text-gray-800 leading-tight">O'qishni ko'chirish uchun ariza</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Ariza ma'lumotlarini to'ldiring va buyruqni yuklang</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-3 pb-8 sm:px-6">
        @if(session('success'))
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mb-4 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-white p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-200">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5h16M6.5 4.5h11A1.5 1.5 0 0119 6v12a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 18V6a1.5 1.5 0 011.5-1.5zM8 11h8M8 14.5h5"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-600">Talaba</p>
                    <p class="truncate text-base font-bold text-slate-800">{{ $student->full_name }}</p>
                    <p class="text-xs text-slate-500">HEMIS ID: {{ $student->hemis_id }}</p>
                </div>
            </div>
            <div class="mt-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                <div><span class="font-semibold text-slate-800">Fakultet:</span> {{ $student->department_name ?: '—' }}</div>
                <div><span class="font-semibold text-slate-800">Yo'nalish:</span> {{ $student->specialty_name ?: '—' }}</div>
                <div><span class="font-semibold text-slate-800">Guruh:</span> {{ $student->group_name ?: '—' }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('student.transfer-application.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            @csrf
            <div class="grid gap-6">
                <div>
                    <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">Telefon raqami <span class="text-red-500">*</span></label>
                    <input id="phone" name="phone" type="tel" required value="{{ old('phone', $student->phone) }}" placeholder="+998 90 123 45 67" class="mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="reason" class="mb-2 block text-sm font-semibold text-slate-700">O'qishni ko'chirish sababi <span class="text-red-500">*</span></label>
                    <textarea id="reason" name="reason" rows="5" required minlength="10" maxlength="2000" placeholder="O'qishni ko'chirish sababini batafsil yozing..." class="mt-1 w-full rounded-xl border-slate-300 px-3 py-3 text-sm leading-6 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('reason') }}</textarea>
                    <p class="mt-2 text-xs text-slate-400">Sababni aniq va tushunarli yozing.</p>
                </div>

                <div>
                    <label for="order_document" class="mb-2 block text-sm font-semibold text-slate-700">O'qishni ko'chirish buyrug'i <span class="text-red-500">*</span></label>
                    <div class="mt-1 rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/40 p-4">
                        <input id="order_document" name="order_document" type="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
                        <p class="mt-3 text-xs text-slate-500">PDF, DOC, DOCX, JPG yoki PNG. Maksimal hajm: 10 MB.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse justify-end gap-2 border-t border-slate-100 pt-4 sm:flex-row">
                <a href="{{ route('student.services') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Bekor qilish</a>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M5 20h14"/></svg>
                    Arizani yuborish
                </button>
            </div>
        </form>

        @if($applications->isNotEmpty())
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-800">Yuborilgan arizalar</h3>
                <div class="space-y-2">
                    @foreach($applications as $application)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                            <div>
                                <p class="text-xs font-semibold text-slate-700">{{ $application->created_at?->format('d.m.Y H:i') }}</p>
                                <p class="text-xs text-slate-500">{{ Str::limit($application->reason, 100) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $application->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($application->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                    {{ $application->status === 'approved' ? 'Qabul qilindi' : ($application->status === 'rejected' ? 'Rad etildi' : "Ko'rib chiqilmoqda") }}
                                </span>
                                @if($application->order_path && $application->order_path !== 'pending')
                                    <a target="_blank" href="{{ route('student.transfer-application.document', $application->id) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Buyruqni ko'rish</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-student-app-layout>

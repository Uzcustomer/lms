<x-student-app-layout>

    <div class="mx-auto max-w-3xl px-3 pb-8 sm:px-6">
        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('error') }}
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

        @if(!$canSubmit && $latest)
            @if($latest instanceof \App\Models\AkademikMobillikAriza && $latest->status === 'rejected')
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 font-black text-red-700">!</div>
                    <div>
                        <p class="text-sm font-bold text-red-800">Ariza rad etilgan</p>
                        <p class="mt-1 text-sm leading-6 text-red-700">Arizangiz ko'rib chiqish jarayonida rad etildi.</p>
                        @foreach($latest->approvals->where('status', 'rejected') as $approval)
                            @if($approval->rejection_comment)
                                <div class="mt-3 rounded-xl border border-red-200 bg-white/70 px-3 py-2 text-sm text-red-800">
                                    <span class="font-bold">{{ $approval->role === 'oquv_bolimi' ? "O'quv bo'limi" : "O'quv prorektori" }} izohi:</span>
                                    {{ $approval->rejection_comment }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @else
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-emerald-800">Arizangiz yuborildi</p>
                        <p class="mt-1 text-sm leading-6 text-emerald-700">Hurmatli talaba, sizning arizangiz qabul qilindi. Arizangiz ko'rib chiqilgach, natija haqida sizga ma'lumot beriladi.</p>
                    </div>
                </div>
            </div>
            @endif
        @endif

        @if($canSubmit)
        <form method="POST" action="{{ route('student.transfer-application.store') }}" enctype="multipart/form-data" class="transfer-form rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            @csrf
            <div class="grid gap-4">
                <div class="transfer-field">
                    <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">Telefon raqami <span class="text-red-500">*</span></label>
                    <input id="phone" name="phone" type="tel" required value="{{ old('phone', $student->phone) }}" placeholder="+998 90 123 45 67" class="transfer-control mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="transfer-field">
                    <label for="target_institution" class="mb-2 block text-sm font-semibold text-slate-700">O'qishni ko'chirmoqchi bo'lgan ta'lim tashkiloti <span class="text-red-500">*</span></label>
                    <input id="target_institution" name="target_institution" type="text" required maxlength="255" value="{{ old('target_institution') }}" placeholder="Ta'lim tashkiloti nomini kiriting..." class="transfer-control mt-1 w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="transfer-field transfer-field-blue">
                    <label for="order_document" class="mb-2 block text-sm font-semibold text-slate-700">Transfer.edu.uz saytida o'qishni ko'chirish bo'yicha bergan arizangizni tasdiqlovchi hujjatni yuklang (skrinshot yoki boshqa turdagi hujjatlar) <span class="text-red-500">*</span></label>
                    <div class="transfer-dropzone transfer-dropzone-blue mt-1 rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/40 p-4">
                        <input id="order_document" name="order_document" type="file" required accept="*/*" class="transfer-file transfer-file-blue block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
                        <p class="mt-3 text-xs text-slate-500">Skrinshot yoki boshqa turdagi hujjatlarni yuklashingiz mumkin. Maksimal hajm: 10 MB.</p>
                    </div>
                </div>

                <div class="transfer-field">
                    <label for="reason" class="mb-2 block text-sm font-semibold text-slate-700">O'qishni ko'chirish sababi <span class="text-xs font-normal text-slate-400">(ixtiyoriy)</span></label>
                    <textarea id="reason" name="reason" rows="5" maxlength="2000" placeholder="O'qishni ko'chirish sababini batafsil yozing..." class="transfer-control transfer-textarea mt-1 w-full rounded-xl border-slate-300 px-3 py-3 text-sm leading-6 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('reason') }}</textarea>
                    <p class="mt-2 text-xs text-slate-400">Agar mavjud bo'lsa, sababni batafsil yozing.</p>
                </div>

                <div class="transfer-field transfer-field-green">
                    <label for="basis_document" class="mb-2 block text-sm font-semibold text-slate-700">O'qishni ko'chirish uchun asos hujjati <span class="text-red-500">*</span></label>
                    <div class="transfer-dropzone transfer-dropzone-green mt-1 rounded-2xl border-2 border-dashed border-emerald-200 bg-emerald-50/40 p-4">
                        <input id="basis_document" name="basis_document" type="file" required accept="*/*" class="transfer-file transfer-file-green block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700">
                        <p class="mt-3 text-xs text-slate-500">O'qishni ko'chirishga asos bo'ladigan hujjatni yuklang. Barcha fayl turlari qabul qilinadi, maksimal hajm: 10 MB.</p>
                    </div>
                </div>
            </div>

            <div class="transfer-actions mt-5 flex flex-col-reverse justify-end gap-2 border-t border-slate-200 pt-4 sm:flex-row">
                <a href="{{ route('student.services') }}" class="transfer-cancel inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Bekor qilish</a>
                <button type="submit" class="transfer-submit inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M5 20h14"/></svg>
                    Arizani yuborish
                </button>
            </div>
        </form>

        @endif

    </div>
        @if(session('success'))
            <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-300/65 px-4 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="transfer-success-title">
                <div @click.outside="open = false" class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-2xl">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 id="transfer-success-title" class="mt-4 text-lg font-bold text-slate-800">Ariza yuborildi</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ session('success') }}</p>
                    <button type="button" @click="open = false" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">Tushunarli</button>
                </div>
            </div>
        @endif

<style>
    .transfer-form {
        background: #f4f7fb !important;
        border-color: #d8e3f0 !important;
        box-shadow: 0 12px 28px rgba(30, 64, 110, .10) !important;
    }
    .transfer-field {
        padding: 15px;
        border: 1px solid #dce5ef;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 3px 10px rgba(30, 64, 110, .04);
    }
    .transfer-field-blue {
        border-color: #bcd8ff;
        background: linear-gradient(135deg, #f4f9ff 0%, #ffffff 75%);
    }
    .transfer-field-green {
        border-color: #b7e7d1;
        background: linear-gradient(135deg, #f1fbf6 0%, #ffffff 75%);
    }
    .transfer-field label {
        margin-bottom: 8px !important;
        color: #19375e !important;
        line-height: 1.45;
    }
    .transfer-control {
        border: 1px solid #b9c8da !important;
        background: #fbfdff !important;
        color: #172b4d !important;
        box-shadow: none !important;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }
    .transfer-control:focus {
        border-color: #2878d0 !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(40, 120, 208, .13) !important;
    }
    .transfer-textarea {
        min-height: 126px;
        resize: vertical;
    }
    .transfer-dropzone {
        padding: 12px !important;
        border-radius: 12px !important;
    }
    .transfer-dropzone-blue {
        border-color: #9fc8ff !important;
        background: rgba(235, 245, 255, .72) !important;
    }
    .transfer-dropzone-green {
        border-color: #9edbbf !important;
        background: rgba(235, 250, 242, .78) !important;
    }
    .transfer-file {
        padding: 5px !important;
        border: 1px solid #c7d5e5 !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: #51657f !important;
    }
    .transfer-file::file-selector-button {
        margin-right: 12px;
        padding: 9px 14px;
        border: 0;
        border-radius: 8px;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }
    .transfer-file-blue::file-selector-button { background: #2368b5; }
    .transfer-file-green::file-selector-button { background: #16845d; }
    .transfer-actions {
        margin-top: 16px !important;
    }
    .transfer-cancel {
        background: #fff !important;
        border-color: #cbd7e5 !important;
    }
    .transfer-submit {
        background: linear-gradient(135deg, #1769bb, #246ee9) !important;
        box-shadow: 0 7px 16px rgba(36, 110, 233, .24) !important;
    }
    @media (max-width: 640px) {
        .transfer-form { padding: 12px !important; }
        .transfer-field { padding: 13px; }
        .transfer-file::file-selector-button { padding: 8px 11px; }
    }
</style>

</x-student-app-layout>

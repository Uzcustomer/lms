<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Guruhni o'zgartirish uchun ariza</h2>
    </x-slot>

    <style>
        .gca-page{max-width:980px;margin:0 auto;padding:22px 14px 40px}.gca-hero{position:relative;overflow:hidden;padding:24px;border-radius:22px;color:#fff;background:linear-gradient(125deg,#0f3d66,#0369a1 58%,#0d9488);box-shadow:0 18px 45px rgba(3,105,161,.2)}.gca-hero:after{content:"";position:absolute;right:-55px;top:-80px;width:230px;height:230px;border-radius:50%;background:rgba(255,255,255,.1)}.gca-kicker{font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#bae6fd}.gca-hero h1{position:relative;z-index:1;margin:5px 0;font-size:24px;font-weight:900}.gca-hero p{position:relative;z-index:1;max-width:620px;margin:0;color:#e0f2fe;font-size:12px;line-height:1.6}.gca-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}.gca-card{border:1px solid #dbe5ef;border-radius:17px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.05)}.gca-info{padding:16px}.gca-label{display:block;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.gca-value{display:block;margin-top:4px;color:#1e3a5f;font-size:13px;font-weight:800}.gca-form{margin-top:16px;padding:20px}.gca-section-title{margin:0;color:#0f2942;font-size:16px;font-weight:900}.gca-section-copy{margin:4px 0 16px;color:#64748b;font-size:11px}.gca-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.gca-option{position:relative;display:block;cursor:pointer}.gca-option input{position:absolute;opacity:0}.gca-option-body{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border:1px solid #dbe5ef;border-radius:13px;background:#f8fbff;transition:.16s}.gca-option input:checked+.gca-option-body{border-color:#0284c7;background:#ecfeff;box-shadow:0 0 0 3px rgba(14,165,233,.12)}.gca-option b{display:block;color:#123766;font-size:13px}.gca-option span{display:block;margin-top:3px;color:#64748b;font-size:10px}.gca-free{flex:0 0 auto;padding:5px 8px;border-radius:999px;background:#dcfce7;color:#047857;font-size:10px;font-weight:900}.gca-textarea{width:100%;min-height:125px;margin-top:7px;resize:vertical;border:1px solid #cbd5e1;border-radius:13px;padding:12px 14px;color:#334155;font-size:12px;outline:none}.gca-textarea:focus{border-color:#0284c7;box-shadow:0 0 0 3px rgba(14,165,233,.12)}.gca-submit{display:inline-flex;align-items:center;justify-content:center;height:42px;margin-top:14px;padding:0 22px;border:0;border-radius:11px;background:#0284c7;color:#fff;font-size:12px;font-weight:900;cursor:pointer}.gca-submit:hover{background:#0369a1}.gca-alert{margin-top:14px;padding:13px 15px;border-radius:12px;font-size:12px}.gca-success{border:1px solid #a7f3d0;background:#ecfdf5;color:#047857}.gca-error{border:1px solid #fecaca;background:#fef2f2;color:#b91c1c}.gca-notice{margin-top:16px;padding:18px;border:1px solid #bae6fd;border-radius:16px;background:#f0f9ff;color:#075985}.gca-notice b{display:block;font-size:14px}.gca-notice span{display:block;margin-top:5px;font-size:11px;line-height:1.55}.gca-history{margin-top:16px;overflow:hidden}.gca-history-head{padding:15px 18px;border-bottom:1px solid #e2e8f0}.gca-history-head h2{margin:0;font-size:14px;font-weight:900}.gca-app{padding:15px 18px;border-bottom:1px solid #edf2f7}.gca-app:last-child{border-bottom:0}.gca-app-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.gca-route{color:#123766;font-size:12px;font-weight:900}.gca-status{padding:5px 9px;border-radius:999px;font-size:10px;font-weight:900}.gca-status-pending{background:#fef3c7;color:#92400e}.gca-status-approved{background:#dcfce7;color:#166534}.gca-status-rejected{background:#fee2e2;color:#991b1b}.gca-reason{margin:8px 0 0;color:#64748b;font-size:11px;line-height:1.55}.gca-date{margin-top:6px;color:#94a3b8;font-size:9px}@media(max-width:640px){.gca-grid,.gca-options{grid-template-columns:1fr}.gca-hero{padding:20px}.gca-form{padding:16px}.gca-submit{width:100%}}
        .gca-option .gca-option-body{display:flex;margin-top:0;color:inherit}.gca-option .gca-free{display:block;margin-top:0;color:#047857}
    </style>

    <div class="gca-page">
        <section class="gca-hero">
            <div class="gca-kicker">Registrator ofisi xizmati</div>
            <h1>Guruhni o'zgartirish uchun ariza</h1>
            <p>Bo'sh joy mavjud guruhlardan birini tanlang va o'tish sababini aniq yozing. Ariza registrator ofisiga ko'rib chiqish uchun yuboriladi.</p>
        </section>

        @if(session('success'))
            <div class="gca-alert gca-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="gca-alert gca-error">{{ $errors->first() }}</div>
        @endif

        <div class="gca-grid">
            <div class="gca-card gca-info">
                <span class="gca-label">Talaba</span>
                <b class="gca-value">{{ $student->full_name }}</b>
                <span class="gca-label" style="margin-top:12px">Talaba ID</span>
                <b class="gca-value">{{ $student->student_id_number ?: '-' }}</b>
            </div>
            <div class="gca-card gca-info">
                <span class="gca-label">Amaldagi guruh</span>
                <b class="gca-value">{{ $sourceGroup?->group_name ?: ($student->group_name ?: 'Taqsimlanmagan') }}</b>
                <span class="gca-label" style="margin-top:12px">Yo'nalish va kurs</span>
                <b class="gca-value">{{ $student->specialty_name ?: '-' }} / {{ $sourceGroup?->course ? $sourceGroup->course.'-kurs' : ($student->level_name ?: '-') }}</b>
            </div>
        </div>

        @if($canSubmit)
            <form class="gca-card gca-form" method="POST" action="{{ route('student.group-change-application.store') }}">
                @csrf
                <h2 class="gca-section-title">O'tmoqchi bo'lgan guruhni tanlang</h2>
                <p class="gca-section-copy">Faqat sizning fakultet, yo'nalish va kursingizga mos bo'sh joyli guruhlar ko'rsatilgan.</p>
                <div class="gca-options">
                    @foreach($availableGroups as $group)
                        <label class="gca-option">
                            <input type="radio" name="target_group_id" value="{{ $group->id }}" @checked((int) old('target_group_id') === $group->id) required>
                            <span class="gca-option-body">
                                <span><b>{{ $group->group_name }}</b><span>Sig'im: {{ $group->capacity }} / band: {{ $group->occupied_count }}</span></span>
                                <span class="gca-free">{{ $group->free_places }} ta bo'sh</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <label class="gca-label" for="reason" style="margin-top:18px">O'tish sababi</label>
                <textarea class="gca-textarea" id="reason" name="reason" maxlength="2000" required placeholder="Nima sababdan boshqa guruhga o'tmoqchi ekaningizni yozing...">{{ old('reason') }}</textarea>
                <button class="gca-submit" type="submit">Arizani yuborish</button>
            </form>
        @elseif($applications->contains('status', 'pending'))
            <div class="gca-notice"><b>Arizangiz qabul qilingan</b><span>Hozir arizangiz registrator ofisi tomonidan ko'rib chiqilmoqda. Natija chiqquncha yangi ariza yuborib bo'lmaydi.</span></div>
        @elseif(!$sourceGroup)
            <div class="gca-notice"><b>Guruh ma'lumoti topilmadi</b><span>Amaldagi guruhingiz taqsimlanadigan guruhlar ro'yxatiga kiritilmagan. Registrator ofisiga murojaat qiling.</span></div>
        @else
            <div class="gca-notice"><b>Bo'sh joyli guruh topilmadi</b><span>Sizning fakultet, yo'nalish va kursingiz uchun hozircha mos bo'sh joy mavjud emas.</span></div>
        @endif

        @if($applications->isNotEmpty())
            <section class="gca-card gca-history">
                <div class="gca-history-head"><h2>Arizalarim</h2></div>
                @foreach($applications as $application)
                    @php
                        $statusLabel = match($application->status) {
                            'approved' => 'Qabul qilingan',
                            'rejected' => 'Rad etilgan',
                            default => "Ko'rib chiqilmoqda",
                        };
                    @endphp
                    <article class="gca-app">
                        <div class="gca-app-top">
                            <span class="gca-route">{{ $application->source_group_name }} &rarr; {{ $application->target_group_name }}</span>
                            <span class="gca-status gca-status-{{ $application->status }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="gca-reason">{{ $application->reason }}</p>
                        <div class="gca-date">{{ optional($application->created_at)->format('d.m.Y H:i') }}</div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</x-student-app-layout>

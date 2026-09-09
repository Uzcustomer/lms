{{--
    Guruh tanlash popupi — ikki ko'rinishda.

    OVOZ: talabaning guruhi (yoki o'zi) uchun ovoz berish ochilgan, u hali
    ovoz bermagan va tanlash uchun mos guruh bor. Yopib bo'lmaydi.

    XABAR: o'sha fakultet + yo'nalish + kursda ovoz berish ketyapti, lekin bu
    guruhga ochilmagan. Talaba "bizniki nega chiqmadi" deb sarosimaga
    tushmasligi uchun guruhi o'zgarmasligi aytiladi. Kichik, bir marta
    ko'rsatiladi va istalgan joyga bosilsa yopiladi.
--}}
@php
    $gvStudent = auth()->guard('student')->user();
    $gvTargets = collect();
    $gvMode = null;   // 'moved' | 'vote' | 'notice'
    $gvDraft = null;

    $gvHasTables = \Illuminate\Support\Facades\Schema::hasTable('distribution_voting_groups')
        && \Illuminate\Support\Facades\Schema::hasTable('distribution_votes');

    // Guruhi o'zgargan talabaga yangi guruhi aytiladi — bu boshqa hamma
    // holatdan ustun: tanlov tugagan, faqat xabar qolgan.
    if ($gvStudent && \Illuminate\Support\Facades\Schema::hasTable('distribution_draft_assignments')) {
        $gvDraft = \App\Models\DistributionDraftAssignment::query()
            ->where('student_id', $gvStudent->id)
            ->first();

        // Har login uchun bir marta: popup yopilganda sessiyaga belgi qo'yiladi
        // va shu sessiya davomida qayta chiqmaydi. Talaba chiqib qayta kirsa
        // (yangi sessiya) yana ko'rsatiladi.
        //
        // Belgida guruh IDsi ham bor: sessiya davomida registrator talabani
        // boshqa guruhga ko'chirsa, yangi guruh haqida popup baribir chiqadi.
        if ($gvDraft && session('gv_moved_seen') !== (int) $gvDraft->to_group_hemis_id) {
            $gvMode = 'moved';
        }
    }

    if ($gvMode === null && $gvStudent && $gvStudent->group_id && $gvHasTables) {
        // Ruxsat guruh bo'yicha yoki shu talabaga alohida berilgan bo'lishi mumkin.
        $gvAllowed = \App\Models\DistributionVotingGroup::query()->where('group_hemis_id', (int) $gvStudent->group_id)->exists()
            || (\Illuminate\Support\Facades\Schema::hasTable('distribution_voting_students')
                && \App\Models\DistributionVotingStudent::query()->where('student_id', $gvStudent->id)->exists());

        // Ovoz bergan yoki registrator qo'lda boshqa guruhga ko'chirgan talaba
        // uchun tanlov tugagan — popup ikkalasida ham chiqmaydi.
        $gvVoted = \App\Models\DistributionVote::query()->where('student_id', $gvStudent->id)->exists()
            || $gvDraft !== null;

        if ($gvAllowed && !$gvVoted) {
            $gvTargets = app(\App\Services\DistributionCatalog::class)->targetsFor((int) $gvStudent->group_id);
            if ($gvTargets->isNotEmpty()) {
                $gvMode = 'vote';
            }
        } elseif (!$gvAllowed && !$gvVoted) {
            // Ovoz ochilgan guruhlar: guruh bo'yicha ochilganlari va alohida
            // talabalarga ochilganlarning guruhlari. Katalog og'ir so'rov, shu
            // sabab avval shu arzon ro'yxat olinadi va bo'sh bo'lsa to'xtaladi.
            $gvOpenIds = \App\Models\DistributionVotingGroup::query()->pluck('group_hemis_id');
            if (\Illuminate\Support\Facades\Schema::hasTable('distribution_voting_students')) {
                $gvOpenIds = $gvOpenIds->merge(
                    \App\Models\DistributionVotingStudent::query()->pluck('group_hemis_id')
                );
            }
            $gvOpenIds = $gvOpenIds->filter()->map(fn ($id) => (int) $id)->unique()->flip();

            if ($gvOpenIds->isNotEmpty()) {
                // Xabar faqat qo'shnilarida ovoz ketayotgan bo'lsa chiqadi: shu
                // fakultet (1-son/2-son bitta) + yo'nalish + kursdagi boshqa
                // guruhga ruxsat berilganmi.
                $gvCatalog = app(\App\Services\DistributionCatalog::class)->groups();
                $gvOwn = $gvCatalog->firstWhere('group_hemis_id', (int) $gvStudent->group_id);

                if ($gvOwn) {
                    $gvCatalogService = app(\App\Services\DistributionCatalog::class);
                    $gvMode = $gvCatalog->contains(fn ($group) => $gvOpenIds->has($group['group_hemis_id'])
                        && $gvCatalogService->facultyGroupKey($group) === $gvCatalogService->facultyGroupKey($gvOwn)
                        && $group['specialty_name'] === $gvOwn['specialty_name']
                        && $group['course'] === $gvOwn['course']) ? 'notice' : null;
                }
            }
        }
    }
@endphp

@if($gvMode === 'moved')
<div id="gvMoved" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,39,72,.55);">
    <div style="width:min(420px,100%);overflow:hidden;border-radius:14px;background:#fff;box-shadow:0 24px 64px rgba(15,39,72,.35);font-family:inherit;">
        <div style="padding:18px 20px;background:#0f2748;color:#fff;">
            <h3 style="margin:0;font-size:16px;font-weight:800;">Guruhingiz o'zgardi</h3>
            <p style="margin:6px 0 0;color:rgba(255,255,255,.75);font-size:12px;">Yangi guruhingiz haqida ma'lumot</p>
        </div>

        <div style="padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="flex:1;min-width:0;text-align:center;padding:11px 8px;border:1px solid #e3e9f2;border-radius:10px;background:#f7f9fc;">
                    <div style="color:#8798b1;font-size:10.5px;font-weight:600;">Avvalgi</div>
                    <div style="margin-top:3px;color:#6b7c95;font-size:13px;font-weight:700;">{{ $gvDraft->from_group_name ?: '—' }}</div>
                </div>
                <div style="flex:none;color:#0f7a52;font-size:20px;font-weight:800;">&rarr;</div>
                <div style="flex:1;min-width:0;text-align:center;padding:11px 8px;border:1px solid #a7e0c4;border-radius:10px;background:#e9f7f0;">
                    <div style="color:#0f7a52;font-size:10.5px;font-weight:600;">Yangi</div>
                    <div style="margin-top:3px;color:#0b5c3e;font-size:14px;font-weight:800;">{{ $gvDraft->to_group_name }}</div>
                </div>
            </div>

            <p style="margin:15px 0 0;color:#41506b;font-size:12.5px;line-height:1.65;">
                Endi dars jadvali, davomat va baholaringiz
                <b style="color:#17233a;">{{ $gvDraft->to_group_name }}</b> guruhi bo'yicha yuritiladi.
                Savol bo'lsa registrator ofisiga murojaat qiling.
            </p>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-top:1px solid #eef2f8;background:#fbfcfe;">
            <span style="color:#8798b1;font-size:11px;">Yopish uchun istalgan joyga bosing</span>
            <span id="gvMovedTimer" style="color:#8798b1;font-size:11px;font-weight:700;">15</span>
        </div>
    </div>
</div>

<script>
(() => {
    const box = document.getElementById('gvMoved');
    const timerEl = document.getElementById('gvMovedTimer');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let left = 15;
    let closed = false;

    // Yopilgani serverga bildiriladi: sessiyaga belgi qo'yiladi va popup shu
    // login davomida qayta chiqmaydi. Talaba chiqib qayta kirsa yana chiqadi.
    //
    // sendBeacon — sahifa yopilib qolsa ham so'rov yetib boradi; u yo'q
    // brauzerlarda oddiy fetch ishlatiladi. So'rov yetmasa popup keyingi
    // sahifada qayta chiqadi, boshqa zarari yo'q.
    function close() {
        if (closed) return;
        closed = true;
        clearInterval(tick);
        box.remove();

        const url = @json(route('student.group-change.seen'));
        if (navigator.sendBeacon) {
            const form = new FormData();
            form.append('_token', csrf);
            navigator.sendBeacon(url, form);
        } else {
            fetch(url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                keepalive: true,
            }).catch(() => {});
        }
    }

    const tick = setInterval(() => {
        left--;
        if (timerEl) timerEl.textContent = left;
        if (left <= 0) close();
    }, 1000);

    box.addEventListener('click', close);
})();
</script>
@endif

@if($gvMode === 'vote')
<div id="gvBackdrop" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,39,72,.6);">
    <div style="display:flex;flex-direction:column;width:min(480px,100%);max-height:calc(100vh - 40px);overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 24px 64px rgba(15,39,72,.35);font-family:inherit;">
        <div style="padding:18px 20px;background:#0f2748;color:#fff;">
            <h3 style="margin:0;font-size:16px;font-weight:800;">Guruh tanlash</h3>
            <p style="margin:7px 0 0;color:rgba(255,255,255,.75);font-size:12.5px;line-height:1.6;">
                Sizning guruhingiz joriy o'quv yili yopilishi sababli quyidagi guruhlardan
                biriga o'tish bo'yicha o'z istagingizni bildiring. Ovoz faqat <b style="color:#fff;">bir marta</b>
                beriladi va siz o'sha guruh talabasi bo'lasiz.
            </p>
        </div>

        <div id="gvList" style="overflow-y:auto;padding:10px 14px;">
            @foreach($gvTargets as $gvTarget)
                <label style="display:flex;align-items:center;gap:11px;padding:11px 12px;margin-bottom:7px;border:1px solid #d8e2ef;border-radius:9px;cursor:pointer;">
                    <input type="radio" name="gv_target" value="{{ $gvTarget['group_hemis_id'] }}" style="width:16px;height:16px;accent-color:#0f2748;">
                    <span style="flex:1;min-width:0;">
                        <b style="display:block;color:#17233a;font-size:13.5px;">{{ $gvTarget['group_name'] }}</b>
                        <span style="display:block;margin-top:2px;color:#8798b1;font-size:11px;">
                            {{ $gvTarget['specialty_name'] }}@if($gvTarget['course']) · {{ $gvTarget['course'] }}-kurs @endif @if($gvTarget['language_name']) · {{ $gvTarget['language_name'] }} @endif
                        </span>
                    </span>
                    <span style="padding:3px 9px;border-radius:999px;background:#e9f7f0;color:#0f7a52;font-size:11px;font-weight:700;white-space:nowrap;">{{ $gvTarget['free_places'] }} bo'sh</span>
                </label>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 16px;border-top:1px solid #eef2f8;background:#fbfcfe;">
            <span style="color:#b3261e;font-size:11.5px;font-weight:600;">Ovoz bermasdan tizimdan foydalanib bo'lmaydi</span>
            <button type="button" id="gvSubmit" disabled style="height:40px;padding:0 20px;border:0;border-radius:7px;background:#0f2748;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;opacity:.5;">Ovoz berish</button>
        </div>
    </div>
</div>

<script>
(() => {
    const backdrop = document.getElementById('gvBackdrop');
    const submit = document.getElementById('gvSubmit');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Popup majburiy: yopib bo'lmaydi, orqadagi sahifa qulflanadi.
    document.body.style.overflow = 'hidden';

    document.getElementById('gvList').addEventListener('change', () => {
        submit.disabled = false;
        submit.style.opacity = '1';
    });

    submit.addEventListener('click', async () => {
        const chosen = document.querySelector('input[name="gv_target"]:checked');
        if (!chosen) return;
        if (!confirm("Tanlovingizni tasdiqlaysizmi? Ovoz faqat bir marta beriladi.")) return;

        submit.disabled = true;
        try {
            const response = await fetch(@json(route('student.group-vote.store')), {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body: JSON.stringify({to_group_hemis_id: Number(chosen.value)}),
            });
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                throw new Error("Server kutilmagan javob qaytardi. Sahifani yangilab (F5) qayta urinib ko'ring.");
            }
            if (!response.ok) throw new Error(data.message || "Ovoz berib bo'lmadi.");
            alert(data.message);
            document.body.style.overflow = '';
            backdrop.remove();
        } catch (error) {
            alert(error.message);
            submit.disabled = false;
        }
    });
})();
</script>
@endif

@if($gvMode === 'notice')
<div id="gvNotice" style="position:fixed;inset:0;z-index:9998;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,39,72,.45);">
    <div style="width:min(380px,100%);overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 20px 52px rgba(15,39,72,.3);font-family:inherit;">
        <div style="display:flex;align-items:center;gap:11px;padding:15px 18px;background:#0f7a52;color:#fff;">
            <span style="font-size:19px;line-height:1;">&#10003;</span>
            <h3 style="margin:0;font-size:14.5px;font-weight:800;">Sizning guruhingiz o'zgarmaydi</h3>
        </div>
        <div style="padding:15px 18px;color:#41506b;font-size:12.5px;line-height:1.65;">
            Hozir ba'zi guruhlarda talabalarni taqsimlash ketmoqda, ammo
            <b style="color:#17233a;">{{ $gvStudent->group_name }}</b> guruhi bunga kirmaydi.
            Siz o'z guruhingizda qolasiz va <b style="color:#17233a;">ovoz berishingiz shart emas</b>.
        </div>
        <div style="padding:0 18px 15px;color:#8798b1;font-size:11.5px;">
            Yopish uchun istalgan joyga bosing.
        </div>
    </div>
</div>

<script>
(() => {
    // Xabar bir marta ko'rsatiladi: yopilgach shu brauzerda qayta chiqmaydi.
    // Saqlash imkoni bo'lmasa (maxfiy oyna) — har safar chiqaveradi, bu xato emas.
    const KEY = 'gvNoticeSeen:{{ $gvStudent->id }}:{{ (int) $gvStudent->group_id }}';
    const notice = document.getElementById('gvNotice');

    try {
        if (localStorage.getItem(KEY)) {
            notice.remove();
            return;
        }
    } catch (e) { /* saqlash yopiq — xabar baribir ko'rsatiladi */ }

    notice.addEventListener('click', () => {
        try { localStorage.setItem(KEY, '1'); } catch (e) { /* e'tiborsiz */ }
        notice.remove();
    });
})();
</script>
@endif

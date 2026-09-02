{{--
    Guruh tanlash ovozi popupi.

    Faqat quyidagi holatda ko'rinadi: jadval mavjud, talabaning guruhi uchun
    ovoz berish ochilgan, talaba hali ovoz bermagan va tanlash uchun kamida
    bitta mos guruh bor. Aks holda hech narsa render qilinmaydi.
--}}
@php
    $gvStudent = auth()->guard('student')->user();
    $gvTargets = collect();
    $gvShow = false;

    if ($gvStudent && $gvStudent->group_id
        && \Illuminate\Support\Facades\Schema::hasTable('distribution_voting_groups')
        && \Illuminate\Support\Facades\Schema::hasTable('distribution_votes')
        && \App\Models\DistributionVotingGroup::query()->where('group_hemis_id', (int) $gvStudent->group_id)->exists()
        && !\App\Models\DistributionVote::query()->where('student_id', $gvStudent->id)->exists()) {
        $gvTargets = app(\App\Services\DistributionCatalog::class)->targetsFor((int) $gvStudent->group_id);
        $gvShow = $gvTargets->isNotEmpty();
    }
@endphp

@if($gvShow)
<div id="gvBackdrop" style="position:fixed;inset:0;z-index:95;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,39,72,.6);">
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
            <button type="button" id="gvLater" style="border:0;background:transparent;color:#8798b1;font-size:12.5px;cursor:pointer;">Keyinroq</button>
            <button type="button" id="gvSubmit" disabled style="height:40px;padding:0 20px;border:0;border-radius:7px;background:#0f2748;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;opacity:.5;">Ovoz berish</button>
        </div>
    </div>
</div>

<script>
(() => {
    const backdrop = document.getElementById('gvBackdrop');
    const submit = document.getElementById('gvSubmit');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.getElementById('gvLater').addEventListener('click', () => backdrop.remove());

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
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || "Ovoz berib bo'lmadi.");
            alert(data.message);
            backdrop.remove();
        } catch (error) {
            alert(error.message);
            submit.disabled = false;
        }
    });
})();
</script>
@endif

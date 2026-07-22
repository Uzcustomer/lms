@php
    $d = $admissionData;
    $show = function ($value) {
        if (is_bool($value)) {
            return $value ? 'Ha' : 'Yo`q';
        }
        if (is_array($value)) {
            return count($value) ? implode(', ', $value) : '—';
        }
        return filled($value) ? $value : '—';
    };
    $decode = function ($value) {
        if (!is_string($value)) {
            return $value;
        }
        $decoded = $value;
        for ($i = 0; $i < 3; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }
        return $decoded;
    };
@endphp

<div class="qabul-form">
    <div class="qabul-card" style="--accent:#1d4ed8;">
        <div class="qabul-card-header">
            <span class="qabul-dot"></span>
            <h4 class="qabul-card-title">Qabul ma`lumotlari</h4>
        </div>
        <div class="qabul-card-body">
            <p style="font-size:13px; color:#64748b; margin:0 0 14px;">1-kurs talaba uchun ma`lumotlar jadval ko`rinishida chiqarildi. Tahrirlash kerak bo`lsa keyin alohida qo`shamiz.</p>
            <style>
                .qsum-steps { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
                .qsum-step { display:flex; align-items:center; gap:8px; cursor:pointer; color:#64748b; font-size:13px; font-weight:700; }
                .qsum-step-index { width:34px; height:34px; border-radius:999px; display:grid; place-items:center; background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe; }
                .qsum-step-active { color:#0f172a; }
                .qsum-step-active .qsum-step-index { background:#2563eb; color:#fff; border-color:#2563eb; }
                .qsum-divider { flex:1 1 48px; min-width:36px; height:2px; background:#dbe4ef; border-radius:999px; }
                .qsum-panel { display:none; }
                .qsum-panel.qsum-panel-active { display:block; }
            </style>

            <div class="qsum-steps">
                <div class="qsum-step qsum-step-active" data-qsum-step="1"><span class="qsum-step-index">1</span><span>Shaxsiy</span></div>
                <div class="qsum-divider"></div>
                <div class="qsum-step" data-qsum-step="2"><span class="qsum-step-index">2</span><span>Ta'lim</span></div>
                <div class="qsum-divider"></div>
                <div class="qsum-step" data-qsum-step="3"><span class="qsum-step-index">3</span><span>DTM</span></div>
                <div class="qsum-divider"></div>
                <div class="qsum-step" data-qsum-step="4"><span class="qsum-step-index">4</span><span>Ota-ona</span></div>
                <div class="qsum-divider"></div>
                <div class="qsum-step" data-qsum-step="5"><span class="qsum-step-index">5</span><span>Ijtimoiy</span></div>
            </div>

            <div class="qsum-panel qsum-panel-active" data-qsum-panel="1">
                <div class="sp-card">
                    <h4 class="sp-title">Shaxsiy</h4>
                    <table class="sp-table">
                        <tr><td>Familiya</td><td>{{ $show($decode($d->familya)) }}</td></tr>
                        <tr><td>Ism</td><td>{{ $show($decode($d->ism)) }}</td></tr>
                        <tr><td>Sharif</td><td>{{ $show($decode($d->otasining_ismi)) }}</td></tr>
                        <tr><td>Tug'ilgan sana</td><td>{{ $d->tugilgan_sana?->format('d.m.Y') ?? '—' }}</td></tr>
                        <tr><td>JSHSHIR</td><td>{{ $show($d->jshshir) }}</td></tr>
                        <tr><td>Jinsi</td><td>{{ $show($d->jinsi) }}</td></tr>
                        <tr><td>Telefon</td><td>{{ $show($d->tel1) }}</td></tr>
                        <tr><td>Qo'shimcha telefon</td><td>{{ $show($d->tel2) }}</td></tr>
                        <tr><td>Email</td><td>{{ $show($d->email) }}</td></tr>
                        <tr><td>Millat</td><td>{{ $show($d->millat) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="qsum-panel" data-qsum-panel="2">
                <div class="sp-card">
                    <h4 class="sp-title">Ta'lim</h4>
                    <table class="sp-table">
                        <tr><td>OTM nomi</td><td>{{ $show($decode($d->otm_nomi)) }}</td></tr>
                        <tr><td>Ta'lim turi</td><td>{{ $show($d->talim_turi) }}</td></tr>
                        <tr><td>Ta'lim shakli</td><td>{{ $show($d->talim_shakli) }}</td></tr>
                        <tr><td>Mutaxassislik</td><td>{{ $show($decode($d->mutaxassislik)) }}</td></tr>
                        <tr><td>Ta'lim davlati</td><td>{{ $show($d->talim_davlat) }}</td></tr>
                        <tr><td>Ta'lim viloyati</td><td>{{ $show($d->talim_viloyat) }}</td></tr>
                        <tr><td>Ta'lim tumani</td><td>{{ $show($d->talim_tuman) }}</td></tr>
                        <tr><td>Muassasa turi</td><td>{{ $show($d->muassasa_turi) }}</td></tr>
                        <tr><td>Muassasa nomi</td><td>{{ $show($decode($d->muassasa_nomi)) }}</td></tr>
                        <tr><td>O'qigan yili</td><td>{{ $show($d->oqigan_yili_boshi) }} — {{ $show($d->oqigan_yili_tugashi) }}</td></tr>
                        <tr><td>Hujjat</td><td>{{ $show($d->hujjat_seriya) }}</td></tr>
                        <tr><td>O'rtacha ball</td><td>{{ $show($d->ortalacha_ball) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="qsum-panel" data-qsum-panel="3">
                <div class="sp-card">
                    <h4 class="sp-title">DTM</h4>
                    <table class="sp-table">
                        <tr><td>Abituriyent ID</td><td>{{ $show($d->abituriyent_id) }}</td></tr>
                        <tr><td>Javoblar varaqasi</td><td>{{ $show($d->javoblar_varaqasi) }}</td></tr>
                        <tr><td>Ta'lim tili</td><td>{{ $show($decode($d->talim_tili)) }}</td></tr>
                        <tr><td>Imtihon alifbosi</td><td>{{ $show($d->imtihon_alifbosi) }}</td></tr>
                        <tr><td>To'plagan ball</td><td>{{ $show($d->toplagan_ball) }}</td></tr>
                        <tr><td>Tavsiya turi</td><td>{{ $show($decode($d->tolov_shakli)) }}</td></tr>
                        <tr><td>Sertifikat turi</td><td>{{ $show($d->sertifikat_turi) }}</td></tr>
                        <tr><td>Sertifikat ball</td><td>{{ $show($d->sertifikat_ball) }}</td></tr>
                        <tr><td>Chet tillari</td><td>{{ $show($d->chet_tillari) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="qsum-panel" data-qsum-panel="4">
                <div class="sp-card">
                    <h4 class="sp-title">Ota-ona</h4>
                    <table class="sp-table">
                        <tr><td>Ota F.I.SH.</td><td>{{ $show(trim(($decode($d->ota_familiya) ?? '').' '.($decode($d->ota_ismi) ?? '').' '.($decode($d->ota_sharifi) ?? ''))) }}</td></tr>
                        <tr><td>Ota telefon</td><td>{{ $show($d->ota_tel) }}</td></tr>
                        <tr><td>Ota ish joyi</td><td>{{ $show($decode($d->ota_ish_joyi)) }}</td></tr>
                        <tr><td>Ota lavozimi</td><td>{{ $show($decode($d->ota_lavozimi)) }}</td></tr>
                        <tr><td>Ona F.I.SH.</td><td>{{ $show(trim(($decode($d->ona_familiya) ?? '').' '.($decode($d->ona_ismi) ?? '').' '.($decode($d->ona_sharifi) ?? ''))) }}</td></tr>
                        <tr><td>Ona telefon</td><td>{{ $show($d->ona_tel) }}</td></tr>
                        <tr><td>Ona ish joyi</td><td>{{ $show($decode($d->ona_ish_joyi)) }}</td></tr>
                        <tr><td>Ona lavozimi</td><td>{{ $show($decode($d->ona_lavozimi)) }}</td></tr>
                        <tr><td>Doimiy manzil</td><td>{{ $show($decode($d->doimiy_manzil)) }}</td></tr>
                        <tr><td>Kenglik</td><td>{{ $show($d->kenglik) }}</td></tr>
                        <tr><td>Uzunlik</td><td>{{ $show($d->uzunlik) }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="qsum-panel" data-qsum-panel="5">
                <div class="sp-card">
                    <h4 class="sp-title">Ijtimoiy</h4>
                    <table class="sp-table">
                        <tr><td>Yetim talaba</td><td>{{ $show((bool) $d->yetim_talaba) }}</td></tr>
                        <tr><td>Yetim turi</td><td>{{ $show($d->yetim_turi) }}</td></tr>
                        <tr><td>Nogironligi</td><td>{{ $show((bool) $d->nogironligi) }}</td></tr>
                        <tr><td>Nogiron guruhi</td><td>{{ $show($d->nogiron_guruh) }}</td></tr>
                        <tr><td>Nogiron toifa</td><td>{{ $show($d->nogiron_toifa) }}</td></tr>
                        <tr><td>Kam ta'minlangan</td><td>{{ $show((bool) $d->kam_taminlangan) }}</td></tr>
                        <tr><td>Harbiy qaytgan</td><td>{{ $show((bool) $d->harbiy_qaytgan) }}</td></tr>
                        <tr><td>Nafaqa oluvchi</td><td>{{ $show((bool) $d->nafaqa_oluvchi) }}</td></tr>
                        <tr><td>Iqtidori</td><td>{{ $show($d->iqtidori) }}</td></tr>
                        <tr><td>Sport qobiliyati</td><td>{{ $show($d->sport_qobiliyat) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const steps = document.querySelectorAll('.qsum-step');
        const panels = document.querySelectorAll('.qsum-panel');
        if (!steps.length || !panels.length) return;

        function activate(step) {
            steps.forEach((item) => item.classList.toggle('qsum-step-active', item.dataset.qsumStep === step));
            panels.forEach((panel) => panel.classList.toggle('qsum-panel-active', panel.dataset.qsumPanel === step));
        }

        steps.forEach((item) => {
            item.addEventListener('click', () => activate(item.dataset.qsumStep));
        });
    })();
</script>

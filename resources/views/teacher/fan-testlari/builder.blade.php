<x-app-layout>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=roboto:300,400,500,700|roboto-slab:400,600,700&display=swap" rel="stylesheet">
<style>
    .bl {
        --navy: #0f2748; --navy-soft: #1b3a63; --gold: #c9a227;
        --ink: #17233a; --ink-soft: #4d6180; --muted: #8798b1;
        --line: #dde5ef; --line-soft: #eef2f8;
        --ok: #0f7a52; --ok-bg: #e9f7f0; --bad: #b3261e; --bad-bg: #fdeceb;
        --warn: #a35a06; --warn-bg: #fdf3e4;
        font-family: 'Roboto', system-ui, sans-serif; color: var(--ink);
    }
    .bl h1, .bl h2, .bl .slab { font-family: 'Roboto Slab', Georgia, serif; }
    .bl [x-cloak] { display: none !important; }

    /* ---- Sarlavha ---- */
    .bl-head {
        display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 14px;
        padding: 20px 24px;
        border: 1px solid var(--line); border-left: 3px solid var(--gold); border-radius: 6px;
        background: linear-gradient(180deg, #fbfcfe, #f5f8fc);
    }
    .bl-eyebrow {
        margin-bottom: 6px; color: var(--gold);
        font-size: 10px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
    }
    .bl-head h1 { margin: 0; color: var(--navy); font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
    .bl-head p { margin: 5px 0 0; color: var(--ink-soft); font-size: 13.5px; }
    .bl-back { color: var(--navy-soft); font-size: 12px; font-weight: 500; text-decoration: none; }
    .bl-back:hover { color: var(--gold); }
    .bl-headnum { text-align: right; }
    .bl-headnum b { display: block; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 26px; font-weight: 600; line-height: 1; }
    .bl-headnum span { display: block; margin-top: 4px; color: var(--muted); font-size: 9.5px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase; }

    /* ---- Panel (bo'lim) ---- */
    .bl-panel { overflow: hidden; border: 1px solid var(--line); border-radius: 6px; background: #fff; }
    .bl-panel-head {
        display: flex; align-items: center; gap: 13px;
        padding: 13px 20px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fbfcfe, #f5f8fc);
    }
    .bl-step {
        flex: none; display: grid; place-items: center; width: 30px; height: 30px;
        border-radius: 4px; background: var(--navy); color: #fff;
        font-family: 'Roboto Slab', serif; font-size: 13px; font-weight: 600;
    }
    .bl-panel-head h2 { margin: 0; color: var(--navy); font-size: 15px; font-weight: 600; }
    .bl-panel-head p { margin: 2px 0 0; color: var(--muted); font-size: 11.5px; }
    .bl-panel-count {
        margin-left: auto; padding: 4px 12px; border: 1px solid var(--line); border-radius: 3px;
        background: #fff; color: var(--ink-soft); font-size: 11px; font-weight: 500; letter-spacing: .04em;
    }
    .bl-panel-body { padding: 18px 20px; }
    .bl-panel-foot {
        display: flex; align-items: center; justify-content: flex-end; gap: 12px;
        padding: 13px 20px; border-top: 1px solid var(--line-soft); background: #fafcfe;
    }

    /* ---- Maydonlar ---- */
    .bl-grid { display: grid; gap: 16px; }
    .bl-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .bl-span { grid-column: 1 / -1; }
    .bl-field { min-width: 0; }
    .bl-field > label, .bl-label {
        display: block; margin-bottom: 6px; color: var(--ink-soft);
        font-size: 11px; font-weight: 500; letter-spacing: .05em;
    }
    .bl-field b { color: var(--bad); }
    .bl-note { margin-top: 6px; color: var(--muted); font-size: 11.5px; }
    .bl-note.is-warn { color: var(--warn); }

    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']),
    .bl select, .bl textarea {
        width: 100%; border: 1px solid #c4d0e0; border-radius: 5px; background: #fcfdff;
        color: var(--ink); font-family: 'Roboto', sans-serif; font-size: 13.5px; outline: none;
        transition: border-color .16s, box-shadow .16s;
    }
    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']), .bl select { height: 40px; padding: 0 11px; }
    .bl textarea { padding: 10px 11px; line-height: 1.6; resize: vertical; }
    .bl input:focus, .bl select:focus, .bl textarea:focus {
        border-color: var(--navy-soft); box-shadow: 0 0 0 3px rgba(27,58,99,.1); background: #fff;
    }
    .bl input[type='checkbox'], .bl input[type='radio'] { width: 15px; height: 15px; accent-color: var(--navy); }
    /* Uzun matn — o'sadi, lekin sahifani cho'zmaydi */
    .bl-grow { min-height: 76px; max-height: 42vh; overflow-y: auto; }

    /* ---- Tugmalar ---- */
    .bl-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        height: 40px; padding: 0 20px; border: 1px solid transparent; border-radius: 5px;
        font-family: 'Roboto', sans-serif; font-size: 13px; font-weight: 500; letter-spacing: .04em;
        line-height: 1; text-decoration: none; cursor: pointer; transition: background .16s, border-color .16s, color .16s;
    }
    .bl-btn-main { background: var(--navy); color: #fff; }
    .bl-btn-main:hover { background: var(--navy-soft); }
    .bl-btn-ghost { border-color: #c4d0e0; background: #fff; color: var(--navy); }
    .bl-btn-ghost:hover { background: #f1f5fa; }
    .bl-btn-ok { background: var(--ok); color: #fff; }
    .bl-btn-ok:hover { background: #0c6444; }
    .bl-btn-bad { border-color: #e8c3c0; background: #fff; color: var(--bad); }
    .bl-btn-bad:hover { background: var(--bad-bg); }
    .bl-btn-sm { height: 32px; padding: 0 13px; font-size: 11.5px; }
    .bl-x {
        display: grid; place-items: center; width: 32px; height: 32px;
        border: 0; border-radius: 4px; background: transparent;
        color: #b8c4d4; font-size: 19px; line-height: 1; cursor: pointer; transition: background .16s, color .16s;
    }
    .bl-x:hover:not(:disabled) { background: var(--bad-bg); color: var(--bad); }
    .bl-x:disabled { cursor: not-allowed; opacity: .35; }

    /* ---- Belgilash ---- */
    .bl-checks { display: flex; flex-wrap: wrap; gap: 10px; }
    .bl-check {
        display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px;
        border: 1px solid var(--line); border-radius: 5px; background: #fafcfe;
        color: var(--ink-soft); font-size: 12.5px; cursor: pointer; transition: border-color .16s, background .16s, color .16s;
    }
    .bl-check:hover { border-color: #c4d0e0; background: #f1f5fa; color: var(--navy); }
    .bl-check:has(input:checked) { border-color: var(--navy-soft); background: #f1f5fa; color: var(--navy); font-weight: 500; }

    /* ---- Savol formasi ---- */
    .bl-qf { display: grid; gap: 14px; padding: 16px 20px 18px; }
    .bl-qf-bar {
        display: flex; flex-wrap: wrap; align-items: center; gap: 11px;
        padding-bottom: 14px; border-bottom: 1px solid var(--line-soft);
    }
    .bl-qf-type { flex: 1 1 190px; max-width: 230px; }
    .bl-qf-end { display: inline-flex; align-items: center; gap: 11px; margin-left: auto; }
    .bl-qf-points {
        display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;
        color: var(--ink-soft); font-size: 11px; font-weight: 500; letter-spacing: .05em;
    }
    .bl-qf-points input { width: 66px !important; text-align: center; }

    .bl-langs { display: inline-flex; flex: none; gap: 2px; padding: 2px; border: 1px solid var(--line); border-radius: 5px; background: #f6f9fd; }
    .bl-lang {
        min-width: 38px; padding: 6px 10px; border: 0; border-radius: 3px; background: transparent;
        color: var(--muted); font-family: 'Roboto', sans-serif; font-size: 11px; font-weight: 600;
        letter-spacing: .06em; cursor: pointer; transition: background .16s, color .16s;
    }
    .bl-lang:hover { color: var(--navy); }
    .bl-lang.is-on { background: var(--navy); color: #fff; }

    .bl-qf-top { display: grid; grid-template-columns: minmax(0, 1fr) 186px; gap: 16px; }
    .bl-qf-media { display: grid; gap: 9px; align-content: start; }
    .bl-drop {
        position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
        min-height: 96px; padding: 12px; border: 1px dashed #c4d0e0; border-radius: 5px;
        background: #fafcfe; text-align: center; cursor: pointer; transition: border-color .16s, background .16s;
    }
    .bl-drop:hover { border-color: var(--navy-soft); background: #f1f5fa; }
    .bl-drop input[type='file'] { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .bl-drop-icon { color: var(--muted); font-size: 19px; }
    .bl-drop-name { overflow: hidden; max-width: 100%; color: var(--navy); font-size: 11.5px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
    .bl-drop-hint { color: var(--muted); font-size: 10.5px; letter-spacing: .04em; }
    .bl-thumb { padding: 8px; border: 1px solid var(--line); border-radius: 5px; background: #fafcfe; }
    .bl-thumb img { display: block; width: 100%; max-height: 104px; border-radius: 3px; object-fit: contain; }
    .bl-thumb label { display: flex; align-items: center; gap: 7px; margin-top: 8px; color: var(--bad); font-size: 11.5px; cursor: pointer; }

    .bl-answers {
        display: grid; gap: 10px; padding: 14px 16px;
        border: 1px solid var(--line); border-left: 3px solid var(--navy-soft); border-radius: 5px; background: #fafcfe;
    }
    .bl-answers.is-blank { border-left-color: var(--gold); background: #fefcf6; }
    .bl-answers-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
        color: var(--navy); font-size: 12px; font-weight: 500; letter-spacing: .04em;
    }
    .bl-answers-head em { color: var(--muted); font-size: 11px; font-style: normal; }

    /* Variantlar ko'p bo'lsa ro'yxatning o'zi skroll bo'ladi */
    .bl-opts { display: grid; gap: 7px; max-height: 340px; overflow-y: auto; padding-right: 3px; }
    .bl-opts::-webkit-scrollbar { width: 9px; }
    .bl-opts::-webkit-scrollbar-thumb { border: 3px solid transparent; border-radius: 9px; background: #c4d0e0; background-clip: content-box; }
    .bl-opts::-webkit-scrollbar-thumb:hover { background: var(--muted); background-clip: content-box; }
    .bl-opt {
        display: grid; grid-template-columns: 44px minmax(0, 1fr) 32px; align-items: center; gap: 9px;
        padding: 8px 10px; border: 1px solid var(--line); border-radius: 5px; background: #fff;
        transition: border-color .16s, background .16s;
    }
    .bl-opt.is-correct { border-color: #a5d6bf; background: var(--ok-bg); }
    .bl-pick { display: inline-flex; align-items: center; gap: 7px; cursor: pointer; }
    .bl-pick input[type='radio'] { accent-color: var(--ok); }
    .bl-pick span { color: var(--ink-soft); font-family: 'Roboto Slab', serif; font-size: 13px; font-weight: 600; }
    .bl-opt.is-correct .bl-pick span { color: var(--ok); }

    .bl-answers.is-match { border-left-color: #7c9dd0; }
    .bl-answers.is-order { border-left-color: #59a389; }

    .bl-tf { display: flex; flex-wrap: wrap; gap: 10px; }
    .bl-tf-pick {
        display: inline-flex; align-items: center; gap: 9px; padding: 11px 20px;
        border: 1px solid var(--line); border-radius: 5px; background: #fff;
        color: var(--ink-soft); font-size: 13.5px; cursor: pointer; transition: border-color .16s, background .16s, color .16s;
    }
    .bl-tf-pick.is-on { border-color: #a5d6bf; background: var(--ok-bg); color: var(--ok); font-weight: 500; }
    .bl-tf-pick.is-off { border-color: #e8c3c0; background: var(--bad-bg); color: var(--bad); font-weight: 500; }

    .bl-pair {
        display: grid; grid-template-columns: 30px minmax(0, 1fr) 22px minmax(0, 1fr) 32px;
        align-items: center; gap: 9px;
        padding: 8px 10px; border: 1px solid var(--line); border-radius: 5px; background: #fff;
    }
    .bl-pair-no, .bl-step-no {
        display: grid; place-items: center; width: 26px; height: 26px;
        border: 1px solid var(--line); border-radius: 4px; background: #fafcfe;
        color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 12px; font-weight: 600;
    }
    .bl-pair-arrow { color: var(--muted); font-size: 15px; text-align: center; }

    .bl-step-row {
        display: grid; grid-template-columns: 30px minmax(0, 1fr) 32px; align-items: center; gap: 9px;
        padding: 8px 10px; border: 1px solid var(--line); border-radius: 5px; background: #fff;
    }

    @media (max-width: 780px) {
        .bl-pair { grid-template-columns: 30px minmax(0, 1fr) 32px; }
        .bl-pair-arrow { display: none; }
    }

    .bl-more { border: 1px solid var(--line); border-radius: 5px; background: #fafcfe; }
    .bl-more > summary {
        padding: 9px 14px; color: var(--ink-soft); font-size: 11.5px; font-weight: 500;
        letter-spacing: .04em; cursor: pointer; list-style: none;
    }
    .bl-more > summary::-webkit-details-marker { display: none; }
    .bl-more > summary::before { content: '▸'; margin-right: 7px; color: var(--muted); font-size: 9px; }
    .bl-more[open] > summary::before { content: '▾'; }
    .bl-more > summary:hover { color: var(--navy); }
    .bl-more-body { display: grid; gap: 12px; padding: 0 14px 14px; }

    .bl-qf-foot {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px;
        padding-top: 14px; border-top: 1px solid var(--line-soft);
    }

    /* ---- Kiritilgan savollar ---- */
    .bl-q { border-bottom: 1px solid var(--line-soft); }
    .bl-q:last-of-type { border-bottom: 0; }
    .bl-q > summary {
        display: flex; align-items: center; gap: 13px; padding: 13px 20px;
        cursor: pointer; list-style: none; transition: background .16s;
    }
    .bl-q > summary::-webkit-details-marker { display: none; }
    .bl-q > summary:hover { background: #fafcfe; }
    .bl-q[open] > summary { background: #f5f8fc; border-bottom: 1px solid var(--line-soft); }
    .bl-q-no {
        flex: none; display: grid; place-items: center; width: 27px; height: 27px;
        border: 1px solid var(--line); border-radius: 4px; background: #fff;
        color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 12px; font-weight: 600;
    }
    .bl-q[open] .bl-q-no { border-color: var(--navy); background: var(--navy); color: #fff; }
    .bl-q-text { overflow: hidden; flex: 1 1 auto; min-width: 0; font-size: 13.5px; text-overflow: ellipsis; white-space: nowrap; }
    .bl-q-text.is-empty { color: var(--muted); font-style: italic; }
    .bl-q-meta { flex: none; color: var(--muted); font-size: 11px; letter-spacing: .04em; white-space: nowrap; }
    .bl-q-del { display: flex; justify-content: flex-end; padding: 0 20px 16px; }

    /* ---- Jadval ---- */
    .bl-scroll { overflow-x: auto; }
    .bl-table { width: 100%; min-width: 700px; border-collapse: collapse; }
    .bl-table th {
        padding: 10px 18px; border-bottom: 1px solid var(--line); background: #fafcfe;
        color: var(--muted); font-size: 9.5px; font-weight: 700; letter-spacing: .12em;
        text-align: left; text-transform: uppercase; white-space: nowrap;
    }
    .bl-table td { padding: 13px 18px; border-bottom: 1px solid var(--line-soft); font-size: 13.5px; vertical-align: middle; }
    .bl-table tbody tr:last-child td { border-bottom: 0; }
    .bl-table tbody tr:hover { background: #fafcfe; }
    .bl-table tbody tr.is-current { background: #f5f8fc; box-shadow: inset 3px 0 0 var(--gold); }
    .bl-t-name { color: var(--ink); font-weight: 500; }
    .bl-t-sub { display: block; margin-top: 2px; color: var(--muted); font-size: 11.5px; }
    .bl-num { color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 14px; font-weight: 600; }
    .bl-pill { display: inline-flex; padding: 3px 11px; border-radius: 3px; font-size: 11px; font-weight: 500; letter-spacing: .03em; }
    .bl-pill.ok { background: var(--ok-bg); color: var(--ok); }
    .bl-pill.off { background: #eef1f6; color: var(--muted); }
    .bl-btn-warn { border-color: #e5cfa4; background: #fff; color: var(--warn); }
    .bl-btn-warn:hover { background: var(--warn-bg); }
    .bl-acts { display: inline-flex; align-items: center; justify-content: flex-end; gap: 7px; white-space: nowrap; }
    .bl-acts form { display: inline-flex; margin: 0; }

    /* ---- Ruxsat etilgan guruhlar ---- */
    .bl-groups {
        display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
        padding: 13px 20px; border: 1px solid var(--line);
        border-left: 3px solid var(--navy-soft); border-radius: 6px; background: #fff;
    }
    .bl-groups-head {
        margin-right: 6px; color: var(--muted); font-size: 9.5px; font-weight: 700;
        letter-spacing: .13em; text-transform: uppercase;
    }
    .bl-group-chip {
        padding: 4px 12px; border: 1px solid var(--line); border-radius: 3px;
        background: #fafcfe; color: var(--navy); font-size: 12px; font-weight: 500;
    }
    .bl-groups-note { color: var(--warn); font-size: 12.5px; }
    .bl-groups-head b { margin-left: 6px; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 12px; }
    option.bl-opt-empty { color: #9aa8bd; }

    /* ---- Xabar / bo'sh holat ---- */
    .bl-alert { padding: 14px 18px; border: 1px solid; border-left-width: 3px; border-radius: 5px; font-size: 13.5px; }
    .bl-alert.is-ok { border-color: #a5d6bf; border-left-color: var(--ok); background: var(--ok-bg); color: #0a6043; }
    .bl-alert.is-bad { border-color: #e8c3c0; border-left-color: var(--bad); background: var(--bad-bg); color: #8f1e18; }
    .bl-alert ul { margin: 6px 0 0; padding-left: 20px; }

    .bl-empty { padding: 48px 24px; border: 1px dashed #c9d5e4; border-radius: 6px; background: #fff; text-align: center; }
    .bl-empty b { display: block; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 16px; font-weight: 600; }
    .bl-empty span { display: block; margin-top: 6px; color: var(--muted); font-size: 13px; }

    @media (max-width: 900px) {
        .bl-grid-2 { grid-template-columns: 1fr; }
        .bl-qf-top { grid-template-columns: 1fr; }
        .bl-qf-end { margin-left: 0; }
        .bl-drop { min-height: 76px; }
        .bl-q-meta { display: none; }
    }
</style>

@php
    $questions = $collection?->questions ?? [];
    $isEdit = (bool) $collection;
    $optionDefaults = [
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
    ];
    $toggles = [
        ['field' => 'shuffle_questions', 'label' => 'Savollarni aralashtirish', 'checked' => $collection?->shuffle_questions ?? false],
        ['field' => 'show_result_after_submit', 'label' => "Topshirgandan keyin natijani ko'rsatish", 'checked' => $collection?->show_result_after_submit ?? true],
        ['field' => 'is_active', 'label' => "To'plam faol", 'checked' => $collection?->is_active ?? true],
    ];
@endphp

<div class="bl py-6">
    <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

        @if(session('success'))
            <div class="bl-alert is-ok">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bl-alert is-bad">
                Ma'lumotlarni tekshiring:
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bl-head">
            <div>
                <div class="bl-eyebrow">Test moduli</div>
                @if($isEdit)
                    <a href="{{ route('teacher.fan-testlari.index') }}" class="bl-back">← Test yaratish</a>
                @endif
                <h1>{{ $isEdit ? "Test to'plamini tahrirlash" : "Yangi test to'plami" }}</h1>
                <p>Dars jadvali tayyor bo'lmasdan test savollarini oldindan yig'ing.</p>
            </div>
            @if($isEdit)
                <div class="bl-headnum">
                    <b>{{ count($questions) }}</b>
                    <span>Savol tayyor</span>
                </div>
            @endif
        </div>

        {{-- 01 · Sozlamalar --}}
        <form method="POST" action="{{ $isEdit ? route('teacher.fan-testlari.update', $collection) : route('teacher.fan-testlari.store') }}" class="bl-panel">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="bl-panel-head">
                <span class="bl-step">01</span>
                <div>
                    <h2>Test sozlamalari</h2>
                    <p>Bu ma'lumotlar keyinchalik darsga biriktiriladigan testga o'tkaziladi.</p>
                </div>
            </div>

            <div class="bl-panel-body bl-grid bl-grid-2">
                <div class="bl-field bl-span">
                    <label for="curriculum_subject_id">Fan</label>
                    <select name="curriculum_subject_id" id="curriculum_subject_id" required>
                        <option value="">Fan tanlang</option>
                        @foreach($subjects as $subject)
                            @php
                                $subjectGroups = (int) ($subject->group_count ?? 0);
                                $subjectLabel = collect([
                                    $subject->subject_name,
                                    $subject->semester_name,
                                    $subject->curriculum_label ?? null,
                                ])->filter()->implode(' · ')
                                    . ' — ' . ($subjectGroups > 0 ? $subjectGroups . ' guruh' : 'guruh yo\'q');
                            @endphp
                            <option value="{{ $subject->id }}" @selected((int) old('curriculum_subject_id', $collection?->curriculum_subject_id) === (int) $subject->id) @class(['bl-opt-empty' => $subjectGroups < 1])>
                                {{ $subjectLabel }}
                            </option>
                        @endforeach
                    </select>
                    @if($subjects->isEmpty())
                        <p class="bl-note is-warn">Sizga tegishli kafedra fanlari topilmadi.</p>
                    @endif
                </div>

                <div class="bl-field">
                    <label for="name">Test to'plami nomi</label>
                    <input id="name" name="name" required maxlength="255" value="{{ old('name', $collection?->name) }}" placeholder="Masalan: 1-mavzu nazorat testi">
                </div>

                <div class="bl-grid bl-grid-2">
                    <div class="bl-field">
                        <label for="duration_minutes">Vaqt (daqiqa)</label>
                        <input id="duration_minutes" type="number" name="duration_minutes" min="1" max="300" required value="{{ old('duration_minutes', $collection?->duration_minutes ?? 20) }}">
                    </div>
                    <div class="bl-field">
                        <label for="pass_percent">O'tish foizi</label>
                        <input id="pass_percent" type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $collection?->pass_percent ?? 60) }}">
                    </div>
                </div>

                <div class="bl-field bl-span">
                    <label for="description">Tavsif</label>
                    <textarea id="description" name="description" rows="2" placeholder="Test to'plami haqida qisqacha izoh...">{{ old('description', $collection?->description) }}</textarea>
                </div>

                <div class="bl-checks bl-span">
                    @foreach($toggles as $toggle)
                        <label class="bl-check">
                            <input type="hidden" name="{{ $toggle['field'] }}" value="0">
                            <input type="checkbox" name="{{ $toggle['field'] }}" value="1" @checked(old($toggle['field'], $toggle['checked']))>
                            {{ $toggle['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="bl-panel-foot">
                <button class="bl-btn bl-btn-main">{{ $isEdit ? 'Sozlamalarni saqlash' : "To'plamni yaratish" }}</button>
            </div>
        </form>

        @if($isEdit)
            @php $allowedGroups = $allowedGroups ?? collect(); @endphp
            <div class="bl-groups">
                <div class="bl-groups-head">
                    Testni ishlay oladigan guruhlar
                    @if($allowedGroups->isNotEmpty())
                        <b>{{ $allowedGroups->count() }}</b>
                    @endif
                </div>
                @forelse($allowedGroups as $groupName)
                    <span class="bl-group-chip">{{ $groupName }}</span>
                @empty
                    <span class="bl-groups-note">Bu fan-semestr-reja uchun guruh biriktirilmagan — testni istalgan talaba ishlay oladi. Ro'yxatdan guruhi bor variantni tanlang.</span>
                @endforelse
            </div>

            {{-- 02 · Yangi savol --}}
            <div class="bl-panel">
                <div class="bl-panel-head">
                    <span class="bl-step">02</span>
                    <div>
                        <h2>Yangi savol qo'shish</h2>
                        <p>Savol turini tanlang, matn va javob variantlarini kiriting.</p>
                    </div>
                </div>
                @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.store', $collection), 'method' => null, 'question' => null, 'questionIndex' => null, 'optionDefaults' => $optionDefaults])
            </div>

            {{-- 03 · Kiritilgan savollar --}}
            <div class="bl-panel">
                <div class="bl-panel-head">
                    <span class="bl-step">03</span>
                    <div>
                        <h2>Kiritilgan savollar</h2>
                        <p>Savol sarlavhasini bosib tahrirlash oynasini oching.</p>
                    </div>
                    <span class="bl-panel-count">{{ count($questions) }} ta</span>
                </div>

                @forelse($questions as $index => $question)
                    @php
                        $preview = trim(strip_tags((string) ($question['prompt'] ?? '')));
                        $typeLabel = [
                            'single_choice' => "Bitta to'g'ri javob",
                            'multiple_choice' => "Bir nechta to'g'ri javob",
                            'true_false' => "To'g'ri / Noto'g'ri",
                            'fill_in_blank' => "Bo'sh joyni to'ldirish",
                            'matching' => 'Moslashtirish',
                            'ordering' => 'Ketma-ketlik',
                        ][$question['type'] ?? 'single_choice'] ?? "Bitta to'g'ri javob";
                    @endphp
                    <details class="bl-q">
                        <summary>
                            <span class="bl-q-no">{{ $index + 1 }}</span>
                            <span class="bl-q-text {{ $preview === '' ? 'is-empty' : '' }}">{{ $preview !== '' ? \Illuminate\Support\Str::limit($preview, 110) : 'Savol matni kiritilmagan' }}</span>
                            <span class="bl-q-meta">{{ $typeLabel }} · {{ $question['points'] ?? 1 }} ball</span>
                        </summary>
                        <div>
                            @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.update', [$collection, $index]), 'method' => 'PUT', 'question' => $question, 'questionIndex' => $index, 'optionDefaults' => $optionDefaults])
                            <form method="POST" action="{{ route('teacher.fan-testlari.questions.destroy', [$collection, $index]) }}" onsubmit="return confirm('Bu savolni o\'chirishni tasdiqlaysizmi?')" class="bl-q-del">
                                @csrf
                                @method('DELETE')
                                <button class="bl-btn bl-btn-bad bl-btn-sm">Savolni o'chirish</button>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="bl-panel-body">
                        <div class="bl-empty">
                            <b>Hali savol qo'shilmagan</b>
                            <span>Yuqoridagi forma orqali test to'plamingizni savollar bilan to'ldiring.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="bl-empty">
                <b>Avval test to'plamini saqlang</b>
                <span>To'plam yaratilgach, shu sahifada savol qo'shish va sozlash oynalari ochiladi.</span>
            </div>
        @endif

        {{-- Mavjud to'plamlar --}}
        @if(($collections ?? collect())->isNotEmpty())
            <div class="bl-panel">
                <div class="bl-panel-head">
                    <div>
                        <h2>Yaratilgan test to'plamlari</h2>
                        <p>Keyinchalik bu to'plamlardan dars testiga biriktiriladi.</p>
                    </div>
                    <span class="bl-panel-count">{{ $collections->count() }} ta to'plam</span>
                </div>

                <div class="bl-scroll">
                    <table class="bl-table">
                        <thead>
                        <tr>
                            <th>Test to'plami</th>
                            <th>Fan</th>
                            <th style="text-align:center">Savollar</th>
                            <th style="text-align:center">Vaqt</th>
                            <th>Holat</th>
                            <th style="text-align:right">Amal</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($collections as $item)
                            <tr class="{{ $isEdit && $item->id === $collection->id ? 'is-current' : '' }}">
                                <td>
                                    <span class="bl-t-name">{{ $item->name }}</span>
                                    @if($item->description)
                                        <span class="bl-t-sub">{{ \Illuminate\Support\Str::limit($item->description, 64) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="bl-t-name">{{ $item->subject?->subject_name ?? '-' }}</span>
                                    <span class="bl-t-sub">{{ $item->subject?->semester_name ?? $item->subject?->subject_code ?? '-' }}</span>
                                </td>
                                <td style="text-align:center"><span class="bl-num">{{ $item->questionCount() }}</span></td>
                                <td style="text-align:center">{{ $item->duration_minutes }} daqiqa</td>
                                <td>
                                    <span class="bl-pill {{ $item->is_active ? 'ok' : 'off' }}">{{ $item->is_active ? 'Ochiq' : 'Yopiq' }}</span>
                                </td>
                                <td style="text-align:right">
                                    <div class="bl-acts">
                                        @if($item->questionCount() > 0)
                                            @if($item->is_active)
                                                <a href="{{ route('kiosk.fan-testi.show', $item) }}" target="_blank" class="bl-btn bl-btn-ok bl-btn-sm" title="Talabalar uchun test sahifasini ochish">Ochish</a>
                                                <form method="POST" action="{{ route('teacher.fan-testlari.toggle-active', $item) }}" onsubmit="return confirm('Test sahifasi yopilsinmi? Talabalar havola orqali kira olmaydi.')">
                                                    @csrf
                                                    <button class="bl-btn bl-btn-warn bl-btn-sm" title="Talabalar uchun test sahifasini yopish">Yopish</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('teacher.fan-testlari.toggle-active', $item) }}">
                                                    @csrf
                                                    <button class="bl-btn bl-btn-ok bl-btn-sm" title="Talabalar uchun test sahifasini ochish">Ochish</button>
                                                </form>
                                            @endif
                                        @endif
                                        <a href="{{ route('teacher.fan-testlari.edit', $item) }}" class="bl-btn bl-btn-ghost bl-btn-sm">Tahrirlash</a>
                                        <form method="POST" action="{{ route('teacher.fan-testlari.destroy', $item) }}" onsubmit="return confirm('Bu test to\'plami va savollarini o\'chirishni tasdiqlaysizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="bl-btn bl-btn-bad bl-btn-sm">O'chirish</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    function fanQuestionBuilder(initial) {
        const source = initial || {};
        const sourceOptions = Array.isArray(source.options) ? source.options : [];
        const correctIndex = Math.max(0, sourceOptions.findIndex(option => option.is_correct) || 0);
        const options = sourceOptions.map(option => ({
            text: option.text || '', text_ru: option.text_ru || '', text_en: option.text_en || ''
        }));
        while (options.length < 3) options.push({text: '', text_ru: '', text_en: ''});

        // Ko'p javobli savol uchun belgilangan variant raqamlari (1 dan boshlab)
        const correctNumbers = sourceOptions
            .map((option, index) => (option.is_correct ? index + 1 : null))
            .filter(number => number !== null)
            .map(String);

        const pairs = (Array.isArray(source.pairs) ? source.pairs : []).map(pair => ({
            left: pair.left || '', left_ru: pair.left_ru || '', left_en: pair.left_en || '',
            right: pair.right || '', right_ru: pair.right_ru || '', right_en: pair.right_en || ''
        }));
        while (pairs.length < 3) pairs.push({left: '', left_ru: '', left_en: '', right: '', right_ru: '', right_en: ''});

        const steps = (Array.isArray(source.steps) ? source.steps : []).map(step => ({
            text: step.text || '', text_ru: step.text_ru || '', text_en: step.text_en || ''
        }));
        while (steps.length < 3) steps.push({text: '', text_ru: '', text_en: ''});

        // To'g'ri/Noto'g'ri: birinchi variant "To'g'ri" bo'lib saqlanadi
        const trueFalse = sourceOptions.length === 2 && sourceOptions[1]?.is_correct ? '0' : '1';

        return {
            type: source.type || 'single_choice',
            prompt: source.prompt || '', prompt_ru: source.prompt_ru || '', prompt_en: source.prompt_en || '',
            helper_text: source.helper_text || '', helper_text_ru: source.helper_text_ru || '', helper_text_en: source.helper_text_en || '',
            correct_explanation: source.correct_explanation || '', correct_explanation_ru: source.correct_explanation_ru || '', correct_explanation_en: source.correct_explanation_en || '',
            correct_answer_text: source.correct_answer_text || '', correct_answer_text_ru: source.correct_answer_text_ru || '', correct_answer_text_en: source.correct_answer_text_en || '',
            case_sensitive: Boolean(source.case_sensitive), points: source.points || 1, is_active: source.is_active !== false,
            options: options, correctOption: correctIndex + 1,
            correctOptions: correctNumbers, trueFalse: trueFalse,
            pairs: pairs, steps: steps,
            lang: 'uz', imageName: '',
            pickImage(event) { this.imageName = event.target.files?.[0]?.name || ''; },
            isCorrectOption(index) {
                return this.type === 'multiple_choice'
                    ? this.correctOptions.includes(String(index + 1))
                    : this.correctOption === index + 1;
            },
            addOption() { this.options.push({text: '', text_ru: '', text_en: ''}); },
            removeOption(index) {
                if (this.options.length <= 2) return;
                this.options.splice(index, 1);
                if (this.correctOption > this.options.length) this.correctOption = this.options.length;
                if (this.correctOption > index + 1) this.correctOption--;
                // Ko'p javobli tanlovda raqamlar siljiydi
                this.correctOptions = this.correctOptions
                    .map(Number)
                    .filter(number => number !== index + 1)
                    .map(number => (number > index + 1 ? number - 1 : number))
                    .map(String);
            },
            addPair() { this.pairs.push({left: '', left_ru: '', left_en: '', right: '', right_ru: '', right_en: ''}); },
            removePair(index) { if (this.pairs.length > 2) this.pairs.splice(index, 1); },
            addStep() { this.steps.push({text: '', text_ru: '', text_en: ''}); },
            removeStep(index) { if (this.steps.length > 3) this.steps.splice(index, 1); }
        };
    }
</script>
</x-app-layout>

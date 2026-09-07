{{--
  Fan testlari dizayn to'plami (design kit).

  Modulning barcha sahifalari — test yaratish, savol formasi, jurnal —
  shu bitta uslub manbasidan foydalanadi. Yangi sahifa qo'shilganda
  <x-fan-testi-kit /> chaqiriladi va quyidagi ft-* klasslari ishlatiladi;
  sahifaga xos CSS yozilmaydi.
--}}
<style>
    :root {
        --ft-bg: #f1f5fb;
        --ft-surface: #ffffff;
        --ft-line: #dde7f4;
        --ft-line-soft: #eaf0f8;
        --ft-ink: #16243c;
        --ft-ink-soft: #5b6f8d;
        --ft-ink-mute: #93a4bd;
        --ft-brand: #2563eb;
        --ft-brand-dark: #1d4ed8;
        --ft-brand-soft: #eef4ff;
        --ft-ok: #059669;
        --ft-ok-soft: #ecfdf5;
        --ft-warn: #d97706;
        --ft-danger: #dc2626;
        --ft-danger-soft: #fef2f2;
        --ft-radius: 14px;
        --ft-radius-sm: 9px;
        --ft-shadow: 0 1px 2px rgba(22, 36, 60, .05), 0 8px 24px rgba(22, 36, 60, .05);
    }

    .ft { color: var(--ft-ink); font-size: 13px; }
    .ft [x-cloak] { display: none !important; }

    /* ── Sahifa karkasi ─────────────────────────────────────────── */
    .ft-page { display: grid; gap: 14px; padding: 18px 16px 28px; }
    @media (min-width: 640px) {
        .ft-page { padding: 20px 24px 32px; }
    }

    .ft-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .ft-back { color: var(--ft-brand); font-size: 12px; font-weight: 700; text-decoration: none; }
    .ft-back:hover { color: var(--ft-brand-dark); }
    .ft-title { margin: 2px 0 0; font-size: 20px; font-weight: 800; letter-spacing: -.01em; }
    .ft-sub { margin: 3px 0 0; color: var(--ft-ink-soft); font-size: 12px; }

    /* ── Kartochka ──────────────────────────────────────────────── */
    .ft-card { overflow: hidden; border: 1px solid var(--ft-line); border-radius: var(--ft-radius); background: var(--ft-surface); box-shadow: var(--ft-shadow); }
    .ft-card-head { display: flex; align-items: center; gap: 10px; padding: 11px 16px; border-bottom: 1px solid var(--ft-line-soft); background: #f8fafd; }
    .ft-card-head h2 { margin: 0; font-size: 13px; font-weight: 800; }
    .ft-card-head p { margin: 1px 0 0; color: var(--ft-ink-mute); font-size: 11px; }
    .ft-card-body { padding: 14px 16px; }
    .ft-card-foot { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 11px 16px; border-top: 1px solid var(--ft-line-soft); background: #fafcfe; }

    .ft-step { display: inline-flex; flex: none; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 7px; background: var(--ft-brand); color: #fff; font-size: 11px; font-weight: 800; }
    .ft-count { flex: none; margin-left: auto; padding: 3px 9px; border-radius: 999px; background: var(--ft-brand-soft); color: var(--ft-brand-dark); font-size: 11px; font-weight: 700; }

    /* ── Maydonlar ──────────────────────────────────────────────── */
    .ft-grid { display: grid; gap: 12px; }
    @media (min-width: 900px) {
        .ft-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ft-span-2 { grid-column: 1 / -1; }
    }

    .ft-field { min-width: 0; }
    .ft-field > label, .ft-label { display: block; margin-bottom: 4px; color: var(--ft-ink-soft); font-size: 11px; font-weight: 700; }
    .ft-field b { color: var(--ft-danger); }
    .ft-hint { margin-top: 4px; color: var(--ft-ink-mute); font-size: 11px; }
    .ft-hint-warn { color: var(--ft-warn); font-weight: 600; }

    .ft input:not([type='checkbox']):not([type='radio']):not([type='file']),
    .ft select,
    .ft textarea {
        width: 100%; border: 1px solid #cddaeb; border-radius: var(--ft-radius-sm);
        background: #fff; color: var(--ft-ink); font-size: 13px; font-family: inherit;
        transition: border-color .15s, box-shadow .15s;
    }
    .ft input:not([type='checkbox']):not([type='radio']):not([type='file']), .ft select { height: 36px; padding: 0 10px; }
    .ft textarea { padding: 8px 10px; line-height: 1.5; resize: vertical; }
    .ft input:focus, .ft select:focus, .ft textarea:focus {
        border-color: #5a8cf3; box-shadow: 0 0 0 3px rgba(90, 140, 243, .14); outline: none;
    }
    .ft input[type='checkbox'], .ft input[type='radio'] { width: 15px; height: 15px; accent-color: var(--ft-brand); }

    /* Uzun matn maydoni — o'sib boradi, lekin sahifani cho'zmaydi */
    .ft-textarea-grow { min-height: 74px; max-height: 40vh; overflow-y: auto; }

    /* ── Belgilash (checkbox chip) ──────────────────────────────── */
    .ft-checks { display: flex; flex-wrap: wrap; gap: 8px; }
    .ft-check { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border: 1px solid var(--ft-line); border-radius: var(--ft-radius-sm); background: #f8fafd; color: var(--ft-ink-soft); font-size: 12px; font-weight: 600; cursor: pointer; transition: border-color .15s, background .15s, color .15s; }
    .ft-check:hover { border-color: #b9cdeb; background: var(--ft-brand-soft); color: var(--ft-brand-dark); }
    .ft-check:has(input:checked) { border-color: #b9cdeb; background: var(--ft-brand-soft); color: var(--ft-brand-dark); }

    /* ── Tugmalar ───────────────────────────────────────────────── */
    .ft-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 36px; padding: 0 15px; border: 1px solid transparent; border-radius: var(--ft-radius-sm); font-size: 12px; font-weight: 700; line-height: 1; text-decoration: none; cursor: pointer; transition: background .15s, border-color .15s, color .15s; }
    .ft-btn-primary { background: var(--ft-brand); color: #fff; }
    .ft-btn-primary:hover { background: var(--ft-brand-dark); }
    .ft-btn-ghost { border-color: var(--ft-line); background: #fff; color: var(--ft-ink-soft); }
    .ft-btn-ghost:hover { border-color: #b9cdeb; background: #f8fafd; color: var(--ft-brand-dark); }
    .ft-btn-soft { border-color: #c9dcf7; background: var(--ft-brand-soft); color: var(--ft-brand-dark); }
    .ft-btn-soft:hover { background: #e0ebff; }
    .ft-btn-ok { background: var(--ft-ok); color: #fff; }
    .ft-btn-ok:hover { background: #047857; }
    .ft-btn-danger { border-color: #f6cbcb; background: var(--ft-danger-soft); color: var(--ft-danger); }
    .ft-btn-danger:hover { background: #fde4e4; }
    .ft-btn-sm { height: 30px; padding: 0 11px; font-size: 11px; }

    .ft-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 7px; background: transparent; color: #b6c4d8; font-size: 18px; line-height: 1; cursor: pointer; transition: background .15s, color .15s; }
    .ft-icon-btn:hover:not(:disabled) { background: var(--ft-danger-soft); color: var(--ft-danger); }
    .ft-icon-btn:disabled { cursor: not-allowed; opacity: .35; }

    /* ── Til almashtirgich ──────────────────────────────────────── */
    .ft-langs { display: inline-flex; flex: none; gap: 2px; padding: 2px; border: 1px solid var(--ft-line); border-radius: var(--ft-radius-sm); background: #f6f9fd; }
    .ft-lang { min-width: 36px; padding: 5px 9px; border: 0; border-radius: 7px; background: transparent; color: var(--ft-ink-mute); font-size: 11px; font-weight: 700; cursor: pointer; }
    .ft-lang:hover { color: var(--ft-brand-dark); }
    .ft-lang.is-on { background: var(--ft-brand); color: #fff; }

    /* ── Yig'iladigan blok ──────────────────────────────────────── */
    .ft-more { border: 1px solid var(--ft-line-soft); border-radius: var(--ft-radius-sm); background: #fbfcfe; }
    .ft-more summary { padding: 7px 11px; color: var(--ft-ink-soft); font-size: 11px; font-weight: 700; cursor: pointer; list-style: none; }
    .ft-more summary::-webkit-details-marker { display: none; }
    .ft-more summary::before { content: '+ '; color: var(--ft-brand); font-weight: 800; }
    .ft-more[open] summary::before { content: '− '; }
    .ft-more summary:hover { color: var(--ft-brand-dark); }
    .ft-more-body { display: grid; gap: 9px; padding: 0 11px 11px; }

    /* ── Xabar bloklari ─────────────────────────────────────────── */
    .ft-alert { border: 1px solid; border-radius: var(--ft-radius-sm); padding: 10px 13px; font-size: 12px; font-weight: 600; }
    .ft-alert-ok { border-color: #a7e3c8; background: var(--ft-ok-soft); color: #04785b; }
    .ft-alert-err { border-color: #f6cbcb; background: var(--ft-danger-soft); color: #b91c1c; }
    .ft-alert-err ul { margin: 5px 0 0; padding-left: 18px; font-weight: 500; }

    .ft-empty { padding: 30px 20px; border: 1px dashed #c6d5ea; border-radius: var(--ft-radius); background: #f8fafd; text-align: center; }
    .ft-empty h3 { margin: 0; font-size: 13px; font-weight: 800; }
    .ft-empty p { margin: 4px 0 0; color: var(--ft-ink-soft); font-size: 12px; }

    /* ── Nishonlar ──────────────────────────────────────────────── */
    .ft-badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .ft-badge-ok { background: var(--ft-ok-soft); color: #047857; }
    .ft-badge-off { background: #eef1f6; color: #7c8ca4; }
    .ft-badge-info { background: var(--ft-brand-soft); color: var(--ft-brand-dark); }

    /* ── Jadval ─────────────────────────────────────────────────── */
    .ft-scroll-x { overflow-x: auto; }
    .ft-table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 12.5px; }
    .ft-table thead th { padding: 9px 14px; border-bottom: 1px solid var(--ft-line); background: #f8fafd; color: var(--ft-ink-soft); font-size: 11px; font-weight: 700; text-align: left; white-space: nowrap; }
    .ft-table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--ft-line-soft); vertical-align: middle; }
    .ft-table tbody tr:last-child td { border-bottom: 0; }
    .ft-table tbody tr:hover { background: #f8fbff; }
    .ft-table tbody tr.is-current { background: var(--ft-brand-soft); }
    .ft-table .ft-t-name { font-weight: 700; }
    .ft-table .ft-t-sub { margin-top: 2px; color: var(--ft-ink-mute); font-size: 11px; }
    .ft-actions { display: inline-flex; align-items: center; gap: 6px; }
    .ft-actions form { display: inline-flex; margin: 0; }
</style>


<style>
    /* Savol formasi — kit ustiga qurilgan ixcham joylashuv */
    .ft-qf { display: grid; gap: 11px; padding: 13px 16px 15px; }

    .ft-qf-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; }
    .ft-qf-type { flex: 1 1 180px; max-width: 220px; font-weight: 600; }
    .ft-qf-bar-end { display: inline-flex; align-items: center; gap: 9px; margin-left: auto; }
    .ft-qf-points { display: inline-flex; align-items: center; gap: 6px; color: var(--ft-ink-soft); font-size: 11px; font-weight: 700; white-space: nowrap; }
    .ft-qf-points input { width: 62px !important; text-align: center; }

    .ft-qf-top { display: grid; grid-template-columns: minmax(0, 1fr) 170px; gap: 12px; }
    .ft-qf-media { display: grid; gap: 8px; align-content: start; }
    .ft-qf-drop { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; min-height: 84px; padding: 10px; border: 1px dashed #bccfea; border-radius: var(--ft-radius-sm); background: #f8fafd; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; }
    .ft-qf-drop:hover { border-color: #5a8cf3; background: var(--ft-brand-soft); }
    .ft-qf-drop input[type='file'] { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .ft-qf-drop-name { overflow: hidden; max-width: 100%; color: var(--ft-ink-soft); font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .ft-qf-drop-hint { color: var(--ft-ink-mute); font-size: 10px; }
    .ft-qf-thumb { padding: 7px; border: 1px solid var(--ft-line); border-radius: var(--ft-radius-sm); background: #f8fafd; }
    .ft-qf-thumb img { display: block; width: 100%; max-height: 92px; border-radius: 6px; object-fit: contain; }
    .ft-qf-thumb-del { display: flex; align-items: center; gap: 6px; margin-top: 6px; color: var(--ft-danger); font-size: 11px; font-weight: 600; cursor: pointer; }

    .ft-qf-block { display: grid; gap: 8px; padding: 11px 12px; border: 1px solid #d9e6fa; border-radius: var(--ft-radius-sm); background: #f9fbff; }
    .ft-qf-block.is-blank { border-color: #f6e2bf; background: #fffdf7; }
    .ft-qf-block-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: var(--ft-ink); font-size: 12px; font-weight: 700; }
    .ft-qf-block-head em { color: var(--ft-ink-mute); font-size: 10.5px; font-style: normal; font-weight: 600; }

    /* Variantlar ko'p bo'lsa ro'yxat o'zi skroll bo'ladi */
    .ft-qf-opts { display: grid; gap: 6px; max-height: 320px; overflow-y: auto; padding-right: 2px; }
    .ft-qf-opts::-webkit-scrollbar { width: 8px; }
    .ft-qf-opts::-webkit-scrollbar-thumb { border: 2px solid transparent; border-radius: 8px; background: #c9d6e8; background-clip: content-box; }
    .ft-qf-opt { display: grid; grid-template-columns: 40px minmax(0, 1fr) 30px; align-items: center; gap: 7px; padding: 6px 8px; border: 1px solid var(--ft-line-soft); border-radius: 8px; background: #fff; transition: border-color .15s, background .15s; }
    .ft-qf-opt.is-correct { border-color: #8fdfba; background: var(--ft-ok-soft); }
    .ft-qf-pick { display: inline-flex; align-items: center; gap: 5px; color: var(--ft-ink-soft); cursor: pointer; }
    .ft-qf-pick input[type='radio'] { accent-color: var(--ft-ok); }
    .ft-qf-pick span { font-size: 12px; font-weight: 800; }
    .ft-qf-opt.is-correct .ft-qf-pick span { color: #047857; }

    .ft-qf-foot { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding-top: 10px; border-top: 1px solid var(--ft-line-soft); }

    @media (max-width: 780px) {
        .ft-qf-top { grid-template-columns: 1fr; }
        .ft-qf-bar-end { margin-left: 0; }
        .ft-qf-drop { min-height: 68px; }
    }
</style>

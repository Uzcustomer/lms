<x-app-layout>
<style>
    /* Shrift LMS sahifasidan olinadi; forma elementlari ham shunga ergashsin */
    .bl input, .bl select, .bl textarea, .bl button { font-family: inherit; }
    .bl {
        --navy: #0f2748; --navy-soft: #1b3a63; --gold: #c9a227;
        --ink: #17233a; --ink-soft: #4d6180; --muted: #8798b1;
        --line: #dde5ef; --line-soft: #eef2f8;
        --ok: #0f7a52; --ok-bg: #e9f7f0; --bad: #b3261e; --bad-bg: #fdeceb;
        --warn: #a35a06; --warn-bg: #fdf3e4;
        font-family: inherit; color: var(--ink);
    }
    .bl h1, .bl h2, .bl .slab { font-family: inherit; }
    .bl [x-cloak] { display: none !important; }

    /* ---- Sarlavha ---- */
    .bl-head {
        position: relative; overflow: hidden;
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;
        padding: 22px 26px;
        border: 1px solid var(--line); border-left: 4px solid var(--gold); border-radius: 8px;
        background:
            radial-gradient(circle at 92% 120%, rgba(201, 162, 39, .10), transparent 55%),
            linear-gradient(180deg, #fdfefe, #f4f8fc);
        box-shadow: 0 1px 2px rgba(15, 39, 72, .04), 0 12px 28px rgba(15, 39, 72, .05);
    }
    .bl-head::after {
        content: ''; position: absolute; right: -30px; bottom: -46px; width: 190px; height: 150px;
        background-repeat: no-repeat; background-position: center; background-size: contain; opacity: .05;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230f2748' stroke-width='1.2'><path d='M12 3 2 8v2h20V8L12 3Z'/><path d='M4 10v9M8 10v9M12 10v9M16 10v9M20 10v9'/><path d='M2 21h20'/></svg>");
        pointer-events: none;
    }
    .bl-head-main { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .bl-head-badge {
        flex: none; display: grid; place-items: center; width: 52px; height: 52px;
        border: 1px solid #dbe6f4; border-radius: 12px;
        background: linear-gradient(160deg, #ffffff, #eef4fb);
        box-shadow: 0 4px 12px rgba(15, 39, 72, .07);
    }
    .bl-head-badge svg { width: 24px; height: 24px; color: var(--navy); }
    .bl-eyebrow {
        margin-bottom: 5px; color: var(--gold);
        font-size: 10px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase;
    }
    .bl-head h1 { margin: 0; color: var(--navy); font-size: 23px; font-weight: 700; letter-spacing: -.015em; }
    .bl-head p { margin: 5px 0 0; color: var(--ink-soft); font-size: 13.5px; }
    .bl-back {
        display: inline-flex; align-items: center; gap: 5px; margin-bottom: 4px;
        color: var(--navy-soft); font-size: 12px; font-weight: 600; text-decoration: none;
    }
    .bl-back:hover { color: var(--gold); }
    .bl-headnum {
        position: relative; flex: none; padding: 10px 20px; text-align: center;
        border: 1px solid #dbe6f4; border-radius: 10px; background: rgba(255, 255, 255, .8);
    }
    .bl-headnum b { display: block; color: var(--navy); font-family: inherit; font-size: 26px; font-weight: 600; line-height: 1; }
    .bl-headnum span { display: block; margin-top: 4px; color: var(--muted); font-size: 9.5px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; }

    /* ---- Panel ---- */
    .bl-panel {
        overflow: hidden; border: 1px solid var(--line); border-radius: 8px; background: #fff;
        box-shadow: 0 1px 2px rgba(15, 39, 72, .04), 0 10px 24px rgba(15, 39, 72, .04);
    }
    .bl-panel-head {
        display: flex; align-items: center; gap: 14px;
        padding: 15px 22px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fdfefe, #f4f8fc);
    }
    .bl-step {
        flex: none; display: grid; place-items: center; width: 34px; height: 34px;
        border-radius: 9px; background: linear-gradient(160deg, var(--navy-soft), var(--navy)); color: #fff;
        font-family: inherit; font-size: 13px; font-weight: 600;
        box-shadow: 0 4px 10px rgba(15, 39, 72, .22);
    }
    .bl-panel-head h2 { margin: 0; color: var(--navy); font-size: 15.5px; font-weight: 700; }
    .bl-panel-head p { margin: 3px 0 0; color: var(--muted); font-size: 11.5px; }
    .bl-panel-count {
        margin-left: auto; padding: 5px 13px; border: 1px solid #dbe6f4; border-radius: 999px;
        background: #fff; color: var(--navy-soft); font-size: 11px; font-weight: 700;
    }
    .bl-panel-body { padding: 20px 22px; }
    .bl-panel-foot {
        display: flex; align-items: center; justify-content: flex-end; gap: 12px;
        padding: 14px 22px; border-top: 1px solid var(--line-soft); background: #fafcfe;
    }

    /* ---- Maydonlar ---- */
    .bl-grid { display: grid; gap: 18px; }
    .bl-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .bl-hint { display: block; margin-top: 5px; color: var(--muted, #8798b1); font-size: 11.5px; line-height: 1.45; }
    .bl-span { grid-column: 1 / -1; }
    .bl-field { min-width: 0; }
    .bl-field > label, .bl-label {
        display: flex; align-items: center; gap: 7px; margin-bottom: 7px;
        color: var(--navy); font-size: 12px; font-weight: 700; letter-spacing: -.005em;
    }
    .bl-field > label svg, .bl-label svg { flex: none; width: 15px; height: 15px; color: var(--navy-soft); opacity: .8; }
    .bl-field > label em { color: var(--muted); font-style: normal; font-weight: 400; font-size: 11.5px; }
    .bl-field b { color: var(--bad); }
    .bl-note { margin-top: 7px; color: var(--muted); font-size: 11.5px; line-height: 1.55; }
    .bl-note.is-warn { color: var(--warn); }

    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']),
    .bl select, .bl textarea {
        width: 100%; border: 1px solid #d5deea; border-radius: 8px; background: #fbfdff;
        color: var(--ink); font-family: inherit; font-size: 13.5px; outline: none;
        transition: border-color .16s, box-shadow .16s, background .16s;
    }
    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']), .bl select { height: 42px; padding: 0 13px; }
    .bl textarea { padding: 11px 13px; line-height: 1.6; resize: vertical; }
    .bl input::placeholder, .bl textarea::placeholder { color: #a9b6c8; }
    .bl input:focus, .bl select:focus, .bl textarea:focus {
        border-color: var(--navy-soft); box-shadow: 0 0 0 3px rgba(27, 58, 99, .1); background: #fff;
    }
    .bl input[type='checkbox'], .bl input[type='radio'] { width: 16px; height: 16px; accent-color: var(--navy); }
    .bl-grow { min-height: 76px; max-height: 42vh; overflow-y: auto; }

    /* ---- Tugmalar ---- */
    .bl-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        height: 42px; padding: 0 20px; border: 1px solid transparent; border-radius: 8px;
        font-family: inherit; font-size: 13px; font-weight: 600; letter-spacing: .01em;
        line-height: 1; text-decoration: none; cursor: pointer;
        transition: background .16s, border-color .16s, color .16s, box-shadow .16s, transform .16s;
    }
    .bl-btn svg { width: 15px; height: 15px; flex: none; }
    .bl-btn-main {
        background: linear-gradient(160deg, var(--navy-soft), var(--navy)); color: #fff;
        box-shadow: 0 4px 12px rgba(15, 39, 72, .2);
    }
    .bl-btn-main:hover { box-shadow: 0 6px 16px rgba(15, 39, 72, .28); transform: translateY(-1px); }
    .bl-btn-ghost { border-color: #d5deea; background: #fff; color: var(--navy); }
    .bl-btn-ghost:hover { border-color: var(--navy-soft); background: #f4f8fc; }
    .bl-btn-ok { background: var(--ok); color: #fff; box-shadow: 0 4px 12px rgba(15, 122, 82, .22); }
    .bl-btn-ok:hover { background: #0c6444; transform: translateY(-1px); }
    .bl-btn-warn { border-color: #e5cfa4; background: #fff; color: var(--warn); }
    .bl-btn-warn:hover { background: var(--warn-bg); }
    .bl-btn-bad { border-color: #ecc9c6; background: #fff; color: var(--bad); }
    .bl-btn-bad:hover { background: var(--bad-bg); }
    .bl-btn-sm { height: 33px; padding: 0 13px; font-size: 11.5px; border-radius: 7px; }
    .bl-btn-sm svg { width: 13px; height: 13px; }
    .bl-x {
        display: grid; place-items: center; width: 33px; height: 33px;
        border: 0; border-radius: 7px; background: transparent;
        color: #b8c4d4; font-size: 19px; line-height: 1; cursor: pointer; transition: background .16s, color .16s;
    }
    .bl-x:hover:not(:disabled) { background: var(--bad-bg); color: var(--bad); }
    .bl-x:disabled { cursor: not-allowed; opacity: .35; }

    /* ---- Belgilash kartochkalari ---- */
    .bl-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
    .bl-check {
        display: flex; align-items: center; gap: 10px; padding: 12px 15px;
        border: 1px solid var(--line); border-radius: 9px; background: #fbfdff;
        color: var(--ink-soft); font-size: 12.5px; cursor: pointer;
        transition: border-color .16s, background .16s, color .16s, box-shadow .16s;
    }
    .bl-check svg { flex: none; width: 16px; height: 16px; color: var(--muted); transition: color .16s; }
    .bl-check:hover { border-color: #c3d2e6; background: #f4f8fc; }
    .bl-check:has(input:checked) {
        border-color: var(--navy-soft); background: #f1f6fc; color: var(--navy); font-weight: 600;
        box-shadow: 0 2px 8px rgba(15, 39, 72, .07);
    }
    .bl-check:has(input:checked) svg { color: var(--navy-soft); }

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

    .bl-langs { display: inline-flex; flex: none; gap: 3px; padding: 3px; border: 1px solid var(--line); border-radius: 9px; background: #f1f6fc; }
    .bl-lang {
        min-width: 40px; padding: 7px 11px; border: 0; border-radius: 6px; background: transparent;
        color: var(--muted); font-family: inherit; font-size: 11px; font-weight: 600;
        letter-spacing: .06em; cursor: pointer; transition: background .16s, color .16s;
    }
    .bl-lang:hover { color: var(--navy); }
    .bl-lang.is-on { background: var(--navy); color: #fff; }

    .bl-qf-top { display: grid; grid-template-columns: minmax(0, 1fr) 210px; gap: 18px; align-items: stretch; }
    /* Rasm ustuni ham yorliq bilan boshlanadi — ikki ustun bir tekisda turadi */
    .bl-qf-media { display: grid; grid-template-rows: auto 1fr; gap: 0; }
    .bl-qf-media .bl-drop { height: 100%; }
    .bl-drop {
        position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;
        min-height: 96px; padding: 12px; border: 1px dashed #c4d0e0; border-radius: 9px;
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
        display: grid; gap: 10px; padding: 15px 17px;
        border: 1px solid var(--line); border-left: 3px solid var(--navy-soft); border-radius: 9px; background: #fafcfe;
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
        display: grid; grid-template-columns: 44px minmax(0, 1fr) 33px; align-items: center; gap: 9px;
        padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; background: #fff;
        transition: border-color .16s, background .16s;
    }
    .bl-opt.is-correct { border-color: #a5d6bf; background: var(--ok-bg); }
    .bl-pick { display: inline-flex; align-items: center; gap: 7px; cursor: pointer; }
    .bl-pick input[type='radio'] { accent-color: var(--ok); }
    .bl-pick span { color: var(--ink-soft); font-family: inherit; font-size: 13px; font-weight: 600; }
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
        color: var(--navy); font-family: inherit; font-size: 12px; font-weight: 600;
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

    /* ---- Savol katakchalari ---- */
    #savollar { scroll-margin-top: 20px; }
    .bl-slots { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .bl-slot {
        display: flex; flex-direction: column; gap: 7px; min-height: 96px;
        padding: 14px 16px; border: 1px solid var(--line); border-radius: 11px;
        background: #fff; font-family: inherit; text-align: left; cursor: pointer;
        transition: border-color .16s, background .16s, box-shadow .16s, transform .16s;
    }
    .bl-slot:hover { border-color: var(--navy-soft); box-shadow: 0 6px 18px rgba(15, 39, 72, .1); transform: translateY(-1px); }
    .bl-slot-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .bl-slot-no { color: var(--navy); font-family: inherit; font-size: 15.5px; font-weight: 600; letter-spacing: -.01em; }

    /* Bo'sh katakcha: yashil doiradagi + */
    .bl-slot.is-empty {
        flex-direction: row; align-items: center; justify-content: space-between; gap: 14px;
        border-style: dashed; border-color: #cfdaea; background: #fbfdff;
    }
    .bl-slot.is-empty .bl-slot-top { flex: 1 1 auto; min-width: 0; justify-content: flex-start; }
    .bl-slot.is-empty .bl-slot-no { color: var(--ink-soft); }
    .bl-slot.is-empty:hover { border-color: var(--ok); background: #f4fbf8; }
    .bl-slot-plus {
        display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%;
        background: linear-gradient(160deg, #17a06a, var(--ok)); color: #fff;
        box-shadow: 0 5px 14px rgba(15, 122, 82, .3); transition: transform .16s, box-shadow .16s;
    }
    .bl-slot-plus svg { width: 19px; height: 19px; }
    .bl-slot.is-empty:hover .bl-slot-plus { transform: scale(1.08); box-shadow: 0 7px 18px rgba(15, 122, 82, .38); }

    /* To'ldirilgan katakcha: yashil tus */
    .bl-slot.is-filled {
        border-color: #a5d6bf; background: linear-gradient(180deg, #f4fbf8, #eaf7f1);
    }
    .bl-slot.is-filled:hover { border-color: var(--ok); }
    .bl-slot-tick {
        display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%;
        background: var(--ok); color: #fff;
    }
    .bl-slot-tick svg { width: 13px; height: 13px; }
    .bl-slot-text {
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        color: var(--ink); font-size: 12.5px; line-height: 1.5;
    }
    .bl-slot-text.is-empty { color: var(--muted); font-style: italic; }
    .bl-slot-meta { margin-top: auto; color: #5c7d6e; font-size: 11px; font-weight: 600; }

    @media (max-width: 1100px) { .bl-slots { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 720px) { .bl-slots { grid-template-columns: 1fr; } }

    .bl-slot-more {
        display: flex; align-items: center; justify-content: center; gap: 11px;
        width: 100%; margin-top: 16px; padding: 15px;
        border: 1px dashed #cfdaea; border-radius: 11px; background: #fbfdff;
        color: var(--ink-soft); font-family: inherit; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: border-color .16s, background .16s, color .16s;
    }
    .bl-slot-more:hover { border-color: var(--ok); background: #f4fbf8; color: var(--ok); }
    .bl-slot-more-plus {
        display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%;
        background: linear-gradient(160deg, #17a06a, var(--ok)); color: #fff;
        box-shadow: 0 4px 12px rgba(15, 122, 82, .28); transition: transform .16s;
    }
    .bl-slot-more-plus svg { width: 17px; height: 17px; }
    .bl-slot-more:hover .bl-slot-more-plus { transform: scale(1.08); }

    /* ---- Savol oynasi ---- */
    .bl-qmodal {
        position: fixed; inset: 0; z-index: 220; display: none;
        align-items: flex-start; justify-content: center; padding: 22px 14px;
        background: rgba(15, 39, 72, .55); overflow-y: auto;
    }
    .bl-qmodal.is-open { display: flex; }
    .bl-qmodal-box {
        width: min(1280px, 100%); overflow: hidden; border-radius: 12px; background: #fff;
        box-shadow: 0 24px 60px rgba(15, 39, 72, .35);
    }
    .bl-qmodal-head {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 22px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fdfefe, #f4f8fc);
    }
    .bl-qmodal-head h3 { margin: 0; color: var(--navy); font-family: inherit; font-size: 16px; font-weight: 600; }
    .bl-qmodal-head p { margin: 3px 0 0; color: var(--muted); font-size: 11.5px; }
    .bl-qmodal-x {
        margin-left: auto; width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px;
        background: #fff; color: var(--ink-soft); font-size: 20px; line-height: 1; cursor: pointer; transition: .16s;
    }
    .bl-qmodal-x:hover { border-color: var(--bad); background: var(--bad-bg); color: var(--bad); }
    .bl-qmodal-body { max-height: calc(100vh - 150px); overflow-y: auto; }
    .bl-qmodal-body .bl-q-del { display: flex; justify-content: flex-end; padding: 0 20px 18px; }

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
    .bl-num { color: var(--navy); font-family: inherit; font-size: 14px; font-weight: 600; }
    .bl-pill { display: inline-flex; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: .02em; }
    .bl-pill.ok { background: var(--ok-bg); color: var(--ok); }
    .bl-pill.off { background: #eef1f6; color: var(--muted); }
    .bl-pill.draft { background: var(--warn-bg); color: var(--warn); }
    .bl-t-draft { color: var(--warn); font-size: 12.5px; font-weight: 500; }

    /* Fan biriktirish oynasi */
    .bl-modal {
        position: fixed; inset: 0; z-index: 200; display: none;
        align-items: center; justify-content: center; padding: 20px;
        background: rgba(15, 39, 72, .55);
    }
    .bl-modal.is-open { display: flex; }
    .bl-modal-box {
        width: min(600px, 100%); max-height: calc(100vh - 48px); overflow-y: auto;
        border-radius: 12px; background: #fff; box-shadow: 0 24px 60px rgba(15, 39, 72, .35);
    }
    .bl-modal-head {
        display: flex; align-items: center; gap: 13px;
        padding: 17px 22px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fdfefe, #f4f8fc);
    }
    .bl-modal-head h3 { margin: 0; color: var(--navy); font-family: inherit; font-size: 16.5px; font-weight: 600; }
    .bl-modal-head p { margin: 3px 0 0; color: var(--ink-soft); font-size: 12px; }
    .bl-modal-icon {
        flex: none; display: grid; place-items: center; width: 38px; height: 38px;
        border-radius: 10px; background: linear-gradient(160deg, var(--navy-soft), var(--navy)); color: #fff;
        box-shadow: 0 4px 12px rgba(15, 39, 72, .22);
    }
    .bl-modal-icon svg { width: 18px; height: 18px; }
    .bl-modal-body { display: grid; gap: 16px; padding: 20px 22px; }
    .bl-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--line-soft); background: #fafcfe; }
    .bl-btn-warn { border-color: #e5cfa4; background: #fff; color: var(--warn); }
    .bl-btn-warn:hover { background: var(--warn-bg); }
    .bl-acts { display: inline-flex; align-items: center; justify-content: flex-end; gap: 7px; white-space: nowrap; }
    .bl-acts form { display: inline-flex; margin: 0; }

    /* ---- Ruxsat etilgan guruhlar ---- */
    .bl-groups {
        display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
        padding: 14px 22px; border: 1px solid var(--line);
        border-left: 4px solid var(--navy-soft); border-radius: 9px; background: #fff;
        box-shadow: 0 1px 2px rgba(15, 39, 72, .04);
    }
    .bl-groups-head {
        margin-right: 6px; color: var(--muted); font-size: 9.5px; font-weight: 700;
        letter-spacing: .13em; text-transform: uppercase;
    }
    .bl-group-chip {
        padding: 5px 13px; border: 1px solid #dbe6f4; border-radius: 999px;
        background: #f1f6fc; color: var(--navy); font-size: 12px; font-weight: 600;
    }
    .bl-groups-note { color: var(--warn); font-size: 12.5px; }
    .bl-groups-head b { margin-left: 6px; color: var(--navy); font-family: inherit; font-size: 12px; }
    option.bl-opt-empty { color: #9aa8bd; }

    /* ---- Xabar / bo'sh holat ---- */
    .bl-alert { display: flex; gap: 10px; padding: 14px 18px; border: 1px solid; border-left-width: 4px; border-radius: 9px; font-size: 13.5px; }
    .bl-alert.is-ok { border-color: #a5d6bf; border-left-color: var(--ok); background: var(--ok-bg); color: #0a6043; }
    .bl-alert.is-bad { border-color: #e8c3c0; border-left-color: var(--bad); background: var(--bad-bg); color: #8f1e18; }
    .bl-alert ul { margin: 6px 0 0; padding-left: 20px; }

    .bl-empty { padding: 52px 24px; border: 1px dashed #c9d5e4; border-radius: 10px; background: #fff; text-align: center; }
    .bl-empty b { display: block; color: var(--navy); font-family: inherit; font-size: 16px; font-weight: 600; }
    .bl-empty span { display: block; margin-top: 6px; color: var(--muted); font-size: 13px; }

    @media (max-width: 900px) {
        .bl-grid-2 { grid-template-columns: 1fr; }
        .bl-qf-top { grid-template-columns: 1fr; }
        .bl-qf-end { margin-left: 0; }
        .bl-drop { min-height: 76px; }
        .bl-q-meta { display: none; }
    }

    /* ============================================================
       Rasmiy ko'rinish qatlami — yuqoridagi bl-* tokenlar ustiga.
       HTML va JS tegilmaydi: faqat rang, tipografiya va fazo.
       ============================================================ */

    .bl {
        /* Universitet palitrasi: to'q ko'k + oltin. Sovuq kulrang o'rniga
           ozgina iliq ohang — sahifa "bo'sh Excel" emas, hujjatdek ko'rinsin. */
        --navy: #0e2342;
        --paper: #f7f9fc;
        --paper-warm: #fcfbf7;
        --gold-soft: #f2e4bd;
        --gold-deep: #a8871d;
        --shadow-sm: 0 1px 2px rgba(14, 35, 66, .05);
        --shadow-md: 0 2px 6px rgba(14, 35, 66, .06), 0 14px 34px rgba(14, 35, 66, .07);
        --shadow-lg: 0 30px 70px rgba(14, 35, 66, .28);
    }

    /* Sahifa foni — oq emas, juda yengil ko'kimtir qog'oz */
    .bl { background: transparent; }

    /* ---- Sarlavha: kuchliroq, gerbli ---- */
    .bl-head {
        padding: 26px 30px;
        border-left-width: 5px;
        border-radius: 10px;
        background:
            radial-gradient(circle at 92% 130%, rgba(201, 162, 39, .13), transparent 58%),
            linear-gradient(180deg, #ffffff, #f2f7fc);
        box-shadow: var(--shadow-md);
    }
    .bl-head::after { width: 210px; height: 168px; opacity: .06; }
    .bl-head-badge {
        width: 56px; height: 56px; border-radius: 13px;
        border-color: #d3e0f0;
        background: linear-gradient(160deg, #ffffff, #eaf1fa);
        box-shadow: 0 5px 14px rgba(14, 35, 66, .09), inset 0 1px 0 #fff;
    }
    .bl-head-badge svg { width: 26px; height: 26px; }
    .bl-eyebrow {
        display: inline-flex; align-items: center; gap: 7px;
        color: var(--gold-deep); letter-spacing: .22em;
    }
    /* Eyebrow oldidan qisqa oltin chiziq — rasmiy blank hissi */
    .bl-eyebrow::before {
        content: ''; width: 18px; height: 2px; border-radius: 2px;
        background: linear-gradient(90deg, var(--gold), var(--gold-soft));
    }
    .bl-head h1 { font-size: 25px; letter-spacing: -.02em; }
    .bl-head p { max-width: 56ch; font-size: 13.5px; line-height: 1.6; }
    .bl-headnum {
        padding: 12px 22px; border-radius: 11px; border-color: #d3e0f0;
        background: linear-gradient(180deg, #fff, #f7fafd);
        box-shadow: var(--shadow-sm);
    }
    .bl-headnum b { font-size: 28px; }

    /* ---- Panellar ---- */
    .bl-panel { border-radius: 10px; box-shadow: var(--shadow-md); }
    .bl-panel-head {
        padding: 17px 24px;
        background: linear-gradient(180deg, #ffffff, #f2f7fc);
        border-bottom-color: #e4ecf5;
    }
    /* Bosqich raqami — oltin kontur bilan */
    .bl-step {
        width: 36px; height: 36px; border-radius: 10px; font-size: 13.5px;
        box-shadow: 0 4px 12px rgba(14, 35, 66, .26), inset 0 0 0 1px rgba(201, 162, 39, .45);
    }
    .bl-panel-head h2 { font-size: 16px; letter-spacing: -.01em; }
    .bl-panel-head p { font-size: 12px; }
    .bl-panel-count {
        border-color: #d3e0f0; background: #f4f8fc;
        font-size: 11.5px; letter-spacing: .02em;
    }
    .bl-panel-body { padding: 22px 24px; }
    .bl-panel-foot { padding: 15px 24px; background: linear-gradient(180deg, #fafcfe, #f4f8fc); }

    /* ---- Maydonlar: yorliqlar aniqroq, inputlar tinchroq ---- */
    .bl-field > label, .bl-label {
        margin-bottom: 8px; font-size: 11.5px;
        letter-spacing: .04em; text-transform: uppercase;
    }
    .bl-field > label em { text-transform: none; letter-spacing: 0; }
    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']),
    .bl select, .bl textarea {
        border-color: #d8e2ee; border-radius: 9px; background: #fcfdff;
    }
    .bl input:not([type='checkbox']):not([type='radio']):not([type='file']), .bl select { height: 44px; }
    .bl input:focus, .bl select:focus, .bl textarea:focus {
        border-color: var(--navy-soft);
        box-shadow: 0 0 0 3px rgba(27, 58, 99, .12);
    }
    /* Fan tanlash — sahifadagi eng muhim maydon, oltin urg'u bilan ajratiladi.
       id bo'yicha: .bl-field panel ichida birinchi element emas. */
    .bl select#curriculum_subject_id { border-left: 3px solid var(--gold); padding-left: 12px; }

    /* ---- Tugmalar ---- */
    .bl-btn { height: 44px; border-radius: 9px; font-size: 13px; letter-spacing: .015em; }
    .bl-btn-main {
        background: linear-gradient(165deg, #1e4272, var(--navy));
        box-shadow: 0 5px 14px rgba(14, 35, 66, .24), inset 0 1px 0 rgba(255, 255, 255, .12);
    }
    .bl-btn-main:hover { box-shadow: 0 8px 20px rgba(14, 35, 66, .3); }
    .bl-btn-ghost { border-color: #d8e2ee; }
    .bl-btn-sm { height: 34px; border-radius: 8px; }

    /* ---- Belgilash kartochkalari ---- */
    .bl-check { padding: 13px 16px; border-radius: 10px; }
    .bl-check:has(input:checked) {
        border-color: #b9cde6; background: #eff5fc;
        box-shadow: 0 2px 8px rgba(14, 35, 66, .08), inset 3px 0 0 var(--gold);
    }

    /* ---- Jadval ---- */
    .bl-table th {
        padding: 11px 20px; background: #f4f8fc;
        font-size: 10px; letter-spacing: .14em; color: #7b8ca6;
    }
    .bl-table td { padding: 14px 20px; }
    .bl-table tbody tr:hover { background: #f7fafd; }
    .bl-pill { padding: 5px 13px; letter-spacing: .03em; }

    /* ---- Bo'sh holat: chiroyliroq ---- */
    .bl-empty {
        padding: 58px 26px; border-color: #d3dfec; border-radius: 11px;
        background: linear-gradient(180deg, #ffffff, #fafcfe);
    }
    .bl-empty b { font-size: 17px; }

    /* ---- Guruhlar paneli ---- */
    .bl-groups { padding: 15px 24px; border-radius: 10px; box-shadow: var(--shadow-sm); }
    .bl-group-chip { border-color: #d3e0f0; background: #f1f6fc; }

    /* ---- Oynalar ---- */
    .bl-modal-box, .bl-qmodal-box { border-radius: 13px; box-shadow: var(--shadow-lg); }
    .bl-modal-head, .bl-qmodal-head {
        padding: 18px 24px;
        background: linear-gradient(180deg, #ffffff, #f2f7fc);
        border-bottom-color: #e4ecf5;
    }

    /* ---- Tipografiya: shrift LMS sahifasidan meros olinadi ---- */
    .bl h1, .bl h2, .bl .slab { letter-spacing: -.015em; font-feature-settings: 'ss01'; }
    .bl-head h1 { font-size: 26px; font-weight: 600; }
    .bl-panel-head h2 { font-size: 16.5px; font-weight: 600; }
    .bl-headnum b, .bl-num, .bl-slot-no, .bl-pair-no, .bl-step-no, .bl-pick span { font-weight: 600; }
    /* Raqamlar bir xil kenglikda tursin — jadval va hisoblagichlar tekis */
    .bl-headnum b, .bl-num, .bl-panel-count, .bl-slot-no, .bl-table td { font-variant-numeric: tabular-nums; }

    /* Oyna orqasi: sahifa xiralashib qoraysin (.bl ning umumiy foni bosib ketmasligi uchun aniq selektor) */
    .bl.bl-modal, .bl.bl-qmodal {
        background: rgba(15, 39, 72, .45);
        -webkit-backdrop-filter: blur(6px);
        backdrop-filter: blur(6px);
    }

    /* ---- Kompyuterlarda boshlash oynasi ---- */
    .bl-btn-launch { background: #1d4ed8; color: #fff; box-shadow: 0 4px 12px rgba(29, 78, 216, .22); }
    .bl-btn-launch:hover { background: #1e40af; }
    .bl-run-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 4px; padding: 2px 8px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 11.5px; font-weight: 700; }
    .bl-run-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #2563eb; }
    .lm-box { width: min(760px, 100%); }
    .lm-body { max-height: min(62vh, 560px); overflow-y: auto; padding: 16px 22px; display: grid; gap: 12px; }
    .lm-room { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
    .lm-room-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 14px; background: #f5f8fc; border-bottom: 1px solid var(--line); cursor: pointer; }
    .lm-room-head label { display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--navy); font-size: 14.5px; font-weight: 700; }
    .lm-room-head small { color: var(--muted); font-size: 12px; }
    .lm-devs { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; padding: 10px; }
    .lm-dev { display: flex; align-items: flex-start; gap: 9px; padding: 9px 10px; border: 1px solid var(--line); border-radius: 9px; background: #fff; cursor: pointer; }
    .lm-dev:hover { border-color: #93c5fd; }
    .lm-dev.is-busy { background: #f8fafc; cursor: not-allowed; opacity: .75; }
    .lm-dev.is-mine { border-color: #93c5fd; background: #eff6ff; }
    .lm-dev input, .lm-room-head input { width: 17px; height: 17px; margin-top: 1px; accent-color: #1d4ed8; flex: none; }
    .lm-dev b { display: block; color: var(--ink); font-size: 13.5px; font-weight: 700; }
    .lm-dev span { display: block; margin-top: 2px; color: var(--muted); font-size: 11.5px; }
    .lm-dot { display: inline-block; width: 7px; height: 7px; margin-right: 5px; border-radius: 50%; background: #cbd5e1; vertical-align: 1px; }
    /* Kompyuter tiklash kodi — o'qituvchi talabaga aytadi */
    .lm-code { display: inline-block; margin-left: 7px; padding: 1px 7px; border-radius: 5px; background: #fef3c7; color: #92400e; font-size: 11px; font-style: normal; font-weight: 800; letter-spacing: .08em; vertical-align: 1px; white-space: nowrap; }
    .lm-dot.on { background: #16a34a; }
    .lm-count { margin-right: auto; color: var(--ink-soft); font-size: 13px; font-weight: 600; }
    .lm-empty { padding: 26px 10px; color: var(--muted); font-size: 13.5px; text-align: center; }

    /* Boshlash rejimi: kompyuterlar / umumiy havola */
    .lm-modes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 16px 22px 0; }
    .lm-mode { display: grid; gap: 3px; padding: 11px 14px; border: 1px solid var(--line); border-radius: 10px; background: #fff; text-align: left; font-family: inherit; cursor: pointer; transition: border-color .16s, background .16s; }
    .lm-mode b { color: var(--navy); font-size: 13.5px; }
    .lm-mode span { color: var(--muted); font-size: 11.5px; line-height: 1.4; }
    .lm-mode:hover { border-color: #93c5fd; }
    .lm-mode.is-on { border-color: #1d4ed8; background: #eff6ff; box-shadow: inset 0 0 0 1px #1d4ed8; }
    .lm-public p { margin: 0; color: var(--ink-soft); font-size: 13.5px; line-height: 1.6; }
    .lm-public .lm-note { color: var(--muted); font-size: 12px; }
    .lm-link { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 6px; }
    .lm-link span { color: var(--muted); font-size: 12px; font-weight: 700; }
    .lm-link code { padding: 5px 10px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; color: var(--navy); font-size: 13px; word-break: break-all; }
    .bl-run-badge.is-public { background: #ecfdf5; color: #047857; }
    /* Yangi to'plam — accordion: sarlavha bosilganda ochiladi */
    .bl-create > .bl-create-sum { display: block; list-style: none; cursor: pointer; border-radius: 10px; }
    .bl-create > .bl-create-sum::-webkit-details-marker { display: none; }
    .bl-create > .bl-create-sum:focus-visible { outline: 2px solid #1e4272; outline-offset: 3px; }
    .bl-create > .bl-create-sum .bl-head { transition: box-shadow .15s, transform .15s; }
    .bl-create > .bl-create-sum:hover .bl-head { box-shadow: 0 10px 26px rgba(14, 35, 66, .12); }
    .bl-create[open] > .bl-panel, .bl-create[open] > .bl-empty { margin-top: 14px; }
    .bl-create-toggle {
        position: relative; z-index: 1; flex: none; display: inline-flex; align-items: center;
        padding: 10px 18px; border-radius: 10px; background: linear-gradient(165deg, #1e4272, #0e2342);
        color: #fff; font-size: 13.5px; font-weight: 700; box-shadow: 0 5px 14px rgba(14, 35, 66, .24);
    }
    .bl-create-toggle > span { display: inline-flex; align-items: center; gap: 8px; }
    .bl-create-toggle svg { width: 16px; height: 16px; }
    .bl-create-toggle .is-open { display: none; }
    .bl-create[open] .bl-create-toggle { background: #fff; color: #0e2342; border: 1px solid #d8e2ee; box-shadow: none; }
    .bl-create[open] .bl-create-toggle .is-open { display: inline-flex; }
    .bl-create[open] .bl-create-toggle .is-closed { display: none; }
    /* Mavzu raqami */
    .bl-topic input { max-width: 220px; }
    .bl-topic-badge { display: inline-block; margin-right: 6px; padding: 1px 8px; border-radius: 6px; background: #e0e7ff; color: #3730a3; font-size: 11.5px; font-weight: 800; white-space: nowrap; vertical-align: 1px; }
    .bl-topic-badge.is-missing { background: #fef3c7; color: #92400e; font-weight: 700; }
    .bl-run-badge.is-retake { background: #f5f3ff; color: #6d28d9; white-space: nowrap; }

    /* To'plamlar tablari: asosiy — to'q ko'k, qayta topshirish — binafsha */
    .bl-ctabs { display: flex; flex-wrap: wrap; gap: 10px; }
    .bl-ctab {
        --c: #0e2342; --ct: #eef3f9;
        display: inline-flex; align-items: center; gap: 9px; padding: 12px 22px;
        border: 2px solid var(--c); border-radius: 11px; background: #fff; color: var(--c);
        font-family: inherit; font-size: 15px; font-weight: 800; cursor: pointer;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .08);
        transition: background .15s, color .15s, border-color .15s, box-shadow .15s;
    }
    .bl-ctab:not(.is-on):hover { background: var(--ct); }
    .tab-nb { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; background: #dc2626; color: #fff; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; }
    .bl-ctab.is-retake { --c: #7c3aed; --ct: #f5f3ff; }
    .bl-ctab.is-on { background: var(--c); border-color: var(--c); color: #fff; box-shadow: 0 6px 16px rgba(15, 23, 42, .16); }
    .bl-ctab:focus-visible { outline: 2px solid var(--c); outline-offset: 2px; }
    .bl-ctab-n { display: inline-grid; place-items: center; min-width: 24px; height: 22px; padding: 0 7px; border-radius: 999px; background: rgba(15, 23, 42, .08); font-size: 12px; }
    .bl-ctab.is-on .bl-ctab-n { background: rgba(255, 255, 255, .22); }
    .bl-retake-note { margin: 0; padding: 12px 22px; border-bottom: 1px solid var(--line-soft); background: #faf8ff; color: #4c1d95; font-size: 13px; line-height: 1.55; }
    .bl-btn-retake { background: #7c3aed; color: #fff; box-shadow: 0 4px 12px rgba(124, 58, 237, .25); }
    .bl-btn-retake:hover { background: #6d28d9; }
    .lm-box.is-retake .bl-modal-icon { background: #f5f3ff; color: #7c3aed; }
    .mn-flag.retake { background: #ede9fe; color: #6d28d9; }

    /* Kompyuterlar oynasi: tablar va kirish so'rovlari */
    .mn-tabs { display: flex; gap: 4px; padding: 10px 22px 0; border-bottom: 1px solid var(--line-soft); background: #fafcfe; }
    .mn-tab { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border: 0; border-bottom: 2px solid transparent; background: none; color: var(--ink-soft); font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; }
    .mn-tab:hover { color: var(--navy); }
    .mn-tab.is-on { color: #1d4ed8; border-bottom-color: #1d4ed8; }
    .mn-badge { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 800; }
    .mn-public { padding: 12px 14px; border: 1px solid #a7f3d0; border-radius: 10px; background: #ecfdf5; color: #065f46; font-size: 13px; }
    .mn-public b { color: #047857; }
    .mn-public.off { border-color: var(--line); background: #f8fafc; color: var(--muted); }
    .mn-elist { display: grid; gap: 8px; }
    .mn-entry { display: grid; gap: 9px; padding: 10px 14px; border: 1px solid var(--line); border-left: 4px solid #cbd5e1; border-radius: 10px; background: #fff; }
    .mn-ehead { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .mn-eprog { display: grid; gap: 7px; }
    .mn-entry .mn-acts:empty { display: none; }
    .mn-entry.pending { border-left-color: #d97706; background: #fffbeb; }
    .mn-entry.approved { border-left-color: #0f7a52; }
    .mn-entry.used { border-left-color: #2563eb; }
    .mn-entry.rejected, .mn-entry.expired { border-left-color: #94a3b8; opacity: .7; }
    /* Testga kirganidan keyingi jonli bosqich — kompyuter kartochkasi ranglari */
    .mn-entry.st-take { border-left-color: #2563eb; }
    .mn-entry.st-take .mn-stage { background: #dbeafe; color: #1e40af; }
    .mn-entry.st-result { border-left-color: #0f7a52; }
    .mn-entry.st-result .mn-stage { background: #dcfce7; color: #166534; }
    .mn-entry.st-offline { border-left-color: #94a3b8; background: #fbfcfd; }
    .mn-entry.st-locked, .mn-entry.st-ended_away { border-color: #fca5a5; border-left-color: #dc2626; background: #fef2f2; }
    .mn-entry.st-locked .mn-stage, .mn-entry.st-ended_away .mn-stage { background: #dc2626; color: #fff; }
    .mn-emeta { display: grid; gap: 3px; justify-items: end; text-align: right; }
    .mn-ewhen { color: var(--muted); font-size: 11.5px; }
    .mn-epill { display: inline-block; padding: 2px 9px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .mn-epill.pending { background: #fef3c7; color: #92400e; }
    .mn-epill.approved { background: #dcfce7; color: #166534; }
    .mn-epill.used { background: #dbeafe; color: #1e40af; }
    .mn-epill.rejected { background: #fee2e2; color: #991b1b; }
    .bl-btn-ok { background: #0f7a52; color: #fff; }
    .bl-btn-ok:hover { background: #0c6443; }
    @media (max-width: 640px) { .lm-modes { grid-template-columns: 1fr; } .mn-ehead { flex-direction: column; align-items: flex-start; } .mn-emeta { justify-items: start; text-align: left; } }

    /* ---- Kompyuterlar (jonli kuzatuv) oynasi ---- */
    .bl-btn-monitor { background: #0f766e; color: #fff; box-shadow: 0 4px 12px rgba(15, 118, 110, .22); }
    .bl-btn-monitor:hover { background: #115e59; }
    .mn-box { width: min(1240px, 100%); display: flex; flex-direction: column; max-height: calc(100vh - 40px); overflow: hidden; }
    .mn-head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .mn-sum { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 22px; border-bottom: 1px solid var(--line-soft); background: #fafcfe; }
    .mn-chip { display: inline-flex; align-items: baseline; gap: 6px; padding: 5px 11px; border-radius: 999px; background: #eef3f9; color: var(--ink-soft); font-size: 12px; }
    .mn-chip b { color: var(--navy); font-size: 14px; font-variant-numeric: tabular-nums; }
    .mn-chip.take b { color: #1d4ed8; } .mn-chip.done b { color: #0f7a52; } .mn-chip.off b { color: #94a3b8; }
    .mn-live { margin-left: auto; align-self: center; color: var(--muted); font-size: 11.5px; }
    .mn-live::before { content: ''; display: inline-block; width: 7px; height: 7px; margin-right: 6px; border-radius: 50%; background: #16a34a; animation: mnPulse 1.6s ease-in-out infinite; vertical-align: 1px; }
    @keyframes mnPulse { 50% { opacity: .3; } }
    @media (prefers-reduced-motion: reduce) { .mn-live::before { animation: none; } }
    .mn-body { flex: 1; overflow-y: auto; padding: 16px 22px; display: grid; gap: 16px; }
    .mn-room-title { margin: 0 0 8px; color: var(--navy); font-size: 13.5px; font-weight: 700; }
    .mn-room-title small { margin-left: 6px; color: var(--muted); font-size: 12px; font-weight: 500; }
    .mn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .mn-card { display: flex; flex-direction: column; gap: 9px; padding: 12px 13px; border: 1px solid var(--line); border-left: 4px solid #cbd5e1; border-radius: 10px; background: #fff; }
    .mn-card.take { border-left-color: #2563eb; } .mn-card.face, .mn-card.start, .mn-card.wait, .mn-card.restarting { border-left-color: #d97706; }
    .mn-card.result { border-left-color: #0f7a52; } .mn-card.offline, .mn-card.closed { border-left-color: #94a3b8; background: #fbfcfd; }
    .mn-card.stopped { border-left-color: #b3261e; background: #fffafa; }
    .mn-card.locked, .mn-card.ended_away { border-color: #fca5a5; border-left-color: #dc2626; background: #fef2f2; box-shadow: 0 0 0 1px #fecaca; }
    .mn-card.locked .mn-stage, .mn-card.ended_away .mn-stage { background: #dc2626; color: #fff; }
    .mn-lock { padding: 7px 9px; border-radius: 7px; background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 600; line-height: 1.4; }
    .bl-btn-unlock { border-color: #15803d; background: #16a34a; color: #fff; }
    .bl-btn-unlock:hover { background: #15803d; }
    .mn-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .mn-name { display: flex; align-items: center; gap: 7px; color: var(--ink); font-size: 14px; font-weight: 700; }
    .mn-stage { flex: none; padding: 2px 9px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .mn-card.take .mn-stage { background: #dbeafe; color: #1e40af; }
    .mn-card.face .mn-stage, .mn-card.start .mn-stage, .mn-card.wait .mn-stage, .mn-card.restarting .mn-stage { background: #fef3c7; color: #92400e; }
    .mn-card.result .mn-stage { background: #dcfce7; color: #166534; }
    .mn-card.stopped .mn-stage { background: #fee2e2; color: #991b1b; }
    .mn-student b { display: block; color: var(--navy); font-size: 13.5px; font-weight: 700; line-height: 1.3; }
    .mn-student span { display: block; margin-top: 2px; color: var(--muted); font-size: 11.5px; }
    .mn-student.none { color: var(--muted); font-size: 12.5px; font-style: italic; }
    .mn-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--ink-soft); font-size: 12px; font-variant-numeric: tabular-nums; }
    .mn-meta b { color: var(--navy); }
    .mn-meta .warn { color: #a35a06; font-weight: 700; } .mn-meta .bad { color: #b3261e; font-weight: 700; }
    .mn-meta .ok { color: #0f7a52; font-weight: 700; }
    .mn-bar { height: 5px; border-radius: 999px; background: #edf1f7; overflow: hidden; }
    .mn-bar i { display: block; height: 100%; border-radius: inherit; background: #2563eb; }
    .mn-acts { display: flex; gap: 6px; margin-top: auto; }
    .mn-acts .bl-btn { flex: 1; justify-content: center; }
    .mn-acts .bl-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .mn-toast { margin-right: auto; color: var(--ink-soft); font-size: 12.5px; }
    .mn-toast.bad { color: #b3261e; }

    /* ============================================================
       "Kompyuterlar" oynasi — kattaroq oyna, rangli kartochkalar.
       Har bosqich o'z rangini --c (asosiy), --t (fon), --s/--sc (belgi) orqali beradi.
       ============================================================ */
    .mn-box { width: min(1600px, calc(100vw - 32px)); height: calc(100vh - 32px); max-height: none; }
    .mn-body { padding: 18px 24px 22px; gap: 22px; align-content: start; background: #f6f8fb; }
    .mn-room-title { display: flex; align-items: baseline; gap: 8px; margin: 0 0 10px; font-size: 15px; }
    .mn-room-title small { margin-left: 0; }
    .mn-grid { grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 14px; }

    .mn-sum { gap: 8px; padding: 12px 24px; }
    .mn-chip { padding: 6px 12px; font-size: 12.5px; font-weight: 600; }
    .mn-chip b { font-size: 15px; }
    .mn-chip.take { background: #dbeafe; color: #1e40af; } .mn-chip.take b { color: #1d4ed8; }
    .mn-chip.done { background: #d1fae5; color: #065f46; } .mn-chip.done b { color: #047857; }
    .mn-chip.wait { background: #fef3c7; color: #92400e; } .mn-chip.wait b { color: #b45309; }
    .mn-chip.off { background: #eef2f6; color: #64748b; }

    .mn-card, .mn-card[class] {
        --c: #94a3b8; --t: #f8fafc; --s: #e2e8f0; --sc: #475569;
        min-width: 0; gap: 0; padding: 0; overflow: hidden;
        border: 1px solid #e2e8f0; border-top: 4px solid var(--c); border-radius: 12px;
        background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }
    .mn-card.take { --c: #2563eb; --t: #eff6ff; --s: #dbeafe; --sc: #1e40af; }
    .mn-card.face, .mn-card.start, .mn-card.wait, .mn-card.restarting, .mn-card.idle { --c: #d97706; --t: #fffbeb; --s: #fde68a; --sc: #92400e; }
    .mn-card.result { --c: #059669; --t: #ecfdf5; --s: #a7f3d0; --sc: #065f46; }
    .mn-card.offline, .mn-card.closed { --c: #94a3b8; --t: #f1f5f9; --s: #e2e8f0; --sc: #475569; }
    .mn-card.stopped { --c: #b91c1c; --t: #fef2f2; --s: #fecaca; --sc: #991b1b; }
    .mn-card.locked, .mn-card.ended_away { --c: #dc2626; --t: #fee2e2; --s: #dc2626; --sc: #fff; border-color: #fca5a5; border-top-color: var(--c); box-shadow: 0 0 0 2px #fecaca; }

    /* Sarlavha: nom + kod chapda, bosqich o'ngda — sig'masa bosqich pastki qatorga tushadi */
    .mn-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 10px; padding: 11px 14px; background: var(--t); border-bottom: 1px solid rgba(15, 23, 42, .06); }
    .mn-name { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; min-width: 0; color: var(--ink); font-size: 15.5px; font-weight: 800; }
    .mn-pc { white-space: nowrap; }
    .mn-name .lm-code { margin-left: 0; }
    .mn-card .mn-stage, .mn-card[class] .mn-stage { flex: none; max-width: 100%; padding: 4px 11px; border-radius: 999px; background: var(--s); color: var(--sc); font-size: 11.5px; font-weight: 800; white-space: normal; text-align: center; line-height: 1.25; }

    .mn-cbody { flex: 1; display: grid; align-content: start; gap: 10px; padding: 13px 14px; }
    .mn-student b { font-size: 14.5px; overflow-wrap: anywhere; }
    .mn-student span { overflow-wrap: anywhere; }
    .mn-student.none { padding: 6px 0; }
    .mn-meta { gap: 6px 14px; font-size: 12.5px; }
    .mn-meta span { overflow-wrap: anywhere; }
    .mn-bar { height: 7px; }
    .mn-bar i { background: linear-gradient(90deg, #60a5fa, #2563eb); }
    .mn-lock { overflow-wrap: anywhere; }

    /* Natija — katta, rangli */
    .mn-result { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 12px; border-radius: 10px; }
    .mn-result b { font-size: 24px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .mn-result span { font-size: 13px; font-weight: 800; letter-spacing: .02em; }
    .mn-result.ok { background: #d1fae5; color: #047857; }
    .mn-result.bad { background: #fee2e2; color: #b91c1c; }

    /* Belgilar — kichik rangli yorliqlar */
    .mn-flags { display: flex; flex-wrap: wrap; gap: 6px; }
    .mn-flag { padding: 3px 9px; border-radius: 6px; background: #f1f5f9; color: #475569; font-size: 11.5px; font-weight: 700; }
    .mn-flag.warn { background: #fef3c7; color: #92400e; }
    .mn-flag.bad { background: #fee2e2; color: #b91c1c; }

    /* Tugmalar — 2 ustunli to'r, matn sig'masa qatorga o'raladi */
    .mn-foot { display: grid; gap: 8px; padding: 11px 14px 13px; border-top: 1px solid #eef2f6; background: #fafbfd; }
    .mn-foot .mn-acts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 0; }
    .mn-foot .mn-acts.one { grid-template-columns: 1fr; }
    .mn-foot .mn-acts .bl-btn { width: 100%; height: auto; min-height: 36px; padding: 7px 10px; white-space: normal; line-height: 1.25; text-align: center; font-size: 12px; }

    /* Amal tugmalari — har biri o'z rangida: --b (asosiy), --bt (och fon), --bb (hoshiya) */
    .mn-acts .bl-btn[data-act] {
        --b: #475569; --bt: #f1f5f9; --bb: #cbd5e1;
        border: 1px solid var(--bb); background: var(--bt); color: var(--b);
        font-weight: 700; box-shadow: none;
        transition: background .15s, color .15s, border-color .15s, box-shadow .15s;
    }
    .mn-acts .bl-btn[data-act]:not([disabled]):hover { background: var(--b); border-color: var(--b); color: #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, .15); }
    .mn-acts .bl-btn[data-act]:focus-visible { outline: 2px solid var(--b); outline-offset: 2px; }
    .mn-acts .bl-btn[data-act="stop"], .mn-acts .bl-btn[data-act="stop-entry"], .mn-acts .bl-btn[data-act="reject"] { --b: #dc2626; --bt: #fef2f2; --bb: #fca5a5; }
    .mn-acts .bl-btn[data-act="restart"] { --b: #2563eb; --bt: #eff6ff; --bb: #93c5fd; }
    .mn-acts .bl-btn[data-act="bypass"] { --b: #7c3aed; --bt: #f5f3ff; --bb: #c4b5fd; }
    /* Faol holatdagilar — to'liq rangli */
    .mn-acts .bl-btn[data-act="bypass"][data-on="1"] { background: #7c3aed; border-color: #7c3aed; color: #fff; }
    .mn-acts .bl-btn[data-act="unlock"], .mn-acts .bl-btn[data-act="approve"] { --b: #15803d; --bt: #16a34a; --bb: #16a34a; color: #fff; box-shadow: 0 3px 10px rgba(22, 163, 74, .28); }
    .mn-acts .bl-btn[data-act][disabled] { --b: #94a3b8; --bt: #f8fafc; --bb: #e2e8f0; opacity: 1; color: #a3b0c2; }

    /* Tablar — katta, rangli: Kompyuterlar ko'k, Kirish so'rovlari moviy-yashil */
    .mn-tabs, .mn-tabs[class] { gap: 10px; padding: 14px 24px; border-bottom: 1px solid var(--line-soft); background: #fff; }
    .mn-tab, .mn-tab[class] {
        --c: #2563eb; --ct: #eff6ff;
        gap: 9px; padding: 11px 22px; border: 2px solid var(--ct); border-radius: 11px;
        background: var(--ct); color: var(--c); font-size: 15px; font-weight: 800;
        transition: background .15s, color .15s, border-color .15s, box-shadow .15s;
    }
    .mn-tab[data-tab="entries"] { --c: #0f766e; --ct: #ecfdf5; }
    .mn-tab[class]:hover { border-color: var(--c); color: var(--c); }
    .mn-tab[class].is-on { background: var(--c); border-color: var(--c); color: #fff; box-shadow: 0 6px 16px rgba(15, 23, 42, .16); }
    .mn-tab:focus-visible { outline: 2px solid var(--c); outline-offset: 2px; }
    .mn-tab svg { width: 18px; height: 18px; flex: none; }
    .mn-tab .mn-badge { min-width: 22px; height: 22px; font-size: 12px; }
    .mn-tab.is-on .mn-badge { background: #fff; color: #dc2626; }
</style>

@php
    // Fan ro'yxati yorlig'i: nomi · semestr · reja — N guruh: nomlari.
    // Ikkala tanlov ro'yxatida bir xil ko'rinishi uchun shu yerda hisoblanadi.
    $subjectOptionLabel = function ($subject) {
        $count = (int) ($subject->group_count ?? 0);
        $names = array_values(array_filter(
            array_map('trim', explode(',', (string) ($subject->group_names ?? '')))
        ));
        // Ro'yxat cho'zilib ketmasligi uchun 6 tadan keyingisi soni bilan
        $shown = array_slice($names, 0, 6);
        $rest = count($names) - count($shown);
        $groups = $count > 0
            ? $count . ' guruh' . ($shown ? ': ' . implode(', ', $shown) . ($rest > 0 ? ' +' . $rest : '') : '')
            : "guruh yo'q";

        return collect([
            $subject->subject_name,
            $subject->semester_name,
            $subject->curriculum_label ?? null,
        ])->filter()->implode(' · ') . ' — ' . $groups;
    };
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
        @if(session('error'))
            <div class="bl-alert is-bad">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bl-alert is-bad">
                Ma'lumotlarni tekshiring:
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Yangi to'plam: yig'ilgan holda turadi, sarlavha ustiga bosilganda ochiladi.
             Xato bilan qaytsa yoki hali to'plam yo'q bo'lsa — ochiq. Tahrirlashda doim ochiq. --}}
        @if(!$isEdit)
            <details class="bl-create" id="blCreate" {{ ($errors->any() || ($collections ?? collect())->isEmpty()) ? 'open' : '' }}>
            <summary class="bl-create-sum">
        @endif
        <div class="bl-head">
            <div class="bl-head-main">
                <span class="bl-head-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M19 8v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5Z"/></svg></span>
                <div>
                    <div class="bl-eyebrow">Test moduli</div>
                    @if($isEdit)
                        <a href="{{ route('teacher.fan-testlari.index') }}" class="bl-back">&larr; Test yaratish</a>
                    @endif
                    <h1>{{ $isEdit ? "Test to'plamini tahrirlash" : "Yangi test to'plami" }}</h1>
                    <p>Dars jadvali tayyor bo'lmasdan test savollarini oldindan yig'ing.</p>
                </div>
            </div>
            @if($isEdit)
                <div class="bl-headnum">
                    <b>{{ count($questions) }}</b>
                    <span>Savol tayyor</span>
                </div>
            @else
                <span class="bl-create-toggle" aria-hidden="true">
                    <span class="is-closed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>Yangi to'plam yaratish</span>
                    <span class="is-open"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 15 6-6 6 6"/></svg>Yopish</span>
                </span>
            @endif
        </div>
        @if(!$isEdit)
            </summary>
        @endif

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
                    <label for="curriculum_subject_id"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3H19v15H5.5A2.5 2.5 0 0 0 3 20.5V5.5Z"/><path d="M3 20.5A2.5 2.5 0 0 1 5.5 18H19v3H5.5A2.5 2.5 0 0 1 3 20.5Z"/></svg> Fan <em>(keyinroq biriktirsa ham bo'ladi)</em></label>
                    <select name="curriculum_subject_id" id="curriculum_subject_id">
                        <option value="">— Hozircha biriktirilmasin (qoralama) —</option>
                        @foreach($subjects as $subject)
                            @php
                                $subjectGroups = (int) ($subject->group_count ?? 0);
                                $subjectLabel = $subjectOptionLabel($subject);
                            @endphp
                            <option value="{{ $subject->id }}" @selected((int) old('curriculum_subject_id', $collection?->curriculum_subject_id) === (int) $subject->id) @class(['bl-opt-empty' => $subjectGroups < 1])>
                                {{ $subjectLabel }}
                            </option>
                        @endforeach
                    </select>
                    <p class="bl-note">Ro'yxatda shu semestrda dars jadvaliga qo'yilgan fanlaringiz turadi.
                        Jadval hali tayyor bo'lmasa fanni bo'sh qoldiring — to'plam qoralama bo'lib turadi,
                        savollarni hozirdan kiritaverasiz. Fan biriktirilgach uni talabalarga ochish mumkin bo'ladi.</p>
                    @if($subjects->isEmpty())
                        <p class="bl-note is-warn">Shu semestrda dars jadvalida sizga qo'yilgan fan topilmadi.
                            Fanni bo'sh qoldirib, savollarni hozirdan kiritishingiz mumkin.</p>
                    @endif
                </div>

                <div class="bl-field">
                    <label for="name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg> Test to'plami nomi</label>
                    <input id="name" name="name" required maxlength="255" value="{{ old('name', $collection?->name) }}" placeholder="Masalan: 1-mavzu nazorat testi">
                </div>

                <div class="bl-field bl-topic">
                    <label for="topic_number"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/></svg> Mavzu raqami</label>
                    <input id="topic_number" name="topic_number" type="number" min="1" max="500" required inputmode="numeric"
                           value="{{ old('topic_number', $collection?->topic_number) }}" placeholder="Masalan: 3">
                    <span class="bl-hint">KTR (kalendar-tematik reja) bo'yicha mavzu tartib raqami.</span>
                </div>

                <div class="bl-grid bl-grid-2">
                    <div class="bl-field">
                        <label for="duration_minutes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Vaqt (daqiqa)</label>
                        <input id="duration_minutes" type="number" name="duration_minutes" min="1" max="300" required value="{{ old('duration_minutes', $collection?->duration_minutes ?? 20) }}">
                    </div>
                    <div class="bl-field">
                        <label for="pass_percent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg> O'tish foizi</label>
                        <input id="pass_percent" type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $collection?->pass_percent ?? 60) }}">
                    </div>
                    <div class="bl-field">
                        <label for="questions_per_attempt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h5"/></svg> Talabaga beriladigan savollar</label>
                        <input id="questions_per_attempt" type="number" name="questions_per_attempt" min="1" max="500" placeholder="hammasi"
                               value="{{ old('questions_per_attempt', $collection ? $collection->questions_per_attempt : 25) }}">
                        <span class="bl-hint">To'plamda bundan ko'p savol bo'lsa, har talabaga tasodifiy shuncha savol tushadi (har safar boshqacha). Bo'sh — hammasi.</span>
                    </div>
                </div>

                <div class="bl-field bl-span">
                    <label for="description"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 11h16M4 16h9"/></svg> Tavsif</label>
                    <textarea id="description" name="description" rows="2" placeholder="Test to'plami haqida qisqacha izoh...">{{ old('description', $collection?->description) }}</textarea>
                </div>

                <div class="bl-checks bl-span">
                    @php
                        $toggleIcons = [
                            'shuffle_questions' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="m15 15 6 6M4 4l5 5"/></svg>',
                            'show_result_after_submit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
                            'is_active' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
                        ];
                    @endphp
                    @foreach($toggles as $toggle)
                        <label class="bl-check">
                            <input type="hidden" name="{{ $toggle['field'] }}" value="0">
                            <input type="checkbox" name="{{ $toggle['field'] }}" value="1" @checked(old($toggle['field'], $toggle['checked']))>
                            {!! $toggleIcons[$toggle['field']] ?? '' !!}
                            <span>{{ $toggle['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="bl-panel-foot">
                <button class="bl-btn bl-btn-main">{!! $isEdit ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>' !!}{{ $isEdit ? 'Sozlamalarni saqlash' : "To'plamni yaratish" }}</button>
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
                    @if(!$collection->curriculum_subject_id)
                        <span class="bl-groups-note">To'plam qoralama — fan biriktirilgach guruhlar shu yerda ko'rinadi.</span>
                    @else
                        <span class="bl-groups-note">Bu fan-semestr-reja uchun guruh biriktirilmagan — testni istalgan talaba ishlay oladi. Ro'yxatdan guruhi bor variantni tanlang.</span>
                    @endif
                @endforelse
            </div>

            {{-- 02 · Savollar katakchalari --}}
            @php
                // Kamida 20 ta, lekin to'ldirilganidan keyin ham bo'sh
                // katakcha qolsin — 25-30 savolli to'plamlar uchun.
                $slotCount = max(20, count($questions) + 5);
                $typeLabels = [
                    'single_choice' => "Bitta to'g'ri javob",
                    'multiple_choice' => "Bir nechta to'g'ri javob",
                    'true_false' => "To'g'ri / Noto'g'ri",
                    'fill_in_blank' => "Bo'sh joyni to'ldirish",
                    'matching' => 'Moslashtirish',
                    'ordering' => 'Ketma-ketlik',
                ];
            @endphp
            <div class="bl-panel" id="savollar">
                <div class="bl-panel-head">
                    <span class="bl-step">02</span>
                    <div>
                        <h2>Savollar</h2>
                        <p>Bo'sh katakchadagi <b>+</b> ni bosing — savol oynasi ochiladi.</p>
                    </div>
                    <span class="bl-panel-count" id="slotCount">{{ count($questions) }} / {{ $slotCount }}</span>
                </div>

                <div class="bl-panel-body">
                    <div class="bl-slots">
                        @foreach($questions as $index => $question)
                            @php
                                $preview = trim(strip_tags((string) ($question['prompt'] ?? '')));
                                $typeLabel = $typeLabels[$question['type'] ?? 'single_choice'] ?? $typeLabels['single_choice'];
                            @endphp
                            <button type="button" class="bl-slot is-filled" data-q-open="{{ $index }}">
                                <span class="bl-slot-top">
                                    <span class="bl-slot-no">{{ $index + 1 }}-savol</span>
                                    <span class="bl-slot-tick">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                    </span>
                                </span>
                                <span class="bl-slot-text {{ $preview === '' ? 'is-empty' : '' }}">{{ $preview !== '' ? \Illuminate\Support\Str::limit($preview, 90) : 'Savol matni kiritilmagan' }}</span>
                                <span class="bl-slot-meta">{{ $typeLabel }} · {{ $question['points'] ?? 1 }} ball</span>
                            </button>
                        @endforeach

                        @for($slot = count($questions); $slot < $slotCount; $slot++)
                            <button type="button" class="bl-slot is-empty" data-q-open="new">
                                <span class="bl-slot-top">
                                    <span class="bl-slot-no">{{ $slot + 1 }}-savol</span>
                                </span>
                                <span class="bl-slot-plus">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </button>
                        @endfor
                    </div>

                    <button type="button" class="bl-slot-more" id="addSlots">
                        <span class="bl-slot-more-plus">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                        <span>Yana savol qo'shish</span>
                    </button>
                </div>
            </div>

            {{-- Savol oynasi --}}
            <div class="bl-qmodal" id="qModal">
                <div class="bl-qmodal-box">
                    <div class="bl-qmodal-head">
                        <span class="bl-step" id="qModalNo">+</span>
                        <div>
                            <h3 id="qModalTitle">Yangi savol</h3>
                            <p>Savol turini tanlang, matn va javob variantlarini kiriting.</p>
                        </div>
                        <button type="button" class="bl-qmodal-x" id="qModalClose" aria-label="Yopish">&times;</button>
                    </div>
                    <div class="bl-qmodal-body">
                        <div data-q-pane="new">
                            @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.store', $collection), 'method' => null, 'question' => null, 'questionIndex' => null, 'optionDefaults' => $optionDefaults])
                        </div>
                        @foreach($questions as $index => $question)
                            <div data-q-pane="{{ $index }}" hidden>
                                @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.update', [$collection, $index]), 'method' => 'PUT', 'question' => $question, 'questionIndex' => $index, 'optionDefaults' => $optionDefaults])
                                <form method="POST" action="{{ route('teacher.fan-testlari.questions.destroy', [$collection, $index]) }}" onsubmit="return confirm('Bu savolni o\'chirishni tasdiqlaysizmi?')" class="bl-q-del">
                                    @csrf
                                    @method('DELETE')
                                    <button class="bl-btn bl-btn-bad bl-btn-sm">Savolni o'chirish</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        @else
            <div class="bl-empty">
                <b>Avval test to'plamini saqlang</b>
                <span>To'plam yaratilgach, shu sahifada savol qo'shish va sozlash oynalari ochiladi.</span>
            </div>
            </details>
        @endif

        {{-- Mavjud to'plamlar --}}
        @if(($collections ?? collect())->isNotEmpty())
            <div class="bl-panel">
                @php
                    // Qayta topshirish uchun ochsa bo'ladigan to'plamlar: fani biriktirilgan va savoli bor
                    $retakeCollections = $collections->filter(fn ($c) => $c->curriculum_subject_id && $c->questionCount() > 0)->values();
                @endphp
                <div class="bl-panel-head">
                    <div class="bl-ctabs" role="tablist" aria-label="Test to'plamlari">
                        <button type="button" class="bl-ctab is-on" role="tab" data-ctab="main" aria-selected="true">
                            Yaratilgan test to'plamlari <span class="bl-ctab-n">{{ $collections->count() }}</span>
                        </button>
                        <button type="button" class="bl-ctab is-retake" role="tab" data-ctab="retake" aria-selected="false">
                            Qayta topshirish testlari <span class="tab-nb">NB</span> <span class="bl-ctab-n">{{ $retakeCollections->count() }}</span>
                        </button>
                    </div>
                </div>

                <div class="bl-scroll" data-pane="main">
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
                            @php $isDraft = !$item->curriculum_subject_id; @endphp
                            <tr class="{{ $isEdit && $item->id === $collection->id ? 'is-current' : '' }}">
                                <td>
                                    <span class="bl-t-name">@if($item->topic_number)<span class="bl-topic-badge">{{ $item->topicLabel() }}</span> @else<span class="bl-topic-badge is-missing" title="Tahrirlab mavzu raqamini kiriting">mavzu raqami yo'q</span> @endif{{ $item->name }}</span>
                                    @if($item->description)
                                        <span class="bl-t-sub">{{ \Illuminate\Support\Str::limit($item->description, 64) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isDraft)
                                        <span class="bl-t-draft">Fan biriktirilmagan</span>
                                        <span class="bl-t-sub">Savollarni kiritaverish mumkin</span>
                                    @else
                                        <span class="bl-t-name">{{ $item->subject?->subject_name ?? '-' }}</span>
                                        <span class="bl-t-sub">{{ collect([$item->subject?->semester_name, $item->subject?->curriculum_label ?? null])->filter()->implode(' · ') ?: '-' }}</span>
                                    @endif
                                </td>
                                <td style="text-align:center">
                                    <span class="bl-num">{{ $item->questionCount() }}</span>
                                    @if($item->questionsPerAttempt() < $item->activeQuestionCount())
                                        <span class="bl-t-sub" title="Har talabaga tasodifiy shuncha savol tushadi">talabaga {{ $item->questionsPerAttempt() }} ta</span>
                                    @endif
                                </td>
                                <td style="text-align:center">{{ $item->duration_minutes }} daqiqa</td>
                                <td>
                                    @if($isDraft)
                                        <span class="bl-pill draft">Qoralama</span>
                                    @else
                                        <span class="bl-pill {{ $item->is_active ? 'ok' : 'off' }}">{{ $item->is_active ? 'Ochiq' : 'Yopiq' }}</span>
                                        @php $runCount = collect($kioskDevices ?? [])->where('assigned_test_id', $item->id)->where('assigned_mode', '!=', 'retake')->count(); @endphp
                                        @if($runCount > 0)
                                            <div><span class="bl-run-badge">{{ $runCount }} ta kompyuterda</span></div>
                                        @endif
                                        @if($item->isPublicOpen())
                                            <div><span class="bl-run-badge is-public" title="Havola orqali istalgan kompyuterda ochiladi, talaba o'qituvchi ruxsati bilan kiradi">Umumiy ochiq</span></div>
                                        @endif
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    <div class="bl-acts">
                                        @php $runningHere = collect($kioskDevices ?? [])->where('assigned_test_id', $item->id)->where('assigned_mode', '!=', 'retake')->count(); @endphp
                                        @if($isDraft)
                                            <button type="button" class="bl-btn bl-btn-main bl-btn-sm bl-attach"
                                                    data-attach-id="{{ $item->id }}"
                                                    data-attach-name="{{ $item->name }}"
                                                    data-attach-url="{{ route('teacher.fan-testlari.attach-subject', $item) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>Biriktirish</button>
                                        @elseif($runningHere > 0 || $item->isPublicOpen())
                                            <button type="button" class="bl-btn bl-btn-monitor bl-btn-sm mn-open"
                                                    data-test-id="{{ $item->id }}"
                                                    data-test-name="{{ $item->name }}"
                                                    data-devices-url="{{ route('teacher.fan-testlari.devices', $item) }}"
                                                    data-launch-url="{{ route('teacher.fan-testlari.launch', $item) }}"
                                                    data-public-url="{{ route('teacher.fan-testlari.launch-public', $item) }}"
                                                    data-public-open="{{ $item->isPublicOpen() ? '1' : '' }}"
                                                    title="Kompyuterlardagi holat va umumiy kirish so'rovlari"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="9" height="7" rx="1.5"/><rect x="13" y="3" width="9" height="7" rx="1.5"/><rect x="2" y="14" width="9" height="7" rx="1.5"/><rect x="13" y="14" width="9" height="7" rx="1.5"/></svg>{{ $runningHere > 0 ? "Kompyuterlar ({$runningHere})" : "Kirish so'rovlari" }}</button>
                                        @elseif($item->questionCount() > 0)
                                            <button type="button" class="bl-btn bl-btn-launch bl-btn-sm lm-open"
                                                    data-test-id="{{ $item->id }}"
                                                    data-test-name="{{ $item->name }}"
                                                    data-launch-url="{{ route('teacher.fan-testlari.launch', $item) }}"
                                                    data-public-url="{{ route('teacher.fan-testlari.launch-public', $item) }}"
                                                    title="Kompyuterlarni tanlab yoki umumiy havola bilan testni boshlash"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>Testni boshlash</button>
                                        @endif
                                        @if(!$isDraft && ($runningHere > 0 || $item->is_active))
                                            <form method="POST" action="{{ route('teacher.fan-testlari.stop', $item) }}" onsubmit="return confirm('Test to\'xtatilsinmi? Kompyuterlar kutish ekraniga qaytadi va test talabalar uchun yopiladi.')">
                                                @csrf
                                                <button class="bl-btn bl-btn-warn bl-btn-sm" title="Kompyuterlarni kutish ekraniga qaytarib, testni yopish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="1.5"/></svg>To'xtatish{{ $runningHere > 0 ? " ({$runningHere})" : '' }}</button>
                                            </form>
                                        @endif

                                        @if($item->questionCount() > 0)
                                            <a href="{{ route('teacher.fan-testlari.preview', $item) }}" target="_blank"
                                               class="bl-btn bl-btn-ghost bl-btn-sm"
                                               title="Testni talaba ko'radigan holicha sinab ko'rish (natija saqlanmaydi)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>Sinash</a>
                                        @endif
                                        <a href="{{ route('teacher.fan-testlari.edit', $item) }}" class="bl-btn bl-btn-ghost bl-btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>Tahrirlash</a>
                                        <form method="POST" action="{{ route('teacher.fan-testlari.destroy', $item) }}" onsubmit="return confirm('Bu test to\'plami va savollarini o\'chirishni tasdiqlaysizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="bl-btn bl-btn-bad bl-btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7"/></svg>O'chirish</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Qayta topshirish testlari: o'tolmagan / qatnashmagan talabalar bir marta qayta ishlaydi --}}
                <div data-pane="retake" hidden>
                    <p class="bl-retake-note">Testdan <b>o'tolmagan yoki qatnashmagan</b> talabalar shu yerdan ochilgan test orqali <b>bir marta</b> qayta topshiradi (talaba ID + yuz tekshiruvi). Natijalar <b>Test jurnali &rarr; "Qayta topshirishlar"</b> tabida chiqadi.</p>
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
                            @forelse($retakeCollections as $item)
                                @php $retakeRunning = collect($kioskDevices ?? [])->where('assigned_test_id', $item->id)->where('assigned_mode', 'retake')->count(); @endphp
                                <tr>
                                    <td>
                                        <span class="bl-t-name">@if($item->topic_number)<span class="bl-topic-badge">{{ $item->topicLabel() }}</span> @else<span class="bl-topic-badge is-missing" title="Tahrirlab mavzu raqamini kiriting">mavzu raqami yo'q</span> @endif{{ $item->name }}</span>
                                        @if($item->description)
                                            <span class="bl-t-sub">{{ \Illuminate\Support\Str::limit($item->description, 64) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="bl-t-name">{{ $item->subject?->subject_name ?? '-' }}</span>
                                        <span class="bl-t-sub">{{ collect([$item->subject?->semester_name, $item->subject?->curriculum_label ?? null])->filter()->implode(' · ') ?: '-' }}</span>
                                    </td>
                                    <td style="text-align:center">
                                        <span class="bl-num">{{ $item->questionCount() }}</span>
                                        @if($item->questionsPerAttempt() < $item->activeQuestionCount())
                                            <span class="bl-t-sub">talabaga {{ $item->questionsPerAttempt() }} ta</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center">{{ $item->duration_minutes }} daqiqa</td>
                                    <td>
                                        @if($retakeRunning > 0)
                                            <span class="bl-run-badge is-retake">{{ $retakeRunning }} ta kompyuterda</span>
                                        @else
                                            <span class="bl-t-sub">&mdash;</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right">
                                        <div class="bl-acts">
                                            @if($retakeRunning === 0)
                                            <button type="button" class="bl-btn bl-btn-retake bl-btn-sm lm-open"
                                                    data-mode="retake"
                                                    data-test-id="{{ $item->id }}"
                                                    data-test-name="{{ $item->name }}"
                                                    data-launch-url="{{ route('teacher.fan-testlari.launch', $item) }}"
                                                    title="Kompyuterlarni tanlab qayta topshirish testini boshlash"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>Testni boshlash</button>
                                            @else
                                                <button type="button" class="bl-btn bl-btn-monitor bl-btn-sm mn-open"
                                                        data-mode="retake"
                                                        data-test-id="{{ $item->id }}"
                                                        data-test-name="{{ $item->name }} — qayta topshirish"
                                                        data-devices-url="{{ route('teacher.fan-testlari.devices', $item) }}"
                                                        data-launch-url="{{ route('teacher.fan-testlari.launch', $item) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="9" height="7" rx="1.5"/><rect x="13" y="3" width="9" height="7" rx="1.5"/><rect x="2" y="14" width="9" height="7" rx="1.5"/><rect x="13" y="14" width="9" height="7" rx="1.5"/></svg>Kompyuterlar ({{ $retakeRunning }})</button>
                                                <form method="POST" action="{{ route('teacher.fan-testlari.stop', $item) }}" onsubmit="return confirm('Test to\'xtatilsinmi? Kompyuterlar kutish ekraniga qaytadi va test talabalar uchun yopiladi.')">
                                                    @csrf
                                                    <button class="bl-btn bl-btn-warn bl-btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="1.5"/></svg>To'xtatish</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="lm-empty">Qayta topshirish uchun ochsa bo'ladigan to'plam yo'q — avval to'plamga fan biriktiring va savol qo'shing.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
            // To'plamlar tablari; tanlangan tab qayta yuklanganda saqlanadi (masalan, test boshlangach)
            (() => {
                const tabs = document.querySelectorAll('.bl-ctab');
                const panes = document.querySelectorAll('[data-pane]');
                function show(name) {
                    tabs.forEach((t) => { const on = t.dataset.ctab === name; t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
                    panes.forEach((p) => { p.hidden = p.dataset.pane !== name; });
                    try { localStorage.setItem('fanTestCtab', name); } catch (e) {}
                }
                tabs.forEach((t) => t.addEventListener('click', () => show(t.dataset.ctab)));
                let saved = null;
                try { saved = localStorage.getItem('fanTestCtab'); } catch (e) {}
                if (saved === 'retake') show('retake');
            })();
            </script>
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

    {{-- Kompyuterlarda boshlash: xona bo'yicha kompyuterlarni tanlash --}}
    <div class="bl bl-modal" id="launchModal">
        <form method="POST" action="" class="bl-modal-box lm-box" id="launchForm">
            @csrf
            <input type="hidden" name="mode" id="launchModeInput" value="normal">
            <div class="bl-modal-head">
                <span class="bl-modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></span>
                <div>
                    <h3 id="launchTitle">Kompyuterlarda testni boshlash</h3>
                    <p id="launchTestName"></p>
                </div>
            </div>
            <div class="lm-modes">
                <button type="button" class="lm-mode is-on" data-mode="devices">
                    <b>Ro'yxatdagi kompyuterlar</b><span>Xonadagi kompyuterlarni tanlaysiz — kutish ekrani testni o'zi ochadi</span>
                </button>
                <button type="button" class="lm-mode" data-mode="public">
                    <b>Umumiy ochish</b><span>Bitta havola, istalgan kompyuter — talaba ID kiritadi, siz ruxsat berasiz</span>
                </button>
            </div>
            <div class="lm-body" id="launchRooms"></div>
            <div class="lm-body lm-public" id="launchPublic" style="display:none">
                <p>Test <b>umumiy havola</b> orqali ochiladi — ro'yxatdan o'tgan kompyuter shart emas. Talaba havolani ochib ID raqamini kiritadi; so'rovi sizning <b>"Kirish so'rovlari"</b> oynangizga tushadi. <b>Ruxsat</b> bersangiz talabaga yuz tekshiruvi ochiladi, <b>Rad etsangiz</b> — kirmaydi.</p>
                <div class="lm-link"><span>Havola:</span><code id="launchPublicLink"></code></div>
                <p class="lm-note">Havola faqat universitet tarmog'idan ochiladi. "To'xtatish" bosilganda umumiy kirish yopiladi va kutayotgan so'rovlar bekor bo'ladi.</p>
            </div>
            <div class="bl-modal-foot">
                <span class="lm-count" id="launchCount">0 ta kompyuter tanlandi</span>
                <button type="button" class="bl-btn bl-btn-ghost" id="launchCancel">Bekor qilish</button>
                <button type="submit" class="bl-btn bl-btn-launch" id="launchSubmit" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>Boshlash</button>
                <button type="button" class="bl-btn bl-btn-launch" id="launchPublicSubmit" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>Umumiy ochish</button>
            </div>
        </form>
        <form method="POST" action="" id="launchPublicForm" style="display:none">@csrf</form>
    </div>

    <script>
    (() => {
        const DEVICES = @json($kioskDevices ?? []);
        const modal = document.getElementById('launchModal');
        const form = document.getElementById('launchForm');
        const box = document.getElementById('launchRooms');
        const countEl = document.getElementById('launchCount');
        const submit = document.getElementById('launchSubmit');
        if (!modal || !form) return;

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function refresh() {
            const picked = box.querySelectorAll('input[name="device_ids[]"]:checked').length;
            countEl.textContent = picked + ' ta kompyuter tanlandi';
            submit.disabled = picked === 0;
            box.querySelectorAll('.lm-room').forEach((room) => {
                const all = room.querySelectorAll('input[name="device_ids[]"]:not(:disabled)');
                const on = room.querySelectorAll('input[name="device_ids[]"]:not(:disabled):checked');
                const head = room.querySelector('.lm-room-all');
                head.checked = all.length > 0 && on.length === all.length;
                head.indeterminate = on.length > 0 && on.length < all.length;
                head.disabled = all.length === 0;
            });
        }

        function render(testId) {
            const rooms = {};
            DEVICES.forEach((d) => { (rooms[d.room] = rooms[d.room] || []).push(d); });
            const names = Object.keys(rooms);
            if (!names.length) {
                box.innerHTML = '<div class="lm-empty">Ro\'yxatdan o\'tgan kompyuter yo\'q. Administrator /test-kompyuter sahifasida kompyuterlarni xonasi bilan ro\'yxatdan o\'tkazadi.</div>';
                return;
            }
            box.innerHTML = names.map((room) => {
                const list = rooms[room];
                const online = list.filter((d) => d.online).length;
                const title = room === 'Xonasiz' ? 'Xona belgilanmagan' : esc(room) + '-xona';
                const devs = list.map((d) => {
                    const mine = d.assigned_test_id === testId;
                    const busy = d.assigned_test_id && !mine;
                    const sub = busy
                        ? 'Band: ' + esc(d.assigned_test_name || 'boshqa test') + (d.assigned_by ? ' · ' + esc(d.assigned_by) : '')
                        : (mine ? 'Shu test ochiq' : '<i class="lm-dot ' + (d.online ? 'on' : '') + '"></i>' + (d.online ? 'Kutish ekranida' : 'Oflayn'));
                    const code = d.code ? '<em class="lm-code" title="Tiklash kodi: brauzer tozalanib ketsa kutish ekranida shu kod kiritiladi">kod ' + esc(d.code) + '</em>' : '';
                    return '<label class="lm-dev' + (busy ? ' is-busy' : '') + (mine ? ' is-mine' : '') + '">'
                        + '<input type="checkbox" name="device_ids[]" value="' + d.id + '"' + (busy ? ' disabled' : '') + (mine ? ' checked' : '') + '>'
                        + '<div><b>' + esc(d.name) + code + '</b><span>' + sub + '</span></div></label>';
                }).join('');
                return '<div class="lm-room"><div class="lm-room-head"><label><input type="checkbox" class="lm-room-all">'
                    + title + '</label><small>' + list.length + ' ta · ' + online + ' ta onlayn</small></div>'
                    + '<div class="lm-devs">' + devs + '</div></div>';
            }).join('');
        }

        // Ikki rejim: ro'yxatdagi kompyuterlar yoki umumiy havola
        const publicForm = document.getElementById('launchPublicForm');
        const publicBox = document.getElementById('launchPublic');
        const publicSubmit = document.getElementById('launchPublicSubmit');
        let publicUrl = '';

        function setMode(mode) {
            modal.querySelectorAll('.lm-mode').forEach((b) => b.classList.toggle('is-on', b.dataset.mode === mode));
            const isPublic = mode === 'public';
            box.style.display = isPublic ? 'none' : '';
            publicBox.style.display = isPublic ? '' : 'none';
            submit.style.display = isPublic ? 'none' : '';
            countEl.style.display = isPublic ? 'none' : '';
            publicSubmit.style.display = isPublic ? '' : 'none';
        }

        document.addEventListener('click', (ev) => {
            const open = ev.target.closest ? ev.target.closest('.lm-open') : null;
            if (open) {
                const testId = parseInt(open.dataset.testId, 10);
                form.action = open.dataset.launchUrl;
                publicUrl = open.dataset.publicUrl || '';
                publicForm.action = publicUrl;
                publicSubmit.disabled = !publicUrl;
                // Kiosk havolasi: /test/{id}
                document.getElementById('launchPublicLink').textContent = @json(url('/test')) + '/' + testId;
                document.getElementById('launchTestName').textContent = open.dataset.testName || '';
                // Qayta topshirish tabidan — faqat kompyuterlar, umumiy havola rejimi yo'q
                const retake = open.dataset.mode === 'retake';
                document.getElementById('launchModeInput').value = retake ? 'retake' : 'normal';
                document.getElementById('launchTitle').textContent = retake ? 'Qayta topshirish testini boshlash' : 'Kompyuterlarda testni boshlash';
                modal.querySelector('.lm-modes').style.display = retake ? 'none' : '';
                modal.querySelector('.lm-box').classList.toggle('is-retake', retake);
                render(testId);
                refresh();
                // Kompyuter ro'yxati bo'sh bo'lsa umumiy rejim qulayroq
                setMode(retake || DEVICES.length ? 'devices' : 'public');
                modal.classList.add('is-open');
                return;
            }
            const modeBtn = ev.target.closest ? ev.target.closest('.lm-mode') : null;
            if (modeBtn) { setMode(modeBtn.dataset.mode); return; }
            if (ev.target === modal || ev.target.id === 'launchCancel') {
                modal.classList.remove('is-open');
            }
        });

        publicSubmit.addEventListener('click', () => {
            if (!publicUrl) return;
            publicSubmit.disabled = true;
            publicSubmit.lastChild.textContent = 'Ochilmoqda...';
            publicForm.submit();
        });

        // Xona belgisi — xonadagi barcha bo'sh kompyuterlarni tanlash/olib tashlash
        box.addEventListener('change', (ev) => {
            if (ev.target.classList.contains('lm-room-all')) {
                const room = ev.target.closest('.lm-room');
                room.querySelectorAll('input[name="device_ids[]"]:not(:disabled)').forEach((cb) => { cb.checked = ev.target.checked; });
            }
            refresh();
        });

        form.addEventListener('submit', () => {
            submit.disabled = true;
            submit.lastChild.textContent = 'Boshlanmoqda...';
        });

        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') modal.classList.remove('is-open');
        });
    })();
    </script>

    {{-- Kompyuterlar: har bir kompyuterdagi jonli holat va boshqaruv --}}
    <div class="bl bl-modal" id="monitorModal" role="dialog" aria-modal="true" aria-labelledby="monitorTitle">
        <div class="bl-modal-box mn-box">
            <div class="bl-modal-head">
                <span class="bl-modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="9" height="7" rx="1.5"/><rect x="13" y="3" width="9" height="7" rx="1.5"/><rect x="2" y="14" width="9" height="7" rx="1.5"/><rect x="13" y="14" width="9" height="7" rx="1.5"/></svg></span>
                <div>
                    <h3 id="monitorTitle">Kompyuterlar</h3>
                    <p id="monitorTestName"></p>
                </div>
            </div>
            <div class="mn-tabs">
                <button type="button" class="mn-tab is-on" data-tab="devices"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>Kompyuterlar</button>
                <button type="button" class="mn-tab" data-tab="entries"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>Kirish so'rovlari <span class="mn-badge" id="monitorEntryCount" style="display:none">0</span></button>
            </div>
            <div class="mn-sum" id="monitorSum"></div>
            <div class="mn-body" id="monitorBody"><div class="lm-empty">Yuklanmoqda...</div></div>
            <div class="mn-body" id="monitorEntries" style="display:none"><div class="lm-empty">Yuklanmoqda...</div></div>
            <div class="bl-modal-foot">
                <span class="mn-toast" id="monitorToast" aria-live="polite"></span>
                <button type="button" class="bl-btn bl-btn-launch lm-open" id="monitorAdd">Kompyuter qo'shish</button>
                <button type="button" class="bl-btn bl-btn-ghost" id="monitorClose">Yopish</button>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const modal = document.getElementById('monitorModal');
        if (!modal) return;
        const body = document.getElementById('monitorBody');
        const sum = document.getElementById('monitorSum');
        const toast = document.getElementById('monitorToast');
        const addBtn = document.getElementById('monitorAdd');
        const CSRF = @json(csrf_token());
        let url = null, timer = null, busy = false, changed = false;

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const clock = (sec) => { sec = Math.max(0, sec | 0); return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0'); };

        function card(d) {
            const a = d.attempt;
            const student = a
                ? '<div class="mn-student"><b>' + esc(a.student) + '</b><span>' + esc(a.student_id) + (a.group ? ' · ' + esc(a.group) : '') + '</span></div>'
                : '<div class="mn-student none">Talaba hali kirmagan</div>';
            let meta = '';
            if (a && d.stage === 'take' && !a.finished) {
                const answered = d.answered ?? 0;
                const pct = a.questions ? Math.round(answered * 100 / a.questions) : 0;
                meta += '<div class="mn-meta"><span>Javob: <b>' + answered + ' / ' + a.questions + '</b></span><span>Qoldi: <b>' + clock(a.seconds_left) + '</b></span></div>'
                    + '<div class="mn-bar" role="img" aria-label="' + pct + '% javob berilgan"><i style="width:' + pct + '%"></i></div>';
            } else if (a && a.finished && a.percent !== null) {
                meta += '<div class="mn-result ' + (a.passed ? 'ok' : 'bad') + '"><b>' + a.percent + '%</b><span>' + (a.passed ? "O'tdi" : "O'tmadi") + '</span></div>';
            }
            if (d.stage === 'locked') {
                meta += '<div class="mn-lock">' + a.away_limit + " marta chalg'igani uchun test to'xtatildi. Javoblari topshirilmagan — ochib berish faqat bir marta mumkin.</div>";
            } else if (d.stage === 'ended_away') {
                meta += '<div class="mn-lock">' + "Ikkinchi marta chalg'idi — test yakunlandi, javoblari saqlandi. Qayta ochib bo'lmaydi.</div>";
            }
            if (d.face_bypass) {
                meta += '<div class="mn-lock" style="background:#e0f2fe;color:#075985">' + "Yuz tekshiruvisiz kirish ruxsati berilgan — keyingi talaba faqat ID bilan kiradi (bir marta).</div>";
            }
            const flags = [];
            if (d.retake) flags.push('<span class="mn-flag retake">Qayta topshirish</span>');
            if (a && a.face_bypassed) flags.push('<span class="mn-flag warn">Yuz tekshiruvisiz kirgan</span>');
            if (a && a.away > 0) flags.push('<span class="mn-flag warn">Chalg\'idi: ' + a.away + '</span>');
            if (a && a.face_mismatches > 0) flags.push('<span class="mn-flag bad">Yuz mos emas: ' + a.face_mismatches + '</span>');
            if (d.stage_since && d.stage !== 'offline') flags.push('<span class="mn-flag">' + esc(d.stage_since) + '</span>');
            if (flags.length) meta += '<div class="mn-flags">' + flags.join('') + '</div>';

            const stopBtn = '<button type="button" class="bl-btn bl-btn-warn bl-btn-sm" data-act="stop" data-url="' + esc(d.stop_url || '') + '" data-name="' + esc(d.name) + '"' + (d.stop_url ? '' : ' disabled') + ">To'xtatish</button>";
            const restartTitle = a && a.lock_count > 0 ? "Chalg'ish sababli qulflangan testni qayta boshlab bo'lmaydi" : "Qayta boshlash uchun kompyuterda talaba bo'lishi kerak";
            const restartBtn = d.unlock_url
                ? '<button type="button" class="bl-btn bl-btn-unlock bl-btn-sm" data-act="unlock" data-url="' + esc(d.unlock_url) + '" data-name="' + esc(d.name) + '" data-student="' + esc(a ? a.student : '') + '">Ochib berish</button>'
                : '<button type="button" class="bl-btn bl-btn-ghost bl-btn-sm" data-act="restart" data-url="' + esc(d.restart_url || '') + '" data-name="' + esc(d.name) + '" data-student="' + esc(a ? a.student : '') + '"'
                    + (d.restart_url ? '' : ' disabled title="' + esc(restartTitle) + '"') + '>Qayta boshlash</button>';

            const bypassBtn = d.face_bypass_url
                ? '<button type="button" class="bl-btn bl-btn-ghost bl-btn-sm" data-act="bypass" data-on="' + (d.face_bypass ? '1' : '') + '" data-url="' + esc(d.face_bypass_url) + '" data-name="' + esc(d.name) + '">'
                    + (d.face_bypass ? 'Ruxsatni bekor qilish' : 'Yuz tekshiruvisiz kirish') + '</button>'
                : '';

            return '<div class="mn-card ' + esc(d.stage) + '">'
                + '<div class="mn-head"><span class="mn-name"><i class="lm-dot ' + (d.online ? 'on' : '') + '"></i><span class="mn-pc">' + esc(d.name) + '</span>'
                + (d.code ? '<em class="lm-code" title="Tiklash kodi: brauzer tozalanib ketsa kutish ekranida shu kod kiritiladi">kod ' + esc(d.code) + '</em>' : '')
                + '</span><span class="mn-stage">' + esc(d.stage_label) + '</span></div>'
                + '<div class="mn-cbody">' + student + meta + '</div>'
                + '<div class="mn-foot"><div class="mn-acts">' + stopBtn + restartBtn + '</div>'
                + (bypassBtn ? '<div class="mn-acts one">' + bypassBtn + '</div>' : '')
                + '</div></div>';
        }

        function render(list, finishedTotal) {
            const count = (fn) => list.filter(fn).length;
            sum.innerHTML = '<span class="mn-chip"><b>' + count((d) => d.running) + '</b> kompyuterda</span>'
                + '<span class="mn-chip take"><b>' + count((d) => d.stage === 'take') + '</b> ishlamoqda</span>'
                + '<span class="mn-chip done"><b>' + (finishedTotal ?? 0) + '</b> topshirdi</span>'
                + (count((d) => d.stage === 'locked') ? '<span class="mn-chip" style="background:#fee2e2;color:#991b1b"><b style="color:#dc2626">' + count((d) => d.stage === 'locked') + '</b> qulflangan</span>' : '')
                + '<span class="mn-chip wait"><b>' + count((d) => ['start', 'face', 'wait', 'idle'].includes(d.stage)) + '</b> kutmoqda</span>'
                + '<span class="mn-chip off"><b>' + count((d) => d.stage === 'offline' || d.stage === 'stopped') + '</b> oflayn / to\'xtatilgan</span>'
                + '<span class="mn-live">Har 3 soniyada yangilanadi</span>';
            if (!list.length) {
                body.innerHTML = '<div class="lm-empty">Bu test hozir hech bir kompyuterda ochiq emas. "Kompyuter qo\'shish" tugmasi orqali boshlang.</div>';
                return;
            }
            const rooms = {};
            list.forEach((d) => { (rooms[d.room] = rooms[d.room] || []).push(d); });
            body.innerHTML = Object.keys(rooms).map((room) => {
                const title = room === 'Xonasiz' ? 'Xona belgilanmagan' : esc(room) + '-xona';
                return '<section><h4 class="mn-room-title">' + title + '<small>' + rooms[room].length + ' ta kompyuter</small></h4>'
                    + '<div class="mn-grid">' + rooms[room].map(card).join('') + '</div></section>';
            }).join('');
        }

        // ---- "Kirish so'rovlari" tabi (umumiy ochilgan test) ----
        const entriesBox = document.getElementById('monitorEntries');
        const entryCount = document.getElementById('monitorEntryCount');
        let tab = 'devices';
        let lastPending = 0;

        function setTab(name) {
            tab = name;
            modal.querySelectorAll('.mn-tab').forEach((b) => b.classList.toggle('is-on', b.dataset.tab === name));
            body.style.display = name === 'devices' ? '' : 'none';
            sum.style.display = name === 'devices' ? '' : 'none';
            entriesBox.style.display = name === 'entries' ? '' : 'none';
        }

        // Testga kirgan talabaning jonli jarayoni — kompyuter kartochkasi bilan bir xil ko'rinish
        function entryProgress(e) {
            const a = e.attempt;
            if (!a || !e.stage) return '';
            let meta = '';
            if (e.stage === 'take' && !a.finished) {
                const answered = e.answered ?? 0;
                const pct = a.questions ? Math.round(answered * 100 / a.questions) : 0;
                meta += '<div class="mn-meta"><span>Javob: <b>' + answered + ' / ' + a.questions + '</b></span><span>Qoldi: <b>' + clock(a.seconds_left) + '</b></span></div>'
                    + '<div class="mn-bar" role="img" aria-label="' + pct + '% javob berilgan"><i style="width:' + pct + '%"></i></div>';
            } else if (a.finished && a.percent !== null) {
                meta += '<div class="mn-meta"><span>Natija: <b class="' + (a.passed ? 'ok' : 'bad') + '">' + a.percent + '%</b></span><span class="' + (a.passed ? 'ok' : 'bad') + '">' + (a.passed ? "O'tdi" : "O'tmadi") + '</span></div>';
            } else if (e.stage === 'offline') {
                meta += '<div class="mn-meta"><span>Qoldi: <b>' + clock(a.seconds_left) + '</b></span><span class="warn">Sahifa yopilgan yoki aloqa uzilgan</span></div>';
            }
            if (e.stage === 'locked') {
                meta += '<div class="mn-lock">' + a.away_limit + " marta chalg'igani uchun test to'xtatildi. Javoblari topshirilmagan — ochib berish faqat bir marta mumkin.</div>";
            } else if (e.stage === 'ended_away') {
                meta += '<div class="mn-lock">' + "Ikkinchi marta chalg'idi — test yakunlandi, javoblari saqlandi.</div>";
            }
            if (e.stop_requested && !a.finished) {
                meta += '<div class="mn-lock" style="background:#fef3c7;color:#92400e">' + "To'xtatish buyrug'i yuborildi — sahifa javoblarini topshirishi kutilmoqda.</div>";
            }
            const flags = [];
            if (a.face_bypassed) flags.push('<span class="warn">Yuz tekshiruvisiz kirgan</span>');
            if (a.away > 0) flags.push('<span class="warn">Chalg\'idi: ' + a.away + '</span>');
            if (a.face_mismatches > 0) flags.push('<span class="bad">Yuz mos emas: ' + a.face_mismatches + '</span>');
            if (flags.length) meta += '<div class="mn-meta">' + flags.join('') + '</div>';
            return meta;
        }

        function entryRow(e) {
            const live = e.stage ? '<span class="mn-stage">' + esc(e.stage_label) + '</span>' : '';
            const pill = '<span class="mn-epill ' + esc(e.status) + '">' + esc(e.status_label) + '</span>';
            const who = '<div class="mn-student"><b>' + esc(e.student) + '</b><span>' + esc(e.student_id)
                + (e.group ? ' · ' + esc(e.group) : '') + (e.faculty ? ' · ' + esc(e.faculty) : '') + '</span></div>';
            const when = '<span class="mn-ewhen">' + esc(e.requested || '') + (e.ago ? ' · ' + esc(e.ago) + ' oldin' : '') + '</span>'
                + (e.decided_by && e.status !== 'pending' ? '<span class="mn-ewhen">' + esc(e.decided_by) + '</span>' : '');
            const approve = e.approve_url
                ? '<button type="button" class="bl-btn bl-btn-ok bl-btn-sm" data-act="approve" data-url="' + esc(e.approve_url) + '" data-student="' + esc(e.student) + '">Ruxsat</button>' : '';
            const reject = e.reject_url
                ? '<button type="button" class="bl-btn bl-btn-warn bl-btn-sm" data-act="reject" data-url="' + esc(e.reject_url) + '" data-student="' + esc(e.student) + '">Rad etish</button>' : '';
            const stop = e.stop_url
                ? '<button type="button" class="bl-btn bl-btn-warn bl-btn-sm" data-act="stop-entry" data-url="' + esc(e.stop_url) + '" data-student="' + esc(e.student) + '">To\'xtatish</button>' : '';
            const unlock = e.unlock_url
                ? '<button type="button" class="bl-btn bl-btn-unlock bl-btn-sm" data-act="unlock" data-url="' + esc(e.unlock_url) + '" data-student="' + esc(e.student) + '">Ochib berish</button>' : '';
            const progress = entryProgress(e);
            return '<div class="mn-entry ' + esc(e.status) + (e.stage ? ' st-' + esc(e.stage) : '') + '">'
                + '<div class="mn-ehead">' + who + '<div class="mn-emeta">' + (live || pill) + when + '</div></div>'
                + (progress ? '<div class="mn-eprog">' + progress + '</div>' : '')
                + '<div class="mn-acts">' + approve + reject + stop + unlock + '</div></div>';
        }

        function renderEntries(list, pub) {
            const pending = list.filter((e) => e.status === 'pending').length;
            entryCount.textContent = pending;
            entryCount.style.display = pending ? '' : 'none';
            // Yangi so'rov kelganda tab o'zi ochiladi — o'qituvchi sezsin
            if (pending > lastPending && tab !== 'entries') setTab('entries');
            lastPending = pending;

            let head = '';
            if (pub && pub.open) {
                head = '<div class="mn-public"><b>Umumiy ochiq</b>' + (pub.since ? ' · ' + esc(pub.since) + ' dan' : '') + (pub.by ? ' · ' + esc(pub.by) : '')
                    + '<div class="lm-link"><span>Havola:</span><code>' + esc(pub.url) + '</code><button type="button" class="bl-btn bl-btn-ghost bl-btn-sm" data-copy="' + esc(pub.url) + '">Nusxalash</button></div></div>';
            } else {
                head = '<div class="mn-public off">Test umumiy ochilmagan. "Kompyuter qo\'shish" → "Umumiy ochish" orqali havola bilan oching.</div>';
            }
            const rows = list.length
                ? list.map(entryRow).join('')
                : '<div class="lm-empty">Hali so\'rov yo\'q. Talaba havolani ochib ID sini kiritganda shu yerda chiqadi.</div>';
            entriesBox.innerHTML = head + '<div class="mn-elist">' + rows + '</div>';
        }

        async function load() {
            if (!url || busy) return;
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (!r.ok) throw new Error();
                const j = await r.json();
                if (j.migrate) { body.innerHTML = '<div class="lm-empty">Server yangilanmagan: php artisan migrate ni ishga tushiring.</div>'; return; }
                render(j.devices || [], j.finished_total);
                renderEntries(j.entries || [], j.public || null);
            } catch (e) {
                toast.className = 'mn-toast bad';
                toast.textContent = "Server bilan aloqa yo'q — qayta urinilmoqda";
            }
        }

        function open(btn) {
            url = btn.dataset.devicesUrl;
            document.getElementById('monitorTestName').textContent = btn.dataset.testName || '';
            // "Kompyuter qo'shish" — shu test uchun boshlash oynasi
            addBtn.dataset.testId = btn.dataset.testId;
            addBtn.dataset.testName = btn.dataset.testName;
            addBtn.dataset.launchUrl = btn.dataset.launchUrl;
            addBtn.dataset.publicUrl = btn.dataset.publicUrl || '';
            addBtn.dataset.mode = btn.dataset.mode || '';
            toast.textContent = '';
            changed = false;
            lastPending = 0;
            body.innerHTML = '<div class="lm-empty">Yuklanmoqda...</div>';
            entriesBox.innerHTML = '<div class="lm-empty">Yuklanmoqda...</div>';
            // Umumiy ochilgan test — so'rovlar tabi birinchi
            setTab(btn.dataset.publicOpen ? 'entries' : 'devices');
            modal.classList.add('is-open');
            load();
            clearInterval(timer);
            timer = setInterval(load, 3000);
        }

        modal.addEventListener('click', (ev) => {
            const t = ev.target.closest ? ev.target.closest('.mn-tab') : null;
            if (t) setTab(t.dataset.tab);
            const copy = ev.target.closest ? ev.target.closest('[data-copy]') : null;
            if (copy && navigator.clipboard) {
                navigator.clipboard.writeText(copy.dataset.copy).then(() => { copy.textContent = 'Nusxalandi'; setTimeout(() => { copy.textContent = 'Nusxalash'; }, 1500); });
            }
        });

        function close() {
            modal.classList.remove('is-open');
            clearInterval(timer);
            timer = null;
            // To'xtatilgan/qayta boshlangan bo'lsa jadvaldagi holat yangilansin
            if (changed) window.location.reload();
        }

        document.addEventListener('click', (ev) => {
            const btn = ev.target.closest ? ev.target.closest('.mn-open') : null;
            if (btn) { open(btn); return; }
            if (ev.target === modal || ev.target.id === 'monitorClose') close();
            if (ev.target.id === 'monitorAdd') { modal.classList.remove('is-open'); clearInterval(timer); }
        });
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && modal.classList.contains('is-open')) close();
        });

        modal.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('button[data-act]');
            if (!btn || btn.disabled || !btn.dataset.url) return;
            const question = btn.dataset.act === 'approve'
                ? null // ruxsat — tasdiq so'ralmaydi, talaba kutib turibdi
                : btn.dataset.act === 'reject'
                ? btn.dataset.student + ' uchun kirish rad etilsinmi?'
                : btn.dataset.act === 'stop-entry'
                ? btn.dataset.student + " uchun test to'xtatilsinmi? Belgilangan javoblari topshiriladi."
                : btn.dataset.act === 'bypass'
                ? (btn.dataset.on
                    ? btn.dataset.name + ' kompyuteridagi yuz tekshiruvisiz kirish ruxsati bekor qilinsinmi?'
                    : btn.dataset.name + " kompyuterida keyingi talaba yuz tekshiruvisiz, faqat ID bilan kirsinmi? Ruxsat bir marta ishlaydi — yuzi tanilmayotgan talaba o'tirganiga ishonch hosil qiling.")
                : btn.dataset.act === 'stop'
                ? btn.dataset.name + " kompyuterida test to'xtatilsinmi? Talabaning belgilangan javoblari topshiriladi."
                : btn.dataset.act === 'unlock'
                    ? btn.dataset.student + " uchun test qayta ochilsinmi? Bu faqat bir marta mumkin — yana chalg'isa test yakunlanadi."
                    : btn.dataset.name + ' kompyuterida test ' + btn.dataset.student + " uchun boshidan boshlansinmi? Hozirgi javoblari o'chiriladi.";
            if (question && !confirm(question)) return;
            btn.disabled = true;
            busy = true;
            try {
                const r = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                });
                const j = await r.json().catch(() => ({}));
                toast.className = 'mn-toast' + (r.ok ? '' : ' bad');
                toast.textContent = j.message || (r.ok ? 'Bajarildi.' : 'Xatolik yuz berdi.');
                if (r.ok) changed = true;
            } catch (e) {
                toast.className = 'mn-toast bad';
                toast.textContent = 'Xatolik: server bilan aloqa yo\'q.';
            }
            busy = false;
            load();
        });
    })();
    </script>

    {{-- Qoralamaga fan biriktirish --}}
    <div class="bl bl-modal" id="attachModal">
        <form method="POST" action="" class="bl-modal-box" id="attachForm">
            @csrf
            <div class="bl-modal-head">
                <span class="bl-modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg></span>
                <div>
                    <h3>To'plamni fanga biriktirish</h3>
                    <p>Fan tanlangach guruhlar aniqlanadi va to'plamni talabalarga ochish mumkin bo'ladi.</p>
                </div>
            </div>
            <div class="bl-modal-body">
                <div class="bl-field">
                    <label for="attachSubject"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3H19v15H5.5A2.5 2.5 0 0 0 3 20.5V5.5Z"/><path d="M3 20.5A2.5 2.5 0 0 1 5.5 18H19v3H5.5A2.5 2.5 0 0 1 3 20.5Z"/></svg> Bu semestrda o'tadigan fanlaringiz</label>
                    <select name="curriculum_subject_id" id="attachSubject" required>
                        <option value="">Fan tanlang</option>
                        @foreach($subjects as $subject)
                            @php
                                $aGroups = (int) ($subject->group_count ?? 0);
                                $aLabel = $subjectOptionLabel($subject);
                            @endphp
                            <option value="{{ $subject->id }}" @class(['bl-opt-empty' => $aGroups < 1])>{{ $aLabel }}</option>
                        @endforeach
                    </select>
                    @if($subjects->isEmpty())
                        <p class="bl-note is-warn">Shu semestrda dars jadvalida sizga qo'yilgan fan topilmadi.</p>
                    @endif
                </div>
                <div class="bl-field">
                    <label for="attachName"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg> To'plam nomi</label>
                    <input id="attachName" name="name" required maxlength="255" placeholder="Masalan: 1-mavzu">
                </div>
            </div>
            <div class="bl-modal-foot">
                <button type="button" class="bl-btn bl-btn-ghost" id="attachCancel">Bekor qilish</button>
                <button type="submit" class="bl-btn bl-btn-main"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>Biriktirish</button>
            </div>
        </form>
    </div>

    <script>
    (() => {
        const modal = document.getElementById('attachModal');
        const form = document.getElementById('attachForm');
        if (!modal || !form) return;

        document.addEventListener('click', ev => {
            const open = ev.target.closest ? ev.target.closest('.bl-attach') : null;
            if (open) {
                form.action = open.dataset.attachUrl;
                document.getElementById('attachName').value = open.dataset.attachName || '';
                document.getElementById('attachSubject').value = '';
                modal.classList.add('is-open');
                return;
            }
            if (ev.target === modal || (ev.target.id === 'attachCancel')) {
                modal.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape') modal.classList.remove('is-open');
        });
    })();
    </script>


    <script>
    (() => {
        const modal = document.getElementById('qModal');
        if (!modal) return;

        const panes = modal.querySelectorAll('[data-q-pane]');
        const title = document.getElementById('qModalTitle');
        const badge = document.getElementById('qModalNo');

        function open(key, label) {
            panes.forEach(pane => { pane.hidden = pane.dataset.qPane !== key; });
            title.textContent = key === 'new' ? 'Yangi savol' : (label || 'Savolni tahrirlash');
            badge.textContent = key === 'new' ? '+' : (Number(key) + 1);
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            // Fokus savol matniga — darrov yozishni boshlash mumkin
            const active = modal.querySelector('[data-q-pane]:not([hidden]) [name="prompt"]');
            if (active) setTimeout(() => active.focus(), 60);
        }

        function close() {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', ev => {
            const slot = ev.target.closest ? ev.target.closest('[data-q-open]') : null;
            if (slot) {
                const key = slot.dataset.qOpen;
                const no = slot.querySelector('.bl-slot-no');
                open(key, key === 'new' ? null : (no ? no.textContent.trim() : null));
                return;
            }
            if (ev.target === modal || ev.target.id === 'qModalClose') close();
        });

        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape' && modal.classList.contains('is-open')) close();
        });

        // Katakcha qo'shish: oxirgi bo'sh katakcha nusxalanadi va
        // raqami yangilanadi. Katakchalar sof ko'rinish uchun — savol
        // qaysi biri bosilsa ham navbatdagi bo'sh joyga tushadi.
        const addBtn = document.getElementById('addSlots');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const grid = document.querySelector('.bl-slots');
                const last = grid ? grid.querySelector('.bl-slot.is-empty:last-of-type') : null;
                if (grid && last) {
                    const total = grid.querySelectorAll('.bl-slot').length + 1;
                    const copy = last.cloneNode(true);
                    const no = copy.querySelector('.bl-slot-no');
                    if (no) no.textContent = total + '-savol';
                    grid.appendChild(copy);

                    const counter = document.getElementById('slotCount');
                    if (counter) {
                        counter.textContent = counter.textContent.split('/')[0].trim() + ' / ' + total;
                    }
                }
                // Katakcha qo'shilishi bilan savol oynasi ochiladi
                open('new');
            });
        }

        // Tekshiruvda o'tmagan forma bo'lsa oyna ochiq qoladi
        @if($errors->any())
            open('new');
        @endif
    })();
    </script>

</x-app-layout>

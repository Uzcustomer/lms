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
    .bl-headnum b { display: block; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 26px; font-weight: 600; line-height: 1; }
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
        font-family: 'Roboto Slab', serif; font-size: 13px; font-weight: 600;
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
        color: var(--ink); font-family: 'Roboto', sans-serif; font-size: 13.5px; outline: none;
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
        font-family: 'Roboto', sans-serif; font-size: 13px; font-weight: 600; letter-spacing: .01em;
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
        flex: none; display: grid; place-items: center; width: 28px; height: 28px;
        border: 1px solid var(--line); border-radius: 8px; background: #fff;
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
    .bl-pill { display: inline-flex; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: .02em; }
    .bl-pill.ok { background: var(--ok-bg); color: var(--ok); }
    .bl-pill.off { background: #eef1f6; color: var(--muted); }
    .bl-pill.draft { background: var(--warn-bg); color: var(--warn); }
    .bl-t-draft { color: var(--warn); font-size: 12.5px; font-weight: 500; }

    /* Fan biriktirish oynasi */
    .bl-modal { position: fixed; inset: 0; z-index: 200; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 39, 72, .55); }
    .bl-modal.is-open { display: flex; }
    .bl-modal-box { width: min(560px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; border-radius: 6px; background: #fff; box-shadow: 0 20px 50px rgba(15, 39, 72, .3); }
    .bl-modal-head { padding: 16px 20px; border-bottom: 1px solid var(--line-soft); background: linear-gradient(180deg, #fbfcfe, #f5f8fc); }
    .bl-modal-head h3 { margin: 0; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 16px; font-weight: 600; }
    .bl-modal-head p { margin: 4px 0 0; color: var(--muted); font-size: 12px; }
    .bl-modal-body { display: grid; gap: 14px; padding: 18px 20px; }
    .bl-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 13px 20px; border-top: 1px solid var(--line-soft); background: #fafcfe; }
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
    .bl-groups-head b { margin-left: 6px; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 12px; }
    option.bl-opt-empty { color: #9aa8bd; }

    /* ---- Xabar / bo'sh holat ---- */
    .bl-alert { display: flex; gap: 10px; padding: 14px 18px; border: 1px solid; border-left-width: 4px; border-radius: 9px; font-size: 13.5px; }
    .bl-alert.is-ok { border-color: #a5d6bf; border-left-color: var(--ok); background: var(--ok-bg); color: #0a6043; }
    .bl-alert.is-bad { border-color: #e8c3c0; border-left-color: var(--bad); background: var(--bad-bg); color: #8f1e18; }
    .bl-alert ul { margin: 6px 0 0; padding-left: 20px; }

    .bl-empty { padding: 52px 24px; border: 1px dashed #c9d5e4; border-radius: 10px; background: #fff; text-align: center; }
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
        @if(session('error'))
            <div class="bl-alert is-bad">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bl-alert is-bad">
                Ma'lumotlarni tekshiring:
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
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
                    <label for="curriculum_subject_id"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3H19v15H5.5A2.5 2.5 0 0 0 3 20.5V5.5Z"/><path d="M3 20.5A2.5 2.5 0 0 1 5.5 18H19v3H5.5A2.5 2.5 0 0 1 3 20.5Z"/></svg> Fan <em>(keyinroq biriktirsa ham bo'ladi)</em></label>
                    <select name="curriculum_subject_id" id="curriculum_subject_id">
                        <option value="">— Hozircha biriktirilmasin (qoralama) —</option>
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
                    <p class="bl-note">Dars jadvali tayyor bo'lmagan bo'lsa fanni bo'sh qoldiring —
                        to'plam qoralama bo'lib turadi, savollarni hozirdan kiritaverasiz.
                        Fan biriktirilgach uni talabalarga ochish mumkin bo'ladi.</p>
                    @if($subjects->isEmpty())
                        <p class="bl-note is-warn">Sizga tegishli kafedra fanlari topilmadi.</p>
                    @endif
                </div>

                <div class="bl-field">
                    <label for="name"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg> Test to'plami nomi</label>
                    <input id="name" name="name" required maxlength="255" value="{{ old('name', $collection?->name) }}" placeholder="Masalan: 1-mavzu nazorat testi">
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
                            @php $isDraft = !$item->curriculum_subject_id; @endphp
                            <tr class="{{ $isEdit && $item->id === $collection->id ? 'is-current' : '' }}">
                                <td>
                                    <span class="bl-t-name">{{ $item->name }}</span>
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
                                <td style="text-align:center"><span class="bl-num">{{ $item->questionCount() }}</span></td>
                                <td style="text-align:center">{{ $item->duration_minutes }} daqiqa</td>
                                <td>
                                    @if($isDraft)
                                        <span class="bl-pill draft">Qoralama</span>
                                    @else
                                        <span class="bl-pill {{ $item->is_active ? 'ok' : 'off' }}">{{ $item->is_active ? 'Ochiq' : 'Yopiq' }}</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    <div class="bl-acts">
                                        @if($item->questionCount() > 0)
                                            <a href="{{ route('teacher.fan-testlari.preview', $item) }}" target="_blank"
                                               class="bl-btn bl-btn-ghost bl-btn-sm"
                                               title="Testni talaba ko'radigan holicha sinab ko'rish (natija saqlanmaydi)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>Sinash</a>
                                        @endif
                                        @if($isDraft)
                                            <button type="button" class="bl-btn bl-btn-main bl-btn-sm bl-attach"
                                                    data-attach-id="{{ $item->id }}"
                                                    data-attach-name="{{ $item->name }}"
                                                    data-attach-url="{{ route('teacher.fan-testlari.attach-subject', $item) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>Biriktirish</button>
                                        @elseif($item->questionCount() > 0)
                                            @if($item->is_active)
                                                <a href="{{ route('kiosk.fan-testi.show', $item) }}" target="_blank" class="bl-btn bl-btn-ok bl-btn-sm" title="Talabalar uchun test sahifasini ochish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>Ochish</a>
                                                <form method="POST" action="{{ route('teacher.fan-testlari.toggle-active', $item) }}" onsubmit="return confirm('Test sahifasi yopilsinmi? Talabalar havola orqali kira olmaydi.')">
                                                    @csrf
                                                    <button class="bl-btn bl-btn-warn bl-btn-sm" title="Talabalar uchun test sahifasini yopish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>Yopish</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('teacher.fan-testlari.toggle-active', $item) }}">
                                                    @csrf
                                                    <button class="bl-btn bl-btn-ok bl-btn-sm" title="Talabalar uchun test sahifasini ochish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>Ochish</button>
                                                </form>
                                            @endif
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

    {{-- Qoralamaga fan biriktirish --}}
    <div class="bl-modal" id="attachModal">
        <form method="POST" action="" class="bl-modal-box" id="attachForm">
            @csrf
            <div class="bl-modal-head">
                <h3>To'plamni fanga biriktirish</h3>
                <p>Fan tanlangach guruhlar aniqlanadi va to'plamni talabalarga ochish mumkin bo'ladi.</p>
            </div>
            <div class="bl-modal-body">
                <div class="bl-field">
                    <label for="attachSubject">Bu semestrda o'tadigan fanlaringiz</label>
                    <select name="curriculum_subject_id" id="attachSubject" required>
                        <option value="">Fan tanlang</option>
                        @foreach($subjects as $subject)
                            @php
                                $aGroups = (int) ($subject->group_count ?? 0);
                                $aLabel = collect([
                                    $subject->subject_name,
                                    $subject->semester_name,
                                    $subject->curriculum_label ?? null,
                                ])->filter()->implode(' · ')
                                    . ' — ' . ($aGroups > 0 ? $aGroups . ' guruh' : 'guruh yo\'q');
                            @endphp
                            <option value="{{ $subject->id }}" @class(['bl-opt-empty' => $aGroups < 1])>{{ $aLabel }}</option>
                        @endforeach
                    </select>
                    @if($subjects->isEmpty())
                        <p class="bl-note is-warn">Sizga biriktirilgan fanlar hali topilmadi.</p>
                    @endif
                </div>
                <div class="bl-field">
                    <label for="attachName">To'plam nomi</label>
                    <input id="attachName" name="name" required maxlength="255" placeholder="Masalan: 1-mavzu">
                </div>
            </div>
            <div class="bl-modal-foot">
                <button type="button" class="bl-btn bl-btn-ghost" id="attachCancel">Bekor qilish</button>
                <button type="submit" class="bl-btn bl-btn-main">Biriktirish</button>
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

</x-app-layout>

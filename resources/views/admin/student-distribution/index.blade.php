<x-app-layout>
<style>
    .sd {
        --navy:#0f2748; --navy-soft:#1b3a63; --gold:#c9a227;
        --ink:#17233a; --ink-soft:#4d6180; --muted:#8798b1;
        --line:#dde5ef; --line-soft:#eef2f8;
        --ok:#0f7a52; --bad:#b3261e;
        color:var(--ink); font-family:'Figtree',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;
    }
    .sd-head {
        display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap;
        padding:20px 24px; border-radius:6px;
        border-bottom:3px solid var(--gold);
        background:linear-gradient(115deg,#0f2748,#1b3a63 62%,#22497a);
        box-shadow:0 2px 10px rgba(15,39,72,.16);
    }
    .sd-head-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .sd-voting-status { color:#e3c964; font-size:11.5px; font-weight:700; }
    .sd-head-btn {
        height:34px; padding:0 14px; border:1px solid rgba(255,255,255,.35); border-radius:5px;
        background:rgba(255,255,255,.1); color:#fff; font-family:inherit;
        font-size:12.5px; font-weight:600; cursor:pointer; transition:background .14s;
    }
    .sd-head-btn:hover { background:rgba(255,255,255,.22); }
    .sd-head-btn b { margin-left:6px; padding:1px 8px; border-radius:999px; background:var(--gold); color:#0f2748; }
    .sd-btn-ok { background:var(--ok); }
    .sd-btn-ok:hover { background:#0a5c3d; }
    .sd-btn-danger { background:var(--bad); }
    .sd-btn-danger:hover { background:#8f1d17; }
    .sd-btn-outline { border:1px solid var(--navy); background:#fff; color:var(--navy); }
    .sd-btn-outline:hover { background:#f1f5fa; }
    .sd-vote-row {
        display:grid; grid-template-columns:24px minmax(0,1fr) auto 30px; gap:11px; align-items:center;
        padding:10px 20px; border-bottom:1px solid var(--line-soft); font-size:13px;
    }
    .sd-vote-del {
        display:grid; place-items:center; width:28px; height:28px;
        border:1px solid #f2c9c6; border-radius:5px; background:#fdf3f2; color:var(--bad);
        cursor:pointer; transition:background .14s;
    }
    .sd-vote-del:hover { background:#fbe3e1; }
    .sd-vote-del svg { width:14px; height:14px; }
    .sd-vote-row:last-child { border-bottom:0; }
    .sd-vote-row .sd-meta { display:block; }
    .sd-vote-route { color:var(--ink-soft); font-size:12px; text-align:right; }
    .sd-vote-route b { color:var(--navy); }
    .sd-head h1 { margin:0; color:#fff; font-size:21px; font-weight:700; letter-spacing:-.01em; }
    .sd-head p { margin:5px 0 0; color:rgba(255,255,255,.72); font-size:13px; }

    /* ---- Ikki ustun ---- */
    .sd-cols { display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:start; }
    .sd-side { display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--line); border-radius:6px; background:#fff; }
    .sd-side-head {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:13px 16px; background:#1b3a63;
    }
    .sd-side.is-right .sd-side-head { background:#8a5a06; }
    .sd-side-head h2 { margin:0; color:#fff; font-size:14.5px; font-weight:700; }
    .sd-side-head p { margin:3px 0 0; color:rgba(255,255,255,.68); font-size:11.5px; }
    .sd-side-tools { display:flex; align-items:center; gap:8px; }
    .sd-xls {
        display:inline-flex; align-items:center; gap:5px; height:28px; padding:0 11px;
        border:1px solid rgba(255,255,255,.3); border-radius:4px;
        background:rgba(255,255,255,.12); color:#fff;
        font-family:inherit; font-size:11.5px; font-weight:600; cursor:pointer;
        transition:background .14s, border-color .14s;
    }
    .sd-xls:hover { background:rgba(255,255,255,.22); border-color:rgba(255,255,255,.5); }
    .sd-xls svg { width:13px; height:13px; }
    .sd-count {
        padding:4px 10px; border-radius:999px;
        background:rgba(255,255,255,.16); color:#fff;
        font-size:11px; font-weight:700; white-space:nowrap;
    }

    /* ---- Har paneldagi filtrlar (ixcham) ---- */
    .sd-filters {
        display:grid; grid-template-columns:1fr 1fr 74px 1fr; gap:6px;
        padding:9px 12px; border-bottom:1px solid var(--line-soft); background:#fdfeff;
    }
    .sd-filters select, .sd-filters input {
        width:100%; height:30px; padding:0 7px; border:1px solid #cfd9e6; border-radius:4px;
        background:#fff; color:var(--ink); font-family:inherit; font-size:11.5px; outline:none;
        transition:border-color .14s, box-shadow .14s;
    }
    .sd-filters select:focus, .sd-filters input:focus {
        border-color:var(--navy-soft); box-shadow:0 0 0 2px rgba(27,58,99,.1);
    }

    /* ---- Tablar ---- */
    .sd-tabs { display:flex; gap:2px; padding:8px 12px 0; border-bottom:1px solid var(--line-soft); background:#fdfeff; }
    .sd-tab {
        padding:7px 12px; border:0; border-bottom:2px solid transparent; background:transparent;
        color:var(--muted); font-family:inherit; font-size:11.5px; font-weight:600; cursor:pointer;
    }
    .sd-tab:hover { color:var(--navy); }
    .sd-tab.is-on { border-color:var(--gold); color:var(--navy); }
    .sd-tab span {
        margin-left:5px; padding:1px 6px; border-radius:999px;
        background:#eef2f8; color:var(--ink-soft); font-size:10px; font-weight:700;
    }
    .sd-tab.is-on span { background:var(--navy); color:#fff; }

    /* ---- Ro'yxat ---- */
    .sd-rows { flex:1 1 auto; min-height:0; overflow-y:auto; }
    .sd-actions { margin-top:auto; }
    /* Ustunlar: № | checkbox | guruh | talaba | sig'im | holat
       Sarlavha va qatorlar bir xil grid ishlatadi — shunda ular tekislanadi. */
    .sd-grid {
        display:grid;
        grid-template-columns:28px 20px minmax(0,1fr) 74px 92px 88px;
        align-items:center; gap:8px;
    }
    /* O'ng panelda faqat talabalar soni ko'rsatiladi. */
    .is-right .sd-grid { grid-template-columns:28px 20px minmax(0,1fr) 74px; }
    .sd-colhead {
        padding:6px 16px; border-bottom:1px solid var(--line); background:#f7f9fc;
    }
    .sd-colhead > span {
        color:var(--muted); font-size:9.5px; font-weight:700;
        letter-spacing:.09em; text-transform:uppercase;
    }
    .sd-colhead input, .sd-colhead select {
        width:100%; height:24px; padding:0 4px;
        border:1px solid #cfd9e6; border-radius:3px; background:#fff; color:var(--navy);
        font-family:inherit; font-size:10.5px; font-weight:600; text-align:center;
        outline:none; -moz-appearance:textfield;
    }
    .sd-colhead select { text-align:left; padding:0 2px; font-weight:600; }
    .sd-colhead input::-webkit-outer-spin-button,
    .sd-colhead input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .sd-colhead input::placeholder { color:#a9b7ca; font-weight:600; }
    .sd-colhead input:focus, .sd-colhead select:focus {
        border-color:var(--navy-soft); box-shadow:0 0 0 2px rgba(27,58,99,.1);
    }
    .sd-colhead input.is-on, .sd-colhead select.is-on {
        border-color:var(--gold); background:#fffdf5;
    }
    .sd-row {
        padding:9px 16px; border-bottom:1px solid var(--line-soft); transition:background .12s;
    }
    .sd-row:last-child { border-bottom:0; }
    .sd-row:hover { background:#fafcfe; }
    .is-right .sd-name { cursor:pointer; }
    .is-right .sd-name:hover { text-decoration:underline; }
    .is-right .sd-filters { grid-template-columns:1fr 1fr 60px 1fr 1fr; }
    .sd-moved-mini {
        display:inline-block; margin-left:6px; padding:1px 6px; border-radius:3px;
        background:#e9f7f0; color:#0f7a52; font-size:10px; font-weight:700;
    }
    .sd-row.is-picked { background:#fffbf2; }
    .sd-idx { color:var(--muted); font-size:11px; font-weight:600; text-align:right; font-variant-numeric:tabular-nums; }
    .sd-row input[type=checkbox] { width:15px; height:15px; accent-color:var(--navy); cursor:pointer; }
    .sd-name { color:var(--ink); font-size:13px; font-weight:600; }
    .sd-meta { margin-top:2px; color:var(--muted); font-size:11px; }
    .sd-num {
        display:flex; align-items:center; justify-content:center;
        height:26px; border-radius:4px; background:#f2f6fc; color:var(--navy);
        font-size:12px; font-weight:700;
    }
    .sd-cap {
        display:flex; align-items:center; justify-content:center;
        height:26px; border:1px solid #d5dfec; border-radius:4px; background:#fff;
    }
    .sd-cap input {
        width:100%; height:100%; padding:0; border:0; background:transparent; color:var(--navy);
        font-family:inherit; font-size:12px; font-weight:700; text-align:center; outline:none;
        -moz-appearance:textfield;
    }
    .sd-cap input::-webkit-outer-spin-button, .sd-cap input::-webkit-inner-spin-button {
        -webkit-appearance:none; margin:0;
    }
    .sd-cap input:focus { background:#eef4fd; border-radius:3px; }
    .sd-cap.is-custom { border-color:#c9a227; background:#fffdf5; }
    .sd-free {
        display:flex; align-items:center; justify-content:center;
        height:26px; border-radius:4px; font-size:11px; font-weight:700; white-space:nowrap;
    }
    .sd-free.free { background:#e9f7f0; color:#0f7a52; }
    .sd-free.full { background:#f2f6fc; color:var(--muted); }
    .sd-free.over { background:#fdeceb; color:#b3261e; }
    .sd-tag {
        margin-left:6px; padding:2px 7px; border-radius:999px;
        background:#fdf3e0; color:#8a5a06; font-size:10px; font-weight:700;
    }

    .sd-empty { padding:42px 20px; color:var(--muted); font-size:12.5px; text-align:center; }
    .sd-empty b { display:block; margin-bottom:5px; color:var(--ink-soft); font-size:13.5px; font-weight:700; }

    .sd-actions {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:12px 16px; border-top:1px solid var(--line-soft); background:#fbfcfe;
    }
    .sd-hint { color:var(--muted); font-size:12px; }
    .sd-hint b { color:var(--navy); font-weight:700; }
    .sd-btn {
        height:34px; padding:0 16px; border:0; border-radius:5px; background:var(--navy); color:#fff;
        font-family:inherit; font-size:12.5px; font-weight:500; letter-spacing:.03em; cursor:pointer;
        transition:background .15s;
    }
    .sd-btn:hover { background:var(--navy-soft); }
    .sd-btn:disabled { background:#9aa9bd; cursor:not-allowed; }

    /* ---- Talabalar modali ---- */
    .sd-modal {
        position:fixed; inset:0; z-index:80; display:none;
        align-items:center; justify-content:center; padding:20px;
        background:rgba(15,39,72,.55);
    }
    .sd-modal.is-open { display:flex; }
    .sd-modal-box {
        display:flex; flex-direction:column;
        width:min(560px,100%); max-height:calc(100vh - 48px);
        overflow:hidden; border-radius:8px; background:#fff;
        box-shadow:0 22px 60px rgba(15,39,72,.32);
    }
    .sd-modal-head {
        display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
        padding:16px 20px; background:#1b3a63; color:#fff;
    }
    .sd-modal-head h3 { margin:0; font-size:16px; font-weight:700; }
    .sd-modal-head p { margin:4px 0 0; color:rgba(255,255,255,.7); font-size:11.5px; }
    .sd-close {
        flex:none; width:26px; height:26px; border:1px solid rgba(255,255,255,.32);
        border-radius:50%; background:transparent; color:#fff;
        font-size:16px; line-height:1; cursor:pointer;
    }
    .sd-close:hover { background:rgba(255,255,255,.16); }
    .sd-modal-tools { display:flex; align-items:center; gap:8px; flex:none; }
    /* "To'liq guruh" rejimi: to'la guruhlarga va ingliz guruhiga ko'chirish */
    .sd-toggle {
        display:inline-flex; align-items:center; gap:6px; height:26px; padding:0 10px;
        border:1px solid rgba(255,255,255,.32); border-radius:4px;
        background:transparent; color:#fff; font-family:inherit; font-size:11.5px; font-weight:600;
        cursor:pointer; transition:background .14s, border-color .14s;
    }
    .sd-toggle:hover { background:rgba(255,255,255,.16); }
    .sd-toggle::before {
        content:''; width:8px; height:8px; border-radius:50%;
        background:rgba(255,255,255,.35);
    }
    .sd-toggle.is-on { background:#c9a227; border-color:#c9a227; color:#1b2a44; }
    .sd-toggle.is-on::before { background:#1b2a44; }
    .sd-modal-body { overflow-y:auto; }
    .sd-student {
        display:grid; grid-template-columns:30px minmax(0,1fr) auto; align-items:center; gap:10px;
        padding:9px 20px; border-bottom:1px solid var(--line-soft);
    }
    .sd-student:last-child { border-bottom:0; }
    .sd-student:hover { background:#fafcfe; }
    .sd-student i { font-style:normal; color:var(--muted); font-size:11px; font-weight:600; text-align:right; }
    .sd-student b { color:var(--ink); font-size:13px; font-weight:600; }
    .sd-student span { color:var(--muted); font-size:11.5px; font-variant-numeric:tabular-nums; }
    .sd-modal-note { padding:34px 20px; color:var(--muted); font-size:12.5px; text-align:center; }
    .sd-student { grid-template-columns:30px minmax(0,1fr) auto auto; }
    .sd-move {
        height:26px; padding:0 6px; max-width:150px;
        border:1px solid #cfd9e6; border-radius:4px; background:#fff; color:var(--navy);
        font-family:inherit; font-size:11px; font-weight:600; outline:none; cursor:pointer;
    }
    .sd-move:focus { border-color:var(--navy-soft); box-shadow:0 0 0 2px rgba(27,58,99,.1); }
    .sd-move:disabled { background:#f3f6fa; color:var(--muted); cursor:not-allowed; }
    .sd-moved {
        display:inline-flex; align-items:center; gap:6px;
        padding:3px 9px; border-radius:4px; background:#e9f7f0; color:#0f7a52;
        font-size:11px; font-weight:700;
    }
    .sd-undo {
        border:0; background:transparent; color:#b3261e;
        font-size:13px; line-height:1; cursor:pointer; padding:0 2px;
    }
    .sd-undo:hover { color:#8f1d17; }
    .sd-moved.is-full { background:#fdf3e0; color:#8a5a06; }
    .sd-moved.is-full .sd-undo { color:#8a5a06; }
    .sd-modal-foot {
        display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:12px 20px; border-top:1px solid var(--line-soft); background:#fbfcfe;
    }
    .sd-choice { display:grid; gap:8px; padding:18px 20px; }
    .sd-choice button {
        display:block; width:100%; padding:12px 14px; text-align:left;
        border:1px solid #cfd9e6; border-radius:6px; background:#fff; cursor:pointer;
        font-family:inherit; transition:border-color .14s, background .14s;
    }
    .sd-choice button:hover { border-color:var(--navy-soft); background:#f6f9fd; }
    .sd-choice b { display:block; color:var(--navy); font-size:13px; font-weight:700; }
    .sd-choice span { display:block; margin-top:3px; color:var(--muted); font-size:11.5px; }

    @media (max-width:1100px) { .sd-cols { grid-template-columns:1fr; } }
    @media (max-width:560px) { .sd-filters { grid-template-columns:1fr 1fr; } }
</style>

    <div class="sd py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

            <div class="sd-head">
                <div>
                    <h1>Talabalarni taqsimlash</h1>
                    <p>Chapda bo'sh joyli guruhlar to'ldiriladi, o'ngda talabalari taqsimlanadigan guruhlar belgilanadi. Faqat bakalavr, faol guruhlar va o'qiyotgan talabalar.</p>
                </div>
                <div class="sd-head-tools">
                    <span class="sd-voting-status" id="votingStatus" hidden></span>
                    <button class="sd-head-btn" id="closeVotingBtn" type="button" hidden>Ovoz berishni yopish</button>
                    <button class="sd-head-btn" id="votesBtn" type="button">Talabalar ovozlari <b id="votesCount">0</b></button>
                </div>
            </div>

            <div class="sd-cols">
                {{-- CHAP --}}
                <section class="sd-side" id="leftSide">
                    <div class="sd-side-head">
                        <div>
                            <h2>Bo'sh guruhlarni to'ldirish</h2>
                            <p>Talabalar shu guruhlarga ko'chiriladi</p>
                        </div>
                        <span class="sd-side-tools">
                            <button class="sd-xls" data-export="left" type="button" title="Filtrga mos guruhlar talabalarini yuklab olish">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 3.5-3.5M12 14l-3.5-3.5M5 17v2a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2"/></svg>
                                Excel
                            </button>
                            <span class="sd-count" id="leftCount">0 ta</span>
                        </span>
                    </div>

                    <div class="sd-filters">
                        <select data-f="faculty"><option value="">Barcha fakultetlar</option></select>
                        <select data-f="specialty"><option value="">Barcha yo'nalishlar</option></select>
                        <select data-f="course"><option value="">Kurs</option></select>
                        <input data-f="search" type="search" placeholder="Guruh nomi">
                    </div>

                    <div class="sd-colhead sd-grid">
                        <span>#</span><span></span><span>Guruh</span>
                        <input data-f="minStudents" type="number" min="0" placeholder="Talaba" title="Shu sondan kam bo'lmagan talabali guruhlar">
                        <input data-f="minCapacity" type="number" min="0" placeholder="Sig'im" title="Shu sondan kam bo'lmagan sig'imli guruhlar">
                        <select data-f="status" title="Holat bo'yicha">
                            <option value="">Holat</option>
                            <option value="free">Bo'sh joy bor</option>
                            <option value="full">To'la</option>
                            <option value="over">Ortiqcha</option>
                        </select>
                    </div>
                    <div class="sd-rows" id="leftRows"></div>
                </section>

                {{-- O'NG --}}
                <section class="sd-side is-right" id="rightSide">
                    <div class="sd-side-head">
                        <div>
                            <h2>Taqsimlanadigan guruhlar</h2>
                            <p>Talabalari ko'chiriladigan guruhlarni belgilang</p>
                        </div>
                        <span class="sd-side-tools">
                            <button class="sd-xls" data-export="right" type="button" title="Filtrga mos guruhlar talabalarini yuklab olish">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 3.5-3.5M12 14l-3.5-3.5M5 17v2a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2"/></svg>
                                Excel
                            </button>
                            <span class="sd-count" id="rightCount">0 ta</span>
                        </span>
                    </div>

                    <div class="sd-filters">
                        <select data-f="faculty"><option value="">Barcha fakultetlar</option></select>
                        <select data-f="specialty"><option value="">Barcha yo'nalishlar</option></select>
                        <select data-f="course"><option value="">Kurs</option></select>
                        <input data-f="search" type="search" placeholder="Guruh nomi">
                        <input data-f="student" type="search" placeholder="Talaba ismi">
                    </div>

                    <div class="sd-tabs">
                        <button class="sd-tab is-on" data-view="all" type="button">Barcha guruhlar <span id="tabAll">0</span></button>
                        <button class="sd-tab" data-view="picked" type="button">Taqsimlanadigan guruhlar <span id="tabPicked">0</span></button>
                    </div>

                    <div class="sd-colhead sd-grid">
                        <span>#</span>
                        <input type="checkbox" id="rightCheckAll" title="Hammasini belgilash" style="width:14px;height:14px;accent-color:var(--navy);cursor:pointer;">
                        <span>Guruh</span>
                        <input data-f="minStudents" type="number" min="0" placeholder="Talaba" title="Shu sondan kam bo'lmagan talabali guruhlar">
                    </div>
                    <div class="sd-rows" id="rightRows"></div>

                    <div class="sd-actions">
                        <span class="sd-hint"><b id="pickedTotal">0</b><span id="footLabel"> ta guruh belgilangan</span></span>
                        <span style="display:flex;gap:8px;">
                            <button class="sd-btn sd-btn-outline" id="openVotingBtn" type="button" hidden>Ovoz berishga ruxsat</button>
                            <button class="sd-btn" id="saveSources" type="button">O'tqazish</button>
                        </span>
                    </div>
                </section>
            </div>
        </div>
        <div class="sd-modal" id="exportModal">
            <div class="sd-modal-box" style="width:min(430px,100%)" role="dialog" aria-modal="true">
                <div class="sd-modal-head">
                    <div>
                        <h3>Excelga yuklab olish</h3>
                        <p>Qaysi holatni chiqaramiz?</p>
                    </div>
                    <button class="sd-close" type="button" data-close="exportModal" aria-label="Yopish">&times;</button>
                </div>
                <div class="sd-choice">
                    <button type="button" data-mode="old">
                        <b>Eski holat</b>
                        <span>LMS dagi hozirgi guruhlar — reja hisobga olinmaydi</span>
                    </button>
                    <button type="button" data-mode="new">
                        <b>Yangi holat</b>
                        <span>Reja qo'llangan — ko'chirilgan talabalar yangi guruhida</span>
                    </button>
                    <button type="button" data-mode="both">
                        <b>Ikkalasi</b>
                        <span>Bitta faylda ketma-ket ikkita bo'lim</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="sd-modal" id="votesModal">
            <div class="sd-modal-box" style="width:min(880px,100%);height:calc(100vh - 90px);" role="dialog" aria-modal="true">
                <div class="sd-modal-head">
                    <div>
                        <h3>Talabalar ovozlari</h3>
                        <p id="votesMeta"></p>
                    </div>
                    <button class="sd-close" type="button" id="votesClose" aria-label="Yopish">&times;</button>
                </div>
                <div class="sd-modal-body" id="votesBody"></div>
                <div class="sd-modal-foot">
                    <label class="sd-hint" style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                        <input type="checkbox" id="votesCheckAll" style="width:15px;height:15px;accent-color:var(--navy);">
                        Hammasini belgilash
                    </label>
                    <span style="display:flex;align-items:center;gap:12px;">
                        <span class="sd-hint"><b id="votesPicked">0</b> ta tanlandi</span>
                        <button class="sd-btn sd-btn-danger" id="deleteVotesBtn" type="button" disabled>O'chirish</button>
                        <button class="sd-btn sd-btn-ok" id="approveVotesBtn" type="button" disabled>Tasdiqlash</button>
                    </span>
                </div>
            </div>
        </div>

        <div class="sd-modal" id="studentsModal">
            <div class="sd-modal-box" role="dialog" aria-modal="true">
                <div class="sd-modal-head">
                    <div>
                        <h3 id="modalGroup">Guruh</h3>
                        <p id="modalMeta"></p>
                    </div>
                    <div class="sd-modal-tools">
                        <button class="sd-toggle" type="button" id="modalFull"
                                title="To'la guruhlarga ham, ingliz guruhiga boshqa tildan ham ko'chirish">To'liq guruh</button>
                        <button class="sd-close" type="button" id="modalClose" aria-label="Yopish">&times;</button>
                    </div>
                </div>
                <div class="sd-modal-body" id="modalBody"></div>
                <div class="sd-modal-foot">
                    <span class="sd-hint" id="modalHint"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const $ = id => document.getElementById(id);
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const saveUrl = @json(route('admin.student-distribution.source-groups.store'));
        const exportUrl = @json(route('admin.student-distribution.export'));
        const capacityUrl = @json(route('admin.student-distribution.capacity.update'));
        const studentsUrl = @json(route('admin.student-distribution.group-students'));
        const searchUrl   = @json(route('admin.student-distribution.search-students'));
        const targetsUrl  = @json(route('admin.student-distribution.target-groups'));
        const assignUrl   = @json(route('admin.student-distribution.assign-student'));
        const unassignUrl = @json(route('admin.student-distribution.unassign-student'));
        const votesUrl        = @json(route('admin.student-distribution.votes'));
        const openVotingUrl   = @json(route('admin.student-distribution.voting.open'));
        const closeVotingUrl  = @json(route('admin.student-distribution.voting.close'));
        const approveVotesUrl = @json(route('admin.student-distribution.votes.approve'));
        const deleteVotesUrl  = @json(route('admin.student-distribution.votes.delete'));

        let groups = @json($groupPayloads);
        // sources — saqlangan taqsimlanadigan guruhlar (server holati).
        // pendingAdd — "Barcha guruhlar" tabida hozir belgilanganlar (hali saqlanmagan).
        // pendingRemove — "Belgilanganlar" tabida qaytarish uchun tanlanganlar.
        let sources = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
        let pendingAdd = new Set();
        let pendingRemove = new Set();
        let rightView = 'all';

        const courseLabel = g => g.course ? g.course + '-kurs' : (g.level_name || '');
        // Fakultet · yo'nalish · kurs · ta'lim tili
        const metaLabel = g => [g.faculty_name || '\u2014', g.specialty_name || '\u2014', courseLabel(g), g.language_name || '']
            .filter(Boolean).join(' \u00b7 ');

        // Bo'sh joy: musbat — joy bor, 0 — to'la, manfiy — ortiqcha talaba.
        function freeHtml(g) {
            if (g.free_places === null || g.free_places === undefined) return '<span></span>';
            if (g.free_places > 0) return '<span class="sd-free free">+' + g.free_places + " bo'sh</span>";
            if (g.free_places === 0) return "<span class=\"sd-free full\">to'la</span>";
            return '<span class="sd-free over">' + Math.abs(g.free_places) + ' ortiqcha</span>';
        }

        // Har bir panelning o'z filtrlari bor — ular bir-biriga ta'sir qilmaydi.
        const panels = {
            left:  {root: $('leftSide'),  rows: $('leftRows'),  count: $('leftCount'),  checkbox: false},
            right: {root: $('rightSide'), rows: $('rightRows'), count: $('rightCount'), checkbox: true},
        };

        const readFilters = panel => {
            const get = key => {
                const field = panel.root.querySelector('[data-f="' + key + '"]');
                return field ? field.value : '';
            };
            return {
                faculty: get('faculty'),
                specialty: get('specialty'),
                course: get('course'),
                search: get('search').trim().toLowerCase(),
                minStudents: get('minStudents'),
                minCapacity: get('minCapacity'),
                status: get('status'),
            };
        };

        // Guruh holati: bo'sh joy bor / to'la / ortiqcha
        function statusOf(g) {
            if (g.free_places === null || g.free_places === undefined) return null;
            if (g.free_places > 0) return 'free';
            if (g.free_places === 0) return 'full';
            return 'over';
        }

        const applyFilters = f => groups.filter(g =>
            (!f.faculty || g.faculty_name === f.faculty) &&
            (!f.specialty || g.specialty_name === f.specialty) &&
            (!f.course || String(g.course) === f.course) &&
            (!f.search || String(g.group_name).toLowerCase().includes(f.search)) &&
            (f.minStudents === '' || g.student_count >= Number(f.minStudents)) &&
            (f.minCapacity === '' || (g.capacity !== null && g.capacity >= Number(f.minCapacity))) &&
            (!f.status || statusOf(g) === f.status)
        );

        function rowHtml(g, index, withCheckbox) {
            const pendingSet = rightView === 'picked' ? pendingRemove : pendingAdd;
            const isChecked = withCheckbox && pendingSet.has(g.group_hemis_id);
            const check = withCheckbox
                ? '<input type="checkbox" data-id="' + g.group_hemis_id + '"' + (isChecked ? ' checked' : '') + '>'
                : '<span></span>';

            return '<label class="sd-row sd-grid' + (isChecked ? ' is-picked' : '') + '">' +
                '<span class="sd-idx">' + index + '.</span>' +
                check +
                '<span><span class="sd-name" data-group="' + g.group_hemis_id + '">' + esc(g.group_name) + '</span>' +
                '<span class="sd-meta">' + esc(metaLabel(g)) + '</span></span>' +
                '<span class="sd-num">' + g.student_count + '</span>' +
                (withCheckbox ? '' :
                    '<span class="sd-cap' + (g.is_custom_capacity ? ' is-custom' : '') + "\" title=\"Sig'im - o'zgartirish mumkin\">" +
                        '<input type="number" min="0" max="200" value="' + (g.capacity ?? '') + '" data-cap="' + g.group_hemis_id + '">' +
                    '</span>' +
                    freeHtml(g)) +
                '</label>';
        }

        // --- Talaba ismi bo'yicha qidiruv (o'ng panel) ---
        let studentQuery = '';
        let studentResults = [];
        let studentSearchTimer = null;

        function studentSearchRow(st, index) {
            const moved = st.moved_to
                ? '<span class="sd-moved-mini">&rarr; ' + esc(st.moved_to) + '</span>'
                : '';

            return '<div class="sd-row sd-grid">' +
                '<span class="sd-idx">' + (index + 1) + '.</span>' +
                '<span></span>' +
                '<span><span class="sd-name" data-group="' + st.group_hemis_id + '">' + esc(st.full_name) + '</span>' + moved +
                '<span class="sd-meta">' + esc(st.student_id_number) + '</span></span>' +
                '<span class="sd-num" title="Guruhi">' + esc(st.group_name) + '</span>' +
                '</div>';
        }

        function renderStudentSearch() {
            panels.right.rows.innerHTML = studentResults.length
                ? studentResults.map(studentSearchRow).join('')
                : '<div class="sd-empty"><b>Talaba topilmadi</b>' + "Boshqa ism bilan urinib ko'ring." + '</div>';
            panels.right.count.textContent = studentResults.length + ' ta';
        }

        async function runStudentSearch() {
            try {
                const response = await fetch(searchUrl + '?q=' + encodeURIComponent(studentQuery), {headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || "Qidirib bo'lmadi.");
                studentResults = data.students;
            } catch (error) {
                studentResults = [];
            }
            renderStudentSearch();
        }

        function renderPanel(key) {
            const panel = panels[key];

            if (key === 'right' && studentQuery.length >= 2) {
                renderStudentSearch();
                return;
            }

            let list = applyFilters(readFilters(panel));

            if (key === 'left') {
                // Taqsimlanadigan guruhlar chap ro'yxatda ko'rinmaydi.
                list = list.filter(g => !sources.has(g.group_hemis_id));
            } else if (rightView === 'picked') {
                list = list.filter(g => sources.has(g.group_hemis_id));
            } else {
                list = list.filter(g => !sources.has(g.group_hemis_id));
            }

            panel.rows.innerHTML = list.length
                ? list.map((g, i) => rowHtml(g, i + 1, panel.checkbox)).join('')
                : '<div class="sd-empty"><b>Guruh topilmadi</b>Tanlangan filtr bo\'yicha guruh yo\'q.</div>';
            panel.count.textContent = list.length + ' ta';
        }

        function renderTabs() {
            const inRight = applyFilters(readFilters(panels.right));
            $('tabAll').textContent = inRight.filter(g => !sources.has(g.group_hemis_id)).length;
            $('tabPicked').textContent = inRight.filter(g => sources.has(g.group_hemis_id)).length;

            const button = $('saveSources');
            if (rightView === 'picked') {
                $('pickedTotal').textContent = pendingRemove.size;
                $('footLabel').textContent = ' ta guruh qaytarish uchun tanlandi';
                button.textContent = 'Tanlanganlarni qaytarish';
                button.disabled = pendingRemove.size === 0;
            } else {
                $('pickedTotal').textContent = pendingAdd.size;
                $('footLabel').textContent = ' ta guruh belgilangan';
                button.textContent = "O'tqazish";
                button.disabled = pendingAdd.size === 0;
            }

            // Ovoz berish tugmasi faqat "Taqsimlanadigan guruhlar" tabida.
            $('openVotingBtn').hidden = rightView !== 'picked' || pendingRemove.size === 0;
        }

        function render() {
            renderPanel('left');
            renderPanel('right');
            renderTabs();
        }

        function setOptions(select, values, placeholder) {
            const current = select.value;
            select.innerHTML = '<option value="">' + placeholder + '</option>' +
                [...new Set(values.filter(Boolean))].sort().map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
            if ([...select.options].some(o => o.value === current)) select.value = current;
        }

        // Fakultet -> yo'nalish -> kurs bog'lanishi har panelda alohida ishlaydi.
        function refreshOptions(panel) {
            const pick = key => panel.root.querySelector('.sd-filters [data-f="' + key + '"]');
            setOptions(pick('faculty'), groups.map(g => g.faculty_name), 'Barcha fakultetlar');

            const faculty = pick('faculty').value;
            const scoped = groups.filter(g => !faculty || g.faculty_name === faculty);
            setOptions(pick('specialty'), scoped.map(g => g.specialty_name), 'Barcha yo\'nalishlar');

            const specialty = pick('specialty').value;
            const scoped2 = scoped.filter(g => !specialty || g.specialty_name === specialty);
            const courses = [...new Set(scoped2.map(g => g.course).filter(Boolean))].sort((a, b) => a - b);
            const courseSelect = pick('course');
            const currentCourse = courseSelect.value;
            courseSelect.innerHTML = '<option value="">Kurs</option>' +
                courses.map(c => '<option value="' + c + '">' + c + '-kurs</option>').join('');
            if ([...courseSelect.options].some(o => o.value === currentCourse)) courseSelect.value = currentCourse;
        }

        // Filtrlar: tugma yo'q — tanlangan/yozilgan zahoti chiqadi.
        // Ustun sarlavhasidagi filtrlar
        Object.entries(panels).forEach(([key, panel]) => {
            const head = panel.root.querySelector('.sd-colhead');
            if (!head) return;

            const react = event => {
                const field = event.target;
                if (!field.dataset.f) return;
                field.classList.toggle('is-on', field.value !== '');
                renderPanel(key);
                if (key === 'right') renderTabs();
            };

            head.addEventListener('input', react);
            head.addEventListener('change', react);
        });

        Object.entries(panels).forEach(([key, panel]) => {
            const box = panel.root.querySelector('.sd-filters');

            box.addEventListener('change', event => {
                if (!event.target.matches('select')) return;
                if (event.target.dataset.f !== 'search') refreshOptions(panel);
                render();
            });

            box.addEventListener('input', event => {
                if (event.target.dataset.f === 'student') {
                    studentQuery = event.target.value.trim();
                    event.target.classList.toggle('is-on', studentQuery !== '');
                    clearTimeout(studentSearchTimer);
                    if (studentQuery.length >= 2) {
                        studentSearchTimer = setTimeout(runStudentSearch, 300);
                    } else {
                        studentResults = [];
                        renderPanel('right');
                        renderTabs();
                    }
                    return;
                }
                if (event.target.dataset.f !== 'search') return;
                renderPanel(key);
                if (key === 'right') renderTabs();
            });
        });

        $('rightRows').addEventListener('change', event => {
            const box = event.target.closest('input[type=checkbox]');
            if (!box) return;
            const id = Number(box.dataset.id);
            const pendingSet = rightView === 'picked' ? pendingRemove : pendingAdd;
            if (box.checked) pendingSet.add(id); else pendingSet.delete(id);
            render();
        });

        function setRightView(view) {
            rightView = view;
            $('rightCheckAll').checked = false;
            document.querySelectorAll('.sd-tab').forEach(t => t.classList.toggle('is-on', t.dataset.view === view));
            renderPanel('right');
            renderTabs();
        }

        document.querySelectorAll('.sd-tab').forEach(tab => tab.addEventListener('click', () => {
            if (rightView !== tab.dataset.view) setRightView(tab.dataset.view);
        }));

        // --- Talabalar modali ---
        const modal = $('studentsModal');
        let modalGroupId = null;
        let modalTargets = [];
        // "To'liq guruh" rejimi: bo'sh joyi yo'q guruhlar ham ro'yxatga
        // tushadi, ingliz guruhiga o'zbek/rus guruhidan o'tish mumkin.
        let modalFull = false;

        // Dropdown yozuvi: bo'sh joy, to'la yoki ortiqcha
        const freeText = g => {
            if (g.free_places === null || g.free_places === undefined) return "sig'im yo'q";
            if (g.free_places > 0) return g.free_places + " bo'sh";
            if (g.free_places === 0) return "to'la";
            return Math.abs(g.free_places) + ' ortiqcha';
        };

        function closeModal() { modal.classList.remove('is-open'); }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || "Amalni bajarib bo'lmadi.");
            return data;
        }

        function studentRow(st, index) {
            if (st.moved_to) {
                return '<div class="sd-student"><i>' + (index + 1) + '.</i>' +
                    '<b>' + esc(st.full_name) + '</b>' +
                    '<span>' + esc(st.student_id_number) + '</span>' +
                    '<span class="sd-moved' + (st.full_group_mode ? ' is-full' : '') + '"' +
                        (st.full_group_mode ? " title=\"To'liq guruh rejimida ko'chirilgan\"" : '') + '>&rarr; ' + esc(st.moved_to) +
                        '<button class="sd-undo" type="button" data-undo="' + st.student_id + '" title="Bekor qilish">&times;</button>' +
                    '</span></div>';
            }

            // Boshqa fakultet yoki tildagi guruh ham chiqishi mumkin — nomi
            // yonida farqi ko'rsatiladi, shunda qaysi guruhga o'tayotgani aniq bo'ladi.
            const source = groups.find(g => g.group_hemis_id === modalGroupId);
            const extra = g => {
                const parts = [];
                if (source && g.faculty_name !== source.faculty_name) parts.push(g.faculty_name || '');
                if (source && g.language_name && g.language_name !== source.language_name) parts.push(g.language_name);
                return parts.filter(Boolean).map(v => ' \u00b7 ' + esc(v)).join('');
            };
            const options = modalTargets.length
                ? modalTargets.map(g => '<option value="' + g.group_hemis_id + '">' +
                    esc(g.group_name) + ' (' + freeText(g) + ')' + extra(g) +
                    '</option>').join('')
                : '';

            const select = modalTargets.length
                ? '<select class="sd-move" data-student="' + st.student_id + '">' +
                    "<option value=\"\">Ko'chirish...</option>" + options + '</select>'
                : "<select class=\"sd-move\" disabled><option>Bo'sh guruh yo'q</option></select>";

            return '<div class="sd-student"><i>' + (index + 1) + '.</i>' +
                '<b>' + esc(st.full_name) + '</b>' +
                '<span>' + esc(st.student_id_number) + '</span>' +
                select + '</div>';
        }

        function renderStudents(students) {
            $('modalBody').innerHTML = students.length
                ? students.map(studentRow).join('')
                : '<div class="sd-modal-note">' + "Bu guruhda o'qiyotgan talaba yo'q." + '</div>';

            const moved = students.filter(st => st.moved_to).length;
            const movedText = moved ? ' &nbsp;&middot;&nbsp; <b>' + moved + '</b> ta talaba rejalashtirilgan' : '';

            if (modalFull) {
                $('modalHint').innerHTML = modalTargets.length
                    ? "To'liq guruh rejimi: <b>" + modalTargets.length + "</b> ta guruh (to'la guruhlar va ingliz guruhlari ham)" + movedText
                    : "Bu yo'nalish va kursda guruh yo'q";
                return;
            }

            $('modalHint').innerHTML = modalTargets.length
                ? '<b>' + modalTargets.length + "</b> ta mos guruhda bo'sh joy bor" + movedText
                : "Bu yo'nalish, kurs va ta'lim tilida bo'sh joyli guruh yo'q";
        }

        async function loadStudents() {
            const [studentsResponse, targetsResponse] = await Promise.all([
                fetch(studentsUrl + '?group_hemis_id=' + modalGroupId, {headers:{'Accept':'application/json'}}),
                fetch(targetsUrl + '?group_hemis_id=' + modalGroupId + (modalFull ? '&full_group_mode=1' : ''), {headers:{'Accept':'application/json'}}),
            ]);
            const studentsData = await studentsResponse.json();
            const targetsData = await targetsResponse.json();
            if (!studentsResponse.ok) throw new Error(studentsData.message || "Yuklab bo'lmadi.");

            modalTargets = targetsResponse.ok ? targetsData.groups : [];
            renderStudents(studentsData.students);
        }

        function setFullMode(on) {
            modalFull = on;
            $('modalFull').classList.toggle('is-on', on);
        }

        async function openStudents(groupId) {
            modalGroupId = groupId;
            setFullMode(false);
            const group = groups.find(g => g.group_hemis_id === groupId);
            $('modalGroup').textContent = group ? group.group_name : 'Guruh';
            $('modalMeta').textContent = group ? metaLabel(group) : '';
            $('modalBody').innerHTML = '<div class="sd-modal-note">Yuklanmoqda...</div>';
            $('modalHint').textContent = '';
            modal.classList.add('is-open');

            try {
                await loadStudents();
            } catch (error) {
                $('modalBody').innerHTML = '<div class="sd-modal-note">' + esc(error.message) + '</div>';
            }
        }

        // Ko'chirish va bekor qilish
        $('modalBody').addEventListener('change', async event => {
            const select = event.target.closest('select[data-student]');
            if (!select || !select.value) return;

            // To'la guruhga ko'chirish to'silmaydi, lekin ogohlantiriladi.
            const target = modalTargets.find(g => g.group_hemis_id === Number(select.value));
            if (target && !(target.free_places > 0)) {
                const after = target.free_places === null || target.free_places === undefined
                    ? "sig'imi belgilanmagan"
                    : (Math.abs(target.free_places) + 1) + " ta ortiqcha bo'ladi";
                if (!confirm(target.group_name + " guruhida bo'sh joy yo'q (" + after + ").\n\nBaribir ko'chirilsinmi?")) {
                    select.value = '';
                    return;
                }
            }

            select.disabled = true;
            try {
                const data = await postJson(assignUrl, {
                    student_id: Number(select.dataset.student),
                    to_group_hemis_id: Number(select.value),
                    full_group_mode: modalFull,
                });
                groups = data.groups;
                render();
                await loadStudents();
            } catch (error) {
                alert(error.message);
                select.disabled = false;
                select.value = '';
            }
        });

        $('modalBody').addEventListener('click', async event => {
            const button = event.target.closest('button[data-undo]');
            if (!button) return;
            button.disabled = true;
            try {
                const data = await postJson(unassignUrl, {student_id: Number(button.dataset.undo)});
                groups = data.groups;
                render();
                await loadStudents();
            } catch (error) {
                alert(error.message);
                button.disabled = false;
            }
        });

        // Modal faqat o'ng paneldan ochiladi.
        panels.right.rows.addEventListener('click', event => {
            const name = event.target.closest('.sd-name');
            if (!name) return;
            event.preventDefault();
            openStudents(Number(name.dataset.group));
        });

        $('modalClose').addEventListener('click', closeModal);
        $('modalFull').addEventListener('click', async () => {
            setFullMode(!modalFull);
            $('modalHint').textContent = '';
            try {
                await loadStudents();
            } catch (error) {
                $('modalBody').innerHTML = '<div class="sd-modal-note">' + esc(error.message) + '</div>';
            }
        });
        modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.sd-modal.is-open').forEach(m => m.classList.remove('is-open'));
        });

        // --- Hammasini belgilash (o'ng panel) ---
        function rightVisibleList() {
            const list = applyFilters(readFilters(panels.right));
            return rightView === 'picked'
                ? list.filter(g => sources.has(g.group_hemis_id))
                : list.filter(g => !sources.has(g.group_hemis_id));
        }

        $('rightCheckAll').addEventListener('change', event => {
            const pendingSet = rightView === 'picked' ? pendingRemove : pendingAdd;
            const list = rightVisibleList();
            if (event.target.checked) {
                list.forEach(g => pendingSet.add(g.group_hemis_id));
            } else {
                list.forEach(g => pendingSet.delete(g.group_hemis_id));
            }
            render();
        });

        // --- Talabalar ovozlari ---
        const votesModal = $('votesModal');
        let votesData = [];
        let votePicked = new Set();

        async function loadVotes() {
            try {
                const response = await fetch(votesUrl, {headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok) return;
                votesData = data.votes;
                $('votesCount').textContent = votesData.filter(v => v.status === 'pending').length;
                const open = data.voting_open_count || 0;
                $('votingStatus').hidden = open === 0;
                $('votingStatus').textContent = 'Ovoz berish ' + open + ' ta guruhda ochiq';
                $('closeVotingBtn').hidden = open === 0;
            } catch (error) { /* jim */ }
        }

        function renderVotes() {
            votePicked = new Set([...votePicked].filter(id => votesData.some(v => v.id === id)));

            const pending = votesData.filter(v => v.status === 'pending').length;
            $('votesMeta').textContent = pending + ' ta kutilmoqda \u00b7 ' + (votesData.length - pending) + ' ta tasdiqlangan';

            $('votesBody').innerHTML = votesData.length
                ? votesData.map(v => {
                    const badge = v.status === 'approved'
                        ? '<span style="margin-left:8px;padding:2px 8px;border-radius:999px;background:#e9f7f0;color:#0f7a52;font-size:10px;font-weight:700;">Tasdiqlangan</span>'
                        : '';

                    return '<label class="sd-vote-row">' +
                        '<input type="checkbox" data-vote="' + v.id + '"' + (votePicked.has(v.id) ? ' checked' : '') + ' style="width:15px;height:15px;accent-color:var(--navy);cursor:pointer;">' +
                        '<span><b>' + esc(v.student_name) + '</b>' + badge +
                        '<span class="sd-meta">' + esc(v.student_id_number || '') + ' \u00b7 ' + esc(v.voted_at || '') + '</span></span>' +
                        '<span class="sd-vote-route">' + esc(v.from_group_name || '') + ' &rarr; <b>' + esc(v.to_group_name || '') + '</b></span>' +
                        '<button class="sd-vote-del" type="button" data-del-vote="' + v.id + '" title="Ovozni o’chirish">' +
                            '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg>' +
                        '</button>' +
                        '</label>';
                }).join('')
                : '<div class="sd-modal-note">Hali ovoz berilmagan.</div>';

            $('votesPicked').textContent = votePicked.size;
            const pendingPicked = votesData.some(v => votePicked.has(v.id) && v.status === 'pending');
            $('approveVotesBtn').disabled = !pendingPicked;
            $('deleteVotesBtn').disabled = votePicked.size === 0;
        }

        $('votesBtn').addEventListener('click', async () => {
            votesModal.classList.add('is-open');
            $('votesBody').innerHTML = '<div class="sd-modal-note">Yuklanmoqda...</div>';
            await loadVotes();
            renderVotes();
        });

        $('votesBody').addEventListener('click', async event => {
            const button = event.target.closest('button[data-del-vote]');
            if (!button) return;
            event.preventDefault();
            const vote = votesData.find(v => v.id === Number(button.dataset.delVote));
            const label = vote ? vote.student_name : 'Bu';
            if (!confirm(label + " ovozi o'chirilsinmi?" + (vote && vote.status === 'approved' ? " Rejasi ham bekor bo'ladi va joyi qaytadi." : ''))) return;
            button.disabled = true;
            try {
                const data = await postJson(deleteVotesUrl, {vote_ids: [Number(button.dataset.delVote)]});
                groups = data.groups;
                render();
                await loadVotes();
                renderVotes();
            } catch (error) {
                alert(error.message);
                button.disabled = false;
            }
        });

        $('votesBody').addEventListener('change', event => {
            const box = event.target.closest('input[data-vote]');
            if (!box) return;
            const id = Number(box.dataset.vote);
            if (box.checked) votePicked.add(id); else votePicked.delete(id);
            renderVotes();
        });

        $('votesCheckAll').addEventListener('change', event => {
            if (event.target.checked) {
                votesData.forEach(v => votePicked.add(v.id));
            } else {
                votePicked.clear();
            }
            renderVotes();
        });

        $('approveVotesBtn').addEventListener('click', async () => {
            if (!confirm(votePicked.size + " ta ovozni tasdiqlaysizmi? Talabalar tanlagan guruhlariga rejalashtiriladi va joylari band qilinadi.")) return;
            $('approveVotesBtn').disabled = true;
            try {
                const data = await postJson(approveVotesUrl, {vote_ids: [...votePicked]});
                groups = data.groups;
                render();
                await loadVotes();
                renderVotes();
                alert(data.message + (data.failed && data.failed.length ? '\n\n' + data.failed.join('\n') : ''));
            } catch (error) {
                alert(error.message);
                $('approveVotesBtn').disabled = false;
            }
        });

        $('deleteVotesBtn').addEventListener('click', async () => {
            if (!confirm(votePicked.size + " ta ovoz o'chirilsinmi? Tasdiqlangan ovozning rejasi ham bekor bo'ladi va joyi qaytadi.")) return;
            $('deleteVotesBtn').disabled = true;
            try {
                const data = await postJson(deleteVotesUrl, {vote_ids: [...votePicked]});
                groups = data.groups;
                render();
                votePicked.clear();
                await loadVotes();
                renderVotes();
                alert(data.message);
            } catch (error) {
                alert(error.message);
                $('deleteVotesBtn').disabled = false;
            }
        });

        $('openVotingBtn').addEventListener('click', async () => {
            const pendingSet = rightView === 'picked' ? pendingRemove : pendingAdd;
            if (!pendingSet.size) return;
            if (!confirm(pendingSet.size + " ta guruh talabalariga ovoz berish ochilsinmi? Ular LMS ga kirganda guruh tanlash oynasi chiqadi.")) return;
            try {
                const data = await postJson(openVotingUrl, {group_hemis_ids: [...pendingSet]});
                alert(data.message);
                await loadVotes();
            } catch (error) {
                alert(error.message);
            }
        });

        $('closeVotingBtn').addEventListener('click', async () => {
            if (!confirm("Ovoz berish barcha guruhlar uchun yopilsinmi?")) return;
            try {
                const data = await postJson(closeVotingUrl, {});
                alert(data.message);
                await loadVotes();
            } catch (error) {
                alert(error.message);
            }
        });

        $('votesClose').addEventListener('click', () => votesModal.classList.remove('is-open'));
        votesModal.addEventListener('click', event => {
            if (event.target === votesModal) votesModal.classList.remove('is-open');
        });

        // --- Eksport tanlovi ---
        const exportModal = $('exportModal');
        let exportSide = 'left';

        exportModal.querySelectorAll('button[data-mode]').forEach(button => {
            button.addEventListener('click', () => {
                exportModal.classList.remove('is-open');
                const f = readFilters(panels[exportSide]);
                const params = new URLSearchParams();
                if (f.faculty) params.set('faculty', f.faculty);
                if (f.specialty) params.set('specialty', f.specialty);
                if (f.course) params.set('course', f.course);
                if (f.search) params.set('search', f.search);
                params.set('side', exportSide);
                params.set('mode', button.dataset.mode);
                if (exportSide === 'right' && rightView === 'picked') params.set('only_sources', '1');
                window.location.href = exportUrl + '?' + params.toString();
            });
        });

        exportModal.querySelector('[data-close]').addEventListener('click', () => exportModal.classList.remove('is-open'));
        exportModal.addEventListener('click', event => {
            if (event.target === exportModal) exportModal.classList.remove('is-open');
        });

        // Sig'im: input <label> ichida bo'lgani uchun bosilganda checkbox
        // almashmasligi kerak — shuning uchun hodisa to'xtatiladi.
        function guardCapacityInputs(root) {
            root.addEventListener('click', event => {
                if (event.target.matches('input[data-cap]')) event.preventDefault();
            });
        }

        async function saveCapacity(input) {
            const groupId = Number(input.dataset.cap);
            const value = input.value.trim();
            if (value === '') return;

            input.disabled = true;
            try {
                const response = await fetch(capacityUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                    body: JSON.stringify({group_hemis_id: groupId, capacity: Number(value)}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || "Saqlab bo'lmadi.");

                const index = groups.findIndex(g => g.group_hemis_id === groupId);
                if (index !== -1) groups[index] = data.group;
                render();
            } catch (error) {
                alert(error.message);
                input.disabled = false;
            }
        }

        Object.values(panels).forEach(panel => {
            guardCapacityInputs(panel.rows);
            panel.rows.addEventListener('change', event => {
                if (event.target.matches('input[data-cap]')) saveCapacity(event.target);
            });
            panel.rows.addEventListener('keydown', event => {
                if (event.target.matches('input[data-cap]') && event.key === 'Enter') {
                    event.preventDefault();
                    event.target.blur();
                }
            });
        });

        // Excel: avval qaysi holat kerakligini so'raymiz.
        document.querySelectorAll('.sd-xls').forEach(button => button.addEventListener('click', () => {
            exportSide = button.dataset.export;
            exportModal.classList.add('is-open');
        }));

        $('saveSources').addEventListener('click', async () => {
            const button = $('saveSources');
            const removing = rightView === 'picked';

            // O'tqazish: saqlanganlarga yangilarini qo'shamiz.
            // Qaytarish: tanlanganlarni saqlanganlardan chiqaramiz.
            const next = new Set(sources);
            if (removing) {
                pendingRemove.forEach(id => next.delete(id));
            } else {
                pendingAdd.forEach(id => next.add(id));
            }

            button.disabled = true;
            try {
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                    body: JSON.stringify({group_hemis_ids: [...next]}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || "Saqlab bo'lmadi.");

                groups = data.groups;
                sources = new Set(groups.filter(g => g.is_source).map(g => g.group_hemis_id));
                pendingAdd.clear();
                pendingRemove.clear();

                // O'tqazishdan keyin "Belgilanganlar" tabiga o'tamiz.
                renderPanel('left');
                setRightView('picked');
            } catch (error) {
                alert(error.message);
                renderTabs();
            }
        });

        // Panellarni oyna balandligiga tenglashtiramiz — pastdan 100px qoladi.
        function sizePanels() {
            const cols = document.querySelector('.sd-cols');
            if (!cols) return;
            if (window.innerWidth <= 1100) {
                cols.querySelectorAll('.sd-side').forEach(el => { el.style.height = ''; });
                return;
            }
            const top = cols.getBoundingClientRect().top + window.scrollY;
            const height = Math.max(360, window.innerHeight - top - 100);
            cols.querySelectorAll('.sd-side').forEach(el => { el.style.height = height + 'px'; });
        }
        window.addEventListener('resize', sizePanels);
        sizePanels();
        loadVotes();

        Object.values(panels).forEach(refreshOptions);
        render();
    })();
    </script>
</x-app-layout>

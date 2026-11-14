{{-- Vedomost tekshirish uslublari: filtrlar, select2, natijalar jadvali, modal --}}
    <style>
        .filter-container { padding:16px 20px 12px; background:linear-gradient(135deg,#f0f4f8,#e8edf5); border-bottom:2px solid #dbe4ef; }
        .filter-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; align-items:flex-end; }
        .filter-row:last-child { margin-bottom:0; }
        .filter-label { display:flex; align-items:center; gap:5px; margin-bottom:4px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; }
        .fl-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }

        /* Select2 single */
        .select2-container--classic .select2-selection--single { height:36px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--single:hover { border-color:#2b5ea7; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height:34px; padding-left:10px; padding-right:52px; color:#1e293b; font-size:.8rem; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height:34px; width:22px; background:transparent; border-left:none; right:0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position:absolute; right:22px; top:50%; transform:translateY(-50%); font-size:16px; font-weight:bold; color:#94a3b8; cursor:pointer; padding:2px 6px; z-index:2; background:#fff; border-radius:50%; line-height:1; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color:#fff; background:#ef4444; }

        /* Select2 (single only) */
        .select2-dropdown { font-size:.8rem; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 8px 24px rgba(0,0,0,.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color:#2b5ea7; }
        .select2-container--classic .select2-results__option[aria-selected=true] { background:#eff6ff; color:#1e40af; }

        /* Checkbox dropdown */
        .cb-wrap { position:relative; width:100%; }
        .cb-btn { width:100%; height:36px; display:flex; align-items:center; justify-content:space-between; gap:6px; padding:0 10px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.04); transition:border-color .15s; }
        .cb-btn:hover { border-color:#2b5ea7; }
        .cb-label-text { flex:1; font-size:.8rem; font-weight:500; color:#94a3b8; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cb-panel { position:fixed; background:#fff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.14); z-index:99999; padding:8px; min-width:220px; }
        .cb-search { width:100%; height:32px; border:1px solid #cbd5e1; border-radius:7px; padding:0 10px; font-size:.8rem; outline:none; margin-bottom:6px; box-sizing:border-box; }
        .cb-search:focus { border-color:#2b5ea7; }
        .cb-all-label { display:flex; align-items:center; gap:8px; padding:5px 4px; font-size:.8rem; font-weight:700; color:#334155; cursor:pointer; border-bottom:1px solid #f1f5f9; margin-bottom:4px; }
        .cb-all-label input { width:14px; height:14px; cursor:pointer; accent-color:#2b5ea7; }
        .cb-list { max-height:180px; overflow-y:auto; display:flex; flex-direction:column; gap:1px; }
        .cb-item { display:flex; align-items:center; gap:8px; padding:5px 4px; font-size:.8rem; color:#1e293b; cursor:pointer; border-radius:5px; }
        .cb-item:hover { background:#eff6ff; }
        .cb-item input[type=checkbox] { width:14px; height:14px; cursor:pointer; accent-color:#2b5ea7; flex-shrink:0; }
        .cb-item span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        /* Date input */
        .date-input { height:36px; border:1px solid #cbd5e1; border-radius:8px; padding:0 30px 0 10px; font-size:.8rem; font-weight:500; color:#1e293b; background:#fff; width:100%; box-shadow:0 1px 2px rgba(0,0,0,.04); outline:none; }
        .date-input:focus { border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
        .date-input::placeholder { color:#94a3b8; font-weight:400; }

        /* Buttons */
        .btn-calc { display:inline-flex; align-items:center; gap:8px; padding:8px 20px; background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(43,94,167,.3); height:36px; }
        .btn-calc:hover:not(:disabled) { background:linear-gradient(135deg,#1e4b8a,#2b5ea7); }
        /* Filtrlarni tozalash — ikkinchi darajali, Qidirish yonida */
        .btn-reset { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; height:36px; background:#fff; color:#475569; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
        .btn-reset:hover { background:#f1f5f9; border-color:#94a3b8; color:#0f172a; }
        .btn-reset:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }
        .btn-excel { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; height:36px; transition:all .2s; white-space:nowrap; }
        .btn-disabled { background:#94a3b8; cursor:not-allowed; }
        .btn-active { background:linear-gradient(135deg,#16a34a,#22c55e); cursor:pointer; box-shadow:0 2px 8px rgba(22,163,74,.3); }
        .btn-active:hover { background:linear-gradient(135deg,#15803d,#16a34a); transform:translateY(-1px); }

        /* Toggle */
        .toggle-switch { display:inline-flex; align-items:center; gap:10px; cursor:pointer; padding:6px 0; height:36px; user-select:none; }
        .toggle-track { width:40px; height:22px; background:#cbd5e1; border-radius:11px; position:relative; transition:background .25s; flex-shrink:0; }
        .toggle-switch.active .toggle-track { background:linear-gradient(135deg,#2b5ea7,#3b7ddb); }
        .toggle-thumb { width:18px; height:18px; background:#fff; border-radius:50%; position:absolute; top:2px; left:2px; transition:transform .25s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
        .toggle-switch.active .toggle-thumb { transform:translateX(18px); }
        .toggle-label { font-size:12px; font-weight:600; color:#64748b; white-space:nowrap; }
        .toggle-switch.active .toggle-label { color:#1e3a5f; }

        /* Result table */
        .result-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .result-table thead { position:sticky; top:0; z-index:10; }
        .result-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef); }
        .result-table th { padding:12px 10px; text-align:left; font-weight:600; font-size:11.5px; color:#334155; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; border-bottom:2px solid #cbd5e1; }
        .result-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
        .result-table tbody tr:nth-child(even) { background:#f8fafc; }
        .result-table tbody tr:nth-child(odd)  { background:#fff; }
        .result-table tbody tr:hover { background:#eff6ff !important; }
        .result-table tbody tr.selected { background:#dbeafe !important; }
        .result-table td { padding:9px 10px; vertical-align:middle; }
        .td-num { font-weight:700; color:#2b5ea7; font-size:13px; padding-left:12px !important; }

        .badge-indigo { display:inline-block; padding:3px 9px; background:linear-gradient(135deg,#1a3268,#2b5ea7); color:#fff; border-radius:6px; font-size:11.5px; font-weight:600; white-space:nowrap; }
        .text-cell { font-size:12.5px; font-weight:500; line-height:1.35; display:block; }
        .text-cyan { color:#0e7490; max-width:200px; white-space:normal; word-break:break-word; }
        .text-subject { color:#0f172a; font-weight:700; font-size:12.5px; max-width:340px; white-space:normal; word-break:break-word; }
        .date-badge { display:inline-block; padding:3px 8px; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:5px; font-size:12px; font-weight:600; white-space:nowrap; }

        /* Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; display:flex; align-items:center; justify-content:center; }
        .modal-box { background:#fff; border-radius:16px; padding:28px 32px; width:360px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .modal-title { font-size:18px; font-weight:700; color:#0f172a; }
        .modal-close { background:none; border:none; font-size:20px; color:#94a3b8; cursor:pointer; padding:2px 6px; border-radius:6px; }
        .modal-close:hover { color:#ef4444; background:#fef2f2; }
        .modal-weights { display:flex; flex-direction:column; gap:10px; }
        .mw-row { display:flex; align-items:center; gap:12px; }
        .mw-label { min-width:70px; font-size:13px; font-weight:700; color:#334155; }
        .mw-input { flex:1; height:38px; border:1px solid #cbd5e1; border-radius:8px; text-align:center; font-size:14px; font-weight:700; padding:0 8px; outline:none; }
        .mw-input:focus { border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
        .modal-cancel-btn { padding:8px 20px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; font-size:13px; font-weight:600; color:#334155; cursor:pointer; }
        .modal-cancel-btn:hover { background:#f1f5f9; }
        .btn-sync-hemis { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; background:linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(37,99,235,.3); transition:all .2s; }
        .btn-sync-hemis:hover:not(:disabled) { background:linear-gradient(135deg,#1d4ed8,#2563eb); }
        .btn-sync-hemis.btn-disabled { opacity:.5; cursor:not-allowed; background:#94a3b8; box-shadow:none; }
        .btn-sync-active { opacity:1 !important; background:linear-gradient(135deg,#2563eb,#3b82f6) !important; box-shadow:0 2px 8px rgba(37,99,235,.3) !important; }
        .modal-download-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 22px; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(22,163,74,.3); }
        .modal-download-btn:hover:not(:disabled) { background:linear-gradient(135deg,#15803d,#16a34a); }
        .modal-download-btn:disabled { opacity:.5; cursor:not-allowed; }
    </style>

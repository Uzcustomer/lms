<style>
    .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
    .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
    .filter-row:last-child { margin-bottom: 0; }
    .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
    .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
    .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
    .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
    .date-input::placeholder { color: #94a3b8; font-weight: 400; }

    .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
    .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

    .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
    .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }
    .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

    .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
    .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
    .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
    .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
    .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
    .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

    .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
    .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
    .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
    .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
    .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
    .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
    .toggle-switch.active .toggle-label { color: #1e3a5f; }

    .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .journal-table thead { position: sticky; top: 0; z-index: 10; }
    .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
    .journal-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
    .journal-table th.th-num { padding: 14px 12px 14px 16px; width: 44px; }
    .sort-link { display: inline-flex; align-items: center; gap: 4px; color: #334155; text-decoration: none; cursor: pointer; }
    .sort-link:hover { opacity: 0.75; }
    .sort-icon { font-size: 8px; opacity: 0.4; }
    .sort-icon.active { font-size: 11px; opacity: 1; color: #ef4444; }

    .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
    .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
    .journal-table tbody tr:nth-child(odd) { background: #fff; }
    .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
    .journal-table td { padding: 10px 12px; vertical-align: middle; line-height: 1.4; }
    .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

    .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
    .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
    .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
    .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
    .badge-grade-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
    .badge-grade-yellow { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
    .badge-grade-green { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }

    .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
    .text-emerald { color: #047857; }
    .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
    .text-subject { color: #0f172a; font-weight: 700; font-size: 12.5px; max-width: 260px; white-space: normal; word-break: break-word; }
    .journal-link { display: inline-block; padding: 3px 10px; background: #eff6ff; color: #2b5ea7; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 11.5px; font-weight: 600; text-decoration: none; transition: all 0.15s; white-space: nowrap; }
    .journal-link:hover { background: #2b5ea7; color: #fff; border-color: #2b5ea7; }

    .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
    .pg-btn:hover { background: #eff6ff; border-color: #2b5ea7; color: #2b5ea7; }
    .pg-active { background: linear-gradient(135deg, #2b5ea7, #3b7ddb) !important; color: #fff !important; border-color: #2b5ea7 !important; }
</style>

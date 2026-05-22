<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Diagnostika (Test markazi)
        </h2>
    </x-slot>

    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <style>
        /* === FILTERS === */
        .filter-container { padding: 12px 16px 10px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { flex: 0 0 auto; }
        .filter-buttons { flex: 0 0 auto; }
        .filter-label { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.78rem; font-weight: 500; color: #1e293b; background: #fff; width: 150px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        /* === ACTION BAR === */
        .action-bar { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 8px; }
        .action-left { display: flex; align-items: center; gap: 12px; }
        .action-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .sel-info { font-size: 12px; font-weight: 600; color: #64748b; padding: 4px 10px; background: #e2e8f0; border-radius: 6px; }
        .total-info { font-size: 12px; font-weight: 700; color: #1e3a5f; padding: 4px 10px; background: #dbeafe; border-radius: 6px; }
        .import-group { display: flex; align-items: center; gap: 4px; }

        /* === BUTTONS === */
        .btn-tartibga { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(8,145,178,0.3); height: 36px; white-space: nowrap; }
        .btn-tartibga:hover { background: linear-gradient(135deg, #0e7490, #0891b2); transform: translateY(-1px); }
        .btn-tartibga:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-excel { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 32px; white-space: nowrap; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-excel-xulosa { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #d97706, #f59e0b); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(217,119,6,0.3); height: 32px; white-space: nowrap; }
        .btn-excel-xulosa:hover:not(:disabled) { background: linear-gradient(135deg, #b45309, #d97706); transform: translateY(-1px); }
        .btn-excel-xulosa:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-upload { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(124,58,237,0.3); height: 32px; white-space: nowrap; }
        .btn-upload:hover:not(:disabled) { background: linear-gradient(135deg, #6d28d9, #7c3aed); transform: translateY(-1px); }
        .btn-upload:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-delete-grades { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(220,38,38,0.3); height: 32px; white-space: nowrap; }
        .btn-delete-grades:hover:not(:disabled) { background: linear-gradient(135deg, #b91c1c, #dc2626); transform: translateY(-1px); }
        .btn-delete-grades:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-compare { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(8,145,178,0.3); height: 32px; white-space: nowrap; }
        .btn-compare:hover:not(:disabled) { background: linear-gradient(135deg, #0e7490, #0891b2); transform: translateY(-1px); }
        .btn-compare:disabled { cursor: not-allowed; opacity: 0.4; }

        .compare-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .compare-modal { background: #fff; border-radius: 12px; max-width: 800px; width: 95%; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .compare-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .compare-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
        .compare-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .compare-table th { background: #f1f5f9; padding: 8px 10px; text-align: left; font-weight: 700; color: #334155; border-bottom: 2px solid #e2e8f0; }
        .compare-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
        .compare-table tr:hover { background: #f8fafc; }
        .compare-empty { text-align: center; padding: 40px; color: #94a3b8; }

        .xulosa-dup-del { display: none; position: absolute; right: -8px; top: -8px; width: 16px; height: 16px; border-radius: 50%; border: none; cursor: pointer; background: #dc2626; color: #fff; font-size: 10px; line-height: 1; align-items: center; justify-content: center; padding: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.3); z-index: 5; }
        .xulosa-dup-del:hover { background: #b91c1c; }
        .xulosa-dup-wrap:hover .xulosa-dup-del { display: inline-flex; }

        /* Conflict modal */
        .conflict-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .conflict-modal { background: #fff; border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .conflict-title { font-size: 16px; font-weight: 700; color: #dc2626; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .conflict-desc { font-size: 13px; color: #475569; margin-bottom: 16px; line-height: 1.5; }
        .conflict-group { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
        .conflict-group-title { font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .conflict-fan { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
        .conflict-fan label { font-size: 13px; color: #334155; cursor: pointer; }
        .conflict-fan input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        .conflict-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
        .conflict-btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; }
        .conflict-btn-cancel { background: #e2e8f0; color: #475569; }
        .conflict-btn-cancel:hover { background: #cbd5e1; }
        .conflict-btn-delete { background: #dc2626; color: #fff; }
        .conflict-btn-delete:hover { background: #b91c1c; }
        .conflict-btn-all { background: #f59e0b; color: #fff; }
        .conflict-btn-all:hover { background: #d97706; }
        .btn-file { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #fff; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.15s; height: 32px; white-space: nowrap; max-width: 160px; overflow: hidden; text-overflow: ellipsis; }
        .btn-file:hover { background: #f1f5f9; border-color: #94a3b8; }
        .btn-import { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(37,99,235,0.3); height: 32px; white-space: nowrap; }
        .btn-import:hover:not(:disabled) { background: linear-gradient(135deg, #1d4ed8, #2563eb); transform: translateY(-1px); }
        .btn-import:disabled { cursor: not-allowed; opacity: 0.4; }
        .btn-cron { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(22,163,74,0.3); height: 36px; white-space: nowrap; }
        .btn-cron:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-cron:disabled { cursor: not-allowed; opacity: 0.4; }

        /* === DIAGNOSTIKA PANELS === */
        .diag-msg { padding: 10px 16px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .diag-info { background: #eff6ff; color: #1e40af; border-bottom: 1px solid #bfdbfe; }
        .diag-success { background: #f0fdf4; color: #166534; border-bottom: 1px solid #bbf7d0; }
        .diag-warning { background: #fffbeb; color: #92400e; border-bottom: 1px solid #fde68a; }
        .diag-error { background: #fef2f2; color: #991b1b; border-bottom: 1px solid #fecaca; }

        /* === TABLE === */
        .empty-state { padding: 60px 20px; text-align: center; }
        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr:first-child { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 10px 8px; text-align: left; font-weight: 600; font-size: 10.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { width: 40px; }

        /* Filter header row */
        .filter-header-row { background: #f1f5f9 !important; }
        .filter-header-row th { padding: 4px 4px 6px; border-bottom: 2px solid #94a3b8; }
        .col-filter-input { width: 100%; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #334155; background: #fff; outline: none; height: 26px; }
        .col-filter-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .col-filter-input::placeholder { color: #94a3b8; }

        /* === ADVANCED FILTER (Baho, Sana) === */
        .adv-filter-wrap { position: relative; }
        .adv-filter-btn { display: inline-flex; align-items: center; gap: 4px; width: 100%; padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #64748b; background: #fff; cursor: pointer; outline: none; height: 26px; transition: all 0.15s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .adv-filter-btn:hover { border-color: #2b5ea7; background: #f0f4ff; }
        .adv-filter-btn.adv-active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 700; }
        .adv-active-label { color: #1d4ed8 !important; }
        .adv-filter-popup { display: none; position: absolute; top: 30px; right: 0; z-index: 100; min-width: 200px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 10px; }
        .adv-filter-title { font-size: 11px; font-weight: 700; color: #1e293b; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.04em; }
        .adv-filter-select { width: 100%; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; font-weight: 500; color: #334155; background: #f8fafc; cursor: pointer; outline: none; margin-bottom: 6px; }
        .adv-filter-select:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .adv-filter-inputs { display: flex; gap: 4px; margin-bottom: 8px; }
        .adv-filter-input { flex: 1; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; font-weight: 500; color: #334155; background: #fff; outline: none; }
        .adv-filter-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .adv-filter-input::placeholder { color: #94a3b8; }
        .adv-filter-actions { display: flex; gap: 6px; justify-content: flex-end; }
        .adv-btn-clear { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 10px; font-weight: 600; color: #64748b; background: #f8fafc; cursor: pointer; transition: all 0.15s; }
        .adv-btn-clear:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
        .adv-btn-apply { padding: 4px 10px; border: none; border-radius: 6px; font-size: 10px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #2563eb, #3b82f6); cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 4px rgba(37,99,235,0.3); }
        .adv-btn-apply:hover { background: linear-gradient(135deg, #1d4ed8, #2563eb); transform: translateY(-1px); }

        /* Ustun ko'p tanlovli filtrlari */
        .ms-wrap { position: relative; }
        .ms-col-btn { display: flex; align-items: center; justify-content: space-between; gap: 3px; width: 100%; padding: 3px 5px; height: 26px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 10px; font-weight: 500; color: #334155; background: #fff; cursor: pointer; outline: none; }
        .ms-col-btn:hover { border-color: #2b5ea7; }
        .ms-col-btn.ms-active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 700; }
        .ms-btn-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ms-popup { display: none; position: absolute; top: 30px; left: 0; z-index: 200; width: 230px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.16); padding: 8px; }
        .ms-search { width: 100%; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 11px; outline: none; margin-bottom: 6px; box-sizing: border-box; }
        .ms-search:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .ms-opts { max-height: 220px; overflow-y: auto; }
        .ms-opt { display: flex; align-items: center; gap: 6px; padding: 4px 6px; font-size: 11px; font-weight: 500; color: #334155; cursor: pointer; border-radius: 5px; }
        .ms-opt:hover { background: #f1f5f9; }
        .ms-opt input[type="checkbox"] { width: 14px; height: 14px; cursor: pointer; flex: 0 0 auto; }
        .ms-opt span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ms-opt-all { border-bottom: 1px solid #e2e8f0; margin-bottom: 4px; padding-bottom: 6px; font-weight: 700; }
        .ms-actions { display: flex; justify-content: flex-end; margin-top: 6px; padding-top: 6px; border-top: 1px solid #e2e8f0; }
        .ms-clear { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 10px; font-weight: 600; color: #64748b; background: #f8fafc; cursor: pointer; }
        .ms-clear:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #f1f5f9 !important; }
        .journal-table td { padding: 7px 8px; vertical-align: middle; line-height: 1.4; }
        .td-num { font-weight: 700; color: #64748b; font-size: 12px; }
        .row-uploaded { background: #dcfce7 !important; }
        .row-uploaded td { opacity: 0.85; }

        .journal-view-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; transition: all .15s; text-decoration: none; }
        .journal-view-btn:hover { background: #dbeafe; border-color: #3b82f6; color: #1e3a8a; }

        /* === BADGES === */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
        .badge-grade { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; font-weight: 800; min-width: 32px; text-align: center; }
        .editable-grade { cursor: pointer; transition: all .15s; }
        .editable-grade:hover { background: #bfdbfe; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.2); }
        .badge-oski { background: #fce7f3; color: #9d174d; border: 1px solid #fbcfe8; font-weight: 800; min-width: 32px; text-align: center; }
        .text-cell { font-size: 12px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 200px; white-space: normal; word-break: break-word; }

        /* === CHECKBOX === */
        .cb-styled { width: 16px; height: 16px; accent-color: #2b5ea7; cursor: pointer; }

        /* === PAGINATION === */
        .pagination-area { padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }

        /* === SPINNER === */
        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .spinner-sm { width: 16px; height: 16px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* === REUPLOAD MODAL === */
        .reupload-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .reupload-modal { background: #fff; border-radius: 12px; max-width: 1100px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .reupload-modal-header { padding: 16px 24px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: space-between; }
        .reupload-modal-header h3 { margin: 0; font-size: 17px; font-weight: 700; }
        .reupload-modal-close { background: none; border: none; color: #fff; font-size: 28px; line-height: 1; cursor: pointer; padding: 0 8px; }
        .reupload-modal-close:hover { opacity: 0.8; }
        .reupload-modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
        .reupload-modal-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .reupload-modal-table thead tr { background: #f8fafc; }
        .reupload-modal-table th { padding: 10px 12px; text-align: left; font-weight: 700; font-size: 11px; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .reupload-modal-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .reupload-modal-table tbody tr:hover { background: #fffbeb; }
        .reupload-grade-badge { display: inline-flex; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #eff6ff; color: #1d4ed8; }
        .reupload-modal-footer { padding: 14px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc; }
        .reupload-btn-cancel { padding: 8px 18px; background: #f1f5f9; color: #475569; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; }
        .reupload-btn-cancel:hover { background: #e2e8f0; }
        .reupload-btn-confirm { padding: 8px 24px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 13px; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
        .reupload-btn-confirm:hover { box-shadow: 0 4px 12px rgba(245,158,11,0.4); }
        .reupload-btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }
        .reupload-subject-select:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
    </style>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Sana filtrlari + Tartibga solish tugmasi -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="max-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Sanadan</label>
                            <input type="text" id="date_from" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="max-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Sanagacha</label>
                            <input type="text" id="date_to" class="date-input" placeholder="Sanani tanlang" autocomplete="off" />
                        </div>
                        <div class="filter-item filter-buttons">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <button type="button" id="btn-trigger-cron" class="btn-cron" onclick="triggerMoodleCron()">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span id="cron-label">Yangilash</span>
                                </button>
                                <button type="button" id="btn-tartibga" class="btn-tartibga" onclick="loadTartibgaSol()">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                                    Tartibga solish
                                </button>
                            </div>
                        </div>
                        <div class="filter-item" style="margin-left:auto;max-width:280px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Ism bo'yicha qidiruv ({{ now('Asia/Tashkent')->year }}-yil)</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="text" id="search_student_name" class="date-input" placeholder="FISH kiriting..." autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();searchByName();}" style="flex:1;" />
                                <button type="button" class="btn-tartibga" onclick="searchByName()" style="background:#10b981;border-color:#059669;white-space:nowrap;">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                        <div class="filter-item" style="max-width:280px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0ea5e9;"></span> Shakl bo'yicha qidiruv (barcha sanalar)</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="text" id="search_shakl" class="date-input" placeholder="masalan: qo'shimcha" autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();searchByShakl();}" style="flex:1;" />
                                <button type="button" class="btn-tartibga" onclick="searchByShakl()" style="background:#0ea5e9;border-color:#0284c7;white-space:nowrap;">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="action-left">
                        <span id="selection-info" class="sel-info">
                            <span id="selected-count">0</span> ta tanlangan
                        </span>
                        <span id="total-info" class="total-info" style="display:none;"></span>
                    </div>
                    <div class="action-right">
                        <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Quiz natijalar
                        </button>

                        <button type="button" id="btn-excel-xulosa" class="btn-excel-xulosa" onclick="downloadXulosaExcel()" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Xulosali Excel
                        </button>

                        <button type="button" id="btn-upload" class="btn-upload" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            Sistemaga yuklash
                        </button>

                        <button type="button" id="btn-reupload" class="btn-upload" style="background:#f59e0b;border-color:#d97706;" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Qayta yuklash
                        </button>

                        <button type="button" id="btn-compare" class="btn-compare" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Solishtirish
                        </button>

                        <button type="button" id="btn-delete-grades" class="btn-delete-grades" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Bahoni o'chirish
                        </button>

                        <div class="import-group">
                            <input type="file" id="file-upload" accept=".xlsx,.xls,.csv" style="display:none;">
                            <button type="button" class="btn-file" onclick="document.getElementById('file-upload').click()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span id="file-label">Fayl tanlash</span>
                            </button>
                            <button type="button" id="btn-import" class="btn-import" onclick="importFile()" disabled>
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Yuklash
                            </button>
                        </div>
                    </div>
                </div>

                <div id="upload-result" style="display:none;"></div>
                <div id="import-result" style="display:none;"></div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" class="empty-state">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Sanalarni tanlang va "Tartibga solish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Quiz natijalarini tartibga solish, diagnostika va sistemaga yuklash</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="max-height:calc(100vh - 340px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table" id="results-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;padding-left:14px;">
                                            <input type="checkbox" id="select-all" class="cb-styled">
                                        </th>
                                        <th class="th-num">#</th>
                                        <th>Student ID</th>
                                        <th>FISH</th>
                                        <th>Fakultet</th>
                                        <th>Yo'nalish</th>
                                        <th>Kurs</th>
                                        <th>Semestr</th>
                                        <th>Guruh</th>
                                        <th>Fan</th>
                                        <th>Fan ID</th>
                                        <th>YN turi</th>
                                        <th>Shakl</th>
                                        <th>Baho</th>
                                        <th>Sana</th>
                                        <th>Xulosa</th>
                                        <th style="width:60px;">Jurnal</th>
                                    </tr>
                                    @php
                                        $msCell = function ($col) {
                                            $h = e($col);
                                            return '<div class="ms-wrap" data-ms="' . $h . '">'
                                                . '<button type="button" class="ms-col-btn" onclick="msToggle(\'' . $h . '\')">'
                                                . '<span class="ms-btn-text" id="ms-text-' . $h . '">Barchasi</span>'
                                                . '<svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
                                                . '</button>'
                                                . '<div class="ms-popup" id="ms-popup-' . $h . '">'
                                                . '<input type="text" class="ms-search" placeholder="Qidirish..." oninput="msFilterOptions(\'' . $h . '\')">'
                                                . '<label class="ms-opt ms-opt-all"><input type="checkbox" class="ms-all-cb" onchange="msToggleAll(\'' . $h . '\')"><span>Barchasi</span></label>'
                                                . '<div class="ms-opts" id="ms-opts-' . $h . '"></div>'
                                                . '<div class="ms-actions"><button type="button" class="ms-clear" onclick="msClear(\'' . $h . '\')">Tozalash</button></div>'
                                                . '</div></div>';
                                        };
                                    @endphp
                                    <tr class="filter-header-row">
                                        <th></th>
                                        <th></th>
                                        <th><input type="text" class="col-filter-input" data-col="student_id" placeholder="ID..."></th>
                                        <th><input type="text" class="col-filter-input" data-col="full_name" placeholder="Ism..."></th>
                                        <th>{!! $msCell('faculty') !!}</th>
                                        <th>{!! $msCell('direction') !!}</th>
                                        <th>{!! $msCell('kurs') !!}</th>
                                        <th>{!! $msCell('semester') !!}</th>
                                        <th>{!! $msCell('group') !!}</th>
                                        <th>{!! $msCell('fan_name') !!}</th>
                                        <th><input type="text" class="col-filter-input" data-col="fan_id" placeholder="Fan ID..."></th>
                                        <th>{!! $msCell('yn_turi') !!}</th>
                                        <th><input type="text" class="col-filter-input" data-col="shakl" placeholder="Shakl..."></th>
                                        <th>
                                            <div class="adv-filter-wrap">
                                                <button type="button" class="adv-filter-btn" onclick="toggleAdvFilter('baho')">
                                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                                    <span id="baho-filter-label">Baho</span>
                                                </button>
                                                <div class="adv-filter-popup" id="baho-popup">
                                                    <div class="adv-filter-title">Baho filtri</div>
                                                    <select id="baho-op" class="adv-filter-select" onchange="toggleBahoSecond()">
                                                        <option value="">Barchasi</option>
                                                        <option value="eq">Teng (=)</option>
                                                        <option value="gt">Dan katta (&gt;)</option>
                                                        <option value="gte">Dan katta yoki teng (&ge;)</option>
                                                        <option value="lt">Dan kichik (&lt;)</option>
                                                        <option value="lte">Dan kichik yoki teng (&le;)</option>
                                                        <option value="between">Orasida</option>
                                                    </select>
                                                    <div class="adv-filter-inputs">
                                                        <input type="number" id="baho-val1" class="adv-filter-input" placeholder="Qiymat" step="0.1">
                                                        <input type="number" id="baho-val2" class="adv-filter-input" placeholder="gacha" step="0.1" style="display:none;">
                                                    </div>
                                                    <div class="adv-filter-actions">
                                                        <button type="button" class="adv-btn-clear" onclick="clearAdvFilter('baho')">Tozalash</button>
                                                        <button type="button" class="adv-btn-apply" onclick="applyAdvFilter('baho')">Qo'llash</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="adv-filter-wrap">
                                                <button type="button" class="adv-filter-btn" onclick="toggleAdvFilter('sana')">
                                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                                    <span id="sana-filter-label">Sana</span>
                                                </button>
                                                <div class="adv-filter-popup" id="sana-popup">
                                                    <div class="adv-filter-title">Sana filtri</div>
                                                    <select id="sana-op" class="adv-filter-select" onchange="toggleSanaSecond()">
                                                        <option value="">Barchasi</option>
                                                        <option value="eq">Teng (=)</option>
                                                        <option value="gt">Dan keyin (&gt;)</option>
                                                        <option value="gte">Dan keyin yoki teng (&ge;)</option>
                                                        <option value="lt">Dan oldin (&lt;)</option>
                                                        <option value="lte">Dan oldin yoki teng (&le;)</option>
                                                        <option value="between">Orasida</option>
                                                    </select>
                                                    <div class="adv-filter-inputs">
                                                        <input type="date" id="sana-val1" class="adv-filter-input">
                                                        <input type="date" id="sana-val2" class="adv-filter-input" style="display:none;">
                                                    </div>
                                                    <div class="adv-filter-actions">
                                                        <button type="button" class="adv-btn-clear" onclick="clearAdvFilter('sana')">Tozalash</button>
                                                        <button type="button" class="adv-btn-apply" onclick="applyAdvFilter('sana')">Qo'llash</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                        <th>{!! $msCell('xulosa_code') !!}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                        <div id="pagination-area" class="pagination-area"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
    <script src="/js/scroll-calendar.js"></script>

    <script>
        var csrfToken = '{{ csrf_token() }}';
        var dataUrl = '{{ route($routePrefix . ".diagnostika.data") }}';
        var tartibgaSolUrl = '{{ route($routePrefix . ".diagnostika.tartibga-sol") }}';
        var uploadUrl = '{{ route($routePrefix . ".quiz-results.upload") }}';
        var reuploadUrl = '{{ route($routePrefix . ".quiz-results.reupload") }}';
        var reuploadPreviewUrl = '{{ route($routePrefix . ".quiz-results.reupload-preview") }}';
        var deleteGradesUrl = '{{ route($routePrefix . ".quiz-results.delete-grades") }}';
        var compareGradesUrl = '{{ route($routePrefix . ".quiz-results.compare-grades") }}';
        var deleteStudentGradeUrl = '{{ route($routePrefix . ".quiz-results.delete-student-grade") }}';
        var importUrl = '{{ route($routePrefix . ".quiz-results.import") }}';
        var triggerCronUrl = '{{ route($routePrefix . ".quiz-results.trigger-cron") }}';
        var destroyUrlBase = '{{ url("/" . $routePrefix . "/quiz-results") }}';

        var allData = [];
        var filteredData = [];

        // Xulosa code -> label mapping
        var xulosaCodes = {
            'ok': 'Yuklasa bo\'ladi',
            'uploaded': 'Jurnalga yuklangan',
            'mavzu_uploaded': 'Jurnalga yuklangan (mavzu)',
            'has_other_grade': 'Bahosi bor',
            'mavzu_nb': 'NB bor',
            'mavzu_grade': 'Baho bor',
            '2O': '2O',
            '2T': '2T',
            'not_in_curriculum': 'Jadvalda yo\'q',
            'jn_low': 'JN yetarli emas',
            'mt_low': 'MT yetarli emas',
            'oski_low': 'OSKI yetarli emas',
            'no_student': 'Talaba topilmadi',
            'unknown_type': 'Quiz turi noma\'lum',
            'bad_grade': 'Baho noto\'g\'ri',
            'not_first': '1-urinish emas'
        };

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function getXulosaBadge(code, text, resultId) {
            var styles = {
                'ok':               'background:#dcfce7;color:#166534;border:1px solid #86efac;',
                'mavzu':            'background:#e0f2fe;color:#075985;border:1px solid #7dd3fc;',
                'uploaded':         'background:#dcfce7;color:#166534;border:1px solid #86efac;',
                'mavzu_uploaded':   'background:#dcfce7;color:#166534;border:1px solid #86efac;',
                'has_other_grade':  'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                'mavzu_nb':         'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                'mavzu_grade':      'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                '2O':               'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                '2T':               'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
                'not_in_curriculum':'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                'jn_low':           'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
                'mt_low':           'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
                'oski_low':         'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
                'no_student':       'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                'unknown_type':     'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                'bad_grade':        'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                'not_first':        'background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;'
            };
            var style = styles[code] || 'background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;';
            var badge = '<span class="badge" style="' + style + 'font-size:10px;white-space:nowrap;">' + esc(text) + '</span>';
            if ((code === '2T' || code === '2O') && resultId) {
                badge = '<span class="xulosa-dup-wrap" style="position:relative;display:inline-block;">' + badge +
                    '<button onclick="deleteDuplicateGrade(' + resultId + ')" class="xulosa-dup-del" title="Dublikat bahoni o\'chirish">&#10005;</button></span>';
            }
            return badge;
        }

        // ========== TARTIBGA SOLISH ==========
        function searchByName() {
            var nameQ = ($('#search_student_name').val() || '').trim();
            if (!nameQ) {
                alert("Iltimos, qidirish uchun talaba ismini kiriting.");
                $('#search_student_name').focus();
                return;
            }
            loadTartibgaSol();
        }

        function searchByShakl() {
            var shaklQ = ($('#search_shakl').val() || '').trim();
            if (!shaklQ) {
                alert("Iltimos, qidirish uchun shakl matnini kiriting.");
                $('#search_shakl').focus();
                return;
            }
            loadTartibgaSol();
        }

        function loadTartibgaSol() {
            var nameQ = ($('#search_student_name').val() || '').trim();
            var shaklQ = ($('#search_shakl').val() || '').trim();
            var hasGlobalSearch = nameQ || shaklQ;
            var params = {
                date_from: hasGlobalSearch ? '' : ($('#date_from').val() || ''),
                date_to:   hasGlobalSearch ? '' : ($('#date_to').val()   || ''),
                student_name: nameQ,
                shakl_search: shaklQ,
            };

            $('#empty-state').hide(); $('#table-area').hide(); $('#loading-state').show();
            $('#btn-tartibga').prop('disabled', true).css('opacity', '0.6');
            $('#upload-result').hide();

            $.ajax({
                url: tartibgaSolUrl, type: 'GET', data: params, timeout: 300000,
                success: function(res) {
                    $('#loading-state').hide();
                    $('#btn-tartibga').prop('disabled', false).css('opacity', '1');
                    if (!res.data || res.data.length === 0) {
                        allData = []; filteredData = [];
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel, #btn-excel-xulosa').prop('disabled', true);
                        $('#total-info').hide();
                        return;
                    }
                    allData = res.data;
                    msPopulate();
                    applyColumnFilters();
                    $('#table-area').show();
                    $('#btn-excel, #btn-excel-xulosa').prop('disabled', false);
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-tartibga').prop('disabled', false).css('opacity', '1');
                    var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    $('#empty-state').show().find('p:first').text(msg);
                }
            });
        }

        // ========== USTUN KO'P TANLOVLI FILTRLARI ==========
        var msSelected = {}; // col => [tanlangan qiymatlar]
        var msColsList = ['faculty','direction','kurs','semester','group','fan_name','yn_turi','xulosa_code'];

        function msPopulate() {
            msColsList.forEach(function(col) {
                var unique = [], seen = {};
                allData.forEach(function(r) {
                    var v = (r[col] || '').toString();
                    if (v && !seen[v]) { seen[v] = true; unique.push(v); }
                });
                unique.sort(function(a, b) { return a.localeCompare(b, undefined, { numeric: true }); });

                // Endi mavjud bo'lmagan tanlovlarni olib tashlash
                if (msSelected[col]) {
                    msSelected[col] = msSelected[col].filter(function(v) { return seen[v]; });
                }

                var box = $('#ms-opts-' + col);
                box.empty();
                unique.forEach(function(v) {
                    var label = col === 'xulosa_code' ? (xulosaCodes[v] || v) : v;
                    var checked = (msSelected[col] && msSelected[col].indexOf(v) !== -1) ? ' checked' : '';
                    box.append(
                        '<label class="ms-opt"><input type="checkbox" class="ms-cb" data-col="' + esc(col) + '" value="' + esc(v) + '"' + checked + '>' +
                        '<span title="' + esc(label) + '">' + esc(label) + '</span></label>'
                    );
                });
                msUpdateLabel(col);
            });
        }

        function msToggle(col) {
            var popup = document.getElementById('ms-popup-' + col);
            var visible = popup.style.display === 'block';
            document.querySelectorAll('.ms-popup').forEach(function(p) { p.style.display = 'none'; });
            document.querySelectorAll('.adv-filter-popup').forEach(function(p) { p.style.display = 'none'; });
            if (!visible) {
                popup.style.left = '0';
                popup.style.right = 'auto';
                popup.style.display = 'block';
                // Ekran o'ng chetidan chiqib ketsa — chapga ochiladi
                var rect = popup.getBoundingClientRect();
                if (rect.right > window.innerWidth - 8) {
                    popup.style.left = 'auto';
                    popup.style.right = '0';
                }
            }
        }

        function msFilterOptions(col) {
            var q = ($('#ms-popup-' + col + ' .ms-search').val() || '').toLowerCase();
            $('#ms-opts-' + col + ' .ms-opt').each(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
            });
        }

        function msToggleAll(col) {
            var checked = $('#ms-popup-' + col + ' .ms-all-cb').prop('checked');
            $('#ms-opts-' + col + ' .ms-opt:visible .ms-cb').prop('checked', checked);
            msApply(col);
        }

        function msClear(col) {
            $('#ms-opts-' + col + ' .ms-cb').prop('checked', false);
            $('#ms-popup-' + col + ' .ms-all-cb').prop('checked', false);
            $('#ms-popup-' + col + ' .ms-search').val('');
            msFilterOptions(col);
            msApply(col);
        }

        function msApply(col) {
            var vals = [];
            $('#ms-opts-' + col + ' .ms-cb:checked').each(function() { vals.push($(this).val()); });
            msSelected[col] = vals;
            msUpdateLabel(col);
            applyColumnFilters();
        }

        function msUpdateLabel(col) {
            var vals = msSelected[col] || [];
            var textEl = $('#ms-text-' + col);
            var btn = textEl.closest('.ms-col-btn');
            if (!vals.length) {
                textEl.text('Barchasi');
                btn.removeClass('ms-active');
            } else if (vals.length === 1) {
                var v = vals[0];
                textEl.text(col === 'xulosa_code' ? (xulosaCodes[v] || v) : v);
                btn.addClass('ms-active');
            } else {
                textEl.text(vals.length + ' ta');
                btn.addClass('ms-active');
            }
            var total = $('#ms-opts-' + col + ' .ms-cb').length;
            var checked = $('#ms-opts-' + col + ' .ms-cb:checked').length;
            $('#ms-popup-' + col + ' .ms-all-cb').prop('checked', total > 0 && checked === total);
        }

        $(document).on('change', '.ms-cb', function() {
            msApply($(this).data('col'));
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.ms-wrap').length) {
                $('.ms-popup').hide();
            }
        });

        function applyColumnFilters() {
            var filters = {};
            $('input.col-filter-input').each(function() {
                var val = $.trim($(this).val()).toLowerCase();
                if (val) filters[$(this).data('col')] = val;
            });

            filteredData = allData.filter(function(r) {
                for (var col in filters) {
                    var fv = filters[col];
                    var rv = (r[col] || '').toString();
                    if (rv.toLowerCase().indexOf(fv) === -1) return false;
                }
                // Ustun ko'p tanlovli filtrlari
                for (var mc in msSelected) {
                    var sel = msSelected[mc];
                    if (sel && sel.length) {
                        if (sel.indexOf((r[mc] || '').toString()) === -1) return false;
                    }
                }
                if (!matchAdvFilter(advFilters.baho, r.grade, false)) return false;
                if (!matchAdvFilter(advFilters.sana, r.date, true)) return false;
                return true;
            });

            renderTable(filteredData);
            // Statistika
            var okCount = 0, mavzuCount = 0, uploadedCount = 0, errCount = 0;
            filteredData.forEach(function(r) {
                if (r.xulosa_code === 'ok') okCount++;
                else if (r.xulosa_code === 'mavzu') mavzuCount++;
                else if (r.xulosa_code === 'uploaded' || r.xulosa_code === 'mavzu_uploaded') uploadedCount++;
                else errCount++;
            });
            var parts = 'Jami: ' + allData.length + ' | Ko\'rsatilmoqda: ' + filteredData.length + ' | <span style="color:#16a34a;">Yuklasa bo\'ladi: ' + okCount + '</span>';
            if (mavzuCount > 0) parts += ' | <span style="color:#0369a1;">Mavzu retake: ' + mavzuCount + '</span>';
            if (uploadedCount > 0) parts += ' | <span style="color:#16a34a;">Jurnalda: ' + uploadedCount + '</span>';
            $('#total-info').html(parts).show();
            updateButtons();
        }

        // ========== BAHO / SANA ADVANCED FILTRLAR ==========
        var advFilters = { baho: null, sana: null };

        function toggleAdvFilter(type) {
            var popup = document.getElementById(type + '-popup');
            var isVisible = popup.style.display === 'block';
            document.querySelectorAll('.adv-filter-popup').forEach(function(p) { p.style.display = 'none'; });
            if (!isVisible) popup.style.display = 'block';
        }

        function toggleBahoSecond() {
            var op = $('#baho-op').val();
            $('#baho-val2').toggle(op === 'between');
            if (op !== 'between') $('#baho-val2').val('');
        }

        function toggleSanaSecond() {
            var op = $('#sana-op').val();
            $('#sana-val2').toggle(op === 'between');
            if (op !== 'between') $('#sana-val2').val('');
        }

        function applyAdvFilter(type) {
            var op = $('#' + type + '-op').val();
            var val1 = $('#' + type + '-val1').val();
            var val2 = $('#' + type + '-val2').val();

            if (!op || !val1) {
                advFilters[type] = null;
                $('#' + type + '-filter-label').text(type === 'baho' ? 'Baho' : 'Sana').removeClass('adv-active-label');
                $('.adv-filter-btn').removeClass('adv-active');
            } else {
                advFilters[type] = { op: op, val1: val1, val2: val2 };
                var opLabels = { eq: '=', gt: '>', gte: '≥', lt: '<', lte: '≤', between: '↔' };
                var labelText = opLabels[op] + ' ' + val1;
                if (op === 'between' && val2) labelText = val1 + ' — ' + val2;
                $('#' + type + '-filter-label').text(labelText).addClass('adv-active-label');
                $('#' + type + '-popup').closest('.adv-filter-wrap').find('.adv-filter-btn').addClass('adv-active');
            }

            document.getElementById(type + '-popup').style.display = 'none';
            applyColumnFilters();
        }

        function clearAdvFilter(type) {
            $('#' + type + '-op').val('');
            $('#' + type + '-val1').val('');
            $('#' + type + '-val2').val('').hide();
            advFilters[type] = null;
            $('#' + type + '-filter-label').text(type === 'baho' ? 'Baho' : 'Sana').removeClass('adv-active-label');
            $('#' + type + '-popup').closest('.adv-filter-wrap').find('.adv-filter-btn').removeClass('adv-active');
            document.getElementById(type + '-popup').style.display = 'none';
            applyColumnFilters();
        }

        function matchAdvFilter(filter, cellValue, isDate) {
            if (!filter) return true;
            var op = filter.op;
            var v1, v2, cv;

            if (isDate) {
                cv = parseDateValue(cellValue);
                v1 = filter.val1;
                v2 = filter.val2;
                if (!cv) return false;
            } else {
                cv = parseFloat(cellValue);
                v1 = parseFloat(filter.val1);
                v2 = parseFloat(filter.val2);
                if (isNaN(cv) || isNaN(v1)) return false;
            }

            switch (op) {
                case 'eq':  return isDate ? cv === v1 : cv === v1;
                case 'gt':  return cv > v1;
                case 'gte': return cv >= v1;
                case 'lt':  return cv < v1;
                case 'lte': return cv <= v1;
                case 'between':
                    if (isDate) return v2 ? (cv >= v1 && cv <= v2) : cv >= v1;
                    return !isNaN(v2) ? (cv >= v1 && cv <= v2) : cv >= v1;
            }
            return true;
        }

        function parseDateValue(dateStr) {
            if (!dateStr) return null;
            dateStr = dateStr.trim();
            if (/^\d{4}-\d{2}-\d{2}/.test(dateStr)) return dateStr.substring(0, 10);
            var parts = dateStr.split('.');
            if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
            return dateStr;
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.adv-filter-wrap')) {
                document.querySelectorAll('.adv-filter-popup').forEach(function(p) { p.style.display = 'none'; });
            }
        });

        var journalShowBaseUrl = @json(url('/admin/journal/show'));

        function buildJournalBtn(r) {
            if (!r.group_local_id || !r.fan_id || !r.semester_code || !r.student_hemis_id) {
                return '<span style="color:#cbd5e1;font-size:11px;">—</span>';
            }
            var url = journalShowBaseUrl + '/' + encodeURIComponent(r.group_local_id) +
                      '/' + encodeURIComponent(r.fan_id) +
                      '/' + encodeURIComponent(r.semester_code) +
                      '?highlight_student=' + encodeURIComponent(r.student_hemis_id);
            return '<a href="' + url + '" target="_blank" rel="noopener" class="journal-view-btn" title="Jurnalda ko\'rish">' +
                   '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;">' +
                   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' +
                   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>' +
                   '</svg></a>';
        }

        // ========== JADVAL RENDERI ==========
        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                var ynBadge = r.yn_turi === 'Test'
                    ? '<span class="badge badge-grade">' + esc(r.yn_turi) + '</span>'
                    : (r.yn_turi === 'OSKI'
                        ? '<span class="badge badge-oski">' + esc(r.yn_turi) + '</span>'
                        : esc(r.yn_turi));

                var isOk = r.xulosa_code === 'ok';
                var rowClass = (r.xulosa_code === 'uploaded' || r.xulosa_code === 'mavzu_uploaded') ? 'journal-row row-uploaded' : 'journal-row';

                var nameCell = '<span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span>';
                var fanCell = '<span class="text-cell" style="font-weight:600;">' + esc(r.fan_name) + '</span>';

                // Quiz semestri talabaning LMS semestriga mos kelmasa — qator qizil belgilanadi.
                var semMismatch = !!r.semester_mismatch;
                var rowStyle = semMismatch ? ' style="background:#fef2f2;"' : '';
                html += '<tr class="' + rowClass + '" id="row-' + r.id + '"' + rowStyle + '>';
                html += '<td style="padding-left:14px;"><input type="checkbox" class="row-checkbox cb-styled" value="' + r.id + '" data-xulosa="' + r.xulosa_code + '"></td>';
                html += '<td class="td-num">' + (i + 1) + '</td>';
                html += '<td><span class="badge badge-indigo">' + esc(r.student_id) + '</span></td>';
                html += '<td>' + nameCell + '</td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.faculty) + '</span></td>';
                html += '<td><span class="text-cell text-cyan">' + esc(r.direction) + '</span></td>';
                html += '<td><span class="badge" style="background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;">' + esc(r.kurs) + '</span></td>';
                if (semMismatch) {
                    html += '<td><span class="badge" title="Quiz semestri talabaning LMS semestriga mos kelmaydi — talaba aslida ' + esc(r.student_semester || '-') + '" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-weight:700;">⚠ ' + esc(r.semester) + '</span></td>';
                } else {
                    html += '<td><span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">' + esc(r.semester) + '</span></td>';
                }
                html += '<td><span class="badge" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;">' + esc(r.group) + '</span></td>';
                html += '<td>' + fanCell + '</td>';
                html += '<td><span class="badge editable-fan-id" data-id="' + r.id + '" onclick="editFanId(this,' + r.id + ')" title="Fan ID ni tahrirlash uchun bosing" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:11px;cursor:pointer;">' + esc(r.fan_id || '-') + '</span></td>';
                html += '<td style="text-align:center;">' + ynBadge + '</td>';
                html += '<td><span class="text-cell">' + esc(r.shakl) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-grade editable-grade" data-id="' + r.id + '" onclick="editGrade(this,' + r.id + ')" title="Tahrirlash uchun bosing" style="cursor:pointer;">' + esc(r.grade) + '</span></td>';
                html += '<td style="font-size:12px;white-space:nowrap;color:#475569;">' + esc(r.date) + '</td>';
                html += '<td>' + getXulosaBadge(r.xulosa_code, r.xulosa, r.id) + '</td>';
                html += '<td style="text-align:center;">' + buildJournalBtn(r) + '</td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
            $('#select-all').prop('checked', false);

        }

        // ========== TANLASH BOSHQARUVI ==========
        function getSelectedIds() {
            var ids = [];
            $('.row-checkbox:checked').each(function() { ids.push(parseInt($(this).val())); });
            return ids;
        }

        function updateButtons() {
            var ids = getSelectedIds();
            var count = ids.length;
            $('#selected-count').text(count);
            $('#btn-upload').prop('disabled', count === 0);

            // Qayta yuklash — har qanday tanlov ochiq (modal orqali fan_id o'zgartirish mumkin,
            // mavjud yuklangan baholar bo'lsa avval o'chiriladi, bo'lmasa shunchaki yuklanadi)
            $('#btn-reupload').prop('disabled', count === 0);

            // Bahoni o'chirish — faqat "uploaded" tanlanganda
            var hasUploaded = false;
            ids.forEach(function(id) {
                var row = allData.find(function(r) { return r.id === id; });
                if (row && row.xulosa_code === 'uploaded') hasUploaded = true;
            });
            $('#btn-delete-grades').prop('disabled', !hasUploaded);
            $('#btn-compare').prop('disabled', count === 0);
        }

        // ========== EXCEL (Quiz natijalar) ==========
        function downloadExcel() {
            var nameQ = ($('#search_student_name').val() || '').trim();
            var params = {
                date_from: nameQ ? '' : ($('#date_from').val() || ''),
                date_to:   nameQ ? '' : ($('#date_to').val()   || ''),
                student_name: nameQ,
                export: 'excel',
            };
            window.location.href = dataUrl + '?' + $.param(params);
        }

        // ========== EXCEL (Xulosali natijalar) ==========
        function downloadXulosaExcel() {
            if (!filteredData || filteredData.length === 0) return;

            var headers = ['#', 'Student ID', 'FISH', 'Fakultet', 'Yo\'nalish', 'Kurs', 'Semestr', 'Guruh', 'Fan', 'Fan ID', 'YN turi', 'Shakl', 'Baho', 'Sana', 'Xulosa', 'JN o\'rtacha', 'MT o\'rtacha', 'OSKI baho'];
            var rows = [headers];
            filteredData.forEach(function(r, i) {
                rows.push([
                    i + 1, r.student_id, r.full_name, r.faculty, r.direction,
                    r.kurs, r.semester, r.group, r.fan_name, r.fan_id || '', r.yn_turi,
                    r.shakl, r.grade, r.date, r.xulosa,
                    r.jn_avg !== null ? r.jn_avg : '',
                    r.mt_avg !== null ? r.mt_avg : '',
                    r.oski_avg !== null ? r.oski_avg : ''
                ]);
            });

            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(rows);

            // Ustun kengliklarini avtomatik belgilash
            var colWidths = headers.map(function(h, ci) {
                var max = h.length;
                rows.forEach(function(row) {
                    var len = String(row[ci] || '').length;
                    if (len > max) max = len;
                });
                return { wch: Math.min(max + 2, 40) };
            });
            ws['!cols'] = colWidths;

            // Xulosa ustuniga rang berish
            for (var ri = 1; ri < rows.length; ri++) {
                var xulosaCode = filteredData[ri - 1].xulosa_code;
                var cellRef = XLSX.utils.encode_cell({ r: ri, c: 13 }); // Xulosa ustuni (N)
                if (!ws[cellRef]) continue;
                var fillColor = 'FFFFFF';
                if (xulosaCode === 'ok') fillColor = 'DCFCE7';
                else if (xulosaCode === 'uploaded') fillColor = 'F1F5F9';
                else if (xulosaCode === '2O' || xulosaCode === '2T') fillColor = 'FEF3C7';
                else fillColor = 'FEF2F2';
                ws[cellRef].s = { fill: { fgColor: { rgb: fillColor } } };
            }

            XLSX.utils.book_append_sheet(wb, ws, 'Xulosali');
            XLSX.writeFile(wb, 'diagnostika_xulosali_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        }

        // ========== FAYL IMPORT ==========
        function importFile() {
            var fileInput = document.getElementById('file-upload');
            if (!fileInput.files.length) return;

            var formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', csrfToken);

            $('#btn-import').prop('disabled', true).text('Yuklanmoqda...');
            $('#import-result').hide();

            $.ajax({
                url: importUrl, type: 'POST', data: formData,
                processData: false, contentType: false,
                success: function() {
                    $('#import-result').html('<div class="diag-msg diag-success">Fayl muvaffaqiyatli yuklandi!</div>').show();
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Yuklashda xatolik';
                    $('#import-result').html('<div class="diag-msg diag-error">' + msg + '</div>').show();
                },
                complete: function() {
                    $('#btn-import').prop('disabled', false).text('Yuklash');
                    fileInput.value = '';
                    $('#file-label').text('Fayl tanlash');
                }
            });
        }

        // ========== MOODLE CRON TRIGGER ==========
        function triggerMoodleCron() {
            if (!confirm('Moodle quiz natijalar sinxronizatsiyasini ishga tushirishni tasdiqlaysizmi?')) return;

            var btn = $('#btn-trigger-cron');
            btn.prop('disabled', true);
            var origHtml = btn.html();
            btn.html('<span class="spinner-sm"></span> Ishga tushirilmoqda...');

            $.ajax({
                url: triggerCronUrl, type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                success: function(data) {
                    var cls = data.success ? 'diag-success' : 'diag-error';
                    $('#upload-result').html('<div class="diag-msg ' + cls + '">' + esc(data.message) + '</div>').show();
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Server xatosi';
                    $('#upload-result').html('<div class="diag-msg diag-error">' + esc(msg) + '</div>').show();
                },
                complete: function() {
                    btn.prop('disabled', false).html(origHtml);
                }
            });
        }

        // ========== DOCUMENT READY ==========
        $(document).ready(function() {
            new ScrollCalendar('date_from');
            new ScrollCalendar('date_to');

            $('#file-upload').on('change', function() {
                var name = $(this).val().split('\\').pop();
                $('#file-label').text(name || 'Fayl tanlash');
                $('#btn-import').prop('disabled', !name);
            });

            $('#select-all').on('change', function() {
                var checked = $(this).is(':checked');
                $('.row-checkbox:not(:disabled)').prop('checked', checked);
                updateButtons();
            });
            $(document).on('change', '.row-checkbox', function() {
                updateButtons();
                var total = $('.row-checkbox:not(:disabled)').length;
                var checked = $('.row-checkbox:checked').length;
                $('#select-all').prop('checked', total > 0 && checked === total);
            });

            var filterTimer = null;
            $(document).on('input', 'input.col-filter-input', function() {
                clearTimeout(filterTimer);
                filterTimer = setTimeout(function() { applyColumnFilters(); }, 300);
            });

            // BAHO TAHRIRLASH
            window.editGrade = function(el, id) {
                var currentGrade = el.textContent.trim();
                var td = el.parentNode;
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.max = '100';
                input.value = currentGrade;
                input.style.cssText = 'width:60px;padding:4px 6px;font-size:13px;font-weight:700;text-align:center;border:2px solid #3b82f6;border-radius:6px;outline:none;';
                input.className = 'grade-edit-input';
                td.innerHTML = '';
                td.appendChild(input);
                input.focus();
                input.select();

                function saveGrade() {
                    var newGrade = parseInt(input.value);
                    if (isNaN(newGrade) || newGrade < 0 || newGrade > 100) {
                        alert('Baho 0 dan 100 gacha bo\'lishi kerak!');
                        input.focus();
                        return;
                    }
                    // allData ni yangilash
                    var row = allData.find(function(r) { return r.id === id; });
                    if (row) row.grade = newGrade;

                    // DB ga saqlash
                    $.ajax({
                        url: '{{ route($routePrefix . ".quiz-results.update-grade") }}',
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        data: { id: id, grade: newGrade },
                        success: function() {
                            td.innerHTML = '<span class="badge badge-grade editable-grade" data-id="' + id + '" onclick="editGrade(this,' + id + ')" title="Tahrirlash uchun bosing" style="cursor:pointer;">' + newGrade + '</span>';
                        },
                        error: function() {
                            alert('Saqlashda xatolik!');
                            td.innerHTML = '<span class="badge badge-grade editable-grade" data-id="' + id + '" onclick="editGrade(this,' + id + ')" title="Tahrirlash uchun bosing" style="cursor:pointer;">' + currentGrade + '</span>';
                        }
                    });
                }

                input.addEventListener('blur', saveGrade);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); saveGrade(); }
                    if (e.key === 'Escape') {
                        td.innerHTML = '<span class="badge badge-grade editable-grade" data-id="' + id + '" onclick="editGrade(this,' + id + ')" title="Tahrirlash uchun bosing" style="cursor:pointer;">' + currentGrade + '</span>';
                    }
                });
            };

            window.editFanId = function(el, id) {
                var currentFanId = el.textContent.trim();
                if (currentFanId === '-') currentFanId = '';
                var td = el.parentNode;
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.value = currentFanId;
                input.style.cssText = 'width:90px;padding:4px 6px;font-size:12px;font-weight:600;text-align:center;border:2px solid #3b82f6;border-radius:6px;outline:none;';
                td.innerHTML = '';
                td.appendChild(input);
                input.focus();
                input.select();

                function restore(val) {
                    td.innerHTML = '<span class="badge editable-fan-id" data-id="' + id + '" onclick="editFanId(this,' + id + ')" title="Fan ID ni tahrirlash uchun bosing" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-size:11px;cursor:pointer;">' + (val || '-') + '</span>';
                }

                function saveFanId() {
                    var newFanId = parseInt(input.value);
                    if (isNaN(newFanId) || newFanId < 1) {
                        alert('Fan ID raqam bo\'lishi kerak!');
                        input.focus();
                        return;
                    }
                    if (String(newFanId) === String(currentFanId)) {
                        restore(currentFanId);
                        return;
                    }
                    $.ajax({
                        url: '{{ route($routePrefix . ".quiz-results.update-fan-id") }}',
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        data: { id: id, fan_id: newFanId },
                        success: function(data) {
                            if (!data.success) {
                                alert(data.message || 'Xatolik');
                                restore(currentFanId);
                                return;
                            }
                            var row = allData.find(function(r) { return r.id === id; });
                            if (row) { row.fan_id = data.fan_id; row.fan_name = data.fan_name; }
                            restore(newFanId);
                            // Xulosa va boshqa derived qiymatlar yangilanishi uchun qayta sinxronlash
                            loadTartibgaSol();
                        },
                        error: function(xhr) {
                            alert('Xato: ' + (xhr.responseJSON?.message || 'Server xatosi'));
                            restore(currentFanId);
                        }
                    });
                }

                input.addEventListener('blur', saveFanId);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); saveFanId(); }
                    if (e.key === 'Escape') { restore(currentFanId); }
                });
            };

            // SISTEMAGA YUKLASH
            $('#btn-upload').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;

                // "ok" (OSKI/YN test) va "mavzu" (JN mavzu retake) tanlanganlarini yuklash
                var okIds = [];
                var skippedCount = 0;
                ids.forEach(function(id) {
                    var row = allData.find(function(r) { return r.id === id; });
                    if (row && (row.xulosa_code === 'ok' || row.xulosa_code === 'mavzu')) {
                        okIds.push(id);
                    } else {
                        skippedCount++;
                    }
                });

                if (okIds.length === 0) {
                    alert('Tanlangan natijalar orasida yuklanishi mumkin bo\'lgani yo\'q. Faqat "Yuklasa bo\'ladi" xulosali natijalar yuklanadi.');
                    return;
                }

                var msg = okIds.length + ' ta natijani sistemaga yuklashni tasdiqlaysizmi?';
                if (skippedCount > 0) {
                    msg += '\n(' + skippedCount + ' ta xatolik bilan — o\'tkazib yuboriladi)';
                }
                if (!confirm(msg)) return;

                var btn = $(this);
                btn.prop('disabled', true);
                var origHtml = btn.html();
                btn.html('<span class="spinner-sm"></span> Yuklanmoqda...');

                $.ajax({
                    url: uploadUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: okIds }),
                    success: function(data) {
                        var html = '';
                        if (data.success_count > 0) {
                            html += '<div class="diag-msg diag-success"><strong>Muvaffaqiyatli!</strong> ' + data.success_count + ' ta natija sistemaga yuklandi.</div>';
                        }
                        if (data.error_count > 0) {
                            html += '<div class="diag-msg diag-error"><strong>' + data.error_count + ' ta xato:</strong><ul style="margin-top:4px;padding-left:20px;">';
                            data.errors.forEach(function(err) { html += '<li>' + esc(err.student_name) + ' — ' + esc(err.fan_name) + ': ' + esc(err.error) + '</li>'; });
                            html += '</ul></div>';
                        }
                        $('#upload-result').html(html).show();

                        if (data.success_count > 0) {
                            // Server bilan to'liq qayta sinxronlash — xulosa kodlari, baholar, holatlar
                            // (mavzu_uploaded, uploaded, has_other_grade va h.k. server tomonida hisoblanadi)
                            loadTartibgaSol();
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        $('#upload-result').html('<div class="diag-msg diag-error"><strong>Xato!</strong> ' + msg + '</div>').show();
                    },
                    complete: function() { btn.prop('disabled', false).html(origHtml); }
                });
            });

            // Qayta yuklash handler — modal orqali fan_id ni o'zgartirish imkoniyati bilan
            // Har qanday tanlov ochiq: yuklangan bo'lsa qayta yuklanadi, yo'q bo'lsa shunchaki yuklanadi
            $('#btn-reupload').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;

                var btn = $(this);
                btn.prop('disabled', true);
                var origHtml = btn.html();
                btn.html('<span class="spinner-sm"></span> Yuklanmoqda...');

                $.ajax({
                    url: reuploadPreviewUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: ids }),
                    success: function(data) {
                        if (!data.success || !data.groups || data.groups.length === 0) {
                            alert('Preview ma\'lumot olinmadi.');
                            return;
                        }
                        showReuploadModal(data.groups, ids);
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        alert('Xato: ' + msg);
                    },
                    complete: function() { btn.prop('disabled', false).html(origHtml); }
                });
            });

            // Qayta yuklash modal
            function showReuploadModal(groups, ids) {
                var groupMap = {};
                groups.forEach(function(g) { groupMap[g.key] = g; });

                // Berilgan semestr uchun "Yuklanadigan fan" select HTML
                function buildSubjectSelect(g, semCode) {
                    var subs = (g.subjects_by_semester && g.subjects_by_semester[semCode]) || [];
                    if (subs.length === 0) {
                        return '<div style="color:#dc2626;font-size:12px;">Bu semestrda fanlar topilmadi — faqat asl ID (' + esc(g.original_fan_id) + ') ishlatiladi</div>';
                    }
                    var foundOriginal = subs.some(function(s) {
                        return String(s.subject_id) === String(g.original_fan_id);
                    });
                    var h = '<select class="reupload-subject-select" data-key="' + esc(g.key) + '" style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">';
                    if (!foundOriginal) {
                        h += '<option value="' + esc(g.original_fan_id) + '" data-lesson-count="0" selected style="color:#dc2626;">';
                        h += esc(g.original_fan_name) + ' (Moodle, ID: ' + g.original_fan_id + ' — semestrda yo\'q)';
                        h += '</option>';
                        h += '<option disabled>──── Semestr fanlari ────</option>';
                    }
                    subs.forEach(function(s) {
                        var selected = foundOriginal && String(s.subject_id) === String(g.original_fan_id);
                        var lc = s.lesson_count || 0;
                        h += '<option value="' + esc(s.subject_id) + '" data-lesson-count="' + lc + '"' + (selected ? ' selected' : '') + '>';
                        h += esc(s.subject_name);
                        if (s.subject_code) h += ' [' + esc(s.subject_code) + ']';
                        h += ' (ID: ' + s.subject_id + (lc > 0 ? ', ' + lc + ' ta dars' : '') + ')';
                        h += '</option>';
                    });
                    h += '</select>';
                    return h;
                }

                var html = '<div id="reupload-modal-overlay" class="reupload-modal-overlay">';
                html += '<div class="reupload-modal">';
                html += '<div class="reupload-modal-header">';
                html += '<h3>Qayta yuklash — semestr va fanni tasdiqlang</h3>';
                html += '<button type="button" class="reupload-modal-close" onclick="closeReuploadModal()">&times;</button>';
                html += '</div>';
                html += '<div class="reupload-modal-body">';
                html += '<p style="margin-bottom:12px;color:#475569;font-size:13px;">Semestr noto\'g\'ri bo\'lsa — to\'g\'ri semestrni tanlang, "Yuklanadigan fan" ro\'yxati o\'sha semestr fanlariga yangilanadi.</p>';
                html += '<table class="reupload-modal-table">';
                html += '<thead><tr><th>#</th><th>Guruh</th><th>Semestr</th><th>Moodle fan</th><th>Baholar</th><th>Shakl</th><th>YN turi</th><th>Yuklanadigan fan</th></tr></thead>';
                html += '<tbody>';
                groups.forEach(function(g, i) {
                    var defSem = String(g.semester_code || '');
                    html += '<tr>';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td><strong>' + esc(g.group_name) + '</strong></td>';
                    // Semestr — tanlanadigan dropdown
                    html += '<td>';
                    if (g.available_semesters && g.available_semesters.length > 0) {
                        html += '<select class="reupload-semester-select" data-key="' + esc(g.key) + '" data-default="' + esc(defSem) + '" style="padding:5px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;min-width:120px;">';
                        g.available_semesters.forEach(function(sm) {
                            var sel = String(sm.code) === defSem ? ' selected' : '';
                            html += '<option value="' + esc(sm.code) + '"' + sel + '>' + esc(sm.name || (sm.code + '-semestr')) + '</option>';
                        });
                        html += '</select>';
                    } else {
                        html += '<span style="font-size:12px;color:#475569;">' + esc(g.semester_name || g.semester_code || '-') + '</span>';
                    }
                    html += '</td>';
                    html += '<td><div style="font-weight:600;">' + esc(g.original_fan_name) + '</div><div style="font-size:11px;color:#94a3b8;">ID: ' + g.original_fan_id + '</div></td>';
                    html += '<td><span class="reupload-grade-badge">' + g.grade_count + ' ta</span></td>';
                    // YN turi tanlovi: OSKI/Test + mavzular (1..N). Default — qatordagi qiymat.
                    var defaultYnTuri = '';
                    if (g.yn_turi === 'oski' || g.yn_turi === 'test') {
                        defaultYnTuri = g.yn_turi;
                    } else if (g.yn_turi === 'jn_mavzu') {
                        var m = String(g.mavzu_shakl || g.shakl || '').match(/(\d+)\s*-\s*mavzu/i);
                        if (m && m[1]) defaultYnTuri = 'mavzu_' + m[1];
                    }
                    html += '<td><select class="reupload-yn-turi-select" data-key="' + esc(g.key) + '" data-default="' + esc(defaultYnTuri) + '" style="padding:5px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;min-width:140px;">';
                    html += '<option value="">Tanlang</option><option value="oski">OSKI</option><option value="test">Test</option>';
                    html += '</select></td>';
                    // Yuklanadigan fan — semestr o'zgarsa shu yacheyka qayta quriladi
                    html += '<td class="reupload-subject-cell" data-key="' + esc(g.key) + '">' + buildSubjectSelect(g, defSem) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div>';
                html += '<div class="reupload-modal-footer">';
                html += '<button type="button" onclick="closeReuploadModal()" class="reupload-btn-cancel">Bekor qilish</button>';
                html += '<button type="button" id="reupload-modal-submit" class="reupload-btn-confirm">Qayta yuklash</button>';
                html += '</div>';
                html += '</div></div>';
                $('body').append(html);

                var $overlay = $('#reupload-modal-overlay');

                // YN turi dropdownini fan tanloviga qarab yangilash:
                // OSKI, Test + tanlangan fanning lesson_count'iga qarab 1-mavzu..N-mavzu
                function refreshYnTuriOptions(key) {
                    var ynSel = $('.reupload-yn-turi-select[data-key="' + $.escapeSelector(key) + '"]');
                    if (ynSel.length === 0) return;
                    var fanSel = $('.reupload-subject-select[data-key="' + $.escapeSelector(key) + '"]');
                    var lc = parseInt(fanSel.find(':selected').data('lesson-count')) || 0;
                    var current = ynSel.val() || ynSel.data('default') || '';

                    var optsHtml = '<option value="">Tanlang</option>';
                    optsHtml += '<option value="oski"' + (current === 'oski' ? ' selected' : '') + '>OSKI</option>';
                    optsHtml += '<option value="test"' + (current === 'test' ? ' selected' : '') + '>Test</option>';
                    if (lc > 0) {
                        optsHtml += '<option disabled>───── Mavzular ─────</option>';
                        for (var i = 1; i <= lc; i++) {
                            var v = 'mavzu_' + i;
                            // Joriy tanlov amal qiladigan oraliqda bo'lsa, saqlab qolamiz
                            optsHtml += '<option value="' + v + '"' + (current === v ? ' selected' : '') + '>' + i + '-mavzu</option>';
                        }
                    }
                    ynSel.html(optsHtml);
                }

                // Boshlang'ich render (har bir mavjud yn_turi select uchun)
                $('.reupload-yn-turi-select').each(function() {
                    refreshYnTuriOptions($(this).data('key'));
                });

                // Fan o'zgarganda — YN turi opsiyalari yangilanadi (delegatsiya: fan
                // select semestr o'zgarganda qayta yaratiladi)
                $overlay.on('change', '.reupload-subject-select', function() {
                    refreshYnTuriOptions($(this).data('key'));
                });

                // Semestr o'zgarganda — shu qator fan ro'yxati o'sha semestrga yangilanadi
                $overlay.on('change', '.reupload-semester-select', function() {
                    var key = $(this).data('key');
                    var g = groupMap[key];
                    if (!g) return;
                    $('.reupload-subject-cell[data-key="' + $.escapeSelector(key) + '"]')
                        .html(buildSubjectSelect(g, String($(this).val())));
                    refreshYnTuriOptions(key);
                });

                $('#reupload-modal-submit').on('click', function() {
                    var overrides = {};
                    $('.reupload-subject-select').each(function() {
                        var key = $(this).data('key');
                        var newSubjectId = $(this).val();
                        var origFanId = key.split('_')[0];
                        if (String(newSubjectId) !== String(origFanId)) {
                            overrides[key] = newSubjectId;
                        }
                    });

                    // Semestr — modalda tanlangan qiymat DOIM yuboriladi. Modal = aniq
                    // qaror: tanlangan semestr talabaning joriy semestri bilan bir xil
                    // bo'lsa ham, natija semestri bilan mos kelmaslik tekshiruvi
                    // o'tkazib yuborilishi kerak (aks holda "Semestr mos kelmadi" xatosi).
                    var semesterOverrides = {};
                    $('.reupload-semester-select').each(function() {
                        var key = $(this).data('key');
                        var val = String($(this).val() || '');
                        if (val) {
                            semesterOverrides[key] = val;
                        }
                    });

                    var ynTuriOverrides = {};
                    var ynMissing = false;
                    $('.reupload-yn-turi-select').each(function() {
                        var key = $(this).data('key');
                        var val = $(this).val();
                        if (!val) { ynMissing = true; $(this).css('border-color', '#dc2626'); }
                        else { ynTuriOverrides[key] = val; $(this).css('border-color', '#cbd5e1'); }
                    });
                    if (ynMissing) { alert('YN turini tanlang (OSKI yoki Test)'); return; }

                    // Shakl (urinish) tanlovi — har qatorda doim qiymatga ega
                    var attemptOverrides = {};
                    $('.reupload-shakl-select').each(function() {
                        attemptOverrides[$(this).data('key')] = $(this).val();
                    });

                    var btn = $(this);
                    btn.prop('disabled', true).html('<span class="spinner-sm"></span> Yuklanmoqda...');

                    $.ajax({
                        url: reuploadUrl, type: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        contentType: 'application/json',
                        data: JSON.stringify({ ids: ids, subject_overrides: overrides, yn_turi_overrides: ynTuriOverrides, semester_overrides: semesterOverrides }),
                        success: function(data) {
                            closeReuploadModal();
                            var html = '';
                            if (data.success_count > 0) {
                                html += '<div class="diag-msg diag-success"><strong>Muvaffaqiyatli!</strong> ' + data.success_count + ' ta natija qayta yuklandi.</div>';
                            }
                            if (data.error_count > 0) {
                                html += '<div class="diag-msg diag-error"><strong>' + data.error_count + ' ta xato:</strong><ul style="margin-top:4px;padding-left:20px;">';
                                data.errors.forEach(function(err) { html += '<li>' + esc(err.student_name) + ' — ' + esc(err.fan_name) + ': ' + esc(err.error) + '</li>'; });
                                html += '</ul></div>';
                            }
                            $('#upload-result').html(html).show();

                            if (data.success_count > 0) {
                                // Server bilan to'liq qayta sinxronlash
                                loadTartibgaSol();
                            }
                        },
                        error: function(xhr) {
                            var msg = xhr.responseJSON?.message || 'Server xatosi';
                            alert('Xato: ' + msg);
                            btn.prop('disabled', false).html('Qayta yuklash');
                        }
                    });
                });
            }

            window.closeReuploadModal = function() {
                $('#reupload-modal-overlay').remove();
            };

            // ========== BAHONI O'CHIRISH ==========
            $('#btn-delete-grades').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;

                openSubjectPickerModal('delete', ids, $(this));
            });

            // Fan + YN turi tanlash modali (delete va compare uchun umumiy)
            function openSubjectPickerModal(action, ids, btn) {
                var origHtml = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-sm"></span> Yuklanmoqda...');

                $.ajax({
                    url: reuploadPreviewUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: ids }),
                    success: function(data) {
                        if (!data.success || !data.groups || data.groups.length === 0) {
                            alert('Preview ma\'lumot olinmadi.');
                            return;
                        }
                        showSubjectPickerModal(action, data.groups, ids);
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        alert('Xato: ' + msg);
                    },
                    complete: function() { btn.prop('disabled', false).html(origHtml); }
                });
            }

            function showSubjectPickerModal(action, groups, ids) {
                var isDelete = action === 'delete';
                var title = isDelete ? 'Bahoni o\'chirish — fan va YN turini tanlang' : 'Solishtirish — fan va YN turini tanlang';
                var submitLabel = isDelete ? 'O\'chirish' : 'Solishtirish';
                var submitClass = isDelete ? 'reupload-btn-confirm' : 'reupload-btn-confirm';

                var html = '<div id="picker-modal-overlay" class="reupload-modal-overlay">';
                html += '<div class="reupload-modal">';
                html += '<div class="reupload-modal-header">';
                html += '<h3>' + title + '</h3>';
                html += '<button type="button" class="reupload-modal-close" onclick="closePickerModal()">&times;</button>';
                html += '</div>';
                html += '<div class="reupload-modal-body">';
                html += '<p style="margin-bottom:12px;color:#475569;font-size:13px;">Default — Moodledan kelgan fan va YN turi. Agar baho boshqa fanga/turiga yuklangan bo\'lsa, to\'g\'rilab tanlang.</p>';
                html += '<table class="reupload-modal-table">';
                html += '<thead><tr><th>#</th><th>Guruh</th><th>Semestr</th><th>Moodle fan</th><th>Baholar</th><th>YN turi</th><th>Fan</th></tr></thead>';
                html += '<tbody>';
                groups.forEach(function(g, i) {
                    html += '<tr>';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td><strong>' + esc(g.group_name) + '</strong></td>';
                    html += '<td style="font-size:12px;color:#475569;">' + esc(g.semester_name || g.semester_code || '-') + '</td>';
                    html += '<td><div style="font-weight:600;">' + esc(g.original_fan_name) + '</div><div style="font-size:11px;color:#94a3b8;">ID: ' + g.original_fan_id + '</div></td>';
                    html += '<td><span class="reupload-grade-badge">' + g.grade_count + ' ta</span></td>';
                    // YN turi — har doim tanlash mumkin
                    html += '<td>';
                    html += '<select class="picker-yn-turi-select" data-key="' + esc(g.key) + '" style="padding:5px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;min-width:100px;">';
                    var ynVal = g.yn_turi === 'oski' || g.yn_turi === 'test' ? g.yn_turi : '';
                    html += '<option value="">Tanlang</option>';
                    html += '<option value="oski"' + (ynVal === 'oski' ? ' selected' : '') + '>OSKI</option>';
                    html += '<option value="test"' + (ynVal === 'test' ? ' selected' : '') + '>Test</option>';
                    html += '</select>';
                    html += '</td>';
                    // Fan dropdown
                    html += '<td>';
                    if (g.available_subjects && g.available_subjects.length > 0) {
                        var foundOriginal = g.available_subjects.some(function(s) {
                            return String(s.subject_id) === String(g.original_fan_id);
                        });
                        var selectHtml = '<select class="picker-subject-select" data-key="' + esc(g.key) + '" style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">';
                        if (!foundOriginal) {
                            selectHtml += '<option value="' + esc(g.original_fan_id) + '" selected style="color:#dc2626;">';
                            selectHtml += esc(g.original_fan_name) + ' (Moodle, ID: ' + g.original_fan_id + ' — semestrda yo\'q)';
                            selectHtml += '</option>';
                            selectHtml += '<option disabled>──── Joriy semestr fanlari ────</option>';
                        }
                        g.available_subjects.forEach(function(s) {
                            var selected = foundOriginal && String(s.subject_id) === String(g.original_fan_id);
                            selectHtml += '<option value="' + esc(s.subject_id) + '"' + (selected ? ' selected' : '') + '>';
                            selectHtml += esc(s.subject_name);
                            if (s.subject_code) selectHtml += ' [' + esc(s.subject_code) + ']';
                            selectHtml += ' (ID: ' + s.subject_id + ')';
                            selectHtml += '</option>';
                        });
                        selectHtml += '</select>';
                        html += selectHtml;
                    } else {
                        html += '<div style="color:#dc2626;font-size:12px;">Joriy semestrda fanlar topilmadi — faqat asl ID (' + g.original_fan_id + ') ishlatiladi</div>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div>';
                html += '<div class="reupload-modal-footer">';
                html += '<button type="button" onclick="closePickerModal()" class="reupload-btn-cancel">Bekor qilish</button>';
                html += '<button type="button" id="picker-modal-submit" class="' + submitClass + '">' + submitLabel + '</button>';
                html += '</div>';
                html += '</div></div>';
                $('body').append(html);

                $('#picker-modal-submit').on('click', function() {
                    var subjectOverrides = {};
                    $('.picker-subject-select').each(function() {
                        var key = $(this).data('key');
                        var newSubjectId = $(this).val();
                        var origFanId = key.split('_')[0];
                        if (String(newSubjectId) !== String(origFanId)) {
                            subjectOverrides[key] = newSubjectId;
                        }
                    });

                    var ynTuriOverrides = {};
                    var ynMissing = false;
                    $('.picker-yn-turi-select').each(function() {
                        var key = $(this).data('key');
                        var val = $(this).val();
                        if (!val) { ynMissing = true; $(this).css('border-color', '#dc2626'); }
                        else { ynTuriOverrides[key] = val; $(this).css('border-color', '#cbd5e1'); }
                    });
                    if (ynMissing) { alert('YN turini tanlang (OSKI yoki Test)'); return; }

                    var btn = $(this);
                    btn.prop('disabled', true).html('<span class="spinner-sm"></span> ...');

                    if (action === 'delete') {
                        $.ajax({
                            url: deleteGradesUrl, type: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                            contentType: 'application/json',
                            data: JSON.stringify({ ids: ids, subject_overrides: subjectOverrides, yn_turi_overrides: ynTuriOverrides }),
                            success: function(data) {
                                closePickerModal();
                                var html = '';
                                if (data.deleted_count > 0) {
                                    html += '<div class="diag-msg diag-success"><strong>Muvaffaqiyatli!</strong> ' + data.deleted_count + ' ta baho sistemadan o\'chirildi.</div>';
                                }
                                if (data.error_count > 0) {
                                    html += '<div class="diag-msg diag-error"><strong>' + data.error_count + ' ta xato:</strong><ul style="margin-top:4px;padding-left:20px;">';
                                    data.errors.forEach(function(err) { html += '<li>' + esc(err.student_name) + ' — ' + esc(err.fan_name) + ': ' + esc(err.error) + '</li>'; });
                                    html += '</ul></div>';
                                }
                                $('#upload-result').html(html).show();
                                if (data.deleted_count > 0) loadTartibgaSol();
                            },
                            error: function(xhr) {
                                alert('Xato: ' + (xhr.responseJSON?.message || 'Server xatosi'));
                                btn.prop('disabled', false).html(submitLabel);
                            }
                        });
                    } else {
                        $.ajax({
                            url: compareGradesUrl, type: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                            contentType: 'application/json',
                            data: JSON.stringify({ ids: ids, subject_overrides: subjectOverrides, yn_turi_overrides: ynTuriOverrides }),
                            success: function(data) {
                                closePickerModal();
                                showCompareModal(data.comparisons || []);
                            },
                            error: function(xhr) {
                                alert('Xato: ' + (xhr.responseJSON?.message || 'Server xatosi'));
                                btn.prop('disabled', false).html(submitLabel);
                            }
                        });
                    }
                });
            }

            window.closePickerModal = function() {
                $('#picker-modal-overlay').remove();
            };

            function sendDeleteRequest(ids, confirmedFanNames) {
                var payload = { ids: ids };
                if (confirmedFanNames.length > 0) {
                    payload.confirmed_fan_names = confirmedFanNames;
                }

                var btn = $('#btn-delete-grades');
                btn.prop('disabled', true);
                var origHtml = btn.html();
                btn.html('<span class="spinner-sm"></span> O\'chirilmoqda...');

                $.ajax({
                    url: deleteGradesUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: function(data) {
                        if (data.status === 'conflict') {
                            // Conflict modal ko'rsatish
                            showConflictModal(ids, data.conflicts);
                            return;
                        }

                        var html = '';
                        if (data.deleted_count > 0) {
                            html += '<div class="diag-msg diag-success"><strong>Muvaffaqiyatli!</strong> ' + data.deleted_count + ' ta baho sistemadan o\'chirildi.</div>';
                        }
                        if (data.error_count > 0) {
                            html += '<div class="diag-msg diag-error"><strong>' + data.error_count + ' ta xato:</strong><ul style="margin-top:4px;padding-left:20px;">';
                            data.errors.forEach(function(err) { html += '<li>' + esc(err.student_name) + ' — ' + esc(err.fan_name) + ': ' + esc(err.error) + '</li>'; });
                            html += '</ul></div>';
                        }
                        $('#upload-result').html(html).show();

                        if (data.deleted_count > 0) {
                            // Server bilan qayta sinxronlash
                            loadTartibgaSol();
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Server xatosi';
                        $('#upload-result').html('<div class="diag-msg diag-error"><strong>Xato!</strong> ' + msg + '</div>').show();
                    },
                    complete: function() { btn.prop('disabled', false).html(origHtml); }
                });
            }

            function showConflictModal(ids, conflicts) {
                var html = '<div class="conflict-overlay" id="conflict-overlay">';
                html += '<div class="conflict-modal">';
                html += '<div class="conflict-title">';
                html += '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>';
                html += 'Bir xil Subject ID da turli fanlar topildi!';
                html += '</div>';
                html += '<div class="conflict-desc">Quyidagi subject_id larda turli fan nomlari bor. Qaysi fan(lar)ning baholarini o\'chirmoqchisiz?</div>';

                var allFanNames = [];
                for (var fanId in conflicts) {
                    html += '<div class="conflict-group">';
                    html += '<div class="conflict-group-title">Subject ID: <span style="color:#2563eb;">' + esc(fanId) + '</span></div>';
                    conflicts[fanId].forEach(function(fanName) {
                        allFanNames.push(fanName);
                        html += '<div class="conflict-fan">';
                        html += '<input type="checkbox" class="conflict-cb" value="' + esc(fanName) + '" id="cf-' + esc(fanName) + '">';
                        html += '<label for="cf-' + esc(fanName) + '">' + esc(fanName) + '</label>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                html += '<div class="conflict-actions">';
                html += '<button type="button" class="conflict-btn conflict-btn-cancel" onclick="closeConflictModal()">Bekor qilish</button>';
                html += '<button type="button" class="conflict-btn conflict-btn-all" onclick="deleteAllConflict()">Hammasini o\'chirish</button>';
                html += '<button type="button" class="conflict-btn conflict-btn-delete" onclick="deleteSelectedConflict()">Tanlanganlarni o\'chirish</button>';
                html += '</div>';
                html += '</div></div>';

                $('body').append(html);

                // IDs ni saqlash
                window._conflictIds = ids;
                window._conflictAllFanNames = allFanNames;
            }

            window.closeConflictModal = function() {
                $('#conflict-overlay').remove();
            };

            window.deleteAllConflict = function() {
                var ids = window._conflictIds;
                var allNames = window._conflictAllFanNames;
                closeConflictModal();
                sendDeleteRequest(ids, allNames);
            };

            window.deleteSelectedConflict = function() {
                var selected = [];
                $('.conflict-cb:checked').each(function() { selected.push($(this).val()); });
                if (selected.length === 0) {
                    alert('Kamida bitta fan tanlang!');
                    return;
                }
                var ids = window._conflictIds;
                closeConflictModal();
                sendDeleteRequest(ids, selected);
            };

            // ========== DUBLIKAT O'CHIRISH (2T/2O) ==========
            window.deleteDuplicateGrade = function(resultId) {
                if (!confirm('Bu dublikat natijani o\'chirishni tasdiqlaysizmi?\nHemis quiz result va unga bog\'langan baho o\'chiriladi.')) return;

                // 1. student_grades dan o'chirish (agar yuklangan bo'lsa)
                $.ajax({
                    url: deleteGradesUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: [resultId], confirmed_fan_names: [] }),
                    complete: function() {
                        // 2. hemis_quiz_results dan o'chirish (is_active = 0)
                        $.ajax({
                            url: destroyUrlBase + '/' + resultId,
                            type: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                            success: function() {
                                // Jadvaldan olib tashlash
                                $('#row-' + resultId).fadeOut(300, function() { $(this).remove(); });
                                allData = allData.filter(function(r) { return r.id !== resultId; });
                                filteredData = filteredData.filter(function(r) { return r.id !== resultId; });
                            },
                            error: function() { alert('O\'chirishda xatolik'); }
                        });
                    }
                });
            };

            // ========== SOLISHTIRISH ==========
            $('#btn-compare').on('click', function() {
                var ids = getSelectedIds();
                if (ids.length === 0) return;
                openSubjectPickerModal('compare', ids, $(this));
            });

            function showCompareModal(comparisons) {
                var html = '<div class="compare-overlay" id="compare-overlay">';
                html += '<div class="compare-modal">';
                html += '<div class="compare-header">';
                html += '<div style="font-size:15px;font-weight:700;color:#0f172a;">Mavjud baholar bilan solishtirish <span style="font-size:12px;font-weight:500;color:#64748b;">(' + comparisons.length + ' ta topildi)</span></div>';
                html += '<button onclick="closeCompareModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#94a3b8;">&times;</button>';
                html += '</div>';
                html += '<div class="compare-body">';

                if (comparisons.length === 0) {
                    html += '<div class="compare-empty">Tanlangan natijalar uchun sistemada mavjud OSKI/Test bahosi topilmadi.</div>';
                } else {
                    html += '<div style="display:flex;justify-content:flex-end;margin-bottom:10px;">';
                    html += '<button onclick="bulkDeleteCompareGrades()" id="cmp-bulk-delete" class="btn-delete-grades" style="height:32px;font-size:12px;padding:6px 14px;">Tanlanganlarni o\'chirish (<span id="cmp-sel-count">0</span>)</button>';
                    html += '</div>';
                    html += '<table class="compare-table">';
                    html += '<thead><tr>';
                    html += '<th style="width:30px;"><input type="checkbox" id="cmp-select-all" onclick="toggleAllCompare(this)"></th>';
                    html += '<th>Talaba</th><th>Fan</th><th>Turi</th>';
                    html += '<th style="color:#059669;">Moodle baho</th>';
                    html += '<th style="color:#dc2626;">Sistemadagi baho</th>';
                    html += '<th>Manba</th><th>Sana</th><th>Amal</th>';
                    html += '</tr></thead><tbody>';

                    for (var i = 0; i < comparisons.length; i++) {
                        var c = comparisons[i];
                        var reasonLabel = c.existing_reason === 'quiz_result' ? 'Moodle' : (c.existing_reason || 'Noma\'lum');
                        var rowBg = c.is_deleted ? 'background:#fef9c3;' : '';
                        html += '<tr id="cmp-row-' + c.student_grade_id + '" style="' + rowBg + '">';
                        html += '<td style="text-align:center;"><input type="checkbox" class="cmp-row-cb" value="' + c.student_grade_id + '" onclick="updateCmpSelCount()"></td>';
                        html += '<td style="font-weight:600;">' + esc(c.student_name) + '<br><span style="font-size:10px;color:#94a3b8;">' + esc(c.student_id) + '</span></td>';
                        html += '<td>' + esc(c.fan_name) + '<br><span style="font-size:10px;color:#94a3b8;">ID: ' + esc(c.fan_id) + '</span>' + (c.is_deleted ? '<br><span style="font-size:10px;color:#b45309;font-weight:600;">⚠ Soft-deleted (orphan)</span>' : '') + '</td>';
                        html += '<td><span class="badge ' + (c.type === 'Test' ? 'badge-grade' : 'badge-oski') + '">' + esc(c.type) + '</span></td>';
                        html += '<td style="font-weight:700;color:#059669;font-size:14px;">' + esc(c.moodle_grade) + '</td>';
                        html += '<td style="font-weight:700;color:#dc2626;font-size:14px;">' + esc(c.existing_grade) + '</td>';
                        html += '<td><span style="font-size:11px;padding:2px 6px;border-radius:4px;background:' + (reasonLabel === 'Moodle' ? '#dbeafe;color:#1e40af' : '#fef3c7;color:#92400e') + ';">' + esc(reasonLabel) + '</span></td>';
                        html += '<td style="font-size:11px;color:#64748b;">' + esc(c.existing_date) + '</td>';
                        html += '<td><button onclick="deleteCompareGrade(' + c.student_grade_id + ')" class="btn-delete-grades" style="height:26px;font-size:10px;padding:3px 8px;">O\'chirish</button></td>';
                        html += '</tr>';
                    }
                    html += '</tbody></table>';
                }

                html += '</div></div></div>';
                $('body').append(html);
            }

            window.closeCompareModal = function() {
                $('#compare-overlay').remove();
            };

            window.updateCmpSelCount = function() {
                $('#cmp-sel-count').text($('.cmp-row-cb:checked').length);
            };

            window.toggleAllCompare = function(el) {
                $('.cmp-row-cb').prop('checked', el.checked);
                updateCmpSelCount();
            };

            window.bulkDeleteCompareGrades = function() {
                var ids = $('.cmp-row-cb:checked').map(function() { return parseInt(this.value); }).get();
                if (ids.length === 0) { alert('Hech narsa tanlanmagan'); return; }
                if (!confirm(ids.length + ' ta bahoni sistemadan o\'chirishni tasdiqlaysizmi?')) return;

                var btn = $('#cmp-bulk-delete');
                btn.prop('disabled', true).html('O\'chirilmoqda...');

                $.ajax({
                    url: deleteStudentGradeUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ student_grade_ids: ids }),
                    success: function(data) {
                        if (data.success) {
                            ids.forEach(function(id) {
                                $('#cmp-row-' + id).css({ background: '#fef2f2', opacity: 0.5 });
                                $('#cmp-row-' + id + ' button').text('O\'chirildi').css({ background: '#9ca3af', cursor: 'default' }).prop('disabled', true);
                                $('#cmp-row-' + id + ' .cmp-row-cb').prop('checked', false).prop('disabled', true);
                            });
                            updateCmpSelCount();
                            btn.html('Tanlanganlarni o\'chirish (<span id="cmp-sel-count">0</span>)').prop('disabled', false);
                            loadTartibgaSol();
                        } else {
                            alert(data.message || 'Xatolik');
                            btn.prop('disabled', false).html('Tanlanganlarni o\'chirish (<span id="cmp-sel-count">' + ids.length + '</span>)');
                        }
                    },
                    error: function(xhr) {
                        alert('Xato: ' + (xhr.responseJSON?.message || 'Server xatosi'));
                        btn.prop('disabled', false).html('Tanlanganlarni o\'chirish (<span id="cmp-sel-count">' + ids.length + '</span>)');
                    }
                });
            };

            window.deleteCompareGrade = function(gradeId) {
                if (!confirm('Bu bahoni sistemadan o\'chirishni tasdiqlaysizmi?')) return;

                var btn = $('#cmp-row-' + gradeId + ' button');
                btn.prop('disabled', true).text('...');

                $.ajax({
                    url: deleteStudentGradeUrl, type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    contentType: 'application/json',
                    data: JSON.stringify({ student_grade_id: gradeId }),
                    success: function(data) {
                        if (data.success) {
                            $('#cmp-row-' + gradeId).css({ background: '#fef2f2', opacity: 0.5 });
                            btn.text('O\'chirildi').css({ background: '#9ca3af', cursor: 'default' });
                        } else {
                            alert(data.message || 'Xatolik');
                            btn.prop('disabled', false).text('O\'chirish');
                        }
                    },
                    error: function() {
                        alert('Server xatosi');
                        btn.prop('disabled', false).text('O\'chirish');
                    }
                });
            };

        });
    </script>

</x-app-layout>

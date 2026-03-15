<style>
    /* Admin hisobot uslubiga mos stillar */
    .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
    .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
    .filter-row:last-child { margin-bottom: 0; }
    .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
    .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .filter-item { min-width: 160px; }

    .filter-select { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
    .filter-select:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
    .filter-select:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }

    .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
    .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

    .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .journal-table thead { position: sticky; top: 0; z-index: 10; }
    .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
    .journal-table th { padding: 14px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
    .journal-table th.th-num { padding: 14px 10px 14px 16px; width: 44px; }
    .journal-table th.th-fan { min-width: 200px; }
    .journal-table th.th-hour { white-space: normal !important; max-width: 90px; min-width: 70px; text-align: center !important; line-height: 1.3; }

    .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
    .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
    .journal-table tbody tr:nth-child(odd) { background: #fff; }
    .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
    .journal-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
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

    .student-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; flex-shrink: 0; }
    .student-avatar-placeholder { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #cbd5e1, #94a3b8); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 12px; font-weight: 700; color: #fff; }
    .student-name-cell { display: flex; align-items: center; gap: 8px; }
    .student-name-cell a { color: #1e40af; font-weight: 700; text-decoration: none; }
    .student-name-cell a:hover { color: #2b5ea7; text-decoration: underline; }
</style>
